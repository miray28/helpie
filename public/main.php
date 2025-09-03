<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use Helpie\Functions;

// Update the getImageUrl function
function getImageUrl($imagePath) {
    if (empty($imagePath)) {
        return '/helpie/public/assets/images/default-post-image.jpg';
    }
    
    // Clean the path and ensure it's using forward slashes
    $imagePath = str_replace('\\', '/', trim($imagePath, '/'));
    
    // If path starts with http:// or https://, return as is
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }
    
    // Construct the full URL path
    if (strpos($imagePath, 'uploads/posts/') === 0) {
        return '/helpie/public/' . $imagePath;
    }
    
    return '/helpie/public/uploads/posts/' . $imagePath;
}

// Add this debug code temporarily to check paths
if (isset($_GET['debug'])) {
    $testImage = '67d57d30a1607.jpg';
    $imageUrl = getImageUrl($testImage);
    $serverPath = $_SERVER['DOCUMENT_ROOT'] . $imageUrl;
    
    echo "<pre>";
    echo "URL Path: $imageUrl\n";
    echo "Server Path: $serverPath\n";
    echo "File exists: " . (file_exists($serverPath) ? 'Yes' : 'No') . "\n";
    echo "File readable: " . (is_readable($serverPath) ? 'Yes' : 'No') . "\n";
    echo "Directory exists: " . (is_dir(dirname($serverPath)) ? 'Yes' : 'No') . "\n";
    echo "Directory readable: " . (is_readable(dirname($serverPath)) ? 'Yes' : 'No') . "\n";
    echo "</pre>";
}

// Add this at the top of your file
$uploadsDir = __DIR__ . '/uploads/posts/';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Uploads directory exists: " . (file_exists($uploadsDir) ? 'Yes' : 'No') . "\n";
    echo "Uploads directory writable: " . (is_writable($uploadsDir) ? 'Yes' : 'No') . "\n";
    echo "</pre>";
}

// Add this near the top of your file after the includes
$uploadsDir = __DIR__ . '/uploads/posts/';
$assetsDir = __DIR__ . '/assets/images/';

// Create directories if they don't exist
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

if (!file_exists($assetsDir)) {
    mkdir($assetsDir, 0777, true);
}

// Verify default image exists
$defaultImage = $assetsDir . 'default-post-image.jpg';
if (!file_exists($defaultImage)) {
    // Create a simple default image or copy from another location
    copy('/path/to/your/default/image.jpg', $defaultImage);
}

