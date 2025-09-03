<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use function Helpie\Functions\format_date;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Helper to get correct image URL (copy from main.php if not available)
function getImageUrl($imagePath) {
    if (empty($imagePath)) {
        return '../assets/images/default-post-image.jpg';
    }
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }
    // If already starts with uploads/posts, return as is
    if (strpos($imagePath, '../uploads/posts/') === 0 || strpos($imagePath, '/uploads/posts/') === 0) {
        return $imagePath;
    }
    return '../uploads/posts/' . ltrim($imagePath, '/');
}

try {
    // Get saved posts for the user with correct save count
    $stmt = $pdo->prepare("
        SELECT 
            p.*, 
            u.username, 
            (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id) as save_count,
            true as is_saved
        FROM posts p
        INNER JOIN saved_posts sp ON p.id = sp.post_id
        INNER JOIN users u ON p.user_id = u.id
        WHERE sp.user_id = ?
        ORDER BY sp.saved_at DESC
    ");
    $stmt->execute([$user_id]);
    $saved_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error in saved-posts.php: " . $e->getMessage());
    $saved_posts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Posts | Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2DA664;
            --secondary-color: #34c576;
            --background-color: #f3f4f6;
            --card-background: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --grid-gap: 1.5rem;
            --container-max-width: 800px;
            --card-min-width: 320px;
            --elevation-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        [data-theme="dark"] {
            --primary-color: #6AFB6A;
            --secondary-color: #4ecf4e;
            --background-color: #1a1f26;
            --card-background: #232832;
            --text-primary: #e0e0e0;
            --text-secondary: #a0a0a0;
            --elevation-shadow: 0 8px 24px rgba(0,0,0,0.18);
        }
        body {
            background-color: var(--background-color);
            color: var(--text-primary);
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            padding-top: 4rem;
        }
        .main-container {
            display: flex;
            margin-top: 60px;
            min-height: calc(100vh - 60px);
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            padding: 1.5rem;
            gap: 1.5rem;
        }
        .posts-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            min-width: 0;
        }
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            width: 100%;
            margin-top: 2rem;
            align-items: stretch;
        }
        .post-card {
            background: var(--card-background);
            border-radius: 1rem;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid var(--text-secondary);
            height: 100%;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }
        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--elevation-shadow);
        }
        .post-header {
            padding: 1rem;
            border-bottom: 1px solid var(--text-secondary);
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
            height: 200px;
            overflow: hidden;
            background-color: var(--text-secondary);
            position: relative;
            margin-bottom: 1rem;
        }
        .post-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .post-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .post-user-link {
            display: inline-block;
            cursor: pointer;
        }
        .post-username {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
        }
        .post-username:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
        .post-actions {
            pointer-events: none;
        }
        .post-actions button {
            pointer-events: all;
        }
        .dropdown {
            pointer-events: all;
            z-index: 1;
        }
        .notification-toast {
            position: fixed;
            top: 1rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            min-width: 300px;
            max-width: 90%;
        }
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    <div class="main-container">
        <div class="posts-container">
            <div class="notification-container" style="position: fixed; top: 20px; right: 20px; z-index: 1060;"></div>
            <div class="posts-grid">
                <?php if (empty($saved_posts)): ?>
                    <div class="no-results">
                        <i class="fas fa-bookmark fa-3x mb-3"></i>
                        <h4>No saved posts</h4>
                        <p class="text-muted">You haven't saved any posts yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($saved_posts as $post): ?>
                        <div class="post-card" data-post-id="<?= $post['id'] ?>" onclick="goToPostDetails(<?= $post['id'] ?>)">
                            <div class="post-header">
                                <div class="d-flex align-items-center">
                                    <a href="profile.php?id=<?= $post['user_id'] ?>" class="post-user-link" onclick="event.stopPropagation()">
                                        <img src="<?= !empty($post['profile_image']) ? htmlspecialchars($post['profile_image']) : '../assets/images/default-avatar.jpg' ?>"
                                            alt="Profile picture of <?= htmlspecialchars($post['username']) ?>"
                                            class="post-avatar"
                                            width="40"
                                            height="40">
                                    </a>
                                    <div class="post-user-info ms-3">
                                        <a href="profile.php?id=<?= $post['user_id'] ?>" class="post-username" onclick="event.stopPropagation()">
                                            <?= htmlspecialchars($post['username']) ?>
                                        </a>
                                        <div class="post-time">
                                            <i class="far fa-clock me-1"></i><?= format_date($post['created_at']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="post-content">
                                <?php if (!empty($post['image'])): ?>
                                    <div class="post-image-container">
                                        <img src="<?= htmlspecialchars(getImageUrl($post['image'])) ?>"
                                            alt="<?= htmlspecialchars($post['title']) ?>"
                                            class="post-image"
                                            onerror="this.onerror=null; this.src='../assets/images/default-post-image.jpg';">
                                    </div>
                                <?php endif; ?>
                                <h5 class="post-title">
                                    <a href="post.php?id=<?= $post['id'] ?>" onclick="event.stopPropagation(); return true;">
                                        <?= htmlspecialchars($post['title']) ?>
                                    </a>
                                </h5>
                                <div class="post-text"><?= nl2br(htmlspecialchars(substr($post['content'], 0, 150))) ?>...</div>
                            </div>
                            <div class="post-actions">
                                <button class="post-action btn active"
                                        onclick="event.stopPropagation(); toggleSavePost(<?= $post['id'] ?>, this)"
                                        data-post-id="<?= $post['id'] ?>"
                                        aria-label="Remove from saved posts">
                                    <i class="fas fa-bookmark"></i>
                                    <span class="action-text">Saved</span>
                                    <span class="action-count"><?= $post['save_count'] ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSavePost(postId, button) {
            fetch('toggle-save-post.php', {
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
            .then(data => {
                if (data.success) {
                    // Remove the post from view
                    const postElement = button.closest('.post-card');
                    postElement.classList.add('removing');
                    setTimeout(() => postElement.remove(), 300);
                    // If no more posts, show empty state
                    if (document.querySelectorAll('.post-card').length === 0) {
                        location.reload();
                    }
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message || 'Error unsaving post', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error communicating with server', 'error');
            });
        }
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.textContent = message;
            const container = document.querySelector('.notification-container');
            container.insertBefore(notification, container.firstChild);
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        function goToPostDetails(postId) {
            window.location.href = `post.php?id=${postId}`;
        }
    </script>
</body>
</html>