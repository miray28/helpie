<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['type', 'title', 'content', 'category_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception(ucfirst($field) . ' is required');
            }
        }

        // Validate post type
        if (!in_array($_POST['type'], ['donation', 'request'])) {
            throw new Exception('Invalid post type');
        }

        // Additional fields
        $condition_state = isset($_POST['condition_state']) ? $_POST['condition_state'] : null;
        $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : null;
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $location = !empty($_POST['location']) ? trim($_POST['location']) : null;
        $image_path = null;

        // Handle image upload if present
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($extension, $allowed)) {
                $filename = uniqid() . '.' . $extension;
                $filepath = 'uploads/posts/' . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                    // Store only the filename in the database
                    $image_path = $filename;
                }
            }
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Insert post
        $stmt = $pdo->prepare("
            INSERT INTO posts (
                user_id, type, title, content, category_id, 
                condition_state, quantity, expiry_date, location, 
                image, status
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, 'available'
            )
        ");

        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['type'],
            trim($_POST['title']),
            trim($_POST['content']),
            $_POST['category_id'],
            $condition_state,
            $quantity,
            $expiry_date,
            $location,
            $image_path
        ]);

        $post_id = $pdo->lastInsertId();

        $pdo->commit();
        
        // Redirect back to main page
        header('Location: main.php?posted=1');
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Delete uploaded file if it exists
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }

        $_SESSION['error'] = $e->getMessage();
        header('Location: main.php?error=1');
        exit;
    }
}