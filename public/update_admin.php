<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    // Update miray28 to admin role
    $stmt = $pdo->prepare("
        UPDATE users 
        SET role = 'admin',
            is_admin = 1
        WHERE username = 'miray28'
    ");
    
    if ($stmt->execute()) {
        // Verify the update
        $verify = $pdo->prepare("SELECT username, role, is_admin FROM users WHERE username = 'miray28'");
        $verify->execute();
        $user = $verify->fetch(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        echo "User successfully updated:\n";
        echo "Username: {$user['username']}\n";
        echo "Role: {$user['role']}\n";
        echo "Is Admin: " . ($user['is_admin'] ? 'Yes' : 'No') . "\n";
        echo "\nYou can now log in with your existing credentials.\n";
        echo "</pre>";
    } else {
        echo "Failed to update user.";
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>