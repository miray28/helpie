<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php'; // Add this line

use Helpie\Functions;

// Add debug logging
error_log("Functions file included");
error_log("time_elapsed_string function exists: " . (function_exists('time_elapsed_string') ? 'yes' : 'no'));

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    // Fetch donation requests for posts owned by the current user
    $stmt = $pdo->prepare("
        SELECT 
            dr.*,
            p.title as post_title,
            u.username as requester_name,
            u.profile_image as requester_image,
            u.id as requester_id
        FROM donation_requests dr
        JOIN posts p ON dr.post_id = p.id
        JOIN users u ON dr.requester_id = u.id
        WHERE p.user_id = ?
        ORDER BY dr.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching requests: " . $e->getMessage());
    $requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Requests - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .request-card {
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            transition: transform 0.2s;
        }

        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .requester-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .requester-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }

        .request-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .status-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
        }

        /* Add to your existing styles */
        .btn-outline-primary {
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }

        .btn-outline-primary:hover {
            background-color: var(--bs-primary);
            color: white;
        }

        .btn-outline-primary:hover i {
            color: white;
        }

        .gap-2 {
            gap: 0.5rem !important;
        }

        .d-flex {
            display: flex !important;
        }

        .align-items-center {
            align-items: center !important;
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container py-5">
        <h1 class="mb-4">Donation Requests</h1>

        <?php if (empty($requests)): ?>
            <div class="alert alert-info">
                <p class="mb-0">You haven't received any donation requests yet.</p>
            </div>
        <?php else: ?>
            <div class="requests-container">
                <?php foreach ($requests as $request): ?>
                    <div class="request-card">
                        <div class="requester-info">
                            <img src="<?= htmlspecialchars($request['requester_image'] ?? 'assets/images/default-avatar.jpg') ?>" 
                                 alt="<?= htmlspecialchars($request['requester_name']) ?>" 
                                 class="requester-avatar">
                            <div>
                                <h5 class="mb-0"><?= htmlspecialchars($request['requester_name']) ?></h5>
                                <small class="text-muted">
                                    Requested <?= Functions\time_elapsed_string($request['created_at']) ?>
                                </small>
                            </div>
                            <div class="ms-auto d-flex align-items-center gap-2">
                                <span class="status-badge badge bg-<?= 
                                    $request['status'] === 'pending' ? 'warning' : 
                                    ($request['status'] === 'approved' ? 'success' : 
                                    ($request['status'] === 'rejected' ? 'danger' : 'secondary')) 
                                ?>">
                                    <?= ucfirst($request['status']) ?>
                                </span>
                                <a href="chat.php?with=<?= $request['requester_id'] ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-comment me-1"></i> Message
                                </a>
                            </div>
                        </div>

                        <div class="request-details">
                            <h6>Request for: <?= htmlspecialchars($request['post_title']) ?></h6>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($request['notes'])) ?></p>
                        </div>

                        <?php if ($request['status'] === 'pending'): ?>
                            <div class="request-actions">
                                <button class="btn btn-success btn-sm" 
                                        onclick="updateRequestStatus(<?= $request['id'] ?>, 'approved')">
                                    <i class="fas fa-check me-1"></i> Approve
                                </button>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="updateRequestStatus(<?= $request['id'] ?>, 'rejected')">
                                    <i class="fas fa-times me-1"></i> Reject
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateRequestStatus(requestId, status) {
            if (!confirm(`Are you sure you want to ${status} this request?`)) {
                return;
            }

            fetch('update-request-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `request_id=${requestId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error updating request status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating request status');
            });
        }
    </script>
</body>
</html>