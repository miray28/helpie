<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to update post status'
    ]);
    exit;
}

// Validate input
if (!isset($_POST['post_id']) || !isset($_POST['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

$allowed_statuses = ['available', 'claimed', 'pending'];
if (!in_array($_POST['status'], $allowed_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status'
    ]);
    exit;
}

try {
    // Verify post ownership
    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND user_id = ? AND type = 'donation'");
    $stmt->execute([$_POST['post_id'], $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Post not found or unauthorized'
        ]);
        exit;
    }

    // Update status
    $stmt = $pdo->prepare("UPDATE posts SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['post_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);

} catch (PDOException $e) {
    error_log("Status update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}