<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Validate post ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid post ID']));
}

try {
    // Fetch post with category and stats
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.name as category_name,
            c.id as category_id,
            (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id) as save_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.user_id = ?
    ");
    
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        die(json_encode(['error' => 'Post not found']));
    }

    // Format dates
    $post['created_at_formatted'] = time_elapsed_string($post['created_at']);
    
    // Add image URL if exists
    if ($post['image']) {
        $post['image_url'] = getImageUrl($post['image']);
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $post
    ]);

} catch (PDOException $e) {
    // Log error and return generic message
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error occurred',
        'debug' => DEBUG ? $e->getMessage() : null
    ]);
}