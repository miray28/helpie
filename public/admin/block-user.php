<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

use function Helpie\Functions\{generateCsrfToken, verifyCsrfToken};

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode([
        'success' => false, 
        'message' => 'Unauthorized access'
    ]));
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode([
        'success' => false, 
        'message' => 'Invalid request method'
    ]));
}

try {
    // Get and validate input
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
        throw new Exception('Invalid user ID');
    }

    // Verify CSRF token
    if (!verifyCsrfToken($data['csrf_token'] ?? null)) {
        throw new Exception('Invalid security token');
    }

    // Prevent blocking self or other admins
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$data['user_id']]);
    $user = $stmt->fetch();

    if ($data['user_id'] == $_SESSION['user_id']) {
        throw new Exception('Cannot block yourself');
    }

    if ($user['role'] === 'admin') {
        throw new Exception('Cannot block administrators');
    }

    // Block the user
    $stmt = $pdo->prepare("UPDATE users SET blocked = 1 WHERE id = ?");
    $stmt->execute([$data['user_id']]);

    // Log the action
    $stmt = $pdo->prepare("
        INSERT INTO admin_logs (admin_id, action, target_id, target_type) 
        VALUES (?, 'block_user', ?, 'user')
    ");
    $stmt->execute([$_SESSION['user_id'], $data['user_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'User blocked successfully'
    ]);

} catch (Exception $e) {
    error_log("Block User Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}