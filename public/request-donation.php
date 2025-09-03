<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to request donations'
    ]);
    exit;
}

// Validate input
if (!isset($_POST['post_id']) || !isset($_POST['notes'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

try {
    // Check if post exists and is a donation
    $stmt = $pdo->prepare("SELECT id, user_id FROM posts WHERE id = ? AND type = 'donation'");
    $stmt->execute([$_POST['post_id']]);
    $post = $stmt->fetch();

    if (!$post) {
        throw new Exception('Post not found or is not a donation');
    }

    // Check if user is not requesting their own donation
    if ($post['user_id'] === $_SESSION['user_id']) {
        throw new Exception('You cannot request your own donation');
    }

    // Check if user has already requested this donation
    $stmt = $pdo->prepare("SELECT id FROM donation_requests 
                          WHERE post_id = ? AND requester_id = ?");
    $stmt->execute([$_POST['post_id'], $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        throw new Exception('You have already requested this donation');
    }

    // Create the request
    $stmt = $pdo->prepare("INSERT INTO donation_requests 
                          (post_id, requester_id, notes) 
                          VALUES (?, ?, ?)");
    $stmt->execute([
        $_POST['post_id'],
        $_SESSION['user_id'],
        $_POST['notes']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Request submitted successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}