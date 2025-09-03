<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Test values
$test_username = 'admin';
$test_password = '2808@Hsgb';

try {
    // Get admin user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$test_username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<pre>";
    if ($user) {
        echo "Admin user found:\n";
        echo "ID: {$user['id']}\n";
        echo "Username: {$user['username']}\n";
        echo "Email: {$user['email']}\n";
        echo "Stored hash: {$user['password']}\n\n";

        // Test password verification
        $verify_result = password_verify($test_password, $user['password']);
        echo "Password verification result: " . ($verify_result ? "SUCCESS" : "FAILED") . "\n\n";

        // Generate new hash and update if verification failed
        if (!$verify_result) {
            $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "Current entered password: $test_password\n";
            echo "New hash generated: $new_hash\n";
            
            // Verify the new hash works before updating
            $verify_new = password_verify($test_password, $new_hash);
            echo "New hash verification test: " . ($verify_new ? "SUCCESS" : "FAILED") . "\n";
            
            if ($verify_new) {
                // Update admin password
                $update = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
                $update->execute([$new_hash, $test_username]);
                echo "\nPassword updated in database!\n";
                echo "Please try logging in again with:\n";
                echo "Username: admin\n";
                echo "Password: 2808@Hsgb\n";
            }
        }
    } else {
        echo "Admin user not found!";
    }
    echo "</pre>";
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>