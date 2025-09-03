<?php

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        error_log("Database Connection Error: " . $e->getMessage());
        die("Database connection failed. Please try again later.");
    }
}

// Test connection
try {
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    error_log("Database Connection Test Failed: " . $e->getMessage());
    die("Database connection test failed.");
}