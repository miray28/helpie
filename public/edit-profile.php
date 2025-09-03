<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

use function Helpie\Functions\{generateCsrfToken, verifyCsrfToken};

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user data with role information
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    $error = DEBUG_MODE ? $e->getMessage() : 'An error occurred';
}

if (!isset($user)) {
    $user = [
        'username' => '',
        'about' => '',
        'phone' => '',
        'address' => '',
        'profile_image' => '',
        'cover_image' => ''
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            throw new Exception('Invalid security token');
        }

        $updates = [];
        $params = [];

        // Define allowed file types and max size
        $allowedTypes = ['image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $validation = validateFileUpload($_FILES['profile_image'], $allowedTypes, $maxSize);
            if (!$validation['success']) {
                throw new Exception($validation['message']);
            }
            
            $newFilename = generateUniqueFilename($_FILES['profile_image']['name'], 'profile_');
            $destination = UPLOAD_PATH . 'profiles/' . $newFilename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                $updates[] = "profile_image = ?";
                $params[] = 'uploads/profiles/' . $newFilename;
                
                // Delete old profile image if exists
                if (!empty($user['profile_image'])) {
                    @unlink(UPLOAD_PATH . substr($user['profile_image'], 8));
                }
            }
        }

        // Handle cover image upload
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $validation = validateFileUpload($_FILES['cover_image'], $allowedTypes, $maxSize);
            if (!$validation['success']) {
                throw new Exception($validation['message']);
            }
            
            $newFilename = generateUniqueFilename($_FILES['cover_image']['name'], 'cover_');
            $destination = UPLOAD_PATH . 'profiles/' . $newFilename;
            
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $destination)) {
                $updates[] = "cover_image = ?";
                $params[] = 'uploads/profiles/' . $newFilename;
                
                // Delete old cover image if exists
                if (!empty($user['cover_image'])) {
                    @unlink(UPLOAD_PATH . substr($user['cover_image'], 8));
                }
            }
        }

        // Handle other profile updates
        $fields = ['username', 'about', 'phone', 'address'];
        foreach ($fields as $field) {
            if (isset($_POST[$field]) && !empty($_POST[$field])) {
                $updates[] = "$field = ?";
                $params[] = htmlspecialchars(trim($_POST[$field]));
            }
        }

        if (!empty($updates)) {
            $params[] = $_SESSION['user_id'];
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Update session data
            $_SESSION['username'] = $_POST['username'] ?? $_SESSION['username'];
            if (isset($params[0]) && strpos($updates[0], 'profile_image') !== false) {
                $_SESSION['profile_image'] = $params[0];
            }
            
            $_SESSION['success'] = 'Profile updated successfully';
            header('Location: profile.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Profile Update Error: " . $e->getMessage());
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .image-preview {
            width: 100%;
            height: 200px;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            background: #f8f9fa;
            margin-bottom: 1rem;
        }
        
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            position: relative;
            background: #f8f9fa;
            margin: 0 auto 1rem;
        }

        .profile-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-upload-button {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .image-upload-button:hover {
            background: white;
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Edit Profile</h2>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <!-- Cover Image -->
                            <div class="mb-4">
                                <label class="form-label">Cover Image</label>
                                <div class="image-preview">
                                    <img src="<?= !empty($user['cover_image']) ? htmlspecialchars($user['cover_image']) : 'assets/images/default-cover.jpg' ?>" 
                                         alt="Cover" id="coverPreview">
                                    <label class="image-upload-button" for="coverImage">
                                        <i class="fas fa-camera"></i>
                                    </label>
                                </div>
                                <input type="file" class="form-control" id="coverImage" name="cover_image" 
                                       accept="image/jpeg,image/png" style="display: none;">
                            </div>

                            <!-- Profile Image -->
                            <div class="mb-4 text-center">
                                <label class="form-label">Profile Picture</label>
                                <div class="profile-preview">
                                    <img src="<?= !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'assets/images/default-avatar.jpg' ?>" 
                                         alt="Profile" id="profilePreview">
                                    <label class="image-upload-button" for="profileImage">
                                        <i class="fas fa-camera"></i>
                                    </label>
                                </div>
                                <input type="file" class="form-control" id="profileImage" name="profile_image" 
                                       accept="image/jpeg,image/png" style="display: none;">
                            </div>

                            <!-- Username -->
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                            </div>

                            <!-- About -->
                            <div class="mb-3">
                                <label for="about" class="form-label">About</label>
                                <textarea class="form-control" id="about" name="about" rows="4"><?= htmlspecialchars($user['about'] ?? '') ?></textarea>
                            </div>

                            <!-- Contact Information -->
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>

                            <div class="mb-4">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="profile.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview handlers
        function handleImagePreview(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        document.getElementById('profileImage').addEventListener('change', function() {
            handleImagePreview(this, 'profilePreview');
        });

        document.getElementById('coverImage').addEventListener('change', function() {
            handleImagePreview(this, 'coverPreview');
        });

        // Add to the existing script section
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        });

        // Add file size validation
        function validateFileSize(input) {
            if (input.files && input.files[0]) {
                if (input.files[0].size > <?= MAX_UPLOAD_SIZE ?>) {
                    alert('File too large (max <?= MAX_UPLOAD_SIZE / 1024 / 1024 ?>MB)');
                    input.value = '';
                    return false;
                }
            }
            return true;
        }

        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                validateFileSize(this);
            });
        });
    </script>
</body>
</html>