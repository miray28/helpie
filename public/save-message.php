<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

use function Helpie\Auth\requireLogin;

// Require user to be logged in
requireLogin();

// Set header to accept JSON
header('Content-Type: application/json');

try {
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['receiverId']) || !isset($input['message'])) {
        throw new Exception('Invalid input data');
    }

    $sender_id = $_SESSION['user_id'];
    $receiver_id = (int)$input['receiverId'];
    $message = trim($input['message']);

    if (empty($message)) {
        throw new Exception('Message cannot be empty');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Get or create conversation
    $stmt = $pdo->prepare("
        SELECT id FROM chat_conversations 
        WHERE (user1_id = ? AND user2_id = ?) 
        OR (user1_id = ? AND user2_id = ?)
    ");
    $stmt->execute([
        $sender_id, $receiver_id,
        $receiver_id, $sender_id
    ]);
    
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        // Create new conversation
        $stmt = $pdo->prepare("
            INSERT INTO chat_conversations (user1_id, user2_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$sender_id, $receiver_id]);
        $conversation_id = $pdo->lastInsertId();
    } else {
        $conversation_id = $conversation['id'];
    }

    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages 
        (conversation_id, sender_id, receiver_id, message) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$conversation_id, $sender_id, $receiver_id, $message]);
    $message_id = $pdo->lastInsertId();

    // Update conversation's last message
    $stmt = $pdo->prepare("
        UPDATE chat_conversations 
        SET last_message_id = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$message_id, $conversation_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'messageId' => $message_id,
        'conversationId' => $conversation_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Save Message Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}