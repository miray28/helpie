<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Validate post ID
if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid post ID']));
}

try {
    $pdo->beginTransaction();

    // First verify post ownership
    $stmt = $pdo->prepare("
        SELECT image 
        FROM posts 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$_POST['post_id'], $_SESSION['user_id']]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        throw new Exception('Post not found or unauthorized');
    }

    // Delete associated records first
    $tables = ['comments', 'saved_posts'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE post_id = ?");
        $stmt->execute([$_POST['post_id']]);
    }

    // Delete the post
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$_POST['post_id']]);

    // Delete associated image if exists
    if ($post['image']) {
        $imagePath = __DIR__ . '/uploads/posts/' . $post['image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Post deleted successfully'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Delete Post Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete post',
        'debug' => DEBUG ? $e->getMessage() : null
    ]);
}