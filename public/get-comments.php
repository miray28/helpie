<?php
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['post_id'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid request']));
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.username,
            u.profile_image,
            TIME_TO_SEC(TIMEDIFF(NOW(), c.created_at)) as seconds_ago
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_GET['post_id']]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the time for each comment
    foreach ($comments as &$comment) {
        $seconds = $comment['seconds_ago'];
        if ($seconds < 60) {
            $comment['created_at'] = 'Just now';
        } elseif ($seconds < 3600) {
            $mins = floor($seconds / 60);
            $comment['created_at'] = $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $comment['created_at'] = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($seconds / 86400);
            $comment['created_at'] = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
        
        // Escape HTML in content
        $comment['content'] = htmlspecialchars($comment['content']);
        $comment['username'] = htmlspecialchars($comment['username']);
    }
    
    echo json_encode($comments);
} catch (PDOException $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Server error']));
}