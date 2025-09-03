<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';

try {
    $username = 'manel'; // The username to make admin
    
    $stmt = $pdo->prepare("UPDATE users SET is_admin = 1, role = 'admin' WHERE username = ?");
    $result = $stmt->execute([$username]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo "Successfully made $username an admin";
    } else {
        echo "Failed to update user or user not found";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}