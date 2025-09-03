<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;

class ChatServer implements \Ratchet\MessageComponentInterface {
    protected $clients;
    protected $userConnections;
    protected $pdo;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->pdo = $GLOBALS['pdo'];
        
        // Enable error logging
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
        ini_set('error_log', dirname(__DIR__) . '/logs/websocket.log');
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            
            if (!isset($data['type'])) {
                throw new \Exception('Message type not specified');
            }
            
            switch ($data['type']) {
                case 'auth':
                    if (!isset($data['userId'])) {
                        throw new \Exception('User ID not provided for authentication');
                    }
                    $this->userConnections[$data['userId']] = $from;
                    $from->send(json_encode([
                        'type' => 'auth',
                        'status' => 'success'
                    ]));
                    break;

                case 'chat':
                    $this->handleChatMessage($from, $data);
                    break;

                default:
                    throw new \Exception('Unknown message type');
            }
        } catch (\Exception $e) {
            echo "Error processing message: " . $e->getMessage() . "\n";
            $from->send(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ]));
        }
    }

    protected function handleChatMessage($from, $data) {
        try {
            if (!isset($data['senderId'], $data['receiverId'], $data['message'])) {
                throw new \Exception('Missing required message fields');
            }

            // Sanitize and validate inputs
            $senderId = filter_var($data['senderId'], FILTER_VALIDATE_INT);
            $receiverId = filter_var($data['receiverId'], FILTER_VALIDATE_INT);
            $message = htmlspecialchars(trim($data['message']), ENT_QUOTES, 'UTF-8');

            if (!$senderId || !$receiverId || empty($message)) {
                throw new \Exception('Invalid message data');
            }

            // Start transaction
            $this->pdo->beginTransaction();

            try {
                // Get or create conversation
                $stmt = $this->pdo->prepare("
                    SELECT id FROM chat_conversations 
                    WHERE (user1_id = :sender AND user2_id = :receiver)
                    OR (user1_id = :receiver AND user2_id = :sender)
                ");
                
                $stmt->execute([
                    ':sender' => $senderId,
                    ':receiver' => $receiverId
                ]);
                
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$conversation) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO chat_conversations (user1_id, user2_id, created_at, updated_at)
                        VALUES (:sender, :receiver, NOW(), NOW())
                    ");
                    
                    $stmt->execute([
                        ':sender' => $senderId,
                        ':receiver' => $receiverId
                    ]);
                    
                    $conversationId = $this->pdo->lastInsertId();
                } else {
                    $conversationId = $conversation['id'];
                }

                // Save message
                $stmt = $this->pdo->prepare("
                    INSERT INTO chat_messages (
                        conversation_id, sender_id, receiver_id, message, created_at
                    ) VALUES (
                        :conv_id, :sender, :receiver, :message, NOW()
                    )
                ");
                
                $stmt->execute([
                    ':conv_id' => $conversationId,
                    ':sender' => $senderId,
                    ':receiver' => $receiverId,
                    ':message' => $message
                ]);

                $messageId = $this->pdo->lastInsertId();
                $timestamp = date('Y-m-d H:i:s');

                // Update conversation
                $stmt = $this->pdo->prepare("
                    UPDATE chat_conversations 
                    SET last_message_id = :msg_id, updated_at = NOW()
                    WHERE id = :conv_id
                ");
                
                $stmt->execute([
                    ':msg_id' => $messageId,
                    ':conv_id' => $conversationId
                ]);

                $this->pdo->commit();

                // Send to receiver if online
                if (isset($this->userConnections[$receiverId])) {
                    $this->userConnections[$receiverId]->send(json_encode([
                        'type' => 'chat',
                        'senderId' => $senderId,
                        'message' => $message,
                        'timestamp' => $timestamp,
                        'conversation_id' => $conversationId
                    ]));
                }

                // Send confirmation to sender
                $from->send(json_encode([
                    'type' => 'confirmation',
                    'messageId' => $messageId,
                    'timestamp' => $timestamp,
                    'conversation_id' => $conversationId
                ]));

            } catch (\Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            error_log("Chat Error: " . $e->getMessage());
            $from->send(json_encode([
                'type' => 'error',
                'message' => 'Failed to send message'
            ]));
        }
    }

    public function onClose(\Ratchet\ConnectionInterface $conn) {
        $this->clients->detach($conn);
        // Remove from user connections
        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                break;
            }
        }
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Server configuration
$port = 8081;
$host = '127.0.0.1';

try {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new ChatServer()
            )
        ),
        $port,
        $host
    );

    echo "Chat WebSocket server running on {$host}:{$port}...\n";
    $server->run();
    
} catch (\Exception $e) {
    error_log("Server Error: " . $e->getMessage());
    die("Failed to start WebSocket server: " . $e->getMessage() . "\n");
}