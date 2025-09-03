<?php
namespace Helpie\Auth;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Core authentication functions
 */
class Auth {
    /**
     * Check if current user is logged in
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    /**
     * Check if current user is an admin
     */
    public static function isAdmin(): bool {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['is_admin']) && 
               $_SESSION['is_admin'] === 1;
    }

    /**
     * Require admin access or redirect
     */
    public static function requireAdmin(): void {
        if (!self::isAdmin()) {
            $_SESSION['error'] = "You must be an administrator to access this area.";
            header('Location: /helpie/public/login.php');
            exit;
        }
    }
}

if (!defined('BASEPATH')) {
    define('BASEPATH', dirname(__DIR__));
}

// Ensure database connection is available
require_once BASEPATH . '/includes/db.php';

/**
 * Check if user is logged in and has valid session
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Require user to be logged in
 */
function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please log in to access this page';
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
}

/**
 * Get current user's data with error handling
 * @return array|null User data or null if not logged in/error
 */
function getCurrentUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.*,
                COUNT(DISTINCT p.id) as total_posts,
                COUNT(DISTINCT CASE WHEN p.type = 'donation' THEN p.id END) as donations,
                COUNT(DISTINCT CASE WHEN p.type = 'request' THEN p.id END) as requests
            FROM users u
            LEFT JOIN posts p ON u.id = p.user_id
            WHERE u.id = ? AND u.status = 'active'
            GROUP BY u.id
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Invalid session - user no longer exists or is inactive
            logout();
            return null;
        }
        
        return $user;
    } catch (PDOException $e) {
        error_log("Auth Error (getCurrentUser): " . $e->getMessage());
        return null;
    }
}

/**
 * Check if current user has specific role
 * @param string $role Role to check
 * @return bool
 */
function hasRole(string $role): bool {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin(): bool {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['is_admin']) && 
           $_SESSION['is_admin'] === 1;
}

/**
 * Require admin access or redirect
 * @return void
 */
function requireAdmin(): void {
    if (!isAdmin()) {
        $_SESSION['error'] = "You must be an administrator to access this area.";
        header('Location: /helpie/public/login.php');
        exit;
    }
}

/**
 * Check if current user is moderator
 * @return bool
 */
function isModerator(): bool {
    return hasRole('moderator') || isAdmin();
}

/**
 * Generate CSRF token
 * @return string
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool
 */
function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log out current user with cleanup
 */
function logout(): void {
    // Clear any sensitive session data
    if (isset($_SESSION['user_id'])) {
        try {
            global $pdo;
            // Update last activity timestamp
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {
            error_log("Auth Error (logout): " . $e->getMessage());
        }
    }
    
    // Destroy session
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    session_destroy();
    
    // Redirect to login
    header('Location: ' . BASEPATH . '/public/login.php');
    exit;
}