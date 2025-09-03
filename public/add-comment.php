<?php
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['post_id']) || !isset($_POST['content'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid request']));
}

try {
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->execute([
        $_POST['post_id'],
        $_SESSION['user_id'],
        trim($_POST['content'])
    ]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Server error']));
}