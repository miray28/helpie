<?php
namespace Helpie\Utils;

/**
 * Convert timestamp to "time ago" format
 * @param string $datetime MySQL datetime string
 * @return string Formatted time ago string
 */
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    }
    if ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    }
    if ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    return 'just now';
}

/**
 * Get icon name based on post type
 * @param string $type Post type ('donation' or 'request')
 * @return string Font Awesome icon name
 */
function getTypeIcon($type) {
    return match($type) {
        'donation' => 'hand-holding-heart',
        'request' => 'hand-holding-medical',
        default => 'question-circle'
    };
}

/**
 * Get Bootstrap badge color based on status
 * @param string $status Post status
 * @return string Bootstrap color class
 */
function getStatusBadgeColor($status) {
    return match($status) {
        'available' => 'success',
        'pending' => 'warning',
        'completed' => 'info',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}

/**
 * Format number with suffix (K, M, B)
 * @param int $number Number to format
 * @return string Formatted number
 */
function formatNumber($number) {
    if ($number >= 1000000000) {
        return round($number / 1000000000, 1) . 'B';
    }
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    }
    if ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return $number;
}

/**
 * Sanitize and validate file path
 * @param string $path File path to validate
 * @return string|false Sanitized path or false if invalid
 */
function sanitizePath($path) {
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('/\/+/', '/', $path);
    $parts = explode('/', $path);
    $parts = array_filter($parts);
    $absolutes = [];

    foreach ($parts as $part) {
        if ('.' === $part) continue;
        if ('..' === $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }
    
    return implode('/', $absolutes);
}/**
 * Generates a CSRF token and stores it in the session
 * @return string The generated token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    // Clean up expired tokens first
    $now = time();
    foreach ($_SESSION['csrf_tokens'] as $token => $expiry) {
        if ($expiry < $now) {
            unset($_SESSION['csrf_tokens'][$token]);
        }
    }

    // Generate new token
    $token = bin2hex(random_bytes(32));
    
    // Store token with 1-hour expiration
    $_SESSION['csrf_tokens'][$token] = time() + 3600;

    return $token;
}

/**
 * Verifies a CSRF token against stored tokens
 * @param string|null $token The token to verify
 * @return bool Whether the token is valid
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        return false;
    }

    // Check if token exists and hasn't expired
    if (isset($_SESSION['csrf_tokens'][$token])) {
        if ($_SESSION['csrf_tokens'][$token] >= time()) {
            // Remove used token
            unset($_SESSION['csrf_tokens'][$token]);
            return true;
        }
        // Remove expired token
        unset($_SESSION['csrf_tokens'][$token]);
    }

    return false;
}

