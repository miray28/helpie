<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';    // Load config first
require_once '../includes/db.php';        // Then database
require_once '../includes/functions.php';  // Then functions

use Helpie\Functions;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize default values
$members = [];
$totalMembers = 0;
$totalPages = 1;
$page = max(1, $_GET['page'] ?? 1);
$perPage = 12;

// Calculate total members and pages
try {
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) FROM users WHERE blocked = 0 AND verified = 1";
    $countParams = [];
    
    if (!empty($_GET['search'])) {
        $countQuery .= " AND (username LIKE ? OR address LIKE ?)";
        $search = '%' . $_GET['search'] . '%';
        $countParams[] = $search;
        $countParams[] = $search;
    }
    
    if (!empty($_GET['role']) && in_array($_GET['role'], ['admin', 'donor', 'recipient'])) {
        $countQuery .= " AND role = ?";
        $countParams[] = $_GET['role'];
    }
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalMembers = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalMembers / $perPage));
    
    // Ensure current page is within valid range
    $page = min(max(1, $page), $totalPages);
    
    // Calculate offset
    $offset = ($page - 1) * $perPage;
    
} catch (PDOException $e) {
    error_log("Error calculating pagination: " . $e->getMessage());
    $totalMembers = 0;
    $totalPages = 1;
    $offset = 0;
}

try {
    // Test database connection
    $testStmt = $pdo->query("SELECT 1");
    if ($testStmt) {
        error_log("Database connection successful");
    }
} catch (PDOException $e) {
    error_log("Database Connection Test Error: " . $e->getMessage());
    die("Database connection test failed: " . $e->getMessage());
}

