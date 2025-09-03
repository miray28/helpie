<?php
// Get conversations
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
    LIMIT 10
");

$stmt->execute([
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id']
]);

$conversations = $stmt->fetchAll();
?>

<div class="conversation-sidebar">
    <div class="conversation-header">
        <div class="d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Messages</h5>
            <button type="button" class="btn btn-sm btn-primary rounded-circle" title="New Message">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>
    <div class="conversations-list">
        <?php if (empty($conversations)): ?>
            <div class="text-center text-muted p-4">
                <i class="fas fa-comments fa-2x mb-2"></i>
                <p class="mb-0">No conversations yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($conversations as $conv): ?>
                <a href="chat.php?with=<?= $conv['user1_id'] === $_SESSION['user_id'] ? 
                    $conv['user2_id'] : $conv['user1_id'] ?>" 
                   class="conversation-item <?= isset($_GET['with']) && $_GET['with'] == ($conv['user1_id'] === $_SESSION['user_id'] ? $conv['user2_id'] : $conv['user1_id']) ? 'active' : '' ?>">
                    <img src="<?= !empty($conv['profile_image']) ? 
                        htmlspecialchars($conv['profile_image']) : 
                        'assets/images/default-avatar.jpg' ?>" 
                         alt="<?= htmlspecialchars($conv['username']) ?>" 
                         class="conversation-avatar">
                    <div class="conversation-details">
                        <div class="conversation-name"><?= htmlspecialchars($conv['username']) ?></div>
                        <?php if ($conv['last_message']): ?>
                            <div class="conversation-preview">
                                <?= htmlspecialchars(substr($conv['last_message'], 0, 30)) ?>...
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($conv['unread_count'] > 0): ?>
                        <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>