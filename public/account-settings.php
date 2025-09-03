<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'update_email':
                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email address');
                }
                
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$_POST['email'], $_SESSION['user_id']]);
                $_SESSION['success'] = 'Email updated successfully';
                break;

            case 'change_password':
                if (!password_verify($_POST['current_password'], $user['password'])) {
                    throw new Exception('Current password is incorrect');
                }
                
                if (strlen($_POST['new_password']) < 8) {
                    throw new Exception('Password must be at least 8 characters');
                }
                
                if ($_POST['new_password'] !== $_POST['confirm_password']) {
                    throw new Exception('Passwords do not match');
                }
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([
                    password_hash($_POST['new_password'], PASSWORD_DEFAULT),
                    $_SESSION['user_id']
                ]);
                $_SESSION['success'] = 'Password changed successfully';
                break;

            case 'update_privacy':
                $privacy = isset($_POST['profile_visible']) ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE users SET verified = ? WHERE id = ?");
                $stmt->execute([$privacy, $_SESSION['user_id']]);
                $_SESSION['success'] = 'Privacy settings updated';
                break;

            case 'delete_account':
                if (!password_verify($_POST['confirm_password'], $user['password'])) {
                    throw new Exception('Incorrect password');
                }
                
                // Begin transaction
                $pdo->beginTransaction();
                
                // Delete user's files
                if (!empty($user['profile_image'])) {
                    @unlink(UPLOAD_PATH . substr($user['profile_image'], 8));
                }
                if (!empty($user['cover_image'])) {
                    @unlink(UPLOAD_PATH . substr($user['cover_image'], 8));
                }
                
                // Delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                $pdo->commit();
                
                // Clear session and redirect
                session_destroy();
                header('Location: login.php?deleted=1');
                exit;
        }
        
        header('Location: account-settings.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/um-account.css" rel="stylesheet">
    <style>
        /* Add these styles to replace any blue colors with #2DA664 */
        .btn-primary {
            background-color: #2DA664;
            border-color: #2DA664;
        }

        .btn-primary:hover {
            background-color: #34c576;
            border-color: #34c576;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 166, 100, 0.2);
        }

        .btn-primary:active, 
        .btn-primary:focus {
            background-color: #2DA664 !important;
            border-color: #2DA664 !important;
            box-shadow: 0 0 0 0.25rem rgba(45, 166, 100, 0.25) !important;
        }

        /* Update any links or interactive elements */
        a {
            color: #2DA664;
        }

        a:hover {
            color: #34c576;
        }

        .dropdown-item:hover {
            color: #2DA664;
        }

        /* Update focus states */
        .form-control:focus {
            border-color: #2DA664;
            box-shadow: 0 0 0 0.25rem rgba(45, 166, 100, 0.25);
        }

        .form-check-input:checked {
            background-color: #2DA664;
            border-color: #2DA664;
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="um-account">
        <div class="container">
            <div class="row g-4">
                <!-- Account Side -->
                <div class="col-lg-4">
                    <div class="um-account-side sticky-lg-top" style="top: 2rem;">
                        <div class="um-account-meta">
                            <div class="um-account-meta-img">
                                <img src="<?= !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'assets/images/default-avatar.jpg' ?>" 
                                     alt="<?= htmlspecialchars($user['username']) ?>">
                            </div>
                            <div class="um-account-name">
                                <a href="profile.php"><?= htmlspecialchars($user['username']) ?></a>
                            </div>
                            <div class="um-account-profile-link">
                                <a href="profile.php">View profile</a>
                            </div>
                        </div>

                        <ul class="um-account-nav">
                            <li>
                                <a href="#account" data-section="account" class="current">
                                    <span class="um-account-icon">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <span class="um-account-title">Account</span>
                                    <span class="um-account-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                </a>
                            </li>
                            <li>
                                <a href="#password" data-section="password">
                                    <span class="um-account-icon">
                                        <i class="fas fa-key"></i>
                                    </span>
                                    <span class="um-account-title">Change Password</span>
                                    <span class="um-account-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                </a>
                            </li>
                            <li>
                                <a href="#privacy" data-section="privacy">
                                    <span class="um-account-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </span>
                                    <span class="um-account-title">Privacy</span>
                                    <span class="um-account-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                </a>
                            </li>
                            <li>
                                <a href="#delete" data-section="delete">
                                    <span class="um-account-icon">
                                        <i class="fas fa-trash-alt"></i>
                                    </span>
                                    <span class="um-account-title">Delete Account</span>
                                    <span class="um-account-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Account Main -->
                <div class="col-lg-8">
                    <div class="um-account-main">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?= htmlspecialchars($_SESSION['success']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Account Settings -->
                        <div class="um-account-tab active" id="account">
                            <h3 class="um-account-heading">Account Settings</h3>
                            <div class="card">
                                <div class="card-body p-4">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_email">
                                        <div class="mb-4">
                                            <label for="email" class="form-label fw-medium">Email Address</label>
                                            <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Update Email</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Password Change -->
                        <div class="um-account-tab" id="password">
                            <h3 class="um-account-heading">Change Password</h3>
                            <div class="card">
                                <div class="card-body p-4">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_password">
                                        <div class="mb-4">
                                            <label for="current_password" class="form-label fw-medium">Current Password</label>
                                            <input type="password" class="form-control form-control-lg" id="current_password" 
                                                   name="current_password" required>
                                        </div>
                                        <div class="mb-4">
                                            <label for="new_password" class="form-label fw-medium">New Password</label>
                                            <input type="password" class="form-control form-control-lg" id="new_password" 
                                                   name="new_password" required minlength="8">
                                        </div>
                                        <div class="mb-4">
                                            <label for="confirm_password" class="form-label fw-medium">Confirm New Password</label>
                                            <input type="password" class="form-control form-control-lg" id="confirm_password" 
                                                   name="confirm_password" required minlength="8">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Change Password</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Privacy Settings -->
                        <div class="um-account-tab" id="privacy">
                            <h3 class="um-account-heading">Privacy Settings</h3>
                            <div class="card">
                                <div class="card-body p-4">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_privacy">
                                        <div class="form-check form-switch mb-4">
                                            <input class="form-check-input" type="checkbox" id="profile_visible" 
                                                   name="profile_visible" <?= $user['verified'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-medium" for="profile_visible">
                                                Make my profile visible to other users
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Update Privacy Settings</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Account -->
                        <div class="um-account-tab" id="delete">
                            <h3 class="um-account-heading">Delete Account</h3>
                            <div class="danger-zone">
                                <h5 class="text-danger mb-3">Danger Zone</h5>
                                <p class="text-muted mb-4">Once you delete your account, there is no going back. Please be certain.</p>
                                <form method="POST" onsubmit="return confirmDelete()">
                                    <input type="hidden" name="action" value="delete_account">
                                    <div class="mb-4">
                                        <label for="confirm_password" class="form-label fw-medium">
                                            Enter your password to confirm
                                        </label>
                                        <input type="password" class="form-control form-control-lg" id="confirm_password" 
                                               name="confirm_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash-alt me-2"></i>Delete My Account
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle settings navigation
    const navLinks = document.querySelectorAll('.um-account-nav a');
    const sections = document.querySelectorAll('.um-account-tab');

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links and sections
            navLinks.forEach(l => l.classList.remove('current'));
            sections.forEach(s => s.classList.remove('active'));
            
            // Add active class to clicked link
            this.classList.add('current');
            
            // Show corresponding section
            const sectionId = this.getAttribute('data-section');
            const targetSection = document.getElementById(sectionId);
            targetSection.classList.add('active');

            // Update URL hash without scrolling
            history.pushState(null, null, `#${sectionId}`);
        });
    });

    // Password strength meter
    const passwordField = document.getElementById('new_password');
    if (passwordField) {
        // Add password strength meter HTML
        const meterContainer = document.createElement('div');
        meterContainer.className = 'password-strength mt-2';
        meterContainer.innerHTML = `
            <div class="progress" style="height: 5px;">
                <div id="password-strength" class="progress-bar" role="progressbar"></div>
            </div>
            <small class="form-text mt-1" id="password-strength-text"></small>
        `;
        passwordField.parentNode.appendChild(meterContainer);

        // Add input listener
        passwordField.addEventListener('input', function() {
            const strength = calculatePasswordStrength(this.value);
            updatePasswordStrength(strength);
        });
    }

    // Enhanced delete account confirmation
    const deleteForm = document.querySelector('form[action="delete_account"]');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (confirm('WARNING: This action is permanent and cannot be undone!\n\nAre you absolutely sure you want to delete your account?')) {
                this.submit();
            }
        });
    }

    // Handle URL hash on page load
    if (window.location.hash) {
        const sectionId = window.location.hash.substring(1);
        const targetLink = document.querySelector(`[data-section="${sectionId}"]`);
        if (targetLink) {
            targetLink.click();
        }
    }
});

function calculatePasswordStrength(password) {
    let strength = 0;
    const checks = {
        length: password.length >= 8,
        lowercase: /[a-z]/.test(password),
        uppercase: /[A-Z]/.test(password),
        numbers: /[0-9]/.test(password),
        special: /[^a-zA-Z0-9]/.test(password)
    };

    strength = Object.values(checks).filter(Boolean).length;
    return strength;
}

function updatePasswordStrength(strength) {
    const meter = document.getElementById('password-strength');
    const text = document.getElementById('password-strength-text');
    if (!meter || !text) return;
    
    const states = [
        { color: '#dc3232', message: 'Very Weak' },
        { color: '#dc3232', message: 'Weak' },
        { color: '#f56e28', message: 'Medium' },
        { color: '#27ae60', message: 'Strong' },
        { color: '#27ae60', message: 'Very Strong' }
    ];
    
    const percentage = (strength / 5) * 100;
    const state = states[strength] || states[0];
    
    meter.style.width = `${percentage}%`;
    meter.style.backgroundColor = state.color;
    text.textContent = state.message;
    text.style.color = state.color;
}
</script>
</body>
</html>