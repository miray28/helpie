<?php
require_once 'auth.php';

use function Helpie\Auth\isAdmin;
use function Helpie\Auth\requireAdmin;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isAdmin() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['is_admin']) && 
           $_SESSION['is_admin'] === 1;
}

function requireAdmin() {
    if (!isAdmin()) {
        $_SESSION['error'] = "You must be an administrator to access this area.";
        header('Location: /helpie/public/login.php');
        exit;
    }
}