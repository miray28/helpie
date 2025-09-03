<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/security.php';  // Add this line

use function Helpie\Auth\requireAdmin;

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// Add at the top of the file after require statements
if (isset($_GET['debug']) && $_SESSION['is_admin']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Verify admin access
requireAdmin();

// Add after the requireAdmin() call
if (defined('DEBUG') && DEBUG && $_SESSION['is_admin']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Add after database connection
if (isset($_GET['debug']) && $_SESSION['is_admin']) {
    try {
        $test = $pdo->query("SELECT 1")->fetch();
        echo '<div class="alert alert-info">Database connection successful</div>';
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Database connection error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter parameters
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

// Build query conditions
$where = [];
$params = [];

if ($status) {
    $where[] = "r.status = ?";
    $params[] = $status;
}

if ($type) {
    $where[] = "r.type = ?";
    $params[] = $type;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Handle report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // Add CSRF protection for POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
                throw new Exception('Invalid security token');
            }
        }

        // Validate report_id before actions
        if (isset($_POST['report_id'])) {
            $report_id = filter_var($_POST['report_id'], FILTER_VALIDATE_INT);
            if ($report_id === false) {
                throw new Exception('Invalid report ID');
            }
        }

        switch ($_POST['action']) {
            case 'resolve':
                $stmt = $pdo->prepare("
                    UPDATE reports 
                    SET status = 'resolved', 
                        resolved_at = NOW(), 
                        resolved_by = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $_POST['report_id']]);
                $_SESSION['success'] = "Report marked as resolved";
                break;

            case 'delete_content':
                $pdo->beginTransaction();
                
                // Get report details
                $stmt = $pdo->prepare("SELECT content_type, content_id FROM reports WHERE id = ?");
                $stmt->execute([$_POST['report_id']]);
                $report = $stmt->fetch();

                // Delete the reported content
                if ($report['content_type'] === 'post') {
                    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
                }
                $stmt->execute([$report['content_id']]);

                // Update report status
                $stmt = $pdo->prepare("
                    UPDATE reports 
                    SET status = 'resolved',
                        resolved_at = NOW(),
                        resolved_by = ?,
                        action_taken = 'content_deleted'
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $_POST['report_id']]);

                $pdo->commit();
                $_SESSION['success'] = "Content deleted and report resolved";
                break;
        }
    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("Report Action Error: " . $e->getMessage());
        $_SESSION['error'] = "Error processing report action";
    }
    header('Location: reports.php');
    exit;
}

// Replace the reports fetching section with this updated version

