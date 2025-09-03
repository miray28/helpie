<?php
require_once '../../includes/config.php';

try {
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    if ($stmt->fetch()) {
        // If admin exists, update the password
        $password = password_hash('2808@Hsgb', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, 
                email = 'admin@admin.com' 
            WHERE username = 'admin'
        ");
        $stmt->execute([$password]);
        echo "Admin password updated successfully!";
    } else {
        // Create new admin user
        $password = password_hash('2808@Hsgb', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, is_admin, created_at) 
            VALUES (?, ?, ?, TRUE, NOW())
        ");
        
        $stmt->execute([
            'admin',
            'admin@admin.com',
            $password
        ]);
        echo "Admin user created successfully!";
    }

} catch (PDOException $e) {
    die("Error managing admin user: " . $e->getMessage());
}
?>