<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid request');
        }

        // Validate input
        $username = trim(filter_var(
            $_POST['username'] ?? '', 
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        ));
        $email = trim(filter_var(
            $_POST['email'] ?? '', 
            FILTER_SANITIZE_EMAIL
        ));
        $password = trim($_POST['password'] ?? '');
        $role = trim(filter_var(
            $_POST['role'] ?? '', 
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        ));
        
        // Enhanced validation
        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new Exception('Username must be between 3 and 50 characters');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            throw new Exception('Password must contain at least 8 characters, one uppercase letter, one lowercase letter, one number and one special character');
        }

        if (!in_array($role, ['donor', 'recipient'])) {
            throw new Exception('Please select a valid role');
        }
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new Exception('Username already taken');
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email already registered');
        }
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $username,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role
        ]);
        
        $_SESSION['success'] = 'Registration successful! Please login.';
        header('Location: login.php');
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
    <title>Register - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2DA664;
            --primary-dark: #218c53;
            --bg-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            --card-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            --input-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            --nav-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            --nav-link-color: #4a5568;
            --nav-hover-color: #2DA664;
        }

        body {
            font-family: 'Inter', 'Montserrat', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 76px;
        }

        .register-container {
            max-width: 500px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .card {
            border: none;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(20px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-body {
            padding: 2.5rem !important;
        }

        .form-control, .form-select {
            border: 1.5px solid #e0e0e0;
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: var(--input-shadow);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(45, 166, 100, 0.1);
        }

        .input-group-text {
            border: 1.5px solid #e0e0e0;
            border-radius: 12px;
            padding: 12px 16px;
            background: #f8f9fa;
        }

        .btn {
            padding: 12px 24px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            height: 56px;
            font-size: 1.1rem;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(45, 166, 100, 0.2);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(45, 166, 100, 0.25);
        }

        .btn-outline-secondary {
            border: 1.5px solid #e0e0e0;
            color: #6c757d;
            height: 56px;
            font-size: 1rem;
            border-radius: 16px;
        }

        .btn-outline-secondary:hover {
            background: #f8f9fa;
            border-color: #e0e0e0;
            transform: translateY(-2px);
        }

        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            border-radius: 6px;
            border: 1.5px solid #e0e0e0;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .password-requirements {
            background: rgba(45, 166, 100, 0.04);
            border-radius: 16px;
            padding: 1.25rem;
            margin-top: 1rem;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .password-requirements li {
            display: flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .password-requirements li:last-child {
            margin-bottom: 0;
        }

        .requirement-met::before {
            content: '✓';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            margin-right: 8px;
            font-size: 12px;
        }

        .requirement-unmet::before {
            content: '';
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid #e0e0e0;
            border-radius: 50%;
            margin-right: 8px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .brand h2 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-floating > .form-control {
            padding: 1.5rem 1rem;
            height: calc(3.5rem + 2px);
            line-height: 1.25;
        }

        .form-floating > label {
            padding: 1rem;
        }

        .input-group-text {
            background: transparent;
            border-right: none;
        }

        .form-control {
            border-left: none;
            padding-left: 0;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            height: 56px;
            font-size: 1.1rem;
        }

        .form-select {
            height: 56px;
        }

        .alert {
            border-left: 4px solid;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .alert-danger {
            border-left-color: #dc3545;
        }

        .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .register-container {
                padding: 1rem;
            }
            
            .brand h2 {
                font-size: 2rem;
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .shake {
            animation: shake 0.3s ease-in-out;
        }

        @keyframes validationShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .form-control.is-invalid {
            animation: validationShake 0.3s ease-in-out;
            border-color: #dc3545;
            background-image: none;
        }

        .invalid-feedback {
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            color: #dc3545;
        }

        .invalid-feedback::before {
            content: '!';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            margin-right: 8px;
            font-size: 12px;
        }

        .navbar {
            box-shadow: var(--nav-shadow);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.98) !important;
        }

        .nav-link {
            color: var(--nav-link-color) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
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
            color: var(--nav-hover-color) !important;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .navbar-toggler {
            padding: 0.5rem;
            transition: all 0.3s ease;
        }

        .navbar-toggler:focus {
            box-shadow: none;
            color: var(--primary-color);
        }

        .btn-outline-success {
            border: 2px solid var(--primary-color);
            font-weight: 600;
            transition: all 0.3s ease;
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
                padding: 0.75rem 0 !important;
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top bg-white py-3">
        <div class="container">
            <a href="intro.php" class="navbar-brand">
                <img src="assets/images/Untitled-1.svg" alt="Helpie Logo" height="32">
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarContent" aria-controls="navbarContent" 
                    aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars text-success"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="intro.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contactus.php">Contact Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="aboutus.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="terms.php">Terms & Privacy</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <a href="login.php" class="btn btn-outline-success rounded-pill px-4">Sign In</a>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <div class="container register-container">
            <div class="brand">
                <h2 class="h4 text-dark mb-2">Create Account</h2>
                <p class="text-muted">Join Helpie and start making a difference</p>
            </div>

            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-4 fade show">
                            <svg width="20" height="20" class="me-2" viewBox="0 0 20 20">
                                <path fill="currentColor" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                            </svg>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] = bin2hex(random_bytes(32)) ?>">

                        <div class="mb-4">
                            <div class="form-floating">
                                <div class="input-group">
                                    <span class="input-group-text border-end-0">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           required 
                                           minlength="3"
                                           maxlength="50"
                                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                           placeholder="Choose a username">
                                    <label for="username"></label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-floating">
                                <div class="input-group">
                                    <span class="input-group-text border-end-0">
                                        <i class="fas fa-envelope text-muted"></i>
                                    </span>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           required
                                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                           placeholder="Enter your email">
                                    <label for="email"></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-floating">
                                <div class="input-group">
                                    <span class="input-group-text border-end-0">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           required 
                                           minlength="8"
                                           placeholder="Create a password">
                                    <label for="password"></label>
                                </div>
                            </div>
                            <div class="password-requirements">
                                <ul>
                                    <li id="req-length" class="requirement-unmet">At least 8 characters</li>
                                    <li id="req-uppercase" class="requirement-unmet">One uppercase letter</li>
                                    <li id="req-lowercase" class="requirement-unmet">One lowercase letter</li>
                                    <li id="req-number" class="requirement-unmet">One number</li>
                                    <li id="req-special" class="requirement-unmet">One special character (@$!%*?&)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">I want to...</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Choose an option</option>
                                <option value="donor" <?= isset($_POST['role']) && $_POST['role'] === 'donor' ? 'selected' : '' ?>>Donate medical supplies</option>
                                <option value="recipient" <?= isset($_POST['role']) && $_POST['role'] === 'recipient' ? 'selected' : '' ?>>Request medical supplies</option>
                            </select>
                            <div class="invalid-feedback">Please select a valid role</div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a>
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-3">
                            <button type="submit" class="btn btn-success py-3 position-relative overflow-hidden">
                                <span class="d-flex align-items-center justify-content-center">
                                    <svg width="20" height="20" class="me-2" viewBox="0 0 20 20">
                                        <path fill="currentColor" d="M10 0C4.477 0 0 4.477 0 10c0 5.522 4.477 10 10 10 5.522 0 10-4.478 10-10 0-5.523-4.478-10-10-10zm5 11h-4v4H9v-4H5V9h4V5h2v4h4v2z"/>
                                    </svg>
                                    Create Account
                                </span>
                            </button>
                            <a href="login.php" class="btn btn-outline-secondary py-3">
                                <span class="d-flex align-items-center justify-content-center">
                                    <svg width="20" height="20" class="me-2" viewBox="0 0 20 20">
                                        <path fill="currentColor" d="M10 9.408l2.963 2.962-1.414 1.414-1.549-1.549v4.765h-2v-4.765L6.451 13.784 5.037 12.37 8 9.408V6h2v3.408zM18 10c0 4.418-3.582 8-8 8s-8-3.582-8-8 3.582-8 8-8 8 3.582 8 8zm-2 0c0-3.314-2.686-6-6-6s-6 2.686-6 6 2.686 6 6 6 6-2.686 6-6z"/>
                                    </svg>
                                    Already have an account?
                                </span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-4 bg-white border-top mt-auto">
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
            const form = document.querySelector('.needs-validation');
            const password = document.getElementById('password');
            
            // Password strength validation
            function validatePassword() {
                const requirements = {
                    length: password.value.length >= 8,
                    uppercase: /[A-Z]/.test(password.value),
                    lowercase: /[a-z]/.test(password.value),
                    number: /\d/.test(password.value),
                    special: /[@$!%*?&]/.test(password.value)
                };

                // Update requirement indicators
                Object.entries(requirements).forEach(([req, met]) => {
                    const el = document.getElementById(`req-${req}`);
                    if (el) {
                        el.classList.toggle('requirement-met', met);
                        el.classList.toggle('requirement-unmet', !met);
                    }
                });

                return Object.values(requirements).every(Boolean);
            }

            password.addEventListener('input', validatePassword);

            form.addEventListener('submit', function(event) {
                if (!form.checkValidity() || !validatePassword()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });

            // Add loading state to submit button
            document.querySelector('form').addEventListener('submit', function(e) {
                if (this.checkValidity() && validatePassword()) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = `
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Creating Account...
                    `;
                }
            });

            // Add ripple effect to buttons
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('div');
                    ripple.classList.add('ripple');
                    
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    
                    ripple.style.width = ripple.style.height = `${size}px`;
                    ripple.style.left = `${e.clientX - rect.left - size/2}px`;
                    ripple.style.top = `${e.clientY - rect.top - size/2}px`;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => ripple.remove(), 600);
                });
            });

            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelector(this.getAttribute('href')).scrollIntoView({
                        behavior: 'smooth'
                    });
                });
            });

            // Add validation feedback animations
            document.querySelectorAll('.form-control, .form-select').forEach(input => {
                input.addEventListener('invalid', function(e) {
                    e.preventDefault();
                    this.classList.add('shake');
                    setTimeout(() => this.classList.remove('shake'), 600);
                });
            });

            // Add success feedback
            document.querySelectorAll('.form-control, .form-select').forEach(input => {
                input.addEventListener('input', function() {
                    if (this.checkValidity()) {
                        this.style.borderColor = 'var(--primary-color)';
                    } else {
                        this.style.borderColor = '';
                    }
                });
            });
        });
    </script>
</body>
</html>