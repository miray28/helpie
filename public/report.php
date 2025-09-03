<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/utils.php';

use function Helpie\Utils\generateCsrfToken;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Validate required parameters
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    $_SESSION['error'] = 'Invalid report parameters';
    header('Location: main.php');
    exit;
}

$type = $_GET['type'];
$id = (int)$_GET['id'];

// Validate report type
$allowedTypes = ['post', 'user', 'comment'];
if (!in_array($type, $allowedTypes)) {
    $_SESSION['error'] = 'Invalid report type';
    header('Location: main.php');
    exit;
}

try {
    // Get details of the reported content
    switch ($type) {
        case 'user':
            $stmt = $pdo->prepare("
                SELECT id, username 
                FROM users 
                WHERE id = ? AND id != ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $content = $stmt->fetch();
            $title = "Report User";
            break;

        case 'post':
            $stmt = $pdo->prepare("
                SELECT p.id, p.title, p.user_id, u.username
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.id = ? AND p.user_id != ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $content = $stmt->fetch();
            $title = "Report Post";
            break;

        case 'comment':
            $stmt = $pdo->prepare("
                SELECT c.id, c.content, c.user_id, u.username
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.id = ? AND c.user_id != ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $content = $stmt->fetch();
            $title = "Report Comment";
            break;
    }

    if (!$content) {
        throw new Exception('Content not found or cannot report own content');
    }

} catch (Exception $e) {
    $_SESSION['error'] = 'Error loading content: ' . $e->getMessage();
    header('Location: main.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .report-form {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .form-label {
            font-weight: 500;
        }

        .btn-submit {
            width: 100%;
            padding: 0.75rem;
        }

        .content-preview {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .report-form {
                margin: 1rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/nav.php'; ?>

    <div class="container">
        <div class="report-form">
            <h2 class="mb-4"><?= $title ?></h2>

            <div class="content-preview">
                <?php if ($type === 'user'): ?>
                    <p><strong>User:</strong> <?= htmlspecialchars($content['username']) ?></p>
                <?php elseif ($type === 'post'): ?>
                    <p><strong>Post:</strong> <?= htmlspecialchars($content['title']) ?></p>
                    <p><strong>By:</strong> <?= htmlspecialchars($content['username']) ?></p>
                <?php else: ?>
                    <p><strong>Comment by:</strong> <?= htmlspecialchars($content['username']) ?></p>
                    <p><strong>Content:</strong> <?= htmlspecialchars($content['content']) ?></p>
                <?php endif; ?>
            </div>

            <form id="reportForm" action="submit-report.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <input type="hidden" name="content_type" value="<?= htmlspecialchars($type) ?>">
                <input type="hidden" name="content_id" value="<?= $content['id'] ?>">
                <input type="hidden" name="reported_user_id" value="<?= $content['user_id'] ?? $content['id'] ?>">

                <div class="mb-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="type" required>
                        <option value="">Select a reason</option>
                        <option value="spam">Spam</option>
                        <option value="inappropriate">Inappropriate Content</option>
                        <option value="harassment">Harassment</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">Additional Details</label>
                    <textarea class="form-control" name="reason" rows="4" 
                              placeholder="Please provide more details about your report..." required></textarea>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-submit">
                        <span class="btn-text">Submit Report</span>
                        <span class="btn-loading d-none">
                            <i class="fas fa-spinner fa-spin me-2"></i>Loading...
                        </span>
                    </button>
                    <a href="#" onclick="history.back()" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            
            // Show loading state
            submitBtn.disabled = true;
            btnText.classList.add('d-none');
            btnLoading.classList.remove('d-none');

            // Submit form
            fetch('submit-report.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Report submitted successfully');
                    history.back();
                } else {
                    throw new Error(data.message || 'Error submitting report');
                }
            })
            .catch(error => {
                alert(error.message);
                // Reset button state
                submitBtn.disabled = false;
                btnText.classList.remove('d-none');
                btnLoading.classList.add('d-none');
            });
        });
    </script>
</body>
</html>