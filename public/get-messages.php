<?php
ob_clean(); // Clear any output buffer
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

use function Helpie\Auth\requireLogin;
requireLogin();

try {
    // Validate user ID using filter_input for better security
    $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    
    if (!$user_id) {
        throw new Exception('Invalid user ID');
    }

    // Get conversation using named parameters for better security
    $stmt = $pdo->prepare("
        SELECT id 
        FROM chat_conversations 
        WHERE (user1_id = :current_user AND user2_id = :other_user) 
        OR (user1_id = :other_user AND user2_id = :current_user)
    ");

    $stmt->execute([
        ':current_user' => $_SESSION['user_id'],
        ':other_user' => $user_id
    ]);
    
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        echo json_encode([
            'success' => true,
            'messages' => [],
            'conversation_id' => null
        ]);
        exit;
    }

    // Get messages with specific fields to reduce data transfer
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.sender_id,
            m.message as message_text,
            m.created_at,
            u.username as sender_username
        FROM chat_messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = :conversation_id
        ORDER BY m.created_at ASC
    ");

    $stmt->execute([':conversation_id' => $conversation['id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format timestamps for consistent output
    //Here, I format the created_at timestamp to ISO 8601 (date('c')) which is cleaner and standard for frontend parsing.
    foreach ($messages as &$message) {
        $message['created_at'] = date('c', strtotime($message['created_at']));
    }

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'conversation_id' => $conversation['id']
    ]);

} catch (Exception $e) {
    error_log("Chat Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load messages'
    ]);
}
// No closing PHP tag to prevent accidental whitespace