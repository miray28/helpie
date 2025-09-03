<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

use Helpie\Functions;

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    // Fetch user's donation requests
    $stmt = $pdo->prepare("
        SELECT 
            dr.*,
            p.title as post_title,
            p.image as post_image,
            p.type as post_type,
            u.username as poster_name
        FROM donation_requests dr
        JOIN posts p ON dr.post_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE dr.requester_id = ?
        ORDER BY dr.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $myRequests = $stmt->fetchAll();

    // Fetch user's donations and requests (posts they've created)
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            p.type as post_type,
            (SELECT COUNT(*) FROM donation_requests WHERE post_id = p.id) as request_count
        FROM posts p
        WHERE p.user_id = ? AND (p.type = 'donation' OR p.type = 'request')
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $myPosts = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching activities: " . $e->getMessage());
    $myRequests = [];
    $myPosts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Activities - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .donation-card {
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            transition: transform 0.2s;
        }

        .donation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .donation-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.5rem;
        }

        .status-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
        }

        .nav-pills .nav-link.active {
            background-color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container py-5">
        <h1 class="mb-4">My Activities</h1>

        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link active" id="requests-tab" data-bs-toggle="pill" href="#requests">
                    My Requests <span class="badge bg-secondary ms-1"><?= count($myRequests) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="posts-tab" data-bs-toggle="pill" href="#posts">
                    My Posts <span class="badge bg-secondary ms-1"><?= count($myPosts) ?></span>
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="requests">
                <?php if (empty($myRequests)): ?>
                    <div class="alert alert-info">
                        <p class="mb-0">You haven't made any requests yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($myRequests as $request): ?>
                        <div class="donation-card">
                            <div class="d-flex gap-3">
                                <img src="<?= htmlspecialchars(Functions\getImageUrl($request['post_image'])) ?>" 
                                     class="donation-image"
                                     alt="Post image">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h5><?= htmlspecialchars($request['post_title']) ?></h5>
                                        <span class="badge bg-<?= $request['post_type'] === 'donation' ? 'success' : 'info' ?>">
                                            <?= ucfirst($request['post_type']) ?>
                                        </span>
                                    </div>
                                    <p class="text-muted mb-2">
                                        From: <?= htmlspecialchars($request['poster_name']) ?><br>
                                        Requested: <?= Functions\time_elapsed_string($request['created_at']) ?>
                                    </p>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($request['notes'])) ?></p>
                                </div>
                                <div>
                                    <span class="status-badge badge bg-<?= 
                                        $request['status'] === 'pending' ? 'warning' : 
                                        ($request['status'] === 'approved' ? 'success' : 
                                        ($request['status'] === 'rejected' ? 'danger' : 'secondary')) 
                                    ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="posts">
                <?php if (empty($myPosts)): ?>
                    <div class="alert alert-info">
                        <p class="mb-0">You haven't created any posts yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($myPosts as $post): ?>
                        <div class="donation-card">
                            <div class="d-flex gap-3">
                                <img src="<?= htmlspecialchars(Functions\getImageUrl($post['image'])) ?>" 
                                     class="donation-image"
                                     alt="Post image">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h5><?= htmlspecialchars($post['title']) ?></h5>
                                        <span class="badge bg-<?= $post['type'] === 'donation' ? 'success' : 'info' ?>">
                                            <?= ucfirst($post['type']) ?>
                                        </span>
                                    </div>
                                    <p class="text-muted mb-2">
                                        Posted: <?= Functions\time_elapsed_string($post['created_at']) ?><br>
                                        <?php if ($post['type'] === 'donation'): ?>
                                            Requests: <?= $post['request_count'] ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                                </div>
                                <div>
                                    <?php if ($post['type'] === 'donation'): ?>
                                        <a href="donation-requests.php?post_id=<?= $post['id'] ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-list me-1"></i> View Requests
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>