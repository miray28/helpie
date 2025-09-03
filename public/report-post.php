<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

use function Helpie\Functions\verifyCsrfToken;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    exit(json_encode([
        'success' => false, 
        'message' => 'Please log in to report posts'
    ]));
}

// Verify CSRF token
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    error_log('CSRF Token Verification Failed');
    exit(json_encode([
        'success' => false, 
        'message' => 'Invalid security token'
    ]));
}

try {
    // Validate required fields
    $required = ['post_id', 'reported_user_id', 'type'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate report type
    $allowed_types = ['spam', 'inappropriate', 'harassment'];
    if (!in_array($_POST['type'], $allowed_types)) {
        throw new Exception("Invalid report type");
    }

    // Insert report
    $stmt = $pdo->prepare("
        INSERT INTO reports (
            content_type, 
            content_id, 
            reporter_id, 
            reported_user_id, 
            type, 
            reason
        ) VALUES (
            'post',
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");

    $stmt->execute([
        $_POST['post_id'],
        $_SESSION['user_id'],
        $_POST['reported_user_id'],
        $_POST['type'],
        $_POST['reason'] ?? null
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Report Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while submitting your report'
    ]);
}