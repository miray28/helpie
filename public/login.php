<?php
require_once '../includes/config.php';
require_once '../includes/db.php';


if (isset($_SESSION['user_id'])) {
    // Redirect logged-in users
    if ($_SESSION['is_admin'] == 1) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: main.php');  // Changed from index.php to main.php
    }
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

function checkLoginAttempts($attempts) {
    $max_attempts = 5;
    $lockout_time = 15 * 60; // 15 minutes

    if (!isset($_SESSION['lockout_time'])) {
        $_SESSION['lockout_time'] = time();
        $_SESSION['login_attempts'] = 1;
        return false;
    }

    if ($attempts >= $max_attempts && (time() - $_SESSION['lockout_time']) < $lockout_time) {
        return true;
    }

    if ((time() - $_SESSION['lockout_time']) > $lockout_time) {
        $_SESSION['login_attempts'] = 1;
        $_SESSION['lockout_time'] = time();
    }

    $_SESSION['login_attempts']++;
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use better sanitization and validation
    $username = trim(filter_var($_POST['username'], FILTER_SANITIZE_EMAIL));
    $password = trim($_POST['password']);
    
    // Add CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid request";
        header('Location: login.php');
        exit;
    }

    // Add rate limiting
    if (checkLoginAttempts($_SESSION['login_attempts'] ?? 0)) {
        $_SESSION['error'] = "Too many login attempts. Please try again later.";
        header('Location: login.php');
        exit;
    }

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please enter both username and password";
        header('Location: login.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, username, password, role, is_admin, email 
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $username]);
        error_log("Login attempt - Input: " . $username);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Enhanced debug logging
        if ($user) {
            error_log("User found in database:");
            error_log("Username: " . $user['username']);
            error_log("Email: " . $user['email']);
            error_log("Role: " . $user['role']);
            error_log("Is Admin: " . $user['is_admin']);
            
            // Only show first few chars of password hash for security
            error_log("Password hash prefix: " . substr($user['password'], 0, 20) . "...");
            
            // Log password verification attempt
            $verification = password_verify($password, $user['password']);
            error_log("Password verification result: " . ($verification ? "true" : "false"));
        } else {
            error_log("No user found with username/email: " . $username);
        }

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_admin'] = (int)$user['is_admin'];

            // After successful login verification:
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['is_admin'] = $user['is_admin'];
            $_SESSION['username'] = $user['username'];

            // Clear any existing error messages
            unset($_SESSION['error']);

            // Debug log - Remove in production
            error_log(sprintf(
                "Login successful - User: %s, Role: %s, Is Admin: %d",
                $user['username'],
                $user['role'],
                $user['is_admin']
            ));

            if ($user['is_admin'] == 1) {
                header('Location: /helpie/public/admin/dashboard.php');
            } else {
                header('Location: /helpie/public/main.php');  // Changed from index.php to main.php
            }
            exit;
        } else {
            error_log("Login failed for user: " . $username . " - Invalid credentials");
            $_SESSION['error'] = "Invalid username/email or password";
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error during login: " . $e->getMessage());
        $_SESSION['error'] = "A system error occurred. Please try again later.";
        header('Location: login.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2DA664;
            --primary-gradient: linear-gradient(135deg, #2DA664 0%, #218c53 100%);
            --surface-color: #ffffff;
            --background-color: #f8f9fa;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-radius: 16px;
            --transition: all 0.3s ease;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 24px rgba(45, 166, 100, 0.12);
            --nav-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            --nav-link-color: #4a5568;
            --nav-link-hover: #2DA664;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 76px;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            animation: fadeIn 0.5s ease-out;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(10px);
            transition: var(--transition);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(45, 166, 100, 0.15);
        }

        .card-body {
            padding: 2.5rem !important;
        }

        .form-control {
            height: 52px;
            border-radius: 12px;
            border: 1.5px solid #e0e0e0;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(45, 166, 100, 0.1);
        }

        .btn {
            height: 52px;
            font-weight: 600;
            border-radius: 12px;
            transition: var(--transition);
        }

        .btn-primary {
            height: 56px;
            background: var(--primary-gradient);
            border: none;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(45, 166, 100, 0.2);
        }

        .btn-light {
            height: 56px;
            background: #f8f9fa;
            border: 1.5px solid #e0e0e0;
            font-weight: 500;
            color: var(--text-secondary);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .btn-light:hover {
            background: #ffffff;
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .shake {
            animation: shake 0.3s ease-in-out;
        }

        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(45, 166, 100, 0.2);
            transform: scale(0);
            animation: ripple 0.6s linear;
        }

        @keyframes ripple {
            to { transform: scale(2.5); opacity: 0; }
        }

        .navbar {
            box-shadow: var(--nav-shadow);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.98) !important;
        }

        .nav-link {
            color: var(--nav-link-color) !important;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary-gradient);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover {
            color: var(--nav-link-hover) !important;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        .btn-outline-success {
            border: 2px solid var(--primary-color);
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-outline-success:hover {
            background: var(--primary-gradient);
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        @media (max-width: 991px) {
            .navbar-collapse {
                padding: 1rem 0;
            }
            
            .nav-link {
                padding: 0.75rem 0;
            }
            
            .nav-link::after {
                display: none;
            }
            
            .navbar-nav {
                margin: 1rem 0;
            }
            
            .d-flex {
                margin-top: 1rem;
            }
        }

        .form-floating {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-floating > .form-control {
            height: 60px;
            padding: 1rem;
            border-radius: 12px;
            border: 1.5px solid #e0e0e0;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .form-floating > .form-control:focus {
            border-color: var(--primary-color);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(45, 166, 100, 0.1);
        }

        .form-floating > label {
            padding: 1rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            transform: scale(0.85) translateY(-0.75rem) translateX(0.15rem);
            color: var(--primary-color);
        }

        .btn-password-toggle {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0.5rem 1rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }

        .btn-password-toggle:hover {
            color: var(--primary-color);
        }

        .form-check {
            padding-left: 1.8rem;
        }

        .form-check-input {
            width: 1.2rem;
            height: 1.2rem;
            margin-left: -1.8rem;
            border-radius: 6px;
            border: 1.5px solid #e0e0e0;
            transition: all 0.2s ease;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: var(--text-secondary);
        }

        a.text-decoration-none {
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        a.text-decoration-none:hover {
            color: var(--primary-dark);
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            animation: fadeIn 0.3s ease-out;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .alert .btn-close {
            padding: 1.05rem;
            transition: all 0.3s ease;
        }

        .alert .btn-close:hover {
            transform: rotate(90deg);
        }
    </style>
</head>
<body>
   <!-- Navigation -->
   <nav class="navbar navbar-expand-lg fixed-top bg-white py-3">
        <div class="container">
            <a href="intro.php" class="navbar-brand">
                <img src="assets/images/Untitled-1.svg" alt="Helpie Logo" height="32">
            </a>
            
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarContent" aria-controls="navbarContent" 
                    aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars text-success"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link px-3" href="intro.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="contactus.php">Contact Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="aboutus.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="terms.php">Terms & Privacy</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <a href="register.php" class="btn btn-outline-success rounded-pill px-4 py-2">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <div class="login-container">
            <div class="brand">
                <h2 class="display-6 fw-bold mb-2">Welcome Back</h2>
                <p class="text-secondary mb-4">Sign in to continue to Helpie</p>
            </div>

            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger d-flex align-items-center fade show border-0 rounded-3">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)) ?>">
                        
                        <div class="mb-4">
                            <div class="form-floating">
                                <input type="text" 
                                       class="form-control" 
                                       id="username"
                                       name="username" 
                                       required 
                                       autocomplete="username"
                                       placeholder="Enter your email or username">
                                <label for="username">Email or Username</label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-floating">
                                <input type="password" 
                                       class="form-control" 
                                       id="password"
                                       name="password" 
                                       required 
                                       autocomplete="current-password"
                                       placeholder="Enter your password">
                                <label for="password">Password</label>
                                <button type="button" 
                                        class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted pe-3"
                                        onclick="togglePassword(this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label small" for="remember">Remember me</label>
                            </div>
                            <a href="forgot-password.php" class="text-decoration-none small">Forgot password?</a>
                        </div>

                        <div class="d-grid gap-3">
                            <button type="submit" class="btn btn-primary">
                                Sign In
                            </button>
                            <a href="register.php" class="btn btn-light">
                                Create Account
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-secondary">&copy; 2025 Helpie. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="terms.php" class="text-secondary text-decoration-none ms-3">Terms & Privacy</a>
                    <a href="contactus.php" class="text-secondary text-decoration-none ms-3">Contact Us</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modern form validation with feedback
        const form = document.querySelector('.needs-validation');
        
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                
                // Add shake animation to invalid fields
                const invalidInputs = form.querySelectorAll(':invalid');
                invalidInputs.forEach(input => {
                    input.parentElement.classList.add('shake');
                    setTimeout(() => {
                        input.parentElement.classList.remove('shake');
                    }, 600);
                });
            } else {
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <div class="d-flex align-items-center">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        <span>Signing in...</span>
                    </div>
                `;
            }
            
            form.classList.add('was-validated');
        });

        // Modern password toggle with smooth transition
        function togglePassword(button) {
            const input = button.closest('.form-floating').querySelector('input');
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
            
            // Add ripple effect
            const ripple = document.createElement('div');
            ripple.classList.add('ripple');
            button.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        }

        // Add floating label animations
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', () => {
                if (!input.value) {
                    input.parentElement.classList.remove('focused');
                }
            });
        });
    });
    </script>
</body>
</html>