<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

use Helpie\Auth;

// Add debugging
if (!isset($_SESSION['user_id'])) {
    error_log("Dashboard: No user_id in session");
}
if (!isset($_SESSION['is_admin'])) {
    error_log("Dashboard: No is_admin in session");
}
error_log("Dashboard: User ID = " . ($_SESSION['user_id'] ?? 'none') . 
          ", Is Admin = " . ($_SESSION['is_admin'] ?? 'none'));

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// Check admin access
Auth\requireAdmin();

// Dashboard data
try {
    // Prepare statistics with error handling
    $stats = [
        'total_users' => 0,
        'total_posts' => 0,
        'total_donations' => 0,
        'total_requests' => 0,
        'active_users' => 0,
        'recent_signups' => 0
    ];

    // Use a single transaction for better performance
    $pdo->beginTransaction();

    // Basic stats
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_posts'] = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $stats['total_donations'] = $pdo->query("SELECT COUNT(*) FROM posts WHERE type = 'donation'")->fetchColumn();
    $stats['total_requests'] = $pdo->query("SELECT COUNT(*) FROM posts WHERE type = 'request'")->fetchColumn();
    
    // Active users in last 30 days
    $stats['active_users'] = $pdo->query("
        SELECT COUNT(DISTINCT user_id) 
        FROM posts 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetchColumn();

    // New users in last 7 days
    $stats['recent_signups'] = $pdo->query("
        SELECT COUNT(*) 
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")->fetchColumn();

    // Get recent activity with prepared statement
    $activityStmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.profile_image,
            u.role,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) as comment_count
        FROM posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $activityStmt->execute();
    $recentActivity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Dashboard Error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading dashboard data. Please try again.";
    $stats = array_fill_keys(array_keys($stats), 0);
    $recentActivity = [];
}

// Calculate percentages for progress bars
$donationPercentage = $stats['total_posts'] > 0 
    ? round(($stats['total_donations'] / $stats['total_posts']) * 100) 
    : 0;
$requestPercentage = $stats['total_posts'] > 0 
    ? round(($stats['total_requests'] / $stats['total_posts']) * 100) 
    : 0;

// Get daily active users for the past 7 days
$dailyActiveUsers = [];
try {
    $activeUsersStmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(DISTINCT user_id) as active_count
        FROM posts
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $activeUsersStmt->execute();
    $dailyActiveUsers = $activeUsersStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Fill in missing dates with zeros
    $dates = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        if (!isset($dailyActiveUsers[$date])) {
            $dailyActiveUsers[$date] = 0;
        }
        $dates[] = date('M j', strtotime($date));
    }
    ksort($dailyActiveUsers);
} catch (PDOException $e) {
    error_log("Chart Data Error: " . $e->getMessage());
    $dailyActiveUsers = array_fill(0, 7, 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Dashboard - Helpie Platform">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Dashboard - Helpie</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="admin-content">
        <h2 class="h2 mb-4">Dashboard Overview</h2>

        <!-- Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon mx-auto mb-2">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="display-4 fw-bold" style="font-size:2.7rem;"><?= number_format($stats['total_users']) ?></div>
                    <div class="small text-light">Total Users</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon mx-auto mb-2">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="display-4 fw-bold" style="font-size:2.7rem;"><?= number_format($stats['total_posts']) ?></div>
                    <div class="small text-light">Total Posts</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon mx-auto mb-2">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div class="display-4 fw-bold" style="font-size:2.7rem;"><?= number_format($stats['total_donations']) ?></div>
                    <div class="small text-light">Total Donations</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon mx-auto mb-2">
                        <i class="fas fa-hand-holding-medical"></i>
                    </div>
                    <div class="display-4 fw-bold" style="font-size:2.7rem;"><?= number_format($stats['total_requests']) ?></div>
                    <div class="small text-light">Total Requests</div>
                </div>
            </div>
        </div>

        <!-- Post Distribution & User Activity -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="progress-card">
                    <h4 class="mb-3">Post Distribution</h4>
                    <div class="d-flex justify-content-center align-items-center" style="height: 260px;">
                        <canvas id="postDistributionChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">User Activity</h5>
                        <div style="height: 260px;">
                            <canvas id="userActivityChart"></canvas>
                        </div>
                        <script>
                            const dailyDates = <?= json_encode(array_values($dates)) ?>;
                            const dailyUsers = <?= json_encode(array_values($dailyActiveUsers)) ?>;
                        </script>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <h3 class="h3 mb-4">Recent Activity</h3>
        <div class="row g-3">
            <?php foreach ($recentActivity as $activity): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card recent-activity-card mb-3 shadow-sm" style="background: var(--card-bg-alt); border: 1px solid var(--border);">
                        <div class="flex-shrink-0">
                            <img src="<?= !empty($activity['profile_image']) ? htmlspecialchars($activity['profile_image']) : '../assets/images/default-avatar.jpg' ?>"
                                 class="recent-activity-avatar"
                                 onerror="this.src='../assets/images/default-avatar.jpg';"
                                 alt="User avatar">
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1 gap-2">
                                <?php if ($activity['type'] === 'donation'): ?>
                                    <span class="badge bg-success"><i class="fas fa-hand-holding-heart me-1"></i> Donation</span>
                                <?php else: ?>
                                    <span class="badge bg-primary"><i class="fas fa-hand-holding-medical me-1"></i> Request</span>
                                <?php endif; ?>
                                <span class="small text-light ms-2"><i class="fas fa-calendar-alt me-1"></i> <?= date('M j, Y', strtotime($activity['created_at'])) ?></span>
                            </div>
                            <div class="recent-activity-title">
                                <?= htmlspecialchars($activity['title']) ?>
                            </div>
                            <div class="d-flex align-items-center gap-2 recent-activity-meta">
                                <span><i class="fas fa-user me-1"></i> <?= htmlspecialchars($activity['username']) ?></span>
                                <span><i class="fas fa-comments me-1"></i> <?= $activity['comment_count'] ?> comments</span>
                            </div>
                        </div>
                        <div class="recent-activity-btn">
                            <a href="../post-details.php?id=<?= $activity['id'] ?>" class="btn btn-outline-primary btn-sm px-3">View</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Post Distribution Donut Chart
        const postDistCtx = document.getElementById('postDistributionChart').getContext('2d');
        new Chart(postDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Donations', 'Requests'],
                datasets: [{
                    data: [<?= $stats['total_donations'] ?>, <?= $stats['total_requests'] ?>],
                    backgroundColor: [
                        getComputedStyle(document.documentElement).getPropertyValue('--primary'),
                        getComputedStyle(document.documentElement).getPropertyValue('--accent-blue')
                    ],
                    borderWidth: 2,
                    borderColor: getComputedStyle(document.documentElement).getPropertyValue('--secondary-bg-alt'),
                    hoverOffset: 8
                }]
            },
            options: {
                cutout: '70%',
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--text-main'),
                            font: { family: 'Montserrat', weight: 'bold' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.parsed} (${Math.round(context.parsed / (<?= $stats['total_posts'] ?> || 1) * 100)}%)`;
                            }
                        }
                    }
                }
            }
        });

        // User Activity Line Chart
        const ctx = document.getElementById('userActivityChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 260);
        gradient.addColorStop(0, 'rgba(37,99,235,0.25)');
        gradient.addColorStop(1, 'rgba(37,99,235,0.02)');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dailyDates,
                datasets: [{
                    label: 'Daily Active Users',
                    data: dailyUsers,
                    borderColor: getComputedStyle(document.documentElement).getPropertyValue('--accent-blue'),
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--accent-blue'),
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBorderWidth: 2,
                    pointBorderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.85)',
                        titleFont: {
                            size: 14,
                            family: 'Montserrat',
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13,
                            family: 'Roboto'
                        }
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0,
                            color: getComputedStyle(document.documentElement).getPropertyValue('--text-light'),
                            font: { family: 'Roboto' }
                        },
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.08)'
                        }
                    },
                    x: {
                        ticks: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--text-light'),
                            font: { family: 'Roboto' }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>