try {
    // Build query conditions
    $where = ["blocked = 0", "verified = 1"]; // Base conditions
    $params = [];

    // Search filter
    if (!empty($_GET['search'])) {
        $where[] = "(username LIKE ? OR address LIKE ?)";
        $search = '%' . $_GET['search'] . '%';
        $params[] = $search;
        $params[] = $search;
    }

    // Role filter
    if (!empty($_GET['role']) && in_array($_GET['role'], ['admin', 'donor', 'recipient'])) {
        $where[] = "role = ?";
        $params[] = $_GET['role'];
    }

    // Combine conditions
    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Determine sort order
    $sort = $_GET['sort'] ?? 'newest';
    switch ($sort) {
        case 'username':
            $orderBy = 'u.username ASC';
            break;
        case 'oldest':
            $orderBy = 'u.created_at ASC';
            break;
        case 'donations':
            $orderBy = 'total_donations DESC';
            break;
        case 'requests':
            $orderBy = 'total_requests DESC';
            break;
        default:
            $orderBy = 'u.created_at DESC';
    }
    // Main query with pagination
    $query = "
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND type = 'donation') as total_donations,
            (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND type = 'request') as total_requests
        FROM users u
        $whereClause
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset
    ";
    // Only WHERE clause params are needed
    $queryParams = $params;
    
    // Execute main query
    $stmt = $pdo->prepare($query);
    $stmt->execute($queryParams);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Members found: " . count($members));
} catch (PDOException $e) {
    error_log("Members query error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading members.";
    $members = [];
    // TEMP: Show SQL error for debugging
    echo '<pre style="color:red;">SQL Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}

// Debug output
if (empty($members)) {
    error_log("No members found with current filters");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Members - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
    /* Remove all previous inline styles, now in main.css */
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    <div class="container py-4">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <!-- Loading Spinner -->
        <div id="members-loading-spinner" aria-live="polite" aria-busy="true">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="row g-3" aria-label="Filter members">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search members..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admins</option>
                        <option value="donor" <?= ($_GET['role'] ?? '') === 'donor' ? 'selected' : '' ?>>Donors</option>
                        <option value="recipient" <?= ($_GET['role'] ?? '') === 'recipient' ? 'selected' : '' ?>>Recipients</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="sort" class="form-select">
                        <option value="newest" <?= ($_GET['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= ($_GET['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="username" <?= ($_GET['sort'] ?? '') === 'username' ? 'selected' : '' ?>>Username</option>
                        <option value="donations" <?= ($_GET['sort'] ?? '') === 'donations' ? 'selected' : '' ?>>Most Donations</option>
                        <option value="requests" <?= ($_GET['sort'] ?? '') === 'requests' ? 'selected' : '' ?>>Most Requests</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
        <!-- Members Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="members-list">
            <?php if (empty($members)): ?>
                <div class="col-12 text-center py-5">
                    <div class="alert alert-info">
                        <i class="fas fa-users me-2"></i>
                        No members found. Try adjusting your search criteria.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($members as $member): ?>
                    <div class="col">
                        <div class="member-card" tabindex="0" aria-label="Member card for <?= htmlspecialchars($member['username']) ?>">
                            <div class="member-header">
                                <!-- Cover Image -->
                                <div class="member-cover" style="background-image: url('<?= !empty($member['cover_image']) ? '../' . htmlspecialchars($member['cover_image']) : 'assets/images/default-cover.jpg' ?>');"></div>
                                <!-- Profile Image with tooltip -->
                                <img src="<?= !empty($member['profile_image']) ? '../' . htmlspecialchars($member['profile_image']) : 'assets/images/default-avatar.jpg' ?>"
                                     alt="<?= htmlspecialchars($member['username']) ?>"
                                     class="member-avatar"
                                     aria-label="<?= htmlspecialchars($member['username']) ?>"
                                     tabindex="0">
                            </div>
                            <div class="member-info">
                                <!-- User Info -->
                                <div class="member-meta">
                                    <a href="profile.php?id=<?= $member['id'] ?>" class="member-name" aria-label="View profile for <?= htmlspecialchars($member['username']) ?>">
                                        <?= htmlspecialchars($member['username']) ?>
                                        <?php if ($member['verified']): ?>
                                            <span class="verified-badge" title="Verified" aria-label="Verified">✔</span>
                                        <?php endif; ?>
                                    </a>
                                    <div class="member-role">
                                        <span class="badge bg-<?= $member['role'] === 'admin' ? 'danger' : ($member['role'] === 'donor' ? 'success' : 'primary') ?>">
                                            <i class="fas fa-user-tag"></i> <?= ucfirst($member['role']) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($member['address'])): ?>
                                        <div class="member-location">
                                            <i class="fas fa-map-marker-alt"></i> <span><?= htmlspecialchars($member['address']) ?></span>
                                            <button class="copy-address-btn" type="button" title="Copy address" aria-label="Copy address for <?= htmlspecialchars($member['username']) ?>" data-address="<?= htmlspecialchars($member['address']) ?>">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Stats -->
                                <div class="member-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?= number_format($member['total_donations']) ?></div>
                                        <div class="stat-label">Donations</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?= number_format($member['total_requests']) ?></div>
                                        <div class="stat-label">Requests</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value" tabindex="0" title="Joined: <?= date('F j, Y', strtotime($member['created_at'])) ?>">
                                            <?= Functions\time_elapsed_string($member['created_at']) ?>
                                        </div>
                                        <div class="stat-label">Joined</div>
                                    </div>
                                </div>
                                <!-- Actions -->
                                <div class="member-actions mt-3">
                                    <a href="profile.php?id=<?= $member['id'] ?>" class="btn btn-primary btn-sm" aria-label="View profile for <?= htmlspecialchars($member['username']) ?>">
                                        <i class="fas fa-user me-1"></i> View Profile
                                    </a>
                                    <?php if ($member['id'] !== $_SESSION['user_id']): ?>
                                        <a href="chat.php?user=<?= $member['id'] ?>" class="btn btn-outline-primary btn-sm" aria-label="Message <?= htmlspecialchars($member['username']) ?>">
                                            <i class="fas fa-comment me-1"></i> Message
                                        </a>
                                        <?php if (!empty($member['email'])): ?>
                                            <a href="mailto:<?= htmlspecialchars($member['email']) ?>" class="btn btn-outline-success btn-sm" aria-label="Send email to <?= htmlspecialchars($member['username']) ?>">
                                                <i class="fas fa-envelope me-1"></i> Email
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Members pagination">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous page" tabindex="0">
                            Previous
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" aria-label="Go to page <?= $i ?>" tabindex="0">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next page" tabindex="0">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    <!-- Toast for Copy Address -->
    <div id="copy-toast" role="status" aria-live="polite">Address copied!</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Show loading spinner on page load
    document.addEventListener('DOMContentLoaded', function() {
        const spinner = document.getElementById('members-loading-spinner');
        spinner.classList.add('active');
        setTimeout(() => spinner.classList.remove('active'), 600); // Simulate loading
    });
    // Copy address to clipboard with toast
    document.querySelectorAll('.copy-address-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const address = this.getAttribute('data-address');
            navigator.clipboard.writeText(address);
            this.innerHTML = '<i class="fas fa-check"></i>';
            // Show toast
            const toast = document.getElementById('copy-toast');
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 1200);
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-copy"></i>';
            }, 1200);
        });
    });
    // Tooltips for avatars and joined stat
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.forEach(function (el) {
        el.addEventListener('focus', function() {
            el.setAttribute('data-bs-toggle', 'tooltip');
        });
    });
    </script>
</body>