<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user's posts
try {
    $where = [];
    $params = [$_SESSION['user_id']]; // User ID as first parameter

    // Build query conditions
    if (!empty($_GET['type'])) {
        $where[] = "p.type = ?";
        $params[] = $_GET['type'];
    }

    if (!empty($_GET['category'])) {
        $where[] = "p.category_id = ?";
        $params[] = $_GET['category'];
    }

    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where[] = "(p.title LIKE ? OR p.content LIKE ?)";
        $params[] = $search;
        $params[] = $search;
    }

    $whereClause = !empty($where) ? ' AND ' . implode(' AND ', $where) : '';

    $query = "
        SELECT 
            p.*,
            c.name as category_name,
            COUNT(sp.id) as save_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN saved_posts sp ON p.id = sp.post_id
        WHERE p.user_id = ? {$whereClause}
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $posts = [];
}

// Handle post deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    $postId = $_POST['post_id'];
    
    try {
        // Verify ownership before deletion
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$postId, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Post deleted successfully";
            header("Location: my-posts.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting post: " . $e->getMessage();
    }
}

// Fetch categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Posts - Helpie</title>
    <style>
    :root {
        --primary-color: #2563eb;
        --secondary-color: #3b82f6;
        --background-color: #f3f4f6;
        --card-background: #ffffff;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --grid-gap: 1.5rem;
        --container-max-width: 1400px;
        --card-min-width: 320px;
        --elevation-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    body {
        background-color: var(--background-color);
        color: var(--text-primary);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        margin: 0;
        min-height: 100vh;
        padding-top: 4rem;
    }

    .main-container {
        display: flex;
        gap: 1.5rem;
        max-width: var(--container-max-width);
        margin: 60px auto 0;
        padding: 1.5rem;
    }

    .posts-container {
        flex: 1;
        min-width: 0;
    }

    .posts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: var(--grid-gap);
        width: 100%;
    }

    .post-card {
        background: var(--card-background);
        border-radius: 1rem;
        box-shadow: var(--elevation-shadow);
        transition: transform 0.2s ease;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .post-header {
        padding: 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .post-content {
        flex: 1;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .post-image-container {
        width: 100%;
        padding-top: 56.25%;
        position: relative;
        background: #f8f9fa;
        margin: -1rem -1rem 1rem -1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .post-image {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .post-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .post-status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .text-bg-success { background-color: #198754; color: #fff; }
    .text-bg-primary { background-color: #0d6efd; color: #fff; }
    .text-bg-warning { background-color: #ffc107; color: #000; }

    .filter-bar {
        width: 100%;
        background: var(--card-background);
        border-radius: 1rem;
        box-shadow: var(--elevation-shadow);
        margin-bottom: 1.5rem;
        padding: 1rem;
    }

    .no-results {
        grid-column: 1 / -1;
        text-align: center;
        padding: 3rem;
        background: var(--card-background);
        border-radius: 1rem;
        box-shadow: var(--elevation-shadow);
    }

    .dropdown-menu {
        border-radius: 0.75rem;
        box-shadow: var(--elevation-shadow);
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .post-actions {
        display: flex;
        justify-content: space-between;
        padding: 1rem;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }

    .post-stats {
        display: flex;
        gap: 1rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    /* Loading animation */
    @keyframes loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .posts-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
    }

    @media (max-width: 992px) {
        .posts-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .main-container {
            flex-direction: column;
            padding: 1rem;
        }

        .post-card {
            max-width: 100%;
        }

        .filter-bar .row {
            flex-direction: column;
        }

        .filter-bar .col-md-3,
        .filter-bar .col-md-4 {
            width: 100%;
            margin-bottom: 1rem;
        }
    }

    /* Management-specific styles */
    .management-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .status-indicator {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
    }

    .edit-post-form .image-preview {
        max-height: 200px;
        object-fit: contain;
    }

    .post-analytics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .analytic-item {
        text-align: center;
        padding: 1rem;
        background: var(--background-color);
        border-radius: 0.5rem;
    }
</style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <div class="posts-container">
            <h2 class="mb-4">My Posts</h2>

            <!-- Filter Bar (same as main.php) -->
            <div class="filter-bar mb-4">
                <!-- ... reuse filter code from main.php ... -->
            </div>

            <!-- Posts Grid -->
            <div class="posts-grid">
                <?php if (empty($posts)): ?>
                    <div class="no-results">
                        <i class="fas fa-box-open fa-3x mb-3"></i>
                        <h4>No posts found</h4>
                        <p class="text-muted">You haven't created any posts yet</p>
                        <a href="create-post.php" class="btn btn-primary">
                            Create Your First Post
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card" data-post-id="<?= $post['id'] ?>">
                            <!-- Post Header -->
                            <div class="post-header">
                                <div class="d-flex align-items-center">
                                    <div class="post-user-info">
                                        <span class="post-username">
                                            <?= htmlspecialchars($post['title']) ?>
                                        </span>
                                        <div class="post-time">
                                            <?= time_elapsed_string($post['created_at']) ?>
                                        </div>
                                    </div>
                                    <div class="dropdown ms-auto">
                                        <button class="post-menu btn" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <button class="dropdown-item" 
                                                        onclick="editPost(<?= $post['id'] ?>)">
                                                    <i class="fas fa-edit me-2"></i> Edit
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-danger" 
                                                        onclick="deletePost(<?= $post['id'] ?>)">
                                                    <i class="fas fa-trash-alt me-2"></i> Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="post-meta">
                                    <span class="badge bg-primary">
                                        <?= ucfirst($post['type']) ?>
                                    </span>
                                    <span class="badge bg-secondary">
                                        <?= $post['category_name'] ?>
                                    </span>
                                    <span class="badge bg-<?= $post['status'] === 'available' ? 
                                        'success' : 'warning' ?>">
                                        <?= ucfirst($post['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Post Content -->
                            <div class="post-content">
                                <?php if ($post['image']): ?>
                                    <div class="post-image-container">
                                        <img src="<?= getImageUrl($post['image']) ?>" 
                                             alt="<?= htmlspecialchars($post['title']) ?>"
                                             class="post-image">
                                    </div>
                                <?php endif; ?>

                                <div class="post-text"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
                                
                                <!-- Post Stats -->
                                <div class="post-stats mt-3">
                                    <div class="stat-item">
                                        <i class="fas fa-comments"></i>
                                        <?= $post['comment_count'] ?> Comments
                                    </div>
                                    <div class="stat-item">
                                        <i class="fas fa-bookmark"></i>
                                        <?= $post['save_count'] ?> Saves
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Post Modal -->
    <div class="modal fade" id="editPostModal">
        <!-- Similar to create post modal but pre-filled -->
    </div>

    <script>
        // Edit Post Function
        function editPost(postId) {
            fetch(`get-post.php?id=${postId}`)
                .then(response => response.json())
                .then(post => {
                    const form = document.getElementById('editPostForm');
                    form.elements['post_id'].value = post.id;
                    form.elements['title'].value = post.title;
                    form.elements['content'].value = post.content;
                    // ... populate other fields ...

                    new bootstrap.Modal(document.getElementById('editPostModal')).show();
                });
        }

        // Handle Edit Form Submission
        document.getElementById('editPostForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('update-post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        });

        // Add this to your my-posts.php file
        function deletePost(postId) {
            if (!confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                return;
            }

            fetch('delete-post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    // Remove the post card from DOM
                    const postCard = document.querySelector(`[data-post-id="${postId}"]`);
                    postCard.remove();

                    // Show success message
                    showNotification('Post deleted successfully', 'success');

                    // If no more posts, show empty state
                    const postsGrid = document.querySelector('.posts-grid');
                    if (!postsGrid.children.length) {
                        postsGrid.innerHTML = `
                            <div class="no-results">
                                <i class="fas fa-box-open fa-3x mb-3"></i>
                                <h4>No posts found</h4>
                                <p class="text-muted">You haven't created any posts yet</p>
                                <a href="create-post.php" class="btn btn-primary">
                                    Create Your First Post
                                </a>
                            </div>
                        `;
                    }
                } else {
                    throw new Error(result.error || 'Failed to delete post');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification(error.message, 'error');
            });
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = '1051';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(notification);
                bsAlert.close();
            }, 3000);
        }
    </script>
</body>
</html>