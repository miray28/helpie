<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    // Update miray28 to be admin
    $stmt = $pdo->prepare("
        UPDATE users 
        SET role = 'admin', 
            is_admin = 1 
        WHERE username = ?
    ");
    
    $stmt->execute(['miray28']);
    
    // Verify the update
    $check = $pdo->prepare("SELECT id, username, role, is_admin FROM users WHERE username = ?");
    $check->execute(['miray28']);
    $user = $check->fetch(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    if ($user) {
        echo "User updated successfully:\n";
        echo "Username: {$user['username']}\n";
        echo "Role: {$user['role']}\n";
        echo "Is Admin: " . ($user['is_admin'] ? 'Yes' : 'No') . "\n";
        echo "\nYou can now login with your existing credentials.";
    } else {
        echo "User not found!";
    }
    echo "</pre>";
    
} catch (PDOException $e) {
    die("Error updating user: " . $e->getMessage());
}
?>