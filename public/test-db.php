<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM users WHERE is_admin = 1");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r([
        'DB Connection' => 'Success',
        'Admin Users' => $admins
    ]);
    echo "</pre>";
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}