<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/functions.php';

use Helpie\Utils;
use Helpie\Functions;
use function Helpie\Utils\{generateCsrfToken, verifyCsrfToken};

// Handle form submission first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            throw new Exception('Invalid security token');
        }

        // Define allowed file types and max size
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        $updates = [];
        $params = [];

        // Handle file uploads
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $validation = Utils\validateFileUpload($_FILES['profile_image'], $allowedTypes, $maxSize);
            if (!$validation['success']) {
                throw new Exception($validation['message']);
            }
            // Process profile image...
        }

        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $validation = Utils\validateFileUpload($_FILES['cover_image'], $allowedTypes, $maxSize);
            if (!$validation['success']) {
                throw new Exception($validation['message']);
            }
            // Process cover image...
        }

        // Update database
        if (!empty($updates)) {
            $params[] = $_SESSION['user_id'];
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        $_SESSION['success'] = 'Profile updated successfully';
        header('Location: profile.php');
        exit;

    } catch (Exception $e) {
        error_log("Profile Update Error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header('Location: profile.php');
        exit;
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Update the user check section
try {
    // Get the requested user ID or fall back to current user
    $requested_user_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)$_SESSION['user_id'];
    
    // Check if admin
    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    $stmt = $pdo->prepare("
        SELECT u.*,
               COUNT(DISTINCT CASE WHEN p.type = 'donation' THEN p.id END) as total_donations,
               COUNT(DISTINCT CASE WHEN p.type = 'request' THEN p.id END) as total_requests
        FROM users u
        LEFT JOIN posts p ON u.id = p.user_id
        WHERE u.id = ? AND (u.blocked = 0 OR ? = TRUE)
        GROUP BY u.id
    ");
    $stmt->execute([$requested_user_id, $is_admin]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($is_admin) {
            $_SESSION['error'] = "User ID {$requested_user_id} not found or has been deleted";
            error_log("Admin attempted to view non-existent user: {$requested_user_id}");
        } else {
            $_SESSION['error'] = "User not found or profile is not accessible";
        }
        header('Location: main.php');
        exit;
    }

    // Check if viewing own profile
    $is_own_profile = ($user['id'] === $_SESSION['user_id']);

} catch (PDOException $e) {
    error_log("Profile Error: " . $e->getMessage());
    $_SESSION['error'] = $is_admin ? 
        "Database error: " . $e->getMessage() : 
        "Error loading profile";
    header('Location: main.php');
    exit;
}

// Update the activity query
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.title,
            p.type,
            p.status,
            p.created_at,
            c.name as category_name
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.user_id = ? 
        AND p.deleted = 0
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $activities = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Activity Error: " . $e->getMessage());
    $activities = [];
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($user['username']) ?>'s Profile - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/nav.php'; ?>

    <div class="um-profile">
        <div class="um-header">
            <div class="um-profile-headericon">
                <div class="dropdown">
                    <?php if ($is_own_profile): ?>
                        <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Profile options">
                            <i class="fas fa-ellipsis"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="account-settings.php">
                                    <i class="fas fa-cog"></i>
                                    Account Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Logout
                                </a>
                            </li>
                        </ul>
                    <?php elseif ($is_admin): ?>
                        <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Admin options">
                            <i class="fas fa-ellipsis"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <button class="dropdown-item" onclick="blockUser(<?= $user['id'] ?>)">
                                    <i class="fas fa-ban"></i>
                                    Block User
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item" onclick="viewUserLogs(<?= $user['id'] ?>)">
                                    <i class="fas fa-history"></i>
                                    View Logs
                                </button>
                            </li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Cover -->
            <div class="um-cover">
                <img src="<?= !empty($user['cover_image']) ? htmlspecialchars($user['cover_image']) : 'assets/images/default-cover.jpg' ?>" 
                     alt="Profile cover photo for <?= htmlspecialchars($user['username']) ?>" 
                     class="cover-image">
            </div>
            
            <!-- Profile Photo -->
            <div class="um-profile-photo">
                <img src="<?= !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'assets/images/default-avatar.jpg' ?>" 
                     alt="Profile picture of <?= htmlspecialchars($user['username']) ?>"
                     class="profile-image">
            </div>

            <!-- Profile Meta -->
            <div class="um-profile-meta">
    <div class="um-name"><?= htmlspecialchars($user['username']) ?></div>
    <?php if (!empty($user['address'])): ?>
        <div class="um-meta">
            <span>
                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($user['address']) ?>
            </span>
        </div>
    <?php endif; ?>
    
    <div class="profile-actions">
    <?php if ($is_own_profile): ?>
        <a href="edit-profile.php" class="btn btn-primary">
            <i class="fas fa-user-edit me-2"></i>Edit Profile
        </a>
    <?php else: ?>
        <a href="chat.php?with=<?= $user['id'] ?>" class="btn btn-gradient">
            <span class="btn-content">
                <i class="fas fa-comments me-2"></i>Send Message
            </span>
            <span class="btn-loading d-none">
                <i class="fas fa-spinner fa-spin me-2"></i>Loading...
            </span>
        </a>
        <button class="btn btn-outline-secondary" onclick="reportUser(<?= $user['id'] ?>)">
            <span class="btn-content">
                <i class="fas fa-flag me-2"></i>Report
            </span>
            <span class="btn-loading d-none">
                <i class="fas fa-spinner fa-spin me-2"></i>Loading...
            </span>
        </button>
    <?php endif; ?>
</div>

</div>

              
      
        <div class="um-profile-body">
            <!-- About Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <?php if (!empty($user['about'])): ?>
                        <h5 class="card-title">About</h5>
                        <p class="card-text"><?= nl2br(htmlspecialchars($user['about'])) ?></p>
                        <hr>
                    <?php endif; ?>

                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="stat-card">
                                <div class="stat-icon donation">
                                    <i class="fas fa-hand-holding-heart"></i>
                                </div>
                                <div class="h4 mb-0"><?= Utils\formatNumber($user['total_donations']) ?></div>
                                <small class="text-muted">Donations</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card">
                                <div class="stat-icon request">
                                    <i class="fas fa-hand-holding-medical"></i>
                                </div>
                                <div class="h4 mb-0"><?= number_format($user['total_requests']) ?></div>
                                <small class="text-muted">Requests</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="h4 mb-0"><?= date('M Y', strtotime($user['created_at'])) ?></div>
                                <small class="text-muted">Joined</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <div class="activity-list">
                        <?php if (empty($activities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No activity yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?= $activity['type'] ?>">
                                        <i class="fas fa-<?= Utils\getTypeIcon($activity['type']) ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-header">
                                            <h6 class="mb-0">
                                                <?= htmlspecialchars($activity['title']) ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?= Functions\time_elapsed_string($activity['created_at']) ?>
                                            </small>
                                        </div>
                                        <div class="activity-meta">
                                            <span class="badge bg-<?= Functions\getStatusBadgeClass($activity['status']) ?>">
                                                <?= ucfirst($activity['status']) ?>
                                            </span>
                                            <?php if (!empty($activity['category_name'])): ?>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($activity['category_name']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });

        // Close dropdown when clicking "Cancel"
        document.querySelectorAll('[data-bs-dismiss="dropdown"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = bootstrap.Dropdown.getInstance(
                    this.closest('.dropdown').querySelector('.dropdown-toggle')
                );
                if (dropdown) {
                    dropdown.hide();
                }
            });
        });
    });

    // Add after existing DOMContentLoaded event listener
    function blockUser(userId) {
        if (confirm('Are you sure you want to block this user?')) {
            const btn = event.target.closest('button');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Blocking...';

            fetch('admin/block-user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    user_id: userId,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'main.php';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                    alert(data.message || 'Error blocking user');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.innerHTML = originalContent;
                alert('Error blocking user');
            });
        }
    }

    // Add loading state to report function
    function reportUser(userId) {
        const btn = event.target.closest('button');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
        
        window.location.href = `report.php?type=user&id=${userId}`;
    }

    // Add loading state to view logs function
    function viewUserLogs(userId) {
        const btn = event.target.closest('button');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...`;
        
        window.location.href = `admin/user-logs.php?id=${userId}`;
    }

    // Add to your existing script section
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            // Check file size
            if (this.files[0] && this.files[0].size > 5 * 1024 * 1024) {
                alert('File too large (max 5MB)');
                this.value = '';
                return;
            }

            // Check file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (this.files[0] && !allowedTypes.includes(this.files[0].type)) {
                alert('Invalid file type. Please use JPG, PNG, or WebP images.');
                this.value = '';
                return;
            }
        });
    });

    // Add form submission handler
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!document.querySelector('input[name="csrf_token"]').value) {
            e.preventDefault();
            alert('Security token missing. Please refresh the page.');
        }
    });

    // Add this function to handle the chat button click
    function handleChatButton() {
        const messageBtn = document.querySelector('.btn-gradient');
        if (messageBtn) {
            messageBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const userId = this.href.split('with=')[1];
                
                // Show loading state
                setButtonLoading(this, true);

                // Redirect to chat page
                window.location.href = `chat.php?with=${userId}`;
            });
        }
    }

    // Update the setButtonLoading function
    function setButtonLoading(button, isLoading) {
        const content = button.querySelector('.btn-content');
        const loading = button.querySelector('.btn-loading');
        
        if (isLoading) {
            content.classList.add('d-none');
            loading.classList.remove('d-none');
            button.disabled = true;
        } else {
            content.classList.remove('d-none');
            loading.classList.add('d-none');
            button.disabled = false;
        }
    }

    // Update the DOMContentLoaded event listener
    document.addEventListener('DOMContentLoaded', function() {
        // ...existing code...
        handleChatButton();
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add hover effect to cover photo
        const coverImage = document.querySelector('.cover-image');
        if (coverImage) {
            coverImage.addEventListener('mouseover', function() {
                this.style.transform = 'scale(1.02)';
            });
            coverImage.addEventListener('mouseout', function() {
                this.style.transform = 'scale(1)';
            });
        }

        // Add loading states to buttons
        document.querySelectorAll('.profile-actions .btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!this.dataset.loading) {
                    const originalText = this.innerHTML;
                    this.dataset.loading = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                    this.disabled = true;

                    // Reset after 2 seconds (adjust based on your actual action)
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        delete this.dataset.loading;
                    }, 2000);
                }
            });
        });

        // Smooth scroll for navigation
        // Remove or comment out this section in your JavaScript
        // document.querySelectorAll('.um-profile-nav-item').forEach(link => {
        //     link.addEventListener('click', function(e) {
        //         e.preventDefault();
        //         const target = document.querySelector(this.getAttribute('href'));
        //         if (target) {
        //             target.scrollIntoView({
        //                 behavior: 'smooth',
        //                 block: 'start'
        //             });
        //         }
        //     });
        // });
    });

    function setButtonLoading(button, isLoading) {
        const content = button.querySelector('.btn-content');
        const loading = button.querySelector('.btn-loading');
        
        if (isLoading) {
            content.classList.add('d-none');
            loading.classList.remove('d-none');
            button.disabled = true;
        } else {
            content.classList.remove('d-none');
            loading.classList.add('d-none');
            button.disabled = false;
        }
    }

    function reportUser(userId) {
        const btn = event.target.closest('button');
        setButtonLoading(btn, true);
        window.location.href = `report.php?type=user&id=${userId}`;
    }

    // Update click handlers for buttons
    document.querySelectorAll('.profile-actions .btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.tagName.toLowerCase() === 'a') {
                setButtonLoading(this, true);
            }
        });
    });
    </script>
</body>
</html>
