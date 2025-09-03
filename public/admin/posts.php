<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

use Helpie\Auth;

// Add this right after the require statements
if (isset($_GET['debug']) && $_SESSION['is_admin']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Add this after the database connection
try {
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// Check admin access
Auth\requireAdmin();

// Add this after the auth checks
function getStatusClass($status) {
    switch ($status) {
        case 'available':
            return 'success';
        case 'reserved':
            return 'warning';
        case 'claimed':
            return 'secondary';
        default:
            return 'light';
    }
}

// Handle filters and pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query conditions
$where = [];
$params = [];

if (!empty($_GET['type'])) {
    $where[] = "p.type = ?";
    $params[] = $_GET['type'];
}

if (!empty($_GET['status'])) {
    $where[] = "p.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where[] = "(p.title LIKE ? OR p.content LIKE ? OR u.username LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count for pagination
try {
    $countQuery = "SELECT COUNT(*) FROM posts p 
                   LEFT JOIN users u ON p.user_id = u.id 
                   $whereClause";
    $stmt = $pdo->prepare($countQuery);
    
    // Bind any existing where clause parameters
    $paramCount = 1;
    foreach ($params as $param) {
        if ($param !== $limit && $param !== $offset) {
            $stmt->bindValue($paramCount++, $param);
        }
    }
    
    $stmt->execute();
    $totalPosts = $stmt->fetchColumn();
    $totalPages = ceil($totalPosts / $limit);
} catch (PDOException $e) {
    error_log("Error counting posts: " . $e->getMessage());
    $totalPosts = 0;
    $totalPages = 1;
}

// Get posts with user data
$query = "SELECT 
    p.*,
    u.username,
    u.email,
    u.profile_image,
    c.name as category_name,
    (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
FROM posts p
LEFT JOIN users u ON p.user_id = u.id
LEFT JOIN categories c ON p.category_id = c.id
$whereClause
ORDER BY p.created_at DESC
LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($query);
    // Bind parameters with explicit types
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
    // Bind any existing where clause parameters
    $paramCount = 1;
    foreach ($params as $param) {
        if ($param !== $limit && $param !== $offset) {
            $stmt->bindValue($paramCount++, $param);
        }
    }
    
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (isset($_GET['debug']) && $_SESSION['is_admin']) {
        echo '<div class="alert alert-info">';
        echo '<h5>Debug Information:</h5>';
        echo '<pre>';
        echo "Query: " . $query . "\n";
        echo "Limit: " . $limit . "\n";
        echo "Offset: " . $offset . "\n";
        echo "Where Params: " . print_r($params, true) . "\n";
        echo "Number of posts found: " . count($posts) . "\n";
        if (!empty($posts)) {
            echo "Sample post data:\n";
            print_r($posts[0]);
        }
        echo '</pre>';
        echo '</div>';
    }
} catch (PDOException $e) {
    error_log("Error in posts query: " . $e->getMessage());
    if (isset($_GET['debug']) && $_SESSION['is_admin']) {
        echo '<div class="alert alert-danger">';
        echo 'Database Error: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    $posts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Posts - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Posts</h2>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-danger" onclick="deleteSelected()">
                    <i class="fas fa-trash-alt"></i> Delete Selected
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search posts..." 
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="donation" <?= ($_GET['type'] ?? '') === 'donation' ? 'selected' : '' ?>>
                                Donations
                            </option>
                            <option value="request" <?= ($_GET['type'] ?? '') === 'request' ? 'selected' : '' ?>>
                                Requests
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="available" <?= ($_GET['status'] ?? '') === 'available' ? 'selected' : '' ?>>
                                Available
                            </option>
                            <option value="reserved" <?= ($_GET['status'] ?? '') === 'reserved' ? 'selected' : '' ?>>
                                Reserved
                            </option>
                            <option value="claimed" <?= ($_GET['status'] ?? '') === 'claimed' ? 'selected' : '' ?>>
                                Claimed
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Posts Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>Post</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Category</th>
                                <th>Comments</th>
                                <th>Created</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php if (empty($posts)): ?>
        <tr>
            <td colspan="9" class="text-center py-4">
                <div class="text-muted">
                    <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                    <p class="mb-0">No posts found</p>
                </div>
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input post-checkbox" 
                           value="<?= $post['id'] ?>">
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (!empty($post['image'])): ?>
                            <img src="/helpie/public/<?= htmlspecialchars($post['image']) ?>" 
                                 class="post-image" 
                                 alt="Post image"
                                 onerror="this.src='/helpie/public/assets/images/default-post-image.jpg';">
                        <?php endif; ?>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($post['title']) ?></div>
                            <div class="text-muted small">
                                <?= mb_strlen($post['content']) > 50 ? 
                                    htmlspecialchars(mb_substr($post['content'], 0, 50)) . '...' : 
                                    htmlspecialchars($post['content']) ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <img src="<?= !empty($post['profile_image']) ? 
                            htmlspecialchars($post['profile_image']) : 
                            '../assets/images/default-avatar.jpg' ?>" 
                             class="rounded-circle" 
                             width="32" height="32"
                             alt="<?= htmlspecialchars($post['username']) ?>'s avatar">
                        <div>
                            <div><?= htmlspecialchars($post['username']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($post['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-<?= $post['type'] === 'donation' ? 'success' : 'primary' ?>">
                        <?= ucfirst($post['type']) ?>
                    </span>
                </td>
                <td>
                    <span class="badge status-badge bg-<?= getStatusClass($post['status']) ?>">
                        <?= ucfirst($post['status']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($post['category_name'] ?? 'Uncategorized') ?></td>
                <td class="text-center"><?= (int)$post['comment_count'] ?></td>
                <td><?= date('M j, Y', strtotime($post['created_at'])) ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="../post-details.php?id=<?= $post['id'] ?>" 
                           class="btn btn-sm btn-outline-primary" 
                           title="View Post"
                           target="_blank">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button type="button" 
                                class="btn btn-sm btn-outline-danger"
                                onclick="deletePost(<?= $post['id'] ?>)"
                                title="Delete Post">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page-1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">
                                Previous
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">
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
        // Select all checkbox functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('.post-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Delete selected posts
        function deleteSelected() {
            const selectedPosts = Array.from(document.querySelectorAll('.post-checkbox:checked'))
                                     .map(checkbox => checkbox.value);
            
            if (selectedPosts.length === 0) {
                alert('Please select posts to delete');
                return;
            }

            if (confirm(`Are you sure you want to delete ${selectedPosts.length} posts?`)) {
                fetch('delete-posts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ posts: selectedPosts })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting posts');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting posts');
                });
            }
        }

        // Delete single post
        function deletePost(postId) {
            if (confirm('Are you sure you want to delete this post?')) {
                fetch('delete-posts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ posts: [postId] })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting post');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting post');
                });
            }
        }

        // Helper function for status badge colors
        function getStatusClass(status) {
            switch (status) {
                case 'available':
                    return 'success';
                case 'reserved':
                    return 'warning';
                case 'claimed':
                    return 'secondary';
                default:
                    return 'light';
            }
        }
    </script>
</body>
</html>