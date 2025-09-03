<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/utils.php';
require_once '../includes/functions.php';

use Helpie\Utils;
use Helpie\Functions;
use function Helpie\Utils\{generateCsrfToken, verifyCsrfToken};

// Handle form submission first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            throw new Exception('Invalid security token');
        }

        // Define allowed file types and max size
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        $updates = [];
        $params = [];

        // Handle file uploads
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $validation = Utils\validateFileUpload($_FILES['profile_image'], $allowedTypes, $maxSize);
            if (!$validation['success']) {
                throw new Exception($validation['message']);
            }
            // Process profile image...
        }

        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $validation = Utils\validateFileUpload($_FILES['cover_image'], $allowedTypes, $maxSize);
            if (!$validation['success']) {
                throw new Exception($validation['message']);
            }
            // Process cover image...
        }

        // Update database
        if (!empty($updates)) {
            $params[] = $_SESSION['user_id'];
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        $_SESSION['success'] = 'Profile updated successfully';
        header('Location: profile.php');
        exit;

    } catch (Exception $e) {
        error_log("Profile Update Error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header('Location: profile.php');
        exit;
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Update the user check section
try {
    // Get the requested user ID or fall back to current user
    $requested_user_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)$_SESSION['user_id'];
    
    // Check if admin
    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    $stmt = $pdo->prepare("
        SELECT u.*,
               COUNT(DISTINCT CASE WHEN p.type = 'donation' THEN p.id END) as total_donations,
               COUNT(DISTINCT CASE WHEN p.type = 'request' THEN p.id END) as total_requests
        FROM users u
        LEFT JOIN posts p ON u.id = p.user_id
        WHERE u.id = ? AND (u.blocked = 0 OR ? = TRUE)
        GROUP BY u.id
    ");
    $stmt->execute([$requested_user_id, $is_admin]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($is_admin) {
            $_SESSION['error'] = "User ID {$requested_user_id} not found or has been deleted";
            error_log("Admin attempted to view non-existent user: {$requested_user_id}");
        } else {
            $_SESSION['error'] = "User not found or profile is not accessible";
        }
        header('Location: main.php');
        exit;
    }

    // Check if viewing own profile
    $is_own_profile = ($user['id'] === $_SESSION['user_id']);

} catch (PDOException $e) {
    error_log("Profile Error: " . $e->getMessage());
    $_SESSION['error'] = $is_admin ? 
        "Database error: " . $e->getMessage() : 
        "Error loading profile";
    header('Location: main.php');
    exit;
}

// Update the activities query section
try {
    // Fetch user's posts
    $stmt = $pdo->prepare("
        SELECT 
            'post' as activity_type,
            p.id,
            p.title,
            p.type as post_type,
            p.status,
            p.created_at,
            p.content,
            p.image,
            NULL as request_status,
            (SELECT COUNT(*) FROM donation_requests WHERE post_id = p.id) as request_count
        FROM posts p
        WHERE p.user_id = ? 
        AND p.deleted = 0

        UNION ALL

        SELECT 
            'request' as activity_type,
            p.id,
            p.title,
            p.type as post_type,
            NULL as status,
            dr.created_at,
            dr.notes as content,
            p.image,
            dr.status as request_status,
            0 as request_count
        FROM donation_requests dr
        JOIN posts p ON dr.post_id = p.id
        WHERE dr.requester_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    $stmt->execute([$requested_user_id, $requested_user_id]);
    $activities = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Activity Error: " . $e->getMessage());
    $activities = [];
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($user['username']) ?>'s Profile - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/profile.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2DA664;
            --primary-hover: #34c576;
            --text-primary: #1a1a1a;
            --text-secondary: #65676b;
            --background: #f0f2f5;
            --card-bg: #ffffff;
            --border-color: #e4e6eb;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.5rem;
            --radius-md: 1rem;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background-color: var(--background);
            font-family: -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto;
        }

        /* Profile Container */
        .um-profile {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Cover Section */
        .um-cover {
            height: 400px;
            position: relative;
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            overflow: hidden;
        }

        .cover-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        /* Profile Photo */
        .um-profile-photo {
            position: absolute;
            bottom: -60px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            padding: 4px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid #fff;
            object-fit: cover;
        }

        /* Profile Info */
        .um-profile-meta {
            text-align: center;
            padding: 70px 1rem 1.5rem;
            background: #fff;
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .um-name {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .um-meta {
            display: flex;
            gap: 24px;
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        /* Action Buttons */
        .profile-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .profile-actions .btn {
            padding: 8px 20px;
            font-weight: 600;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Stats Cards */
        .stat-card {
            padding: 24px;
            border-radius: var(--radius-md);
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        /* Profile Container */
        .um-profile {
            background: #f0f2f5;
            min-height: 100vh;
        }

        /* Header Section */
        .um-header {
            position: relative;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .um-cover {
            height: 350px;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }

        .cover-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        /* Profile Photo */
        .um-profile-photo {
            position: absolute;
            bottom: -60px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            padding: 4px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid #fff;
            object-fit: cover;
        }

        /* Profile Meta */
        .um-profile-meta {
            text-align: center;
            padding: 70px 1rem 1.5rem;
            background: #fff;
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            box-shadow: var(--shadow-sm);
        }

        .um-name {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .um-meta {
            display: flex;
            justify-content: center;
            gap: 1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        /* Navigation */
        .um-profile-nav {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #fff;
            border-top: 1px solid #e4e6eb;
            border-bottom: 1px solid #e4e6eb;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .um-profile-nav-item {
            color: #65676b;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .um-profile-nav-item:hover {
            color: #2DA664;
            background: #f0f2f5;
        }

        .um-profile-nav-item.active {
            color: #2DA664;
            background: #e7f3ff;
        }

        /* Action Buttons */
        .profile-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .profile-actions .btn {
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 140px;
            justify-content: center;
        }

        .btn-primary {
            background: #1876f2;
            border-color: #1876f2;
        }

        .btn-primary:hover {
            background:#2DA664;
            border-color: #2DA664;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Stats Cards */
        .stat-card {
            padding: 1.5rem;
            border-radius: 1rem;
            background: #fff;
            transition: all 0.3s ease;
            border: 1px solid #e4e6eb;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.25rem;
            background: #e7f3ff;
            color: #1876f2;
        }

        /* Activity List */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            padding: 1rem;
            background: #fff;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }

        .activity-status {
            margin-top: 0.5rem;
        }

        .activity-status .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
        }

        .card-header .btn-outline-primary {
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .um-cover {
                height: 250px;
            }

            .um-profile-photo {
                bottom: -45px;
            }

            .profile-image {
                width: 90px;
                height: 90px;
            }

            .um-name {
                font-size: 1.5rem;
            }

            .um-meta {
                flex-direction: column;
                gap: 0.75rem;
            }

            .um-profile-nav {
                top: 48px; /* Adjust for smaller navbar on mobile */
                padding: 0 0.5rem;
                overflow-x: auto;
                justify-content: flex-start;
            }

            .um-profile-nav-item {
                padding: 0.75rem 1rem;
                white-space: nowrap;
            }
        }

        /* Add to your existing style section */
        .btn-gradient {
            background: linear-gradient(135deg, #2DA664 0%, #34c576 100%);
            color: #fff;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .btn-gradient:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(24, 118, 242, 0.35);
        }

        .btn-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }

        .btn-gradient:hover::before {
            left: 100%;
        }

        /* Add/update these styles in your CSS section */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-spinner {
            animation: spin 1s linear infinite;
        }

        .btn-content,
        .btn-loading {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .d-none {
            display: none !important;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #2DA664 0%, #34c576 100%);
            color: #fff;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }

        .btn-gradient:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(24, 118, 242, 0.35);
        }

        .btn-gradient:disabled {
            opacity: 0.7;
            transform: none;
        }

        .btn-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }

        .btn-gradient:not(:disabled):hover::before {
            left: 100%;
        }

        /* Add/update these styles in your CSS section */
        .um-profile-headericon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 10;
        }

        .dropdown-toggle {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a1a1a;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .dropdown-toggle:hover {
            background: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .dropdown-toggle:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(24, 118, 242, 0.3);
        }

        .dropdown-toggle::after {
            display: none;
        }

        .dropdown-menu {
            margin-top: 0.5rem;
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            padding: 0.5rem;
            min-width: 200px;
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #1a1a1a;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: #f0f2f5;
            color: #1876f2;
        }

        .dropdown-item i {
            font-size: 1rem;
            width: 1.25rem;
            text-align: center;
        }

        .dropdown-item.text-danger {
            color: #dc3545;
        }

        .dropdown-item.text-danger:hover {
            background: #dc354520;
            color: #dc3545;
        }

        .dropdown-divider {
            margin: 0.5rem 0;
            border-color: #e4e6eb;
        }

        /* Update the navbar and header spacing */
        .navbar {
            padding: 0.75rem 1rem;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }

        .um-profile {
            max-width: 1200px;
            margin: 1rem auto;
            padding: 0 1rem;
        }

        /* Update the profile navigation */
        .um-profile-nav {
            display: flex;
            justify-content: center;
            gap: 1rem;
            padding: 0;
            background: #fff;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 56px; /* Adjust this value to match your navbar height */
            z-index: 100;
        }

        .um-profile-nav-item {
            padding: 1rem 1.5rem;
            color: var(--text-secondary);
            font-weight: 600;
            position: relative;
            text-decoration: none;
            transition: var(--transition);
        }

        .um-profile-nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .um-profile {
                padding: 0;
                margin-top: 0;
            }

            .navbar {
                padding: 0.5rem;
            }

            .um-profile-nav {
                top: 48px; /* Adjust for smaller navbar on mobile */
                padding: 0 0.5rem;
                overflow-x: auto;
                justify-content: flex-start;
            }

            .um-profile-nav-item {
                padding: 0.75rem 1rem;
                white-space: nowrap;
            }
        }

        @media (max-width: 768px) {
            .profile-actions {
                flex-direction: column;
                padding: 0 1rem;
            }
            
            .profile-actions .btn {
                width: 100%;
            }
        }

        /* Replace the existing responsive media queries with these enhanced ones */

        /* Large screens (desktop) */
        @media (min-width: 1200px) {
            .um-profile {
                max-width: 1140px;
                margin: 2rem auto;
            }
        }

        /* Medium screens (tablet) */
        @media (max-width: 991px) {
            .um-profile {
                margin: 1rem auto;
            }

            .um-cover {
                height: 300px;
            }

            .um-profile-photo {
                bottom: -60px;
            }

            .profile-image {
                width: 160px;
                height: 160px;
            }

            .um-name {
                font-size: 1.75rem;
            }

            .stat-card {
                padding: 1.25rem;
            }
        }

        /* Small screens (mobile) */
        @media (max-width: 767px) {
            .um-profile {
                margin: 0;
                padding: 0;
            }

            .um-cover {
                height: 200px;
            }

            .um-profile-photo {
                bottom: -45px;
            }

            .profile-image {
                width: 90px;
                height: 90px;
            }

            .um-profile-meta {
                padding: 55px 1rem 1rem;
            }

            .um-name {
                font-size: 1.5rem;
            }

            .um-meta {
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.75rem;
            }

            .um-profile-nav {
                display: none; /* Hide the navigation completely on mobile since it's empty */
            }

            .um-profile-nav-item {
                padding: 0.75rem 1rem;
                white-space: nowrap;
                font-size: 0.9rem;
            }

            .profile-actions {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0 1rem;
            }

            .profile-actions .btn {
                width: 100%;
                padding: 0.625rem 1rem;
                font-size: 0.9rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .activity-item {
                padding: 0.75rem;
            }

            .dropdown-menu {
                width: calc(100vw - 2rem);
                max-width: 300px;
            }
        }

        /* Extra small screens */
        @media (max-width: 375px) {
            .um-cover {
                height: 180px;
            }

            .um-profile-photo {
                bottom: -40px;
            }

            .profile-image {
                width: 80px;
                height: 80px;
            }

            .um-profile-meta {
                padding: 50px 0.75rem 1rem;
            }

            .um-name {
                font-size: 1.25rem;
            }

            .um-meta {
                font-size: 0.8rem;
            }

            .um-profile-nav-item {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }
        }

        /* Add touch scrolling support for navigation */
        .um-profile-nav {
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
        }

        .um-profile-nav::-webkit-scrollbar {
            display: none; /* Chrome/Safari */
        }

        /* Improve button touch targets on mobile */
        @media (max-width: 767px) {
            .btn, 
            .dropdown-item {
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .dropdown-toggle {
                width: 44px;
                height: 44px;
            }
        }

        /* Add/update these styles in your CSS section */
        .stats-container {
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .stat-card {
            padding: 1rem;
            border-radius: 0.75rem;
            background: #fff;
            transition: all 0.2s ease;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-size: 1rem;
            background: #e7f3ff;
            color: #2DA664;
        }

        .stat-icon.donation {
            background: #e7f3ff;
            color: #2DA664;
        }

        .stat-icon.request {
            background: #fff3e6;
            color: #f90;
        }

        /* Update responsive styles */
        @media (max-width: 767px) {
            .stats-container {
                margin: 1rem -1rem -1rem;
                padding: 1rem;
                background: #f8f9fa;
            }

            .stat-card {
                padding: 0.75rem;
            }

            .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 0.875rem;
            }

            .stat-card .h5 {
                font-size: 1rem;
            }

            .stat-card small {
                font-size: 0.75rem;
            }
        }

        /* Add or update these styles in your CSS section */
        .btn-primary {
            background-color: #2DA664 !important;
            border-color: #2DA664 !important;
        }

        .btn-primary:hover {
            background-color: #34c576 !important;
            border-color: #34c576 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 166, 100, 0.2);
        }

        .btn-primary:active, .btn-primary:focus {
            background-color: #2DA664 !important;
            border-color: #2DA664 !important;
            box-shadow: 0 0 0 0.25rem rgba(45, 166, 100, 0.5) !important;
        }

        /* Add to your existing style section */
        .donation-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.5rem;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            padding: 1rem;
            background: #fff;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="um-profile">
        <div class="um-header">
            <div class="um-profile-headericon">
                <div class="dropdown">
                    <?php if ($is_own_profile): ?>
                        <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Profile options">
                            <i class="fas fa-ellipsis"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="account-settings.php">
                                    <i class="fas fa-cog"></i>
                                    Account Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Logout
                                </a>
                            </li>
                        </ul>
                    <?php elseif ($is_admin): ?>
                        <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Admin options">
                            <i class="fas fa-ellipsis"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <button class="dropdown-item" onclick="blockUser(<?= $user['id'] ?>)">
                                    <i class="fas fa-ban"></i>
                                    Block User
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item" onclick="viewUserLogs(<?= $user['id'] ?>)">
                                    <i class="fas fa-history"></i>
                                    View Logs
                                </button>
                            </li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Cover -->
            <div class="um-cover">
                <img src="<?= !empty($user['cover_image']) ? htmlspecialchars($user['cover_image']) : 'assets/images/default-cover.jpg' ?>" 
                     alt="Profile cover photo for <?= htmlspecialchars($user['username']) ?>" 
                     class="cover-image">
            </div>
            
            <!-- Profile Photo -->
            <div class="um-profile-photo">
                <img src="<?= !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'assets/images/default-avatar.jpg' ?>" 
                     alt="Profile picture of <?= htmlspecialchars($user['username']) ?>"
                     class="profile-image">
            </div>

            <!-- Profile Meta -->
            <div class="um-profile-meta">
    <div class="um-name"><?= htmlspecialchars($user['username']) ?></div>
    <?php if (!empty($user['address'])): ?>
        <div class="um-meta">
            <span>
                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($user['address']) ?>
            </span>
        </div>
    <?php endif; ?>
    
    <div class="profile-actions">
    <?php if ($is_own_profile): ?>
        <a href="edit-profile.php" class="btn btn-primary">
            <i class="fas fa-user-edit me-2"></i>Edit Profile
        </a>
    <?php else: ?>
        <a href="chat.php?with=<?= $user['id'] ?>" class="btn btn-gradient">
            <span class="btn-content">
                <i class="fas fa-comments me-2"></i>Send Message
            </span>
            <span class="btn-loading d-none">
                <i class="fas fa-spinner fa-spin me-2"></i>Loading...
            </span>
        </a>
        <button class="btn btn-outline-secondary" onclick="reportUser(<?= $user['id'] ?>)">
            <span class="btn-content">
                <i class="fas fa-flag me-2"></i>Report
            </span>
            <span class="btn-loading d-none">
                <i class="fas fa-spinner fa-spin me-2"></i>Loading...
            </span>
        </button>
    <?php endif; ?>
</div>

</div>

              
      
        <div class="um-profile-body">
            <!-- About Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <?php if (!empty($user['about'])): ?>
                        <h5 class="card-title">About</h5>
                        <p class="card-text"><?= nl2br(htmlspecialchars($user['about'])) ?></p>
                        <hr>
                    <?php endif; ?>

                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="stat-card">
                                <div class="stat-icon donation">
                                    <i class="fas fa-hand-holding-heart"></i>
                                </div>
                                <div class="h4 mb-0"><?= Utils\formatNumber($user['total_donations']) ?></div>
                                <small class="text-muted">Donations</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card">
                                <div class="stat-icon request">
                                    <i class="fas fa-hand-holding-medical"></i>
                                </div>
                                <div class="h4 mb-0"><?= number_format($user['total_requests']) ?></div>
                                <small class="text-muted">Requests</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="h4 mb-0"><?= date('M Y', strtotime($user['created_at'])) ?></div>
                                <small class="text-muted">Joined</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activities Section -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Activities</h5>
                    <?php if ($is_own_profile): ?>
                        <a href="my-activities.php" class="btn btn-sm btn-outline-primary">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <ul class="nav nav-pills mb-3" id="activities-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="posts-tab" data-bs-toggle="pill" data-bs-target="#posts" type="button">
                                Posts <span class="badge bg-secondary ms-1">
                                    <?= count(array_filter($activities, fn($a) => $a['activity_type'] === 'post')) ?>
                                </span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="requests-tab" data-bs-toggle="pill" data-bs-target="#requests" type="button">
                                Requests <span class="badge bg-secondary ms-1">
                                    <?= count(array_filter($activities, fn($a) => $a['activity_type'] === 'request')) ?>
                                </span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="activities-content">
                        <!-- Posts Tab -->
                        <div class="tab-pane fade show active" id="posts">
                            <?php 
                            $posts = array_filter($activities, fn($a) => $a['activity_type'] === 'post');
                            if (empty($posts)): 
                            ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted mb-0">No posts yet</p>
                                </div>
                            <?php else: ?>
                                <div class="activity-list">
                                    <?php foreach ($posts as $activity): ?>
                                        <div class="activity-item">
                                            <div class="d-flex gap-3">
                                                <?php if ($activity['image']): ?>
                                                    <img src="<?= htmlspecialchars(Functions\getImageUrl($activity['image'])) ?>" 
                                                         class="donation-image"
                                                         alt="Post image">
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <h6 class="mb-1"><?= htmlspecialchars($activity['title']) ?></h6>
                                                        <span class="badge bg-<?= $activity['post_type'] === 'donation' ? 'success' : 'info' ?>">
                                                            <?= ucfirst($activity['post_type']) ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-muted mb-2">
                                                        Posted: <?= Functions\time_elapsed_string($activity['created_at']) ?>
                                                        <?php if ($activity['post_type'] === 'donation'): ?>
                                                            <br>Requests: <?= $activity['request_count'] ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    <p class="mb-0"><?= nl2br(htmlspecialchars($activity['content'])) ?></p>
                                                    <div class="mt-2">
                                                        <span class="badge bg-<?= 
                                                            $activity['status'] === 'available' ? 'success' : 
                                                            ($activity['status'] === 'claimed' ? 'secondary' : 'warning') 
                                                        ?>">
                                                            <?= ucfirst($activity['status']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Requests Tab -->
                        <div class="tab-pane fade" id="requests">
                            <?php 
                            $requests = array_filter($activities, fn($a) => $a['activity_type'] === 'request');
                            if (empty($requests)): 
                            ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted mb-0">No requests yet</p>
                                </div>
                            <?php else: ?>
                                <div class="activity-list">
                                    <?php foreach ($requests as $activity): ?>
                                        <div class="activity-item">
                                            <div class="d-flex gap-3">
                                                <?php if ($activity['image']): ?>
                                                    <img src="<?= htmlspecialchars(Functions\getImageUrl($activity['image'])) ?>" 
                                                         class="donation-image"
                                                         alt="Post image">
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <h6 class="mb-1"><?= htmlspecialchars($activity['title']) ?></h6>
                                                        <small class="text-muted">
                                                            <?= Functions\time_elapsed_string($activity['created_at']) ?>
                                                        </small>
                                                    </div>
                                                    <p class="mb-0"><?= nl2br(htmlspecialchars($activity['content'])) ?></p>
                                                    <div class="mt-2">
                                                        <span class="badge bg-<?= 
                                                            $activity['request_status'] === 'pending' ? 'warning' :
                                                            ($activity['request_status'] === 'approved' ? 'success' : 'danger')
                                                        ?>">
                                                            <?= ucfirst($activity['request_status']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });

        // Close dropdown when clicking "Cancel"
        document.querySelectorAll('[data-bs-dismiss="dropdown"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = bootstrap.Dropdown.getInstance(
                    this.closest('.dropdown').querySelector('.dropdown-toggle')
                );
                if (dropdown) {
                    dropdown.hide();
                }
            });
        });
    });

    // Add after existing DOMContentLoaded event listener
    function blockUser(userId) {
        if (confirm('Are you sure you want to block this user?')) {
            const btn = event.target.closest('button');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Blocking...';

            fetch('admin/block-user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    user_id: userId,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'main.php';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                    alert(data.message || 'Error blocking user');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.innerHTML = originalContent;
                alert('Error blocking user');
            });
        }
    }

    // Add loading state to report function
    function reportUser(userId) {
        const btn = event.target.closest('button');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
        
        window.location.href = `report.php?type=user&id=${userId}`;
    }

    // Add loading state to view logs function
    function viewUserLogs(userId) {
        const btn = event.target.closest('button');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...`;
        
        window.location.href = `admin/user-logs.php?id=${userId}`;
    }

    // Add to your existing script section
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            // Check file size
            if (this.files[0] && this.files[0].size > 5 * 1024 * 1024) {
                alert('File too large (max 5MB)');
                this.value = '';
                return;
            }

            // Check file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (this.files[0] && !allowedTypes.includes(this.files[0].type)) {
                alert('Invalid file type. Please use JPG, PNG, or WebP images.');
                this.value = '';
                return;
            }
        });
    });

    // Add form submission handler
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!document.querySelector('input[name="csrf_token"]').value) {
            e.preventDefault();
            alert('Security token missing. Please refresh the page.');
        }
    });

    // Add this function to handle the chat button click
    function handleChatButton() {
        const messageBtn = document.querySelector('.btn-gradient');
        if (messageBtn) {
            messageBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const userId = this.href.split('with=')[1];
                
                // Show loading state
                setButtonLoading(this, true);

                // Redirect to chat page
                window.location.href = `chat.php?with=${userId}`;
            });
        }
    }

    // Update the setButtonLoading function
    function setButtonLoading(button, isLoading) {
        const content = button.querySelector('.btn-content');
        const loading = button.querySelector('.btn-loading');
        
        if (isLoading) {
            content.classList.add('d-none');
            loading.classList.remove('d-none');
            button.disabled = true;
        } else {
            content.classList.remove('d-none');
            loading.classList.add('d-none');
            button.disabled = false;
        }
    }

    // Update the DOMContentLoaded event listener
    document.addEventListener('DOMContentLoaded', function() {
        // ...existing code...
        handleChatButton();
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add hover effect to cover photo
        const coverImage = document.querySelector('.cover-image');
        if (coverImage) {
            coverImage.addEventListener('mouseover', function() {
                this.style.transform = 'scale(1.02)';
            });
            coverImage.addEventListener('mouseout', function() {
                this.style.transform = 'scale(1)';
            });
        }

        // Add loading states to buttons
        document.querySelectorAll('.profile-actions .btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!this.dataset.loading) {
                    const originalText = this.innerHTML;
                    this.dataset.loading = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                    this.disabled = true;

                    // Reset after 2 seconds (adjust based on your actual action)
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        delete this.dataset.loading;
                    }, 2000);
                }
            });
        });

        // Smooth scroll for navigation
        // Remove or comment out this section in your JavaScript
        // document.querySelectorAll('.um-profile-nav-item').forEach(link => {
        //     link.addEventListener('click', function(e) {
        //         e.preventDefault();
        //         const target = document.querySelector(this.getAttribute('href'));
        //         if (target) {
        //             target.scrollIntoView({
        //                 behavior: 'smooth',
        //                 block: 'start'
        //             });
        //         }
        //     });
        // });
    });

    function setButtonLoading(button, isLoading) {
        const content = button.querySelector('.btn-content');
        const loading = button.querySelector('.btn-loading');
        
        if (isLoading) {
            content.classList.add('d-none');
            loading.classList.remove('d-none');
            button.disabled = true;
        } else {
            content.classList.remove('d-none');
            loading.classList.add('d-none');
            button.disabled = false;
        }
    }

    function reportUser(userId) {
        const btn = event.target.closest('button');
        setButtonLoading(btn, true);
        window.location.href = `report.php?type=user&id=${userId}`;
    }

    // Update click handlers for buttons
    document.querySelectorAll('.profile-actions .btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.tagName.toLowerCase() === 'a') {
                setButtonLoading(this, true);
            }
        });
    });
    </script>
</body>
</html>
