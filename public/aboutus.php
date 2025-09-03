<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Montserrat', sans-serif; 
            background-color: #f8f9fa;
        }

        .about-section {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border-radius: 2rem;
            padding: 4rem 0;
        }

        .team-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .team-card:hover {
            transform: translateY(-5px);
        }

        .team-social {
            position: absolute;
            bottom: -50px;
            left: 0;
            right: 0;
            background: rgba(45, 166, 100, 0.95);
            padding: 1rem;
            transition: bottom 0.3s ease;
        }

        .team-card:hover .team-social {
            bottom: 0;
        }

        .mission-card {
            background: linear-gradient(145deg, #2DA664, #218c53);
            color: white;
            border-radius: 2rem;
            padding: 3rem;
        }

        .stats-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            text-align: center;
            transition: transform 0.3s ease;
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card:nth-child(1) { animation-delay: 0.2s; }
        .stats-card:nth-child(2) { animation-delay: 0.4s; }
        .stats-card:nth-child(3) { animation-delay: 0.6s; }

        .number-highlight {
            color: #2DA664;
            font-size: 2.5rem;
            font-weight: bold;
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

        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #DFF8E5;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .avatar img {
            width: 40px;
            height: 40px;
        }

        .nav-link {
            position: relative;
            transition: color 0.3s ease;
            font-size: 0.875rem;
            letter-spacing: 0.02em;
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

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        .nav-link.active {
            color: #2DA664 !important;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stats-card, .team-card, .value-item {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        .shadow-custom {
            box-shadow: 0 4px 12px rgba(45, 166, 100, 0.15);
        }

        .value-item {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="container py-4">
        <div class="d-flex justify-content-between align-items-center">
            <a href="intro.php" class="navbar-brand">
                <img src="assets/images/Untitled-1.svg" alt="Helpie Logo" height="28">
            </a>
            <div class="d-none d-md-flex gap-4 mx-auto">
                <a href="intro.php" class="text-secondary text-decoration-none nav-link">Home</a>
                <a href="contactus.php" class="text-secondary text-decoration-none nav-link">Contact Us</a>
                <a href="aboutus.php" class="text-secondary text-decoration-none nav-link active">About Us</a>
                <a href="terms.php" class="text-secondary text-decoration-none nav-link">Terms & Privacy</a>
            </div>
            <button class="btn btn-success rounded-pill px-4 shadow-custom position-relative">Sign Up</button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="container py-5">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold mb-4">Our Mission</h1>
                <p class="lead text-secondary mb-5">
                    At Helpie, we believe in creating a world where medical supplies reach those who need them most. 
                    Our platform connects donors with recipients, making healthcare more accessible for everyone.
                </p>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="container py-5">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number-highlight mb-2">5000+</div>
                    <h3 class="h5 mb-2">Donations Made</h3>
                    <p class="text-secondary mb-0">Successfully connected donors with recipients</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number-highlight mb-2">3000+</div>
                    <h3 class="h5 mb-2">Active Users</h3>
                    <p class="text-secondary mb-0">Growing community of donors and recipients</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number-highlight mb-2">100+</div>
                    <h3 class="h5 mb-2">Cities Covered</h3>
                    <p class="text-secondary mb-0">Expanding our reach across the nation</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section class="container py-5">
        <div class="mission-card">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="fw-bold mb-4">Our Values</h2>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="value-item">
                                <h3 class="h5 mb-3">Accessibility</h3>
                                <p class="mb-0">Making medical supplies available to everyone who needs them</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="value-item">
                                <h3 class="h5 mb-3">Community</h3>
                                <p class="mb-0">Building a supportive network of donors and recipients</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="value-item">
                                <h3 class="h5 mb-3">Impact</h3>
                                <p class="mb-0">Creating meaningful change in healthcare accessibility</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="container py-5">
        <h2 class="text-center fw-bold mb-5">Our Team</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="team-card text-center">
                    <div class="avatar mx-auto">
                        <img src="assets/images/team-1.svg" alt="Team Member">
                    </div>
                    <h3 class="h5 mb-2">Sarah Johnson</h3>
                    <p class="text-secondary mb-0">Founder & CEO</p>
                    <div class="team-social">
                        <a href="#" class="text-white text-decoration-none">LinkedIn</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="team-card text-center">
                    <div class="avatar mx-auto">
                        <img src="assets/images/team-2.svg" alt="Team Member">
                    </div>
                    <h3 class="h5 mb-2">Michael Chen</h3>
                    <p class="text-secondary mb-0">Head of Operations</p>
                    <div class="team-social">
                        <a href="#" class="text-white text-decoration-none">LinkedIn</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="team-card text-center">
                    <div class="avatar mx-auto">
                        <img src="assets/images/team-3.svg" alt="Team Member">
                    </div>
                    <h3 class="h5 mb-2">Emily Rodriguez</h3>
                    <p class="text-secondary mb-0">Community Manager</p>
                    <div class="team-social">
                        <a href="#" class="text-white text-decoration-none">LinkedIn</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="container py-5 mb-5">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h2 class="fw-bold mb-4">Join Our Mission</h2>
                <p class="lead text-secondary mb-4">
                    Be part of our community and help make healthcare more accessible for everyone.
                </p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="#" class="btn btn-success rounded-pill px-5 py-3 fw-bold shadow-custom">Get Started</a>
                    <a href="contactus.php" class="btn btn-outline-secondary rounded-pill px-5 py-3">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-light py-4 mt-auto">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-secondary">&copy; 2025 Helpie. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="terms.php" class="text-secondary text-decoration-none">Terms & Privacy</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>