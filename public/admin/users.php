<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

use Helpie\Auth\Auth;

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// Verify admin access
Auth::requireAdmin();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid security token";
        header('Location: users.php');
        exit;
    }
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'update_role':
                $user_id = (int)$_POST['user_id'];
                $new_role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);
                $is_admin = ($new_role === 'admin') ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET role = ?, is_admin = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_role, $is_admin, $user_id]);
                $_SESSION['success'] = "User role updated successfully";
                break;

            case 'verify_user':
                $user_id = (int)$_POST['user_id'];
                $stmt = $pdo->prepare("UPDATE users SET verified = 1 WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['success'] = "User verified successfully";
                break;

            case 'block_user':
                $user_id = (int)$_POST['user_id'];
                $stmt = $pdo->prepare("UPDATE users SET blocked = 1 WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['success'] = "User blocked successfully";
                break;

            case 'unblock_user':
                $user_id = (int)$_POST['user_id'];
                $stmt = $pdo->prepare("UPDATE users SET blocked = 0 WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['success'] = "User unblocked successfully";
                break;
        }
    } catch (PDOException $e) {
        error_log("User Management Error: " . $e->getMessage());
        $_SESSION['error'] = "Error processing user action";
    }
    header('Location: users.php');
    exit;
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';

// Build query conditions
$where = [];
$params = [];

if ($search) {
    $where[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $where[] = "role = ?";
    $params[] = $role_filter;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Replace the query section with this corrected version
try {
    // First get total count
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users 
        $where_clause
    ");
    $count_stmt->execute($params);
    $total_users = $count_stmt->fetchColumn();
    $total_pages = ceil($total_users / $per_page);

    // Then fetch users with their stats
    $query = "
        SELECT 
            u.*,
            COALESCE(COUNT(DISTINCT p.id), 0) as post_count,
            MAX(p.created_at) as last_post
        FROM users u
        LEFT JOIN posts p ON u.id = p.user_id
        $where_clause
        GROUP BY 
            u.id, u.username, u.email, u.role, u.verified, 
            u.blocked, u.created_at, u.profile_image
        ORDER BY u.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($query);
    
    // Bind search and filter parameters first
    $param_count = 1;
    foreach ($params as $param) {
        $stmt->bindValue($param_count++, $param);
    }
    
    // Bind pagination parameters
    $stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add debug output
    if (isset($_GET['debug']) && $_SESSION['is_admin']) {
        echo '<div class="alert alert-info">';
        echo '<h5>Debug Information:</h5>';
        echo '<pre>';
        echo "Query:\n" . $query . "\n\n";
        echo "Parameters:\n";
        echo "limit: " . $per_page . "\n";
        echo "offset: " . $offset . "\n";
        echo "where params: "; print_r($params);
        echo "\nNumber of users found: " . count($users) . "\n";
        if (!empty($users)) {
            echo "\nFirst user data:\n";
            print_r($users[0]);
        }
        echo '</pre>';
        echo '</div>';
    }

} catch (PDOException $e) {
    error_log("User Fetch Error: " . $e->getMessage());
    if (isset($_GET['debug']) && $_SESSION['is_admin']) {
        echo '<div class="alert alert-danger">';
        echo 'Database Error: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    $users = [];
    $_SESSION['error'] = "Error loading users";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

    <!-- Add this button for mobile -->
    <button class="btn btn-primary toggle-sidebar d-md-none position-fixed" 
            style="top: 1rem; left: 1rem; z-index: 1001;">
        <i class="fas fa-bars"></i>
    </button>

    <div class="admin-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Users</h2>
            <div class="d-flex gap-2">
                <form class="d-flex gap-2">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="donor" <?= $role_filter === 'donor' ? 'selected' : '' ?>>Donor</option>
                        <option value="recipient" <?= $role_filter === 'recipient' ? 'selected' : '' ?>>Recipient</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>

        <?php include '../../includes/alerts.php'; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Posts</th>
                                <th>Verified</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= !empty($user['profile_image']) ? 
                                            htmlspecialchars($user['profile_image']) : 
                                            '../assets/images/default-avatar.jpg' ?>" 
                                             class="rounded-circle me-2" 
                                             width="32" height="32">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="donor" <?= $user['role'] === 'donor' ? 'selected' : '' ?>>
                                                Donor
                                            </option>
                                            <option value="recipient" <?= $user['role'] === 'recipient' ? 'selected' : '' ?>>
                                                Recipient
                                            </option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>
                                                Admin
                                            </option>
                                        </select>
                                    </form>
                                </td>
                                <td><?= $user['post_count'] ?></td>
                                <td>
                                    <?php if ($user['verified']): ?>
                                        <span class="badge bg-success">Verified</span>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="verify_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                Verify
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="profile.php?id=<?= $user['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($user['blocked']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="unblock_user">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="block_user">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
    <nav aria-label="User pagination" class="mt-4">
        <ul class="pagination justify-content-center">
            <!-- Previous button -->
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page-1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $role_filter ? '&role=' . urlencode($role_filter) : '' ?>">
                    Previous
                </a>
            </li>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                if ($start_page > 2) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $role_filter ? '&role=' . urlencode($role_filter) : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor;
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
            }
            ?>
            
            <!-- Next button -->
            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page+1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $role_filter ? '&role=' . urlencode($role_filter) : '' ?>">
                    Next
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Role change confirmation
    document.querySelectorAll('select[name="role"]').forEach(select => {
        select.addEventListener('change', function(e) {
            if (!confirm('Are you sure you want to change this user\'s role?')) {
                e.preventDefault();
                this.value = this.getAttribute('data-original');
                return false;
            }
        });
        select.setAttribute('data-original', select.value);
    });

    // Block/Unblock confirmation
    document.querySelectorAll('[data-action="block"], [data-action="unblock"]').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to ' + this.dataset.action + ' this user?')) {
                e.preventDefault();
                return false;
            }
        });
    });
});

// Add CSRF protection
let csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';
document.querySelectorAll('form').forEach(form => {
    let input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'csrf_token';
    input.value = csrfToken;
    form.appendChild(input);
});

// Sidebar toggle for mobile
document.querySelector('.toggle-sidebar')?.addEventListener('click', function() {
    document.querySelector('.admin-sidebar').classList.toggle('show');
});

// Hide sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) {
        const sidebar = document.querySelector('.admin-sidebar');
        const toggleBtn = document.querySelector('.toggle-sidebar');
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    }
});
</script>
</body>
</html>