try {
    // First, get the total counts
    $count_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN type = 'spam' THEN 1 ELSE 0 END) as spam,
            SUM(CASE WHEN type = 'inappropriate' THEN 1 ELSE 0 END) as inappropriate,
            SUM(CASE WHEN type = 'harassment' THEN 1 ELSE 0 END) as harassment
        FROM reports r
    ";
    
    if (!empty($where)) {
        $count_query .= ' WHERE ' . implode(' AND ', $where);
    }
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $stats = $count_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate pagination
    $total_pages = max(1, ceil($stats['total'] / $per_page));
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;

    // Replace the existing query section with this corrected version
    $query = "
        SELECT 
            r.*,
            reporter.username as reporter_name,
            reported.username as reported_user,
            resolver.username as resolver_name,
            CASE 
                WHEN r.content_type = 'post' THEN COALESCE(p.title, '[Deleted Post]')
                WHEN r.content_type = 'comment' THEN COALESCE(SUBSTRING(c.content, 1, 100), '[Deleted Comment]')
                WHEN r.content_type = '' OR r.content_type IS NULL THEN 'User Report'
                ELSE 'Unknown Content'
            END as content_title
        FROM reports r
        LEFT JOIN users reporter ON r.reporter_id = reporter.id
        LEFT JOIN users reported ON r.reported_user_id = reported.id
        LEFT JOIN users resolver ON r.resolved_by = resolver.id
        LEFT JOIN posts p ON r.content_type = 'post' AND r.content_id = p.id
        LEFT JOIN comments c ON r.content_type = 'comment' AND r.content_id = c.id
    ";

    if (!empty($where)) {
        $query .= ' WHERE ' . implode(' AND ', $where);
    }

    $query .= "
        ORDER BY 
            CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
            r.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    try {
        $stmt = $pdo->prepare($query);
        
        // Bind pagination parameters using named parameters
        $stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        // Bind any existing where clause parameters
        foreach ($params as $key => $param) {
            $stmt->bindValue($key + 1, $param);
        }
        
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug output
        if (isset($_GET['debug']) && $_SESSION['is_admin']) {
            echo '<div class="alert alert-info">';
            echo '<h5>Debug Information:</h5>';
            echo '<pre>';
            echo "Query:\n" . $query . "\n\n";
            echo "Parameters:\n";
            echo "limit: " . $per_page . "\n";
            echo "offset: " . $offset . "\n";
            echo "where params: "; print_r($params);
            echo "\nNumber of reports found: " . count($reports) . "\n";
            if (!empty($reports)) {
                echo "\nFirst report data:\n";
                print_r($reports[0]);
            }
            echo '</pre>';
            echo '</div>';
        }
    } catch (PDOException $e) {
        error_log("Error in reports query: " . $e->getMessage());
        if (isset($_GET['debug']) && $_SESSION['is_admin']) {
            echo '<div class="alert alert-danger">';
            echo 'Database Error: ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        $reports = [];
    }
} catch (PDOException $e) {
    error_log("Error in reports query: " . $e->getMessage());
    if (isset($_GET['debug']) && $_SESSION['is_admin']) {
        echo '<div class="alert alert-danger">';
        echo 'Database Error: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    $reports = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

    <!-- Mobile toggle button -->
    <button class="btn btn-primary toggle-sidebar d-md-none position-fixed" 
            style="top: 1rem; left: 1rem; z-index: 1001;">
        <i class="fas fa-bars"></i>
    </button>

    <div class="admin-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Reports</h2>
            <div class="d-flex gap-2">
                <form class="d-flex gap-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    </select>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="spam" <?= $type === 'spam' ? 'selected' : '' ?>>Spam</option>
                        <option value="inappropriate" <?= $type === 'inappropriate' ? 'selected' : '' ?>>Inappropriate</option>
                        <option value="harassment" <?= $type === 'harassment' ? 'selected' : '' ?>>Harassment</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>

        <!-- Add this after the filter form -->

        <?php if ($_SESSION['is_admin']): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['debug' => '1'])) ?>" 
               class="btn btn-outline-secondary">
                <i class="fas fa-bug me-1"></i>Debug Mode
            </a>
        <?php endif; ?>

        <!-- Add this after the header -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Pending Reports</h6>
                        <h3 class="card-text text-warning mb-0"><?= $stats['pending'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Resolved Reports</h6>
                        <h3 class="card-text text-success mb-0"><?= $stats['resolved'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Total Reports</h6>
                        <h3 class="card-text text-primary mb-0"><?= $stats['total'] ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../../includes/alerts.php'; ?>

        <?php if (empty($reports)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No reports found matching your criteria
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($reports as $report): ?>
                    <div class="col-md-6" data-status="<?= htmlspecialchars($report['status']) ?>">
                        <div class="card report-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-<?= $report['status'] === 'pending' ? 'warning' : 'success' ?>">
                                        <?= ucfirst(htmlspecialchars($report['status'])) ?>
                                    </span>
                                    <span class="badge bg-secondary ms-2">
                                        <?= ucfirst(htmlspecialchars($report['type'])) ?>
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <?= date('M j, Y g:i A', strtotime($report['created_at'])) ?>
                                </small>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title text-truncate">
                                    <?= htmlspecialchars($report['content_title']) ?>
                                </h5>
                                <dl class="row mb-0">
                                    <dt class="col-sm-4">Reported User</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($report['reported_user']) ?></dd>
                                    
                                    <dt class="col-sm-4">Reporter</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($report['reporter_name']) ?></dd>
                                    
                                    <dt class="col-sm-4">Reason</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($report['reason']) ?></dd>
                                </dl>
                            </div>
                            
                            <div class="card-footer bg-transparent">
                                <?php if ($report['status'] === 'pending'): ?>
                                    <div class="d-flex gap-2">
                                        <form method="POST" class="d-inline action-form flex-grow-1">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="resolve">
                                            <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm w-100">
                                                <i class="fas fa-check me-1"></i> Resolve
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline action-form flex-grow-1" 
                                              data-confirm="Are you sure you want to delete this content?">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_content">
                                            <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm w-100">
                                                <i class="fas fa-trash me-1"></i> Delete Content
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted">
                                        Resolved by <?= htmlspecialchars($report['resolver_name']) ?> 
                                        on <?= date('M j, Y', strtotime($report['resolved_at'])) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
    <nav aria-label="Reports pagination" class="mt-4">
        <ul class="pagination justify-content-center">
            <?php
            // Show first and last page, plus 2 pages before and after current page
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            // Always show first page
            if ($start > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1<?= $status ? '&status=' . urlencode($status) : '' ?><?= $type ? '&type=' . urlencode($type) : '' ?>">1</a>
                </li>
                <?php if ($start > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif;
            endif;

            // Show page numbers
            for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?><?= $status ? '&status=' . urlencode($status) : '' ?><?= $type ? '&type=' . urlencode($type) : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor;

            // Always show last page
            if ($end < $total_pages): 
                if ($end < $total_pages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $total_pages ?><?= $status ? '&status=' . urlencode($status) : '' ?><?= $type ? '&type=' . urlencode($type) : '' ?>">
                        <?= $total_pages ?>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle for mobile
        const toggleBtn = document.querySelector('.toggle-sidebar');
        const sidebar = document.querySelector('.admin-sidebar');

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(e.target) && 
                    !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            });
        }
    });

    // Add this to your existing script section
    document.querySelectorAll('.action-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const confirmMessage = this.dataset.confirm;
            if (confirmMessage && !confirm(confirmMessage)) {
                e.preventDefault();
                return;
            }

            const button = this.querySelector('button[type="submit"]');
            const normalState = button.querySelector('.normal-state');
            const loadingState = button.querySelector('.loading-state');

            // Show loading state
            button.disabled = true;
            normalState.classList.add('d-none');
            loadingState.classList.remove('d-none');
        });
    });

    // Add auto-refresh for pending reports
    let refreshTimeout;
    function setupAutoRefresh() {
        if (document.querySelector('[data-status="pending"]')) {
            refreshTimeout = setTimeout(() => {
                window.location.reload();
            }, 60000); // Refresh every minute
        }
    }

    // Clear timeout when leaving page
    window.addEventListener('beforeunload', () => {
        if (refreshTimeout) clearTimeout(refreshTimeout);
    });

    setupAutoRefresh();
    </script>
</body>
</html>