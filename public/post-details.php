<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

use function Helpie\Functions\{generateCsrfToken, verifyCsrfToken};

// Add this after the require statements
function getImageUrl($imagePath) {
    if (empty($imagePath)) {
        return '/helpie/public/assets/images/default-post-image.jpg';
    }
    
    // Remove any leading slashes
    $imagePath = ltrim($imagePath, '/');
    
    // If path starts with http:// or https://, return as is
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }
    
    // If path already contains uploads/posts, just prepend base path
    if (strpos($imagePath, 'uploads/posts/') === 0) {
        return '/helpie/public/' . $imagePath;
    }
    
    // Otherwise, construct full path
    return '/helpie/public/uploads/posts/' . $imagePath;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if post ID is provided
if (!isset($_GET['id'])) {
    header('Location: main.php');
    exit;
}

try {
    // Fetch post details with user information
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.profile_image,
            p.image as image_path
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        $_SESSION['error'] = "Post not found.";
        header('Location: main.php');
        exit;
    }

    // Add this after fetching the post
    if (!$post || $post['status'] === 'deleted') {
        $_SESSION['error'] = "Post not found or has been deleted.";
        header('Location: main.php');
        exit;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "An error occurred. Please try again.";
    header('Location: error.php');
    exit;
}

// Helper function for time elapsed string
function time_elapsed_string($datetime) {
    // ...existing time_elapsed_string function...
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .post-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.2s;
        }

        .post-card:hover {
            transform: translateY(-2px);
        }

        .post-header {
            border-bottom: 1px solid rgba(0,0,0,.1);
            padding-bottom: 1rem;
        }

        .post-content {
            padding: 1.5rem 0;
        }

        .post-image {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border-radius: 0.5rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            object-fit: cover;
        }

        .post-metadata {
            color: #6c757d;
            font-size: 0.875rem;
        }

        .post-actions {
            border-top: 1px solid rgba(0,0,0,.1);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .details-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: 0.5rem;
        }

        .back-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        .post-image-container {
            width: 100%;
            max-height: 500px;
            overflow: hidden;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            background-color: #f8f9fa;
        }

        .post-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .post-image:hover {
            transform: scale(1.02);
        }

        .comments-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .comment-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .comment-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        .comment-user {
            font-weight: 500;
            color: var(--text-primary);
        }

        .comment-time {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .comment-content {
            color: var(--text-primary);
            white-space: pre-wrap;
            word-break: break-word;
        }

        .comment-form {
            position: sticky;
            bottom: 0;
            background: var(--card-background);
            padding-top: 1rem;
        }

        .comment-form .input-group {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .comment-form textarea {
            border: none;
            resize: none;
            height: 40px;
            padding: 0.5rem;
        }

        .comment-form textarea:focus {
            box-shadow: none;
        }

        .comment-form button {
            border: none;
            padding: 0 1rem;
        }

        .btn-outline-danger:hover {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .btn-outline-danger:hover i {
            color: white;
        }

        .status-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
        }

        .btn-outline-info:hover {
            color: white;
        }

        .btn-outline-info:hover i {
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card post-card">
                    <div class="card-body">
                        <!-- Post Header -->
                        <div class="post-header">
                            <div class="d-flex align-items-center">
                                <img src="<?= !empty($post['profile_image']) ? htmlspecialchars($post['profile_image']) : 'assets/images/default-avatar.jpg' ?>" 
                                     alt="<?= htmlspecialchars($post['username']) ?>" 
                                     class="rounded-circle user-avatar">
                                <div class="ms-3">
                                    <h5 class="mb-0"><?= htmlspecialchars($post['username']) ?></h5>
                                    <div class="post-metadata">
                                        <i class="far fa-clock me-1"></i>
                                        <?= time_elapsed_string($post['created_at']) ?>
                                        <?php if (!empty($post['location'])): ?>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?= htmlspecialchars($post['location']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Post Content -->
                        <div class="post-content">
                            <?php if (!empty($post['image_path'])): ?>
                                <div class="post-image-container">
                                    <img src="<?= htmlspecialchars(getImageUrl($post['image_path'])) ?>" 
                                         alt="Post image" 
                                         class="post-image"
                                         onerror="this.onerror=null; this.src='/helpie/public/assets/images/default-post-image.jpg';"
                                         loading="lazy">
                                </div>
                            <?php endif; ?>

                            <h4 class="mb-3"><?= htmlspecialchars($post['title']) ?></h4>
                            <p class="mb-4"><?= nl2br(htmlspecialchars($post['content'])) ?></p>

                            <?php if ($post['type'] === 'donation'): ?>
                                <div class="post-details mb-4">
                                    <?php if (!empty($post['condition_state'])): ?>
                                        <span class="details-badge">
                                            <i class="fas fa-star"></i>
                                            <?= ucfirst(str_replace('_', ' ', $post['condition_state'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($post['quantity'])): ?>
                                        <span class="details-badge">
                                            <i class="fas fa-box"></i>
                                            <?= htmlspecialchars($post['quantity']) ?> available
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($post['expiry_date'])): ?>
                                        <span class="details-badge">
                                            <i class="fas fa-calendar-alt"></i>
                                            Expires: <?= date('F j, Y', strtotime($post['expiry_date'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Post Actions -->
                        <div class="post-actions">
                            <button class="btn btn-outline-primary me-2" onclick="showComments(<?= $post['id'] ?>)">
                                <i class="far fa-comment me-1"></i> Comments
                                <span class="comment-count"></span>
                            </button>
                            <button class="btn btn-outline-secondary me-2" onclick="sharePost(<?= $post['id'] ?>)">
                                <i class="far fa-share-square me-1"></i> Share
                            </button>
                            <?php if ($post['user_id'] === $_SESSION['user_id']): ?>
                                <button class="btn btn-outline-danger" onclick="deletePost(<?= $post['id'] ?>)">
                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                </button>
                            <?php else: ?>
                                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#reportModal">
                                    <i class="fas fa-flag me-1"></i> Report
                                </button>
                            <?php endif; ?>
                            <?php if ($post['type'] === 'donation' && $post['user_id'] === $_SESSION['user_id']): ?>
                                <button class="btn btn-outline-info me-2" onclick="showStatusModal(<?= $post['id'] ?>, '<?= $post['status'] ?>')">
                                    <i class="fas fa-sync-alt me-1"></i> Update Status
                                </button>
                            <?php endif; ?>
                            <?php if ($post['type'] === 'donation' && $post['user_id'] !== $_SESSION['user_id']): ?>
                                <?php
                                // Check if user has already requested this donation
                                $stmt = $pdo->prepare("SELECT status FROM donation_requests 
                                                      WHERE post_id = ? AND requester_id = ?");
                                $stmt->execute([$post['id'], $_SESSION['user_id']]);
                                $existingRequest = $stmt->fetch();
                                ?>
                                
                                <?php if (!$existingRequest): ?>
                                    <button class="btn btn-success me-2" onclick="showRequestModal(<?= $post['id'] ?>)">
                                        <i class="fas fa-hand-holding-heart me-1"></i> Request Donation
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-<?= 
                                        $existingRequest['status'] === 'pending' ? 'warning' : 
                                        ($existingRequest['status'] === 'approved' ? 'success' : 'secondary') 
                                    ?> me-2">
                                        Request <?= ucfirst($existingRequest['status']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Back Button -->
                <a href="main.php" class="btn btn-primary rounded-circle back-button" 
                   title="Back to Home">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Report Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="reportForm" method="POST" action="report-post.php">
                    <div class="modal-body">
                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                        <input type="hidden" name="reported_user_id" value="<?= $post['user_id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for reporting</label>
                            <select name="type" class="form-select" required>
                                <option value="">Select a reason...</option>
                                <option value="spam">Spam or misleading</option>
                                <option value="inappropriate">Inappropriate content</option>
                                <option value="harassment">Harassment or hate speech</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional details</label>
                            <textarea name="reason" class="form-control" rows="3" 
                                placeholder="Please provide more details about your report"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Comments Modal -->
    <div class="modal fade" id="commentsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Comments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="commentsList" class="comments-list mb-3">
                        <!-- Comments will be loaded here -->
                    </div>
                    <form id="commentForm" class="comment-form">
                        <input type="hidden" name="post_id" id="commentPostId" value="<?= $post['id'] ?>">
                        <div class="input-group">
                            <textarea class="form-control" name="content" placeholder="Write a comment..." required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this post? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Donation Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="statusForm">
                    <div class="modal-body">
                        <input type="hidden" name="post_id" id="statusPostId">
                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <select name="status" class="form-select" required>
                                <option value="available">Available</option>
                                <option value="claimed">Claimed</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Donation Request Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Donation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="requestForm">
                    <div class="modal-body">
                        <input type="hidden" name="post_id" id="requestPostId">
                        <div class="mb-3">
                            <label class="form-label">Why do you need this donation?</label>
                            <textarea name="notes" class="form-control" rows="4" 
                                    placeholder="Please explain why you're requesting this donation..."
                                    required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Load Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Share functionality
        const shareButton = document.querySelector('.btn-outline-secondary');
        if (shareButton) {
            shareButton.addEventListener('click', function() {
                const url = window.location.href;
                
                if (navigator.share) {
                    navigator.share({
                        title: '<?= addslashes($post['title']) ?>',
                        text: 'Check out this post on Helpie!',
                        url: url
                    }).catch(console.error);
                } else {
                    navigator.clipboard.writeText(url)
                        .then(() => {
                            alert('Link copied to clipboard!');
                        })
                        .catch(console.error);
                }
            });
        }

        // Report form handling
        const reportForm = document.getElementById('reportForm');
        if (reportForm) {
            reportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                fetch('report-post.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(response => response.json())
                .then(data => {
                    // Hide the modal
                    bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
                    
                    if (data.success) {
                        alert('Thank you for your report. We will review it shortly.');
                    } else {
                        alert(data.message || 'An error occurred. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        }

        // Handle image loading
        const postImage = document.querySelector('.post-image');
        if (postImage) {
            postImage.addEventListener('error', function() {
                console.error('Image failed to load:', this.src);
                this.src = '/helpie/public/assets/images/default-post-image.jpg';
            });
        }
    });

    // Add this inside your existing <script> tag
    function showComments(postId) {
        const commentsModal = new bootstrap.Modal(document.getElementById('commentsModal'));
        const commentsList = document.getElementById('commentsList');
        const commentForm = document.getElementById('commentForm');
        
        // Clear existing comments
        commentsList.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>';
        
        // Load comments
        fetch(`get-comments.php?post_id=${postId}`)
            .then(response => response.json())
            .then(comments => {
                if (comments.length === 0) {
                    commentsList.innerHTML = '<div class="text-center text-muted">No comments yet</div>';
                    return;
                }
                
                commentsList.innerHTML = comments.map(comment => `
                    <div class="comment-item">
                        <div class="comment-header">
                            <img src="${comment.profile_image || 'assets/images/default-avatar.jpg'}" 
                                alt="${comment.username}" 
                                class="comment-avatar">
                            <div>
                                <div class="comment-user">${comment.username}</div>
                                <div class="comment-time">${comment.created_at}</div>
                            </div>
                        </div>
                        <div class="comment-content">${comment.content}</div>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error loading comments:', error);
                commentsList.innerHTML = '<div class="text-center text-danger">Failed to load comments</div>';
            });

        // Handle comment submission
        commentForm.onsubmit = function(e) {
            e.preventDefault();
            const content = this.querySelector('textarea').value.trim();
            
            if (!content) return;
            
            fetch('add-comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}&content=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear the form
                    this.reset();
                    // Reload comments
                    showComments(postId);
                } else {
                    alert(data.message || 'Error posting comment');
                }
            })
            .catch(error => {
                console.error('Error posting comment:', error);
                alert('Error posting comment');
            });
        };
        
        commentsModal.show();
    }

    function deletePost(postId) {
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        const confirmButton = document.getElementById('confirmDelete');
        
        confirmButton.onclick = function() {
            fetch('delete-post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}`
            })
            .then(response => response.json())
            .then(data => {
                deleteModal.hide();
                
                if (data.success) {
                    // Show success message
                    alert('Post deleted successfully');
                    // Redirect to main page
                    window.location.href = 'main.php';
                } else {
                    alert(data.error || 'Error deleting post');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting post');
            });
        };
        
        deleteModal.show();
    }

    function showStatusModal(postId, currentStatus) {
        const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
        const statusForm = document.getElementById('statusForm');
        const statusSelect = statusForm.querySelector('select[name="status"]');
        
        // Set current status
        statusSelect.value = currentStatus || 'available';
        document.getElementById('statusPostId').value = postId;
        
        // Handle form submission
        statusForm.onsubmit = function(e) {
            e.preventDefault();
            
            fetch('update-post-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}&status=${statusSelect.value}`
            })
            .then(response => response.json())
            .then(data => {
                statusModal.hide();
                
                if (data.success) {
                    // Update status badge if it exists
                    const statusBadge = document.querySelector('.status-badge');
                    if (statusBadge) {
                        statusBadge.textContent = statusSelect.value.charAt(0).toUpperCase() + 
                                               statusSelect.value.slice(1);
                        statusBadge.className = `status-badge badge bg-${
                            statusSelect.value === 'available' ? 'success' : 
                            statusSelect.value === 'claimed' ? 'secondary' : 'warning'
                        }`;
                    }
                    
                    alert('Status updated successfully');
                    location.reload(); // Refresh to show updated status
                } else {
                    alert(data.message || 'Error updating status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating status');
            });
        };
        
        statusModal.show();
    }

    function showRequestModal(postId) {
        const requestModal = new bootstrap.Modal(document.getElementById('requestModal'));
        const requestForm = document.getElementById('requestForm');
        document.getElementById('requestPostId').value = postId;
        
        requestForm.onsubmit = function(e) {
            e.preventDefault();
            const notes = this.querySelector('textarea').value.trim();
            
            fetch('request-donation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}&notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                requestModal.hide();
                
                if (data.success) {
                    alert('Donation request submitted successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Error submitting request');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error submitting request');
            });
        };
        
        requestModal.show();
    }
    </script>
</body>
</html>