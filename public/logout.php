<?php
// Start the session if not already started
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Clear any remember-me cookies if implemented
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Log the logout action
error_log("User logged out: " . ($_SESSION['username'] ?? 'Unknown user'));

// Redirect to login page with a success message
header('Location: login.php?logout=1');
exit;