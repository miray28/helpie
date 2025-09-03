<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Debug logging
error_log("=== Toggle Save Post Request ===");
error_log("POST data: " . print_r($_POST, true));
error_log("Session data: " . print_r($_SESSION, true));

// Check login
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please login to save posts'
    ]);
    exit;
}

// Validate post_id
if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
    error_log("Invalid post_id: " . ($_POST['post_id'] ?? 'not set'));
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid post ID'
    ]);
    exit;
}

try {
    $post_id = (int)$_POST['post_id'];
    $user_id = (int)$_SESSION['user_id'];

    error_log("Attempting to save/unsave - Post ID: $post_id, User ID: $user_id");

    // Verify post exists
    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Post not found");
    }

    // Start transaction
    $pdo->beginTransaction();
    error_log("Transaction started");

    // Check if already saved
    $stmt = $pdo->prepare("SELECT id FROM saved_posts WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $user_id]);
    $saved = $stmt->fetch();
    error_log("Current save status: " . ($saved ? "Saved" : "Not saved"));

    if ($saved) {
        // Remove save
        $stmt = $pdo->prepare("DELETE FROM saved_posts WHERE post_id = ? AND user_id = ?");
        $result = $stmt->execute([$post_id, $user_id]);
        error_log("Delete result: " . ($result ? "Success" : "Failed"));
        $message = 'Post removed from saved items';
        $is_saved = false;
    } else {
        // Add save
        $stmt = $pdo->prepare("INSERT INTO saved_posts (post_id, user_id) VALUES (?, ?)");
        $result = $stmt->execute([$post_id, $user_id]);
        error_log("Insert result: " . ($result ? "Success" : "Failed"));
        if (!$result) {
            error_log("PDO Error Info: " . print_r($stmt->errorInfo(), true));
        }
        $message = 'Post saved successfully';
        $is_saved = true;
    }

    // Get updated save count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_posts WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $save_count = $stmt->fetchColumn();
    error_log("Updated save count: $save_count");

    $pdo->commit();
    error_log("Transaction committed");

    echo json_encode([
        'success' => true,
        'message' => $message,
        'is_saved' => $is_saved,
        'save_count' => $save_count
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PDO Error: " . $e->getMessage());
    error_log("PDO Error Info: " . print_r($e->errorInfo, true));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("General Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}