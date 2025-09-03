<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

use function Helpie\Auth\requireLogin;

// Require user to be logged in
requireLogin();

// Get conversations
try {
    $chat_with_id = isset($_GET['with']) ? (int)$_GET['with'] : 0;

    if ($chat_with_id <= 0) {
        $_SESSION['error'] = 'Invalid user ID provided';
        header('Location: main.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, blocked FROM users WHERE id = ?");
    $stmt->execute([$chat_with_id]);
    $user = $stmt->fetch();

    if (!$user || $user['blocked']) {
        throw new Exception('User not found or is blocked');
    }

    // Check if conversation exists or create new one
    $stmt = $pdo->prepare("
        SELECT id FROM chat_conversations 
        WHERE (user1_id = ? AND user2_id = ?) 
        OR (user1_id = ? AND user2_id = ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'], $chat_with_id,
        $chat_with_id, $_SESSION['user_id']
    ]);
    
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        // Create new conversation without explicit timestamps
        $stmt = $pdo->prepare("
            INSERT INTO chat_conversations (user1_id, user2_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $chat_with_id]);
    }

    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.username,
            u.profile_image,
            m.message as last_message,
            m.created_at as last_message_time,
            (SELECT COUNT(*) FROM chat_messages 
             WHERE receiver_id = ? AND read_at IS NULL 
             AND sender_id = IF(c.user1_id = ?, c.user2_id, c.user1_id)) as unread_count
        FROM chat_conversations c
        JOIN users u ON (u.id = IF(c.user1_id = ?, c.user2_id, c.user1_id))
        LEFT JOIN chat_messages m ON c.last_message_id = m.id
        WHERE c.user1_id = ? OR c.user2_id = ?
        ORDER BY c.updated_at DESC
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['user_id'],
        $_SESSION['user_id'],
        $_SESSION['user_id'],
        $_SESSION['user_id']
    ]);
    
    $conversations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Chat Error (PDO): " . $e->getMessage());
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: main.php');
    exit;
} catch (Exception $e) {
    error_log("Chat Error (General): " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header('Location: main.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2DA664;
            --primary-light: rgba(45, 166, 100, 0.1);
            --secondary-color: #34c576;
            --background-color: #f3f4f6;
            --card-background: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --elevation-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            --border-radius: 16px;
            --message-radius: 20px;
        }

        /* Chat Container */
        .chat-container {
            max-width: 1200px;
            height: calc(100vh - 80px);
            margin: 70px auto 10px;
            background-color: var(--card-background);
            box-shadow: var(--elevation-shadow);
            border-radius: var(--border-radius);
            overflow: hidden;
            display: flex;
        }

        /* Conversation List */
        .conversation-list {
            width: 350px;
            border-right: 1px solid var(--border-color);
            background-color: var(--card-background);
            overflow-y: auto;
        }

        .conversation-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .conversation-item:hover {
            background-color: var(--primary-light);
        }

        .conversation-item.active {
            background-color: var(--primary-light);
            border-left: 4px solid var(--primary-color);
        }

        .conversation-item img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        /* Chat Messages Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-messages {
            flex: 1;
            padding: 1.5rem;
            background-color: var(--background-color);
            overflow-y: auto;
        }

        .message {
            max-width: 70%;
            margin-bottom: 1rem;
            padding: 12px 16px;
            border-radius: var(--message-radius);
            position: relative;
            animation: fadeIn 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            line-height: 1.5;
        }

        .message.sent {
            background-color: var(--primary-color);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }

        .message.received {
            background-color: var(--card-background);
            color: var(--text-primary);
            border-bottom-left-radius: 4px;
        }

        /* Chat Input Area */
        .chat-input {
            padding: 1.25rem;
            background-color: var(--card-background);
            border-top: 1px solid var(--border-color);
        }

        .chat-input form {
            display: flex;
            gap: 12px;
            max-width: 900px;
            margin: 0 auto;
        }

        .chat-input input {
            flex: 1;
            border-radius: 30px;
            padding: 14px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .chat-input input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(45, 166, 100, 0.1);
            outline: none;
        }

        .chat-input button {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .chat-input button:hover {
            background-color: var(--secondary-color);
            transform: scale(1.05);
        }

        /* Badges and Status */
        .unread-badge {
            background-color: var(--primary-color);
            color: white;
            border-radius: 12px;
            padding: 3px 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .message.pending {
            opacity: 0.7;
        }

        .message.failed {
            opacity: 0.7;
            background-color: #ef4444 !important;
        }

        /* Message Time */
        .message-time {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 4px;
        }

        /* User Info */
        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .last-message {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.4;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .chat-container {
                margin: 0;
                height: 100vh;
                border-radius: 0;
            }

            .conversation-list {
                position: fixed;
                width: 100%;
                height: 100vh;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }

            .conversation-list.active {
                transform: translateX(0);
            }

            .mobile-toggle {
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 1000;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background-color: var(--primary-color);
                color: white;
                box-shadow: var(--elevation-shadow);
                border: none;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
            }

            .mobile-toggle:hover {
                background-color: var(--secondary-color);
                transform: scale(1.05);
            }
        }

        /* Disconnected State */
        .chat-messages.disconnected::before {
            content: 'Chat server disconnected. Reconnecting...';
            display: block;
            text-align: center;
            padding: 0.75rem;
            background-color: #fef3c7;
            color: #92400e;
            margin-bottom: 1rem;
            border-radius: var(--border-radius);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="chat-container">
        <!-- Conversation List -->
        <div class="conversation-list">
            <?php foreach ($conversations as $conv): ?>
                <div class="conversation-item d-flex align-items-center" 
                     data-user-id="<?= $conv['user1_id'] === $_SESSION['user_id'] ? 
                                      $conv['user2_id'] : $conv['user1_id'] ?>">
                    <img src="<?= !empty($conv['profile_image']) ? 
                                htmlspecialchars($conv['profile_image']) : 
                                'assets/images/default-avatar.jpg' ?>" 
                         alt="<?= htmlspecialchars($conv['username']) ?>" 
                         class="rounded-circle me-2"
                         width="40" height="40">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?= htmlspecialchars($conv['username']) ?></h6>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($conv['last_message']): ?>
                            <small class="text-muted">
                                <?= htmlspecialchars(substr($conv['last_message'], 0, 30)) ?>...
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Chat Area -->
        <div class="chat-area">
            <div class="chat-messages" id="chatMessages">
                <!-- Messages will be loaded here -->
            </div>
            <div class="chat-input">
                <form id="messageForm" class="d-flex gap-2">
                    <input type="text" class="form-control" 
                           placeholder="Type a message..." 
                           id="messageInput" required>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // WebSocket configuration
        const WS_PORT = 8081;
        const WS_HOST = 'localhost';
        let ws = null;
        let wsRetries = 0;
        const MAX_RETRIES = 3;
        let reconnectTimer = null;

        // Add this near the top of your script section
        let currentReceiverId = null;

        function connectWebSocket() {
            if (wsRetries >= MAX_RETRIES) {
                showError('Chat server connection failed. Please refresh the page to try again.');
                return;
            }

            try {
                clearTimeout(reconnectTimer); // Clear any pending reconnection
                console.log('Attempting WebSocket connection...');
                ws = new WebSocket(`ws://${WS_HOST}:${WS_PORT}`);

                ws.onopen = () => {
                    console.log('Connected to chat server');
                    wsRetries = 0;
                    document.getElementById('chatMessages').classList.remove('disconnected');
                    ws.send(JSON.stringify({
                        type: 'auth',
                        userId: <?= $_SESSION['user_id'] ?>
                    }));
                };

                ws.onerror = (error) => {
                    console.error('WebSocket Error:', error);
                    document.getElementById('chatMessages').classList.add('disconnected');
                    handleReconnect();
                };

                // Replace the existing ws.onmessage handler with this updated version:
                ws.onmessage = (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        console.log('Received message:', data);
                        
                        if (data.type === 'chat') {
                            // Only append message if it's from another user
                            if (data.senderId !== <?= $_SESSION['user_id'] ?>) {
                                if (data.senderId === currentReceiverId) {
                                    appendMessage(data.message, 'received', data.timestamp);
                                } else {
                                    updateUnreadCount(data.senderId);
                                }
                            }
                        } else if (data.type === 'error') {
                            showError(data.message);
                        }
                    } catch (error) {
                        console.error('Error processing message:', error);
                    }
                };

                ws.onclose = () => {
                    console.log('Disconnected from chat server');
                    document.getElementById('chatMessages').classList.add('disconnected');
                    handleReconnect();
                };
            } catch (error) {
                console.error('WebSocket connection error:', error);
                handleReconnect();
            }
        }

        function handleReconnect() {
            wsRetries++;
            if (wsRetries < MAX_RETRIES) {
                console.log(`Retrying connection in 3 seconds... (Attempt ${wsRetries}/${MAX_RETRIES})`);
                reconnectTimer = setTimeout(connectWebSocket, 3000);
            } else {
                showError('Chat server connection failed. Please refresh the page to try again.');
            }
        }

        // Update loadMessages function
        function loadMessages(userId) {
            console.log('Loading messages for user:', userId);
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.innerHTML = '<div class="text-center">Loading messages...</div>';
            currentReceiverId = userId;

            fetch(`get-messages.php?user_id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    // Debug log the raw response
                    console.log('Raw response:', text);
                    
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed data:', data);
                        
                        if (data.success && Array.isArray(data.messages)) {
                            displayMessages(data.messages);
                        } else {
                            throw new Error(data.error || 'Invalid response format');
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Invalid server response');
                    }
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                    chatMessages.innerHTML = `
                        <div class="alert alert-danger m-3">
                            ${error.message || 'Unable to load messages. Please try again later.'}
                        </div>`;
                });
        }

        // Append a message to the chat
        function appendMessage(message, type, timestamp) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = `
                <div class="message-content">${message}</div>
                <div class="message-time">${formatTime(timestamp)}</div>
            `;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            return messageDiv; // Return the message element
        }

        // Format timestamp
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        // Validate message
        function validateMessage(message) {
            return message.length > 0 && message.length <= 1000;
        }

        // Add this function after the validateMessage function
        function showError(message) {
            alert(message); // For now using alert, you can make this more sophisticated later
        }

        // Add this function to display messages
        function displayMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            
            try {
                if (!messages || !Array.isArray(messages)) {
                    console.error('Invalid messages data received:', messages);
                    throw new Error('Invalid messages data');
                }

                chatMessages.innerHTML = '';
                
                messages.forEach(msg => {
                    // Debug log to see message structure
                    console.log('Processing message:', msg);

                    if (!msg || typeof msg !== 'object') {
                        console.warn('Invalid message object:', msg);
                        return;
                    }

                    // Use message_text instead of message
                    const messageText = msg.message_text || msg.message;
                    const senderId = msg.sender_id;
                    const timestamp = msg.created_at;
                    
                    if (!messageText || !senderId) {
                        console.warn('Message missing required fields:', msg);
                        return;
                    }

                    const messageType = parseInt(senderId) === <?= $_SESSION['user_id'] ?> ? 'sent' : 'received';
                    appendMessage(messageText, messageType, timestamp);
                });
                
                // Scroll to bottom after messages are loaded
                chatMessages.scrollTop = chatMessages.scrollHeight;

            } catch (error) {
                console.error('Error displaying messages:', error);
                chatMessages.innerHTML = `
                    <div class="alert alert-danger m-3">
                        Error displaying messages: ${error.message}
                    </div>`;
            }
        }

        // Add this helper function
        function updateUnreadCount(senderId) {
            const conversationItem = document.querySelector(`.conversation-item[data-user-id="${senderId}"]`);
            if (conversationItem) {
                let badge = conversationItem.querySelector('.unread-badge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'unread-badge';
                    conversationItem.querySelector('.d-flex').appendChild(badge);
                }
                const count = parseInt(badge.textContent || '0') + 1;
                badge.textContent = count;
            }
        }

        // Remove the updateSidebar references and add proper initialization code
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize WebSocket connection
            connectWebSocket();

            // Handle conversation selection
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.addEventListener('click', function() {
                    document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    loadMessages(this.dataset.userId);
                });
            });

            // Initialize chat if URL has 'with' parameter
            const urlParams = new URLSearchParams(window.location.search);
            const withUserId = urlParams.get('with');
            if (withUserId) {
                const conversationItem = document.querySelector(`.conversation-item[data-user-id="${withUserId}"]`);
                if (conversationItem) {
                    conversationItem.classList.add('active');
                    loadMessages(withUserId);
                } else {
                    loadMessages(withUserId);
                }
            }

            // Initialize message form handling
            const messageForm = document.getElementById('messageForm');
            if (messageForm) {
                messageForm.addEventListener('submit', handleMessageSubmit);
            }

            // Initialize mobile support
            initializeMobileSupport();
        });

        // Add this function to handle message submission
        function handleMessageSubmit(e) {
            e.preventDefault();
            
            if (!currentReceiverId) {
                showError('No conversation selected');
                return;
            }

            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!validateMessage(message)) {
                showError('Message must be between 1 and 1000 characters');
                return;
            }

            if (!ws || ws.readyState !== WebSocket.OPEN) {
                showError('Chat connection lost. Trying to reconnect...');
                connectWebSocket();
                return;
            }

            sendMessage(message);
            input.value = '';
        }

        // Replace the existing sendMessage function
        function sendMessage(message) {
            try {
                const messageData = {
                    type: 'chat',
                    senderId: <?= $_SESSION['user_id'] ?>,
                    receiverId: currentReceiverId,
                    message: message,
                    timestamp: new Date().toISOString()
                };

                // Show message immediately with pending status
                const messageElement = appendMessage(message, 'sent', messageData.timestamp);
                messageElement.classList.add('pending');

                fetch('save-message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        receiverId: currentReceiverId,
                        message: message
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to save message');
                    }
                    
                    // Update message data with IDs from server
                    messageData.conversationId = data.conversationId;
                    messageData.messageId = data.messageId;
                    
                    // Send via WebSocket
                    ws.send(JSON.stringify(messageData));
                    
                    // Update UI to show success
                    messageElement.classList.remove('pending');
                    messageElement.dataset.messageId = data.messageId;
                })
                .catch(error => {
                    console.error('Error saving message:', error);
                    messageElement.classList.add('failed');
                    messageElement.title = 'Failed to save: ' + error.message;
                    showError('Failed to send message: ' + error.message);
                });

            } catch (error) {
                console.error('Error sending message:', error);
                showError('Failed to send message. Please try again.');
            }
        }

        // Add to chat.php script section
        function debugRequest(url, options) {
            console.log('Request:', {
                url,
                method: options.method,
                headers: options.headers,
                body: options.body
            });
        }

        function debugResponse(response) {
            console.log('Response:', {
                status: response.status,
                statusText: response.statusText,
                headers: Object.fromEntries(response.headers.entries())
            });
            return response;
        }

        // Add this after your existing JavaScript code
        function initializeMobileSupport() {
            // Create mobile toggle button
            const toggleButton = document.createElement('button');
            toggleButton.className = 'mobile-toggle btn';
            toggleButton.innerHTML = '<i class="fas fa-comments"></i>';
            toggleButton.style.display = 'none';
            document.body.appendChild(toggleButton);

            // Handle mobile view
            function handleMobileView() {
                const conversationList = document.querySelector('.conversation-list');
                const toggle = document.querySelector('.mobile-toggle');
                
                if (window.innerWidth <= 768) {
                    toggle.style.display = 'block';
                    
                    toggle.addEventListener('click', () => {
                        conversationList.classList.toggle('active');
                    });

                    // Close conversation list when a chat is selected
                    document.querySelectorAll('.conversation-item').forEach(item => {
                        item.addEventListener('click', () => {
                            conversationList.classList.remove('active');
                        });
                    });
                } else {
                    toggle.style.display = 'none';
                    conversationList.classList.remove('active');
                }
            }

            // Initial check and event listener for window resize
            handleMobileView();
            window.addEventListener('resize', handleMobileView);
        }
    </script>
</body>
</html>