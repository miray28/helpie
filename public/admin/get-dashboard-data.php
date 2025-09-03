<?php
require_once '../../includes/config.php';
require_once '../../includes/admin-auth.php';

// Verify admin access
requireAdmin();

header('Content-Type: application/json');

try {
    // Basic statistics using prepared statements
    $stats = [];
    $queries = [
        'total_users' => "SELECT COUNT(*) FROM users",
        'total_posts' => "SELECT COUNT(*) FROM posts",
        'total_donations' => "SELECT COUNT(*) FROM posts WHERE type = 'donation'",
        'total_requests' => "SELECT COUNT(*) FROM posts WHERE type = 'request'"
    ];

    foreach ($queries as $key => $query) {
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $stats[$key] = (int)$stmt->fetchColumn();
    }

    // Get daily active users with proper date filling
    $activeUsersStmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as activity_date,
            COUNT(DISTINCT user_id) as user_count,
            COUNT(*) as post_count,
            SUM(CASE WHEN type = 'donation' THEN 1 ELSE 0 END) as donation_count,
            SUM(CASE WHEN type = 'request' THEN 1 ELSE 0 END) as request_count
        FROM posts
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY activity_date ASC
    ");
    $activeUsersStmt->execute();
    $activityData = $activeUsersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fill in missing dates and format data
    $formattedActivity = [
        'users' => array_fill(0, 7, 0),
        'posts' => array_fill(0, 7, 0),
        'donations' => array_fill(0, 7, 0),
        'requests' => array_fill(0, 7, 0)
    ];
    $labels = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M j', strtotime($date));
        
        foreach ($activityData as $data) {
            if ($data['activity_date'] === $date) {
                $index = 6 - $i;
                $formattedActivity['users'][$index] = (int)$data['user_count'];
                $formattedActivity['posts'][$index] = (int)$data['post_count'];
                $formattedActivity['donations'][$index] = (int)$data['donation_count'];
                $formattedActivity['requests'][$index] = (int)$data['request_count'];
                break;
            }
        }
    }

    // Calculate trends
    $trends = [
        'user_growth' => calculateGrowth(
            array_sum(array_slice($formattedActivity['users'], 0, 3)),
            array_sum(array_slice($formattedActivity['users'], -3))
        ),
        'post_growth' => calculateGrowth(
            array_sum(array_slice($formattedActivity['posts'], 0, 3)),
            array_sum(array_slice($formattedActivity['posts'], -3))
        )
    ];

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'activity' => [
            'labels' => $labels,
            'datasets' => $formattedActivity
        ],
        'trends' => $trends
    ]);

} catch (PDOException $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch dashboard data'
    ]);
}

function calculateGrowth($previous, $current) {
    if ($previous == 0) return 0;
    $growth = (($current - $previous) / $previous) * 100;
    return round($growth, 1);
}