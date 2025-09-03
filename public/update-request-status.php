<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to update requests'
    ]);
    exit;
}

// Validate input
if (!isset($_POST['request_id']) || !isset($_POST['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

$allowed_statuses = ['approved', 'rejected'];
if (!in_array($_POST['status'], $allowed_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status'
    ]);
    exit;
}

try {
    // Verify ownership of the post
    $stmt = $pdo->prepare("
        SELECT dr.id 
        FROM donation_requests dr
        JOIN posts p ON dr.post_id = p.id
        WHERE dr.id = ? AND p.user_id = ? AND dr.status = 'pending'
    ");
    $stmt->execute([$_POST['request_id'], $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Request not found or not authorized');
    }

    // Update request status
    $stmt = $pdo->prepare("
        UPDATE donation_requests 
        SET status = ? 
        WHERE id = ?
    ");
    $stmt->execute([$_POST['status'], $_POST['request_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Request status updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}