// Add debug information
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Directory Check:\n";
    echo "Uploads Directory: " . $uploadsDir . "\n";
    echo "Uploads Exists: " . (file_exists($uploadsDir) ? 'Yes' : 'No') . "\n";
    echo "Uploads Writable: " . (is_writable($uploadsDir) ? 'Yes' : 'No') . "\n";
    echo "Default Image Path: " . $defaultImage . "\n";
    echo "Default Image Exists: " . (file_exists($defaultImage) ? 'Yes' : 'No') . "\n";
    echo "</pre>";
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Ensure categories are always available for the filter bar
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

    // Build query conditions
    $where = [];
    $params = [];

    // Type filter
    if (!empty($_GET['type'])) {
        $where[] = "p.type = ?";
        $params[] = $_GET['type'];
    }

    // Category filter
    if (!empty($_GET['category'])) {
        $where[] = "p.category_id = ?";
        $params[] = $_GET['category'];
    }

    // Search filter
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where[] = "(p.title LIKE ? OR p.content LIKE ?)";
        $params[] = $search;
        $params[] = $search;
    }

    // Combine where conditions
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Update the posts query to include comment count
    $postsQuery = "
        SELECT 
            p.*,
            u.username,
            u.profile_image,
            c.name as category_name,
            p.image as image_path,
            (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id) as save_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
            EXISTS(SELECT 1 FROM saved_posts WHERE post_id = p.id AND user_id = ?) as is_saved
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN categories c ON p.category_id = c.id
        $whereClause
        ORDER BY p.created_at DESC
        LIMIT 20
    ";

    // Add user_id to params array at the beginning
    array_unshift($params, $_SESSION['user_id']);
    
    // Debug information if needed
    if (isset($_GET['debug'])) {
        echo "<pre>";
        echo "Query: " . $postsQuery . "\n";
        echo "Params: ";
        print_r($params);
        echo "</pre>";
    }
    
    $stmt = $pdo->prepare($postsQuery);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add this near your other debug output
    if (isset($_GET['debug'])) {
        echo "<pre>";
        echo "Query: " . $postsQuery . "\n";
        echo "Params: ";
        print_r($params);
        echo "Posts found: " . count($posts) . "\n";
        echo "Image paths:\n";
        foreach ($posts as $post) {
            echo "ID: {$post['id']}\n";
            echo "Original: " . ($post['image'] ?? 'null') . "\n";
            echo "Processed: " . getImageUrl($post['image']) . "\n";
            echo "---\n";
        }
        echo "</pre>";
    }

    if (isset($_GET['debug'])) {
        echo "<pre>";
        echo "Total posts in database: " . count($posts) . "\n";
        echo "Image paths check:\n";
        foreach ($posts as $post) {
            echo "ID: {$post['id']}\n";
            echo "DB path: " . ($post['image'] ?? 'null') . "\n";
            echo "Full URL: " . getImageUrl($post['image']) . "\n";
            echo "File exists: " . (file_exists($_SERVER['DOCUMENT_ROOT'] . getImageUrl($post['image'])) ? 'Yes' : 'No') . "\n";
            echo "---\n";
        }
        echo "</pre>";
    }

    // Add this after your posts query
    if (isset($_GET['debug'])) {
        echo "<pre>";
        echo "Image Path Debug:\n";
        foreach ($posts as $post) {
            $imageUrl = getImageUrl($post['image']);
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . $imageUrl;
            echo "Post ID: {$post['id']}\n";
            echo "DB Image Path: " . ($post['image'] ?? 'null') . "\n";
            echo "Generated URL: $imageUrl\n";
            echo "Full System Path: $fullPath\n";
            echo "File Exists: " . (file_exists($fullPath) ? 'Yes' : 'No') . "\n";
            echo "Directory Writable: " . (is_writable(dirname($fullPath)) ? 'Yes' : 'No') . "\n";
            echo "-------------------\n";
        }
        echo "</pre>";
    }

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Feed - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2DA664;
            --secondary-color: #34c576;
            --background-color: #f3f4f6;
            --card-background: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --grid-gap: 1.5rem;
            --container-max-width: 800px;
            --card-min-width: 320px;
            --elevation-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        body {
            background-color: var(--background-color);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            padding-top: 4rem; /* Increased padding-top */
        }

        .main-container {
            display: flex;
            margin-top: 60px;
            min-height: calc(100vh - 60px);
            max-width: 1400px; /* Increased from 800px */
            margin-left: auto;
            margin-right: auto;
            padding: 1.5rem;
            gap: 1.5rem;
        }

        .posts-grid {
            display: flex; /* Changed from grid to flex */
            flex-direction: column; /* Stack items vertically */
            width: 100%;
            gap: var(--grid-gap);
            margin-top: 2rem;
            align-items: center; /* Center items horizontally */
        }

        .posts-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            min-width: 0; /* Important for flex layout */
        }

        .create-post-card {
            width: 100%;
            background: var(--card-background);
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .post-card {
            background: var(--card-background);
            border-radius: 1rem;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(0, 0, 0, 0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }

        /* Prevent click on action buttons from triggering post navigation */
        .post-actions {
            pointer-events: none;
        }

        .post-actions button {
            pointer-events: all;
        }

        /* Same for dropdown menu */
        .dropdown {
            pointer-events: all;
            z-index: 1;
        }

        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .post-content {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .post-image-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background-color: #f8f9fa;
            position: relative;
            margin-bottom: 1rem;
        }

        .post-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .create-post-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .create-post-body {
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .post-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .create-post-input {
            flex: 1;
            background: var(--background-color);
            border-radius: 20px;
            padding: 0.75rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
        }

        .post-type-tabs {
            display: flex;
            padding: 0.5rem;
            gap: 0.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .post-type-tab {
            flex: 1;
            padding: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .post-type-tab.active {
            background: rgba(45, 166, 100, 0.1);
            color: var(--primary-color);
        }

        .text-bg-primary {
            background-color: #2DA664;
            color: #fff;
        }

        .conversation-item.active {
            background-color: rgba(45, 166, 100, 0.1);
            border-left: 3px solid var(--primary-color);
        }

        .post-username:hover {
            color: var(--primary-color);
        }

        .conversation-toggle {
            background: var(--primary-color);
        }

        /* Update media queries */
        @media (max-width: 768px) {
            :root {
                --grid-gap: 1rem;
            }
            
            .main-container {
                flex-direction: column;
                padding: 1rem;
            }

            .create-post-card,
            .post-card {
                max-width: 100%;
            }

            .posts-container {
                grid-template-columns: 1fr;
            }

            .conversation-sidebar {
                width: 100%;
                height: auto;
                position: static;
            }
        }

        /* Add styles for notification toast */
        .notification-toast {
            position: fixed;
            top: 1rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            min-width: 300px;
            max-width: 90%;
        }

        .comments-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .comment-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .comment-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        .comment-user {
            font-weight: 500;
            color: var(--text-primary);
        }

        .comment-time {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .comment-content {
            color: var(--text-primary);
            white-space: pre-wrap;
            word-break: break-word;
        }

        .comment-form {
            position: sticky;
            bottom: 0;
            background: var(--card-background);
            padding-top: 1rem;
        }

        .comment-form .input-group {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .comment-form textarea {
            border: none;
            resize: none;
            height: 40px;
            padding: 0.5rem;
        }

        .comment-form textarea:focus {
            box-shadow: none;
        }

        .comment-form button {
            border: none;
            padding: 0 1rem;
        }

        .comment-form button {
            border: none;
            padding: 0 1rem;
        }

        /* Add to your existing styles */
        .notification-toast {
            min-width: 300px;
            max-width: 500px;
            margin-bottom: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .modal-dialog {
            max-width: 500px;
        }

        .dropdown-item {
            cursor: pointer;
        }

        .dropdown-item:active {
            color: #fff;
        }

        /* Add to your existing styles */
        .filter-bar {
            grid-column: 1 / -1;
            width: 100%;
            background: var(--card-background);
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .filter-form .input-group-text {
            background: transparent;
            border-right: none;
        }

        .filter-form .form-select,
        .filter-form .form-control {
            border-left: none;
        }

        .filter-form .form-select:focus,
        .filter-form .form-control:focus {
            border-color: #dee2e6;
            box-shadow: none;
        }

        .filter-form .btn {
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }

        .filter-form .btn {
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }

        /* No results message */
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: var (--card-background);
            border-radius: 0.5rem;
            color: var(--text-secondary);
        }

        
        .post-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .post-status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .text-bg-success {
            background-color: #198754;
            color: #fff;
        }

        .text-bg-primary {
            background-color: #0d6efd;
            color: #fff;
        }

        .text-bg-warning {
            background-color: #ffc107;
            color: #000;
        }

        /* Add to your existing styles */
        .post-avatar {
            aspect-ratio: 1;
            object-fit: cover;
        }

        .post-image-container {
            aspect-ratio: 16/9;
            background-color: #f8f9fa;
        }

        .post-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        
        .post-user-link {
            display: inline-block;
            cursor: pointer;
        }

        .post-user-link:hover .post-avatar {
            opacity: 0.9;
        }

        .post-username {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .post-username:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }

        .main-container {
            display: flex;
            margin-top: 60px;
            min-height: calc(100vh - 60px);
        }

        .conversation-sidebar {
            width: 300px;
            border-right: 1px solid #dee2e6;
            background: #fff;
            height: calc(100vh - 60px);
            position: sticky;
            top: 60px;
            overflow-y: auto;
        }

        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            transition: all 0.2s ease;
        }

        .conversation-item:hover {
            background-color: #f8f9fa;
        }

        .unread-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            min-width: 1.5rem;
            text-align: center;
        }

        @media (max-width: 1200px) {
            .posts-container {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .posts-container {
                grid-template-columns: 1fr;
                padding: 1rem;
            }

            .post-card {
                max-width: 100%;
            }

            .conversation-sidebar {
                display: none;
            }
        }

        /* Main container layout */
        .main-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem;
            max-width: 1400px;
            margin: 60px auto 0;
            padding: 1.5rem;
        }

        /* Conversation sidebar */
        .conversation-sidebar {
            position: sticky;
            top: calc(60px + 1rem);
            height: calc(100vh - 80px);
            background: var(--card-background);
            border-radius: 1rem;
            box-shadow: var(--elevation-shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .conversation-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background: var(--card-background);
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .conversation-item {
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .conversation-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
            transform: translateX(2px);
        }

        .conversation-item.active {
            background-color: rgba(45, 166, 100, 0.1);
            border-left: 3px solid var(--primary-color);
        }

        .conversation-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .conversation-details {
            flex: 1;
            min-width: 0;
        }

        .conversation-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-preview {
            font-size: 0.875rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-badge {
            background: var(--primary-color);
            color: white;
            border-radius: 1rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            min-width: 1.5rem;
            text-align: center;
        }

        /* Mobile styles */
        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
            }

            .conversation-sidebar {
                display: none;
                position: fixed;
                left: 0;
                top: 60px;
                width: 100%;
                height: calc(100vh - 60px);
                z-index: 1030;
                border-radius: 0;
            }

            .conversation-sidebar.active {
                display: block;
                animation: slideIn 0.3s ease;
            }
        }

        /* Conversation toggle button */
        .conversation-toggle {
            display: none;
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            border: none;
            box-shadow: var(--elevation-shadow);
            z-index: 1040;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .conversation-toggle {
                display: flex;
            }
        }

        /* Animation */
        @keyframes slideIn {
            from {
                transform: translateX(-100%);
            }
            to {
                transform: translateX(0);
            }
        }

        /* Update the main container and posts container styles */
        .main-container {
            display: flex;
            gap: 1.5rem;
            max-width: 1400px;
            margin: 60px auto 0;
            padding: 1.5rem;
        }

        .conversation-sidebar {
            width: 300px;
            flex-shrink: 0;
        }

        .posts-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            width: 100%;
        }

        /* Create post card should span full width */
        .create-post-card {
            grid-column: 1 / -1;
            background: var(--card-background);
            border-radius: 1rem;
            box-shadow: var(--elevation-shadow);
        }

        /* Filter bar should span full width */
        .filter-bar {
            grid-column: 1 / -1;
            width: 100%;
            background: var(--card-background);
            border-radius: 1rem;
            box-shadow: var(--elevation-shadow);
            margin-bottom: 0;
        }

        /* Post card styles */
        .post-card {
            background: var(--card-background);
            border-radius: 1rem;
            box-shadow: var(--elevation-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .posts-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 992px) {
            .posts-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }

            .conversation-sidebar {
                width: 100%;
            }

            .posts-grid {
                grid-template-columns: 1fr;
            }

            .create-post-card,
            .post-card {
                margin-bottom: 0;
            }
        }

        /* No results message */
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: var(--card-background);
            border-radius: 1rem;
            box-shadow: var(--elevation-shadow);
        }

        /* Update the post image container styles */
        .post-image-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background-color: #f8f9fa;
            position: relative;
            margin-bottom: 1rem;
        }

        .post-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Main container and grid layout */
        .main-container {
            display: flex;
            gap: 1.5rem;
            max-width: 1400px;
            margin: 60px auto 0;
            padding: 1.5rem;
        }

        .posts-container {
            flex: 1;
            min-width: 0;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            width: 100%;
        }

        /* Post card styles */
        .post-card {
            background: var(--card-background);
            border-radius: 1rem;
            box-shadow: var(--elevation-shadow);
            transition: transform 0.2s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: auto;
        }

        .post-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .post-content {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        /* Image container styles */
        .post-image-container {
            width: 100%;
            padding-top: 56.25%; /* 16:9 aspect ratio */
            position: relative;
            background: #f8f9fa;
            margin: -1rem -1rem 1rem -1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .post-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Add these styles to your existing CSS */
        .post-image-container {
            width: 100%;
            height: 200px;
            position: relative;
            background-color: #f8f9fa;
            overflow: hidden;
            margin: -1rem -1rem 1rem -1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .post-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .post-image:hover {
            transform: scale(1.05);
        }

        /* Add loading state */
        .post-image-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        .post-image-container img.loaded {
            opacity: 1;
        }

        .post-image-container img.loaded + ::before {
            display: none;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Update these styles in your existing CSS section */
        .post-image-container {
            width: 100%;
            height: 200px;
            position: relative;
            background-color: #f8f9fa;
            overflow: hidden;
            margin: -1rem -1rem 1rem -1rem;
        }

        .post-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        .post-image.loading {
            opacity: 0;
        }

        /* Add loading animation */
        .post-image-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        .post-image:not(.loading) + .post-image-container::before {
            display: none;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Add loading skeleton animation */
        .post-card.loading {
            position: relative;
            overflow: hidden;
        }

        .post-card.loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        /* Add these new styles in your existing style section */

        /* Conversation toggle button in navbar */
        .navbar .conversation-toggle {
            border: none;
            padding: 0.5rem;
            margin-right: 1rem;
            background: transparent;
            position: relative;
        }

        .navbar .conversation-toggle i {
            font-size: 1.25rem;
            color: var(--primary-color);
        }

        /* Badge for unread messages */
        .navbar .conversation-toggle::after {
            content: attr(data-unread);
            position: absolute;
            top: 0;
            right: 0;
            background: var(--primary-color);
            color: white;
            font-size: 0.75rem;
            padding: 0.15rem 0.35rem;
            border-radius: 1rem;
            transform: translate(25%, -25%);
            display: none;
        }

        .navbar .conversation-toggle[data-unread]:not([data-unread="0"])::after {
            display: block;
        }

        /* Offcanvas styles */
        .offcanvas {
            max-width: 320px;
        }

        .offcanvas-header {
            background: var(--card-background);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .offcanvas .conversation-sidebar {
            position: static;
            height: auto;
            box-shadow: none;
            border-radius: 0;
        }

        /* Hide sidebar on mobile */
        @media (max-width: 991.98px) {
            .main-container .conversation-sidebar {
                display: none;
            }
        }

        /* Show sidebar on larger screens */
        @media (min-width: 992px) {
            .navbar .conversation-toggle,
            .offcanvas.offcanvas-start {
                display: none !important;
            }
        }

        .message-btn {
            border-radius: 20px;
            padding: 0.375rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        .message-btn:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
        }

        .message-btn i {
            margin-right: 0.5rem;
        }

        /* Add these styles to your existing CSS */
        .post-action.message-btn {
            color: #0d6efd;
            transition: all 0.2s ease;
        }

        .post-action.message-btn:hover {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0a58ca;
        }

        .post-action.message-btn i {
            margin-right: 0.5rem;
        }

        /* Remove the existing .message-btn styles that were for the old button */
        .message-btn {
            display: none;
        }

        /* Add to your existing styles section */
        .post-card {
            transition: opacity 0.3s ease-out;
        }

        .dropdown-item.text-danger:hover {
            background-color: #dc3545;
            color: white !important;
        }

        .dropdown-item.text-danger:hover i {
            color: white;
        }

        /* Add to your existing styles section */
        .post-menu {
            padding: 0.25rem 0.5rem;
            color: var(--text-secondary);
            background: transparent;
            border: none;
        }

        .post-menu:hover,
        .post-menu:focus {
            color: var(--text-primary);
            background: rgba(0, 0, 0, 0.05);
        }

        .dropdown-menu {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .dropdown-item i {
            width: 1.25rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="main-container">
        <!-- Conversation List -->
        <?php include '../includes/conversation-list.php'; ?>

        <!-- Posts Container -->
        <div class="posts-container">
            <div class="notification-container" style="position: fixed; top: 20px; right: 20px; z-index: 1060;"></div>

            <div class="posts-grid">
                <!-- Create Post Card -->
                <div class="create-post-card">
                    <div class="create-post-header">
                        <h5>Create a New Post</h5>
                        <i class="fas fa-plus"></i>
                    </div>
                    <div class="create-post-body">
                        <img src="<?= isset($user['profile_image']) && $user['profile_image'] ? 
                            htmlspecialchars($user['profile_image']) : 
                            'assets/images/default-avatar.jpg' ?>" 
                            alt="Profile picture" 
                            class="post-avatar">
                        <div class="create-post-input" data-bs-toggle="modal" data-bs-target="#createPostModal">
                            <i class="far fa-edit"></i>
                            <span>Want to post a donation or a request?</span>
                        </div>
                    </div>
                    <div class="post-type-tabs">
                        <div class="post-type-tab active">
                            <i class="fas fa-hand-holding-heart"></i>
                            <span>Donation</span>
                        </div>
                        <div class="post-type-tab">
                            <i class="fas fa-hand-holding-medical"></i>
                            <span>Request</span>
                        </div>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar mb-4">
                    <form id="filterForm" method="GET" class="filter-form">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-filter"></i>
                                    </span>
                                    <select name="type" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Types</option>
                                        <option value="donation" <?= ($_GET['type'] ?? '') === 'donation' ? 'selected' : '' ?>>
                                            <i class="fas fa-hand-holding-heart"></i> Donations
                                        </option>
                                        <option value="request" <?= ($_GET['type'] ?? '') === 'request' ? 'selected' : '' ?>>
                                            <i class="fas fa-hand-holding-medical"></i> Requests
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-tags"></i>
                                    </span>
                                    <select name="category" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Categories</option>
                                        <?php foreach($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" 
                                                <?= ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control" placeholder="Search posts..." 
                                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <?php if (!empty($_GET)): ?>
                                    <a href="main.php" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-times me-1"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Posts -->
                <?php if (empty($posts)): ?>
                    <?php if (isset($_GET['debug'])): ?>
                        <div class="alert alert-warning">
                            Debug Info:<br>
                            Number of posts: <?= count($posts) ?><br>
                            POST data: <?= print_r($_POST, true) ?><br>
                            GET data: <?= print_r($_GET, true) ?><br>
                        </div>
                    <?php endif; ?>
                    <div class="no-results">
                        <i class="fas fa-search fa-3x mb-3"></i>
                        <h4>No posts found</h4>
                        <p class="text-muted">Try adjusting your search criteria or clear the filters</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card" data-post-id="<?= $post['id'] ?>" onclick="goToPostDetails(<?= $post['id'] ?>)">
                            <div class="post-header">
                                <div class="d-flex align-items-center">
                                    <a href="profile.php?id=<?= $post['user_id'] ?>" class="post-user-link" onclick="event.stopPropagation()">
                                        <img src="<?= !empty($post['profile_image']) ? htmlspecialchars($post['profile_image']) : 'assets/images/default-avatar.jpg' ?>" 
                                            alt="Profile picture of <?= htmlspecialchars($post['username']) ?>" 
                                            class="post-avatar"
                                            width="40"
                                            height="40">
                                    </a>
                                    <div class="post-user-info ms-3">
                                        <a href="profile.php?id=<?= $post['user_id'] ?>" class="post-username" onclick="event.stopPropagation()">
                                            <?= htmlspecialchars($post['username']) ?>
                                        </a>
                                        <div class="post-time">
                                            <i class="far fa-clock me-1"></i><?= Functions\time_elapsed_string($post['created_at']) ?>
                                        </div>
                                    </div>
                                    <!-- Inside the post-header div, update the dropdown section -->
                                    <?php if ($post['user_id'] === $_SESSION['user_id']): ?>
                                        <div class="dropdown ms-auto">
                                            <button class="btn post-menu" 
                                                    type="button"
                                                    data-bs-toggle="dropdown" 
                                                    aria-expanded="false"
                                                    onclick="event.stopPropagation();">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); editPost(<?= $post['id'] ?>);">
                                                        <i class="fas fa-edit me-2"></i> Edit
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="event.stopPropagation(); deletePost(<?= $post['id'] ?>);">
                                                        <i class="fas fa-trash-alt me-2"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <div class="dropdown ms-auto">
                                            <button class="btn post-menu" 
                                                    type="button"
                                                    data-bs-toggle="dropdown" 
                                                    aria-expanded="false"
                                                    onclick="event.stopPropagation();">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item text-warning" href="#" 
                                                       onclick="event.stopPropagation(); reportPost(<?= $post['id'] ?>, '<?= htmlspecialchars($post['title']) ?>');">
                                                        <i class="fas fa-flag me-2"></i> Report
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); sharePost(<?= $post['id'] ?>);">
                                                        <i class="fas fa-share me-2"></i> Share
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); toggleSavePost(<?= $post['id'] ?>, this);">
                                                        <i class="fas fa-bookmark me-2"></i> Save
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="post-meta">
                                    <?php if ($post['type'] === 'donation'): ?>
                                        <span class="post-type-badge donation">
                                            <i class="fas fa-hand-holding-heart"></i> Donation
                                        </span>
                                    <?php else: ?>
                                        <span class="post-type-badge request">
                                            <i class="fas fa-hand-holding-medical"></i> Request
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- Find and replace the existing status badge with this -->
                                    <div class="post-status-badge <?= Functions\getStatusBadgeClass($post['status']) ?>">
                                        <?= ucfirst($post['status'] ?? 'available') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="post-content">
                                <!-- Update the image section in your posts loop -->
                                <?php if (!empty($post['image'])): ?>
                                    <div class="post-image-container">
                                        <img src="<?= htmlspecialchars(getImageUrl($post['image'])) ?>" 
                                             alt="<?= htmlspecialchars($post['title']) ?>" 
                                             class="post-image"
                                             onerror="this.onerror=null; this.src='/helpie/public/assets/images/default-post-image.jpg'; console.error('Failed to load image:', this.src);"
                                             loading="lazy">
                                    </div>
                                <?php endif; ?>
                                
                                <h5 class="post-title"><?= htmlspecialchars($post['title']) ?></h5>
                                <div class="post-text"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
                                
                                <div class="post-meta">
                                    <?php if ($post['type'] === 'donation'): ?>
                                        <div class="post-details">
                                            <?php if ($post['condition_state']): ?>
                                                <span class="detail-item">
                                                    <i class="fas fa-star me-1"></i>
                                                    Condition: <?= ucfirst(str_replace('_', ' ', $post['condition_state'])) ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($post['quantity']): ?>
                                                <span class="detail-item ms-3">
                                                    <i class="fas fa-box me-1"></i>
                                                    Quantity: <?= $post['quantity'] ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($post['expiry_date']): ?>
                                                <span class="detail-item ms-3">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Expires: <?= date('M d, Y', strtotime($post['expiry_date'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($post['location']): ?>
                                        <div class="post-location mt-2">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?= htmlspecialchars($post['location']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="post-status mt-2">
                                        <span class="badge bg-<?= $post['status'] === 'available' ? 'success' : 
                                            ($post['status'] === 'reserved' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($post['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- Replace the entire post-actions div with this updated version -->
                            <div class="post-actions">
                                <!-- Comment button -->
                                <button class="post-action btn" 
                                        onclick="event.stopPropagation(); showComments(<?= $post['id'] ?>)"
                                        aria-label="Show comments">
                                    <i class="far fa-comment" aria-hidden="true"></i>
                                    <span class="action-text">Comment</span>
                                    <span class="action-count"><?= intval($post['comment_count']) ?></span>
                                </button>

                                <!-- Share button -->
                                <button class="post-action btn share-btn" 
                                        data-post-id="<?= $post['id'] ?>"
                                        onclick="event.stopPropagation(); sharePost(<?= $post['id'] ?>)"
                                        aria-label="Share post">
                                    <i class="far fa-share-square" aria-hidden="true"></i>
                                    <span class="action-text">Share</span>
                                </button>

                                <!-- Save button -->
                                <button class="post-action btn <?= $post['is_saved'] ? 'active' : '' ?>" 
                                        onclick="event.stopPropagation(); toggleSavePost(<?= $post['id'] ?>, this)"
                                        aria-label="<?= $post['is_saved'] ? 'Remove from saved posts' : 'Save post' ?>">
                                    <i class="<?= $post['is_saved'] ? 'fas' : 'far' ?> fa-bookmark" aria-hidden="true"></i>
                                    <span class="action-count"><?= $post['save_count'] ?? 0 ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Comments Modal -->
    <div class="modal fade" id="commentsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Comments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="commentsList" class="comments-list mb-3">
                        <!-- Comments will be loaded here -->
                    </div>
                    <form id="commentForm" class="comment-form">
                        <input type="hidden" name="post_id" id="commentPostId">
                        <div class="input-group">
                            <textarea class="form-control" name="content" placeholder="Write a comment..." required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Post Modal -->
    <div class="modal fade create-post-modal" id="createPostModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createPostForm" action="create-post.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <!-- Post Type Selector -->
                        <div class="post-type-selector btn-group w-100 mb-3">
                            <button type="button" class="btn btn-outline-primary active" data-type="donation">
                                <i class="fas fa-hand-holding-heart"></i> Donation
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-type="request">
                                <i class="fas fa-hand-holding-medical"></i> Request
                            </button>
                        </div>
                        <input type="hidden" name="type" value="donation">

                        <!-- Title -->
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <!-- Category -->
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php
                                $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
                                foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Condition (for donations) -->
                        <div class="condition-group show">
                            <div class="form-group">
                                <label class="form-label">Condition</label>
                                <select name="condition_state" class="form-select">
                                    <option value="new">New</option>
                                    <option value="like_new">Like New</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="quantity" class="form-control" min="1" value="1">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Expiry Date (if applicable)</label>
                                <input type="date" name="expiry_date" class="form-control">
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="content" class="form-control" rows="4" required></textarea>
                        </div>

                        <!-- Location -->
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control">
                        </div>

                        <!-- Image Upload -->
                        <div class="form-group">
                            <label class="form-label">Add Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                            <div id="imagePreview" class="image-upload-preview mt-2" style="display: none;">
                                <img src="" alt="Preview" class="img-fluid rounded">
                                <div class="remove-image" onclick="removeImage()">
                                    <i class="fas fa-times"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Post</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Report Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="reportForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="post_id" id="reportPostId">
                        <p class="text-muted mb-3" id="reportPostTitle"></p>
                        <div class="mb-3">
                            <label class="form-label">Reason for reporting</label>
                            <select name="type" class="form-select" required>
                                <option value="">Select a reason</option>
                                <option value="spam">Spam or Misleading</option>
                                <option value="inappropriate">Inappropriate Content</option>
                                <option value="harassment">Harassment or Abuse</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Details</label>
                            <textarea name="reason" class="form-control" rows="3" required 
                                        placeholder="Please provide more details about your report..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide notifications after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.querySelectorAll('.notification-toast').forEach(function(toast) {
                    const closeBtn = toast.querySelector('.btn-close');
                    if (closeBtn) closeBtn.click();
                });
            }, 5000);

            // Initialize share buttons
            document.querySelectorAll('.share-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const postId = this.dataset.postId;
                    const url = window.location.origin + window.location.pathname + '?post=' + postId;
                    
                    if (navigator.share) {
                        navigator.share({
                            title: 'Helpie Post',
                            text: 'Check out this post on Helpie!',
                            url: url
                        });
                    } else {
                        // Fallback: Copy to clipboard
                        navigator.clipboard.writeText(url).then(function() {
                            alert('Link copied to clipboard!');
                        });
                    }
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Post type selector handling
            const typeButtons = document.querySelectorAll('.post-type-selector .btn');
            const typeInput = document.querySelector('input[name="type"]');
            const conditionGroup = document.querySelector('.condition-group');

            typeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Update buttons
                    typeButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // Update hidden input
                    typeInput.value = this.dataset.type;

                    // Show/hide condition fields for donation
                    if (this.dataset.type === 'donation') {
                        conditionGroup.classList.add('show');
                    } else {
                        conditionGroup.classList.remove('show');
                    }
                });
            });

            // Create post card click handler
            const createPostCard = document.querySelector('.create-post-card');
            const createPostModal = new bootstrap.Modal(document.getElementById('createPostModal'));

            createPostCard.addEventListener('click', function() {
                createPostModal.show();
            });

            // Form validation
            const createPostForm = document.getElementById('createPostForm');
            createPostForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Basic validation
                const title = this.querySelector('input[name="title"]').value.trim();
                const content = this.querySelector('textarea[name="content"]').value.trim();
                const category = this.querySelector('select[name="category_id"]').value;

                if (!title || !content || !category) {
                    alert('Please fill in all required fields');
                    return;
                }

                // Submit the form
                this.submit();
            });
        });

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const img = preview.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage() {
            const preview = document.getElementById('imagePreview');
            const input = document.querySelector('input[type="file"]');
            preview.style.display = 'none';
            input.value = '';
        }

        function likePost(postId) {
            // TODO: Implement like functionality
        }

        function showComments(postId) {
            const commentsModal = new bootstrap.Modal(document.getElementById('commentsModal'));
            const commentsList = document.getElementById('commentsList');
            const commentForm = document.getElementById('commentForm');
            const commentPostId = document.getElementById('commentPostId');
            
            // Set the post ID for the comment form
            commentPostId.value = postId;
            
            // Clear existing comments
            commentsList.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>';
            
            // Load comments
            fetch(`get-comments.php?post_id=${postId}`)
                .then(response => response.json())
                .then(comments => {
                    if (comments.length === 0) {
                        commentsList.innerHTML = '<div class="text-center text-muted">No comments yet</div>';
                        return;
                    }
                    
                    commentsList.innerHTML = comments.map(comment => `
                        <div class="comment-item">
                            <div class="comment-header">
                                <img src="${comment.profile_image || 'assets/images/default-avatar.jpg'}" 
                                    alt="${comment.username}" 
                                    class="comment-avatar">
                                <div>
                                    <div class="comment-user">${comment.username}</div>
                                    <div class="comment-time">${comment.created_at}</div>
                                </div>
                            </div>
                            <div class="comment-content">${comment.content}</div>
                        </div>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    commentsList.innerHTML = '<div class="text-center text-danger">Error loading comments</div>';
                });
            
            // Handle comment submission
            commentForm.onsubmit = function(e) {
                e.preventDefault();
                const content = this.querySelector('textarea').value.trim();
                
                if (!content) return;
                
                fetch('add-comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `post_id=${postId}&content=${encodeURIComponent(content)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear the form
                        this.reset();
                        // Reload comments
                        showComments(postId);
                    } else {
                        alert(data.message || 'Error posting comment');
                    }
                })
                .catch(error => {
                    console.error('Error posting comment:', error);
                    alert('Error posting comment');
                });
            };
            
            // Show the modal
            commentsModal.show();
        }

        function editPost(postId) {
            // TODO: Implement edit functionality
        }

        function deletePost(postId) {
            if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                fetch('delete-post.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `post_id=${postId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the post card from the DOM
                        const postCard = document.querySelector(`.post-card[data-post-id="${postId}"]`);
                        postCard.style.opacity = '0';
                        setTimeout(() => {
                            postCard.remove();
                            // Show success notification
                            showNotification('Post deleted successfully', 'success');
                        }, 300);
                    } else {
                        showNotification(data.error || 'Error deleting post', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error deleting post', 'error');
                });
            }
        }

        // Update the goToPostDetails function
        function goToPostDetails(postId) {
            // Prevent navigation if clicking on buttons, links, or profile elements
            if (event.target.closest('.dropdown') || 
                event.target.closest('.post-actions') || 
                event.target.closest('.post-user-info') ||
                event.target.closest('.post-avatar')) {
                return;
            }
            
            // Navigate to post details page
            window.location.href = `post-details.php?id=${postId}`;
        }

        function reportPost(postId, postTitle) {
            // Prevent event bubbling
            event.stopPropagation();
            
            // Set the post details in the modal
            document.getElementById('reportPostId').value = postId;
            document.getElementById('reportPostTitle').textContent = `Post: ${postTitle}`;
            
            // Show the modal
            const reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
            reportModal.show();
        }

        // Update the report form submission handler
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            
            fetch('report-post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                submitButton.disabled = false;
                
                if (data.success) {
                    // Close the modal
                    bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
                    
                    // Show success message
                    showNotification(data.message, 'success');
                    
                    // Clear the form
                    this.reset();
                } else {
                    showNotification(data.message || 'Error submitting report', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitButton.disabled = false;
                showNotification('Error submitting report', 'error');
            });
        });

        // Update the showNotification function
        function showNotification(message, type = 'success') {
            const container = document.querySelector('.notification-container');
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show notification-toast`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Remove any existing notifications
            container.querySelectorAll('.notification-toast').forEach(t => t.remove());
            
            // Add new notification
            container.appendChild(toast);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                toast.querySelector('.btn-close')?.click();
            }, 5000);
        }

        function sharePost(postId) {
            event.stopPropagation();
            const url = `${window.location.origin}/helpie/public/post-details.php?id=${postId}`;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Helpie Post',
                    text: 'Check out this post on Helpie!',
                    url: url
                }).catch(console.error);
            } else {
                navigator.clipboard.writeText(url)
                    .then(() => {
                        showNotification('Link copied to clipboard!', 'success');
                    })
                    .catch(() => {
                        showNotification('Failed to copy link', 'error');
                    });
            }
        }

        function toggleSavePost(postId, button) {
            event.stopPropagation(); 
            
            // Add debug logs
            console.log('Saving post:', postId);
            
            fetch('toggle-save-post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Update button state
                    button.classList.toggle('active', data.is_saved);
                    
                    // Update save count
                    const countSpan = button.querySelector('.action-count');
                    if (countSpan) {
                        countSpan.textContent = data.save_count;
                    }
                    
                    // Toggle icon
                    const icon = button.querySelector('i');
                    if (icon) {
                        icon.className = data.is_saved ? 'fas fa-bookmark' : 'far fa-bookmark';
                    }
                    
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message || 'Error saving post', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error saving post', 'error');
            });
        }

        // Add to your existing JavaScript in main.php
        document.addEventListener('DOMContentLoaded', function() {
            // ...existing code...

            // Add click handler for profile images
            document.querySelectorAll('.post-avatar').forEach(img => {
                img.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent post click event
                    const userId = this.closest('.post-card').querySelector('.post-username').href.split('id=')[1];
                    window.location.href = `profile.php?id=${userId}`;
                });
            });

            // Initialize WebSocket connection
            const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsHost = `${protocol}//${window.location.hostname}:8081`;
            
            let ws = null;
            let reconnectAttempts = 0;
            const maxReconnectAttempts = 5;

            function connectWebSocket() {
                try {
                    ws = new WebSocket(wsHost);

                    ws.onopen = function() {
                        console.log('WebSocket Connected');
                        reconnectAttempts = 0;
                    };

                    ws.onmessage = function(event) {
                        try {
                            const notification = JSON.parse(event.data);
                            showNotification(notification.message, notification.type || 'info');
                        } catch (e) {
                            console.error('WebSocket message error:', e);
                        }
                    };

                    ws.onclose = function() {
                        console.log('WebSocket Disconnected');
                        if (reconnectAttempts < maxReconnectAttempts) {
                            setTimeout(() => {
                                reconnectAttempts++;
                                connectWebSocket();
                            }, 3000 * reconnectAttempts); // Exponential backoff
                        }
                    };

                    ws.onerror = function(error) {
                        console.error('WebSocket Error:', error);
                    };

                } catch (e) {
                    console.error('WebSocket Connection Error:', e);
                }
            }

            // Start WebSocket connection
            connectWebSocket();
        });

        // Add this to your existing JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Handle image loading
            document.querySelectorAll('.post-image').forEach(img => {
                img.addEventListener('load', function() {
                    this.classList.add('loaded');
                });
                
                img.addEventListener('error', function() {
                    // If image fails to load, show default image
                    this.src = '/helpie/public/assets/images/default-post-image.jpg';
                });
            });
        });

        // Update the image loading handler in your JavaScript section
        document.addEventListener('DOMContentLoaded', function() {
            // Handle image loading
            document.querySelectorAll('.post-image').forEach(img => {
                if (img.complete) {
                    img.classList.remove('loading');
                } else {
                    img.classList.add('loading');
                }
                
                img.addEventListener('load', function() {
                    this.classList.remove('loading');
                    console.log('Image loaded:', this.src); // Debug log
                });
                
                img.addEventListener('error', function() {
                    console.error('Image failed to load:', this.src); // Debug log
                    this.src = '/helpie/public/assets/images/default-post-image.jpg';
                    this.classList.remove('loading');
                });
            });
        });

        // Add this to your existing JavaScript section
        function updateUnreadCount(count) {
            const toggleButton = document.querySelector('.conversation-toggle');
            if (toggleButton) {
                toggleButton.setAttribute('data-unread', count);
            }
        }

        // Example: Update count when receiving WebSocket message
        ws.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                if (data.type === 'unread_count') {
                    updateUnreadCount(data.count);
                }
                // ... rest of your existing WebSocket message handling
            } catch (e) {
                console.error('WebSocket message error:', e);
            }
        };

        function startConversation(userId) {
            // Create or open conversation
            fetch(SITE_URL + '/public/chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=create_conversation&user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to chat with this user
                    window.location.href = `${SITE_URL}/public/chat.php?conversation=${data.conversation_id}`;
                } else {
                    alert('Could not start conversation. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Add this to your existing JavaScript section
        function startChat(userId) {
            // Prevent post click event
            event.stopPropagation();
            
            // Navigate to chat.php with the user ID
            window.location.href = `chat.php?user=${userId}`;
        }

        // Add this to your existing JavaScript section
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all dropdowns
            var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'))
            var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl)
            });

            // Stop event propagation on dropdown menus
            document.querySelectorAll('.dropdown-menu').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        });
    </script>
</body>
</html>