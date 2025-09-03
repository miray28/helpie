<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/utils.php';

use function Helpie\Utils\verifyCsrfToken;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        throw new Exception('Invalid security token');
    }

    // Validate required fields
    $required = ['content_type', 'content_id', 'reported_user_id', 'type', 'reason'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Insert report
    $stmt = $pdo->prepare("
        INSERT INTO reports (
            content_type, 
            content_id, 
            reporter_id, 
            reported_user_id, 
            type, 
            reason, 
            status
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");

    $stmt->execute([
        $_POST['content_type'],
        $_POST['content_id'],
        $_SESSION['user_id'],
        $_POST['reported_user_id'],
        $_POST['type'],
        $_POST['reason']
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Report submission error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}