<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base paths
define('BASEPATH', dirname(__DIR__));
define('PUBLIC_PATH', BASEPATH . '/public');
define('BASE_URL', '/helpie');
define('PUBLIC_URL', 'http://localhost/helpie/public');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'helpie');
define('DB_USER', 'root');     // Default WAMP username
define('DB_PASS', '');         // Default WAMP password

// Add debug mode
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Set default timezone
date_default_timezone_set('UTC');

// Load essential files
require_once BASEPATH . '/includes/db.php';
require_once BASEPATH . '/includes/functions.php';
require_once BASEPATH . '/includes/auth.php';

// Application configuration
define('SITE_NAME', 'Helpie');
define('SITE_URL', 'http://localhost/helpie');
define('UPLOAD_PATH', __DIR__ . '/../public/uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Allowed file types
define('ALLOWED_IMAGES', ['image/jpeg', 'image/png', 'image/gif']);

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}

// Upload settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png']);

// Ensure upload directories exist
$uploadDirs = ['profiles', 'covers'];
foreach ($uploadDirs as $dir) {
    $path = UPLOAD_PATH . $dir;
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }
}

// Connect to database
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
$username = DB_USER;
$password = DB_PASS;

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}