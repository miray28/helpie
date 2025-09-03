<?php
namespace Helpie\Functions;

if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}

/**
 * Sanitize string input
 * @param mixed $input The input to sanitize
 * @return mixed Sanitized input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate file upload
 */
function validateFileUpload(array $file, array $allowedTypes, int $maxSize): array {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false, 
            'message' => 'Upload failed: ' . getUploadErrorMessage($file['error'])
        ];
    }

    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return [
            'success' => false,
            'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes)
        ];
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        return [
            'success' => false,
            'message' => sprintf('File too large (max %dMB)', $maxSize / 1024 / 1024)
        ];
    }

    return ['success' => true];
}

/**
 * Get upload error message
 */
function getUploadErrorMessage(int $code): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'File exceeds upload_max_filesize directive';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File exceeds MAX_FILE_SIZE directive';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}

/**
 * Generate unique filename
 * @param string $originalName Original filename
 * @param string $prefix Optional prefix for the filename
 * @return string Generated unique filename
 */
function generateUniqueFilename(string $originalName, string $prefix = ''): string {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return $prefix . uniqid() . '_' . time() . ($ext ? '.' . $ext : '');
}

/**
 * Get status badge color
 * @param string $status The status to get color for
 * @return string The corresponding color name
 */
function getStatusBadgeColor($status) {
    switch ($status) {
        case 'available':
        case 'completed':
            return 'success';
        case 'pending':
            return 'warning';
        case 'reserved':
            return 'info';
        case 'claimed':
            return 'primary';
        case 'rejected':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Get the Bootstrap color class for a given status
 * @param string $status The status to get the color class for
 * @return string The corresponding Bootstrap color class
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'available':
        case 'completed':
            return 'success';
        case 'pending':
            return 'warning';
        case 'reserved':
            return 'info';
        case 'claimed':
            return 'primary';
        case 'rejected':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Convert datetime to "time ago" string
 * @param string $datetime The datetime string to convert
 * @param bool $full Whether to show full detail
 * @return string Elapsed time string
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new \DateTime;
    $ago = new \DateTime($datetime);
    $diff = $now->diff($ago);

    // Calculate weeks from days instead of adding a dynamic property
    $weeks = floor($diff->d / 7);
    $days = $diff->d % 7;

    $string = [];
    $periods = [
        'y' => ['year', $diff->y],
        'm' => ['month', $diff->m],
        'w' => ['week', $weeks],
        'd' => ['day', $days],
        'h' => ['hour', $diff->h],
        'i' => ['minute', $diff->i],
        's' => ['second', $diff->s]
    ];

    foreach ($periods as $k => $data) {
        list($period, $value) = $data;
        if ($value) {
            $string[$k] = $value . ' ' . $period . ($value > 1 ? 's' : '');
        }
    }

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }
    
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

/**
 * Generate or retrieve CSRF token
 * @return string The CSRF token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string|null $token The token to verify
 * @return bool True if token is valid
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get the full URL for an uploaded image
 * @param string|null $imagePath The image path from the database
 * @return string The full URL to the image
 */
function getImageUrl($imagePath) {
    if (empty($imagePath)) {
        return '/helpie/public/assets/images/default-post-image.jpg';
    }
    
    // If the path already includes the base directory, return as is
    if (strpos($imagePath, '/helpie/public/uploads/posts/') === 0) {
        return $imagePath;
    }
    
    // Otherwise, construct the full path
    return '/helpie/public/uploads/posts/' . $imagePath;
}

/**
 * Debug SQL query with parameters
 * @param string $query The SQL query with placeholders
 * @param array $params The parameters to bind to the query
 * @return string The SQL query with actual values
 */
function debugQuery($query, $params) {
    global $pdo;
    $stmt = $pdo->prepare($query);
    
    // Replace placeholders with actual values for debugging
    $keys = array();
    $values = array();
    
    // Build a regular expression for finding placeholders
    $types = array(
        'string' => '\'%s\'',
        'integer' => '%d',
        'double' => '%f',
        'boolean' => '%b'
    );
    
    foreach ($params as $key => $value) {
        $type = gettype($value);
        $keys[] = is_string($key) ? "/:$key\b/" : "/[?]/";
        $values[] = sprintf($types[$type] ?? '%s', $value);
    }
    
    // Perform the replacement
    $bound = preg_replace($keys, $values, $query);
    
    return $bound;
}

function format_date($datetime) {
    return date('F j, Y, g:i a', strtotime($datetime));
}

