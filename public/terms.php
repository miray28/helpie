<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Privacy - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fa;
        }

        .terms-section {
            background: white;
            border-radius: 2rem;
            padding: 3rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .policy-card {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .policy-card:hover {
            transform: translateY(-5px);
        }

        .btn-success {
            background: #2DA664;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 100px;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 166, 100, 0.2);
            background: #218c53;
        }

        .nav-link {
            position: relative;
            transition: color 0.3s ease;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 1.5px;
            bottom: -4px;
            left: 0;
            background-color: #2DA664;
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Active state for navigation */
        .nav-link.active {
            color: #2DA664 !important;
        }

        .nav-link.active::after {
            width: 100%;
        }

        /* Print styles */
        @media print {
            .btn, .navbar, .contact-section {
                display: none !important;
            }
            
            .terms-section {
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
            
            .policy-card {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="container py-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="intro.php" class="navbar-brand">
                    <img src="assets/images/Untitled-1.svg" alt="Helpie Logo" height="28">
                </a>
                
            </div>
            <div class="d-none d-md-flex gap-4 mx-auto">
                <a href="intro.php" class="text-secondary text-decoration-none nav-link">Home</a>
                <a href="contactus.php" class="text-secondary text-decoration-none nav-link">Contact Us</a>
                <a href="aboutus.php" class="text-secondary text-decoration-none nav-link">About Us</a>
                <a href="terms.php" class="text-secondary text-decoration-none nav-link">Terms & Privacy</a>
            </div>
            <button class="btn btn-success rounded-pill px-4">Sign Up</button>
        </div>
    </nav>

    <!-- Header Section -->
    <section class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h1 class="display-4 fw-bold mb-4">Terms of Service & Privacy Policy</h1>
                <p class="lead text-secondary mb-5">Please read these terms carefully before using our platform</p>
            </div>
        </div>
    </section>

    <!-- Terms Section -->
    <section class="container">
        <div class="terms-section">
            <h2 class="fw-bold mb-4">Terms of Service</h2>
            
            <div class="policy-card">
                <h3 class="h5 fw-bold mb-3">1. Acceptance of Terms</h3>
                <p class="text-secondary">By using our platform, you agree to comply with our terms and conditions.</p>
            </div>

            <div class="policy-card">
                <h3 class="h5 fw-bold mb-3">2. User Responsibilities</h3>
                <p class="text-secondary">Users must ensure that all donated medical supplies are in good condition and properly sanitized. Users are responsible for accurate descriptions of items.</p>
            </div>

            <div class="policy-card">
                <h3 class="h5 fw-bold mb-3">3. Prohibited Items</h3>
                <p class="text-secondary">Certain medical supplies may be restricted. Users must comply with local regulations regarding medical supply donations.</p>
            </div>
        </div>

        <!-- Privacy Section -->
        <div class="terms-section">
            <h2 class="fw-bold mb-4">Privacy Policy</h2>
            
            <div class="policy-card">
                <h3 class="h5 fw-bold mb-3">1. Information We Collect</h3>
                <p class="text-secondary">We collect basic personal information necessary for account creation and platform functionality. This includes name, email, and location.</p>
            </div>

            <div class="policy-card">
                <h3 class="h5 fw-bold mb-3">2. How We Use Your Information</h3>
                <p class="text-secondary">Your information is used to facilitate donations, maintain platform security, and improve user experience.</p>
            </div>

            <div class="policy-card">
                <h3 class="h5 fw-bold mb-3">3. Data Protection</h3>
                <p class="text-secondary">We implement industry-standard security measures to protect your personal information.</p>
            </div>
        </div>

        <!-- Cookie Policy Section -->
        <div class="terms-section">
            <h2 class="fw-bold mb-4">Cookie Policy</h2>
            
            <div class="policy-card">
                <h3 class="h5 fw-bold mb-3">Cookie Usage</h3>
                <p class="text-secondary">We use cookies to enhance your browsing experience and analyze platform usage patterns.</p>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="container py-5 mb-5">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h2 class="fw-bold mb-4">Questions About Our Policies?</h2>
                <p class="lead text-secondary mb-4">We're here to help clarify any concerns you may have about our terms and privacy policies.</p>
                <a href="contactus.php" class="btn btn-success rounded-pill px-5">Contact Us</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-light py-4 mt-5">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>