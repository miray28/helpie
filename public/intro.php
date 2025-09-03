<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .custom-rounded { border-radius: 100px; }
        .rotate-90 { transform: rotate(90deg); }
        .shadow-custom { box-shadow: 0px 5px 5px 0px rgba(75,93,104,0.10); }
        .mix-blend-multiply { mix-blend-mode: multiply; }
        body { font-family: 'Montserrat', sans-serif; }
        .hero-image {
            max-width: 100%;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
            transition: transform 0.3s ease;
            animation: float 6s ease-in-out infinite;
        }

        .hero-image:hover {
            transform: translateY(-5px);
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .highlight-text {
            color: #2DA664;
        }

        .section-image {
            max-width: 100%;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
            transition: transform 0.3s ease;
        }

        .section-image:hover {
            transform: translateY(-5px);
        }

        .how-it-works {
            background: #DFF8E5;
            border-radius: 2rem;
            overflow: hidden;
        }

        .why-donate {
            background: #FFFFFF;
            border-radius: 2rem;
            padding: 4rem 0;
            margin: 6rem 0;
            
        }

        .benefit-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .benefit-card:hover {
            transform: translateY(-5px);
        }

        .navbar-brand {
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .navbar-brand img {
            height: 28px; /* Further reduced for better proportion */
            width: auto;
        }

        .container-fluid.py-4 {
            max-width: var(--container-max-width, 1320px);
            margin: 0 auto;
            padding-left: calc(var(--bs-gutter-x) * .5);
            padding-right: calc(var(--bs-gutter-x) * .5);
        }

        /* Add new container variable */
        :root {
            --container-max-width: 1320px;
            --bs-gutter-x: 3rem;
        }

        /* Update hero section padding */
        .container.my-5 {
            padding-left: calc(var(--bs-gutter-x) * .5);
            padding-right: calc(var(--bs-gutter-x) * .5);
        }

        .nav-link {
            position: relative;
            transition: color 0.3s ease;
            font-size: 0.875rem; /* 14px */
            letter-spacing: 0.02em;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 1.5px; /* Slightly reduced from 2px */
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

        /* Updated navigation spacing */
        .d-none.d-md-flex.gap-4 {
            gap: 2rem !important;
        }

        .testimonials {
            background: linear-gradient(135deg, #F8F9FA 0%, #E9ECEF 100%);
        }

        .testimonial-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
        }

        .accordion-button {
            background: white;
            padding: 1.5rem;
            font-size: 1.1rem;
        }

        .accordion-button:not(.collapsed) {
            color: #2DA664;
            background: white;
            box-shadow: none;
        }

        .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(0,0,0,.125);
        }

        .accordion-button::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%232DA664'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }

        .bg-light-green {
            background-color: #DFF8E5;
        }

        .rounded-4 {
            border-radius: 300rem;
        }

        .rounded-5 {
            border-radius: 3rem; /* More rounded than Bootstrap's default */
        }

        .how-it-works-section .how-it-works-box {
            background-color: #DFF8E5;
            border-radius: 3rem; /* Extra rounded corners */
            padding: 3rem;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.05);
        }

        /* Enhanced animations and transitions */
        .btn {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 166, 100, 0.2);
        }

        /* Gradient overlay for sections */
        .bg-success {
            background: linear-gradient(135deg, #2DA664 0%, #218c53 100%) !important;
        }

        /* Enhanced spacing utilities */
        .section-spacing {
            padding: 7rem 0;
        }

        /* Floating animation for images */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .bg-success-subtle {
            background-color: #DFF8E5;
        }

        .shadow-custom {
            box-shadow: 0 4px 12px rgba(45, 166, 100, 0.2);
        }

        /* Enhanced Animations */
        .nav-link, .btn, .testimonial-card, .benefit-card, .hero-image, .section-image {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Smooth page load animations */
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

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Apply animations to sections */
        .hero-content {
            animation: fadeInUp 0.8s ease-out;
        }

        .hero-image {
            animation: scaleIn 0.8s ease-out;
        }

        .how-it-works, .why-donate, .testimonials {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .how-it-works.animate, .why-donate.animate, .testimonials.animate {
            opacity: 1;
            transform: translateY(0);
        }

        /* Enhanced hover effects */
        .testimonial-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12) !important;
        }

        .btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 16px rgba(45, 166, 100, 0.25);
        }

        .nav-link:hover {
            transform: translateY(-2px);
        }

        /* Smooth accordion transitions */
        .accordion-button {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .accordion-button:not(.collapsed) {
            transform: scale(1.01);
        }

        /* Enhanced floating animation */
        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-10px) rotate(1deg);
            }
            100% {
                transform: translateY(0) rotate(0deg);
            }
        }

        .hero-image {
            animation: float 6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }
    </style>
</head>
<body class="bg-white">
    <!-- Navigation -->
    <nav class="container py-4 position-relative">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <a href="intro.php" class="navbar-brand">
                    <img src="assets/images/Untitled-1.svg" 
                         alt="Helpie Logo" 
                         class="d-inline-block align-text-top">
                </a>
            </div>
            
            <div class="d-none d-md-flex gap-4 mx-auto">
                <a href="intro.php" class="text-secondary text-decoration-none nav-link active">Home</a>
                <a href="contactus.php" class="text-secondary text-decoration-none nav-link">Contact Us</a>
                <a href="aboutus.php" class="text-secondary text-decoration-none nav-link">About Us</a>
                <a href="terms.php" class="text-secondary text-decoration-none nav-link">Terms & Privacy</a>
            </div>
            
            <a href="register.php" class="btn btn-success rounded-pill px-4 shadow-custom text-white text-decoration-none">Join Us</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="container my-5 position-relative">
        <div class="row align-items-center">
            <div class="col-md-7 hero-content">
                <h1 class="display-4 fw-bold mb-4">
                    <span class="highlight-text">Helpie:</span> Connect & Share Medical Supplies for Those in Need
                </h1>
                <p class="lead text-secondary fw-bold mb-5">
                    Your platform for making a direct impact on the lives of others. Have medical supplies you no longer need? 
                    Or are you in need of essential supplies? Join our community today.
                </p>
                <div class="d-flex gap-3">
                    <a href="register.php" class="btn btn-success rounded-pill px-4 shadow-custom text-white text-decoration-none">Join Us</a>
                    <button class="btn btn-outline-secondary rounded-pill px-4">
                        Learn More
                    </button>
                </div>
            </div>
            <div class="col-md-5">
                <img src="assets/images/download (4) [Converted].svg" 
                     class="hero-image mix-blend-multiply" 
                     alt="Helpie Platform Illustration">
            </div>
        </div>
    </section>


 <!-- How It Works Section -->
<section class="how-it-works-section py-5">
    <div class="container">
        <div class="row align-items-center how-it-works-box">
            <div class="col-md-6">
                <img src="assets/images/Free Vector _ User research concept illustration [Converted].svg" 
                     class="img-fluid" alt="User Research">
            </div>
            <div class="col-md-6">
                <h2 class="fw-bold mb-4 text-dark">How It Works?</h2>
                <p class="text-secondary lead">
                    Whether you're a <span class="fw-semibold text-dark">donor</span> looking to give back or someone in need of 
                    <span class="fw-semibold text-dark">medical supplies</span>, Helpie makes the process 
                    <span class="fw-semibold text-dark">simple and straightforward</span>. Donors can easily
                    list their available supplies, while individuals in need can 
                    <span class="fw-semibold text-dark">browse and request</span> items that meet their requirements.
                </p>
            </div>
        </div>
    </div>
</section>



    <!-- Why Donate Section -->
    <section class="why-donate">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 order-md-2">
                    <img src="assets/images/Free Vector _ Disabled day concept illustration [Converted].svg" 
                         class="section-image mix-blend-multiply" 
                         alt="Making a Difference Through Medical Supply Donations">
                </div>
                <div class="col-md-6 order-md-1">
                    <h2 class="display-4 fw-bold mb-4">Why Donate Through Helpie?</h2>
                    <div class="benefits-list">
                        <p class="lead text-secondary mb-5">
                        By donating through Helpie, you're not only decluttering your space but also making a meaningful 
                        difference in someone else's life. Your unused medical supplies can provide essential care and 
                        comfort to those who might otherwise go without.
                    </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-5 bg-light testimonials section-spacing">
        <div class="container">
            <h2 class="display-5 fw-bold text-center mb-2">What Our Users Say</h2>
            <p class="text-center text-secondary mb-5 lead">Real stories from our community members</p>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card p-4 rounded-4 shadow-sm h-100">
                        <div class="mb-4">
                            <span class="text-success">★★★★★</span>
                        </div>
                        <p class="text-secondary mb-4">"Thanks to Helpie, I received a much-needed wheelchair. The process was seamless and the community was incredibly supportive!"</p>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <span class="fw-bold">AB</span>
                            </div>
                            <div class="ms-3">
                                <p class="fw-bold mb-0 text-success">Ahmed B.</p>
                                <small class="text-secondary">Recipient</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card p-4 rounded-4 shadow-sm h-100">
                        <div class="mb-4">
                            <span class="text-success">★★★★★</span>
                        </div>
                        <p class="text-secondary mb-4">"As a donor, I found the perfect match for my extra supplies. The platform made it easy to help someone in need."</p>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <span class="fw-bold">SJ</span>
                            </div>
                            <div class="ms-3">
                                <p class="fw-bold mb-0 text-success">Sarah J.</p>
                                <small class="text-secondary">Donor</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card p-4 rounded-4 shadow-sm h-100">
                        <div class="mb-4">
                            <span class="text-success">★★★★★</span>
                        </div>
                        <p class="text-secondary mb-4">"Helpie's community is amazing. I found medical supplies I couldn't afford otherwise. Thank you!"</p>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px">
                                <span class="fw-bold">MC</span>
                            </div>
                            <div class="ms-3">
                                <p class="fw-bold mb-0 text-success">Mike C.</p>
                                <small class="text-secondary">Recipient</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="container py-5">
        <h2 class="display-5 fw-bold mb-4 text-center">FAQs</h2>
        <div class="accordion rounded-4 overflow-hidden" id="faqAccordion">
            <div class="accordion-item border-0">
                <h2 class="accordion-header" id="faq1">
                    <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                        Is there any cost to use Helpie?
                    </button>
                </h2>
                <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-secondary">
                        No, Helpie is completely free to use for both donors and recipients.
                    </div>
                </div>
            </div>
            <div class="accordion-item border-0">
                <h2 class="accordion-header" id="faq2">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                        What kind of medical supplies can I donate?
                    </button>
                </h2>
                <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-secondary">
                        You can donate anything from bandages and wheelchairs to unused medications (following safety guidelines).
                    </div>
                </div>
            </div>
            <div class="accordion-item border-0">
                <h2 class="accordion-header" id="faq3">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                        How do I know the medical supplies are safe to use?
                    </button>
                </h2>
                <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-secondary">
                        All donors must verify that their supplies are unused or properly sanitized. We also provide guidelines for proper cleaning and packaging of medical supplies.
                    </div>
                </div>
            </div>
            <div class="accordion-item border-0">
                <h2 class="accordion-header" id="faq4">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                        How can I arrange pickup or delivery?
                    </button>
                </h2>
                <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-secondary">
                        Once connected, donors and recipients can arrange pickup or delivery through our secure messaging system. We recommend meeting in safe, public locations.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="bg-light text-dark section-spacing position-relative overflow-hidden">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="text-center">
                        <span class="badge bg-success-subtle text-success px-3 py-2 mb-3">Join Our Community</span>
                        <h2 class="display-4 fw-bold mb-4">Ready to Make a Difference?</h2>
                        <p class="lead mb-5 text-secondary px-4">Join Helpie and be a part of the change. Every donation makes an impact.</p>
                        <div class="d-flex gap-3 justify-content-center">
                            <a href="register.php" class="btn btn-success rounded-pill px-5 py-3 fw-bold shadow-custom text-white text-decoration-none">Join Now</a>
                            <a href="#" class="btn btn-outline-secondary rounded-pill px-5 py-3">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Add decorative background elements -->
        <div class="position-absolute top-0 end-0">
            <svg width="400" height="400" viewBox="0 0 400 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="200" cy="200" r="200" fill="#DFF8E5" opacity="0.5"/>
            </svg>
        </div>
        <div class="position-absolute bottom-0 start-0">
            <svg width="300" height="300" viewBox="0 0 300 300" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="150" cy="150" r="150" fill="#DFF8E5" opacity="0.3"/>
            </svg>
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
                    <a href="contactus.php" class="text-secondary text-decoration-none ms-3">Contact</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Intersection Observer for scroll animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate');
            }
        });
    }, {
        threshold: 0.1
    });

    // Observe elements
    document.querySelectorAll('.how-it-works, .why-donate, .testimonials').forEach((element) => {
        observer.observe(element);
    });

    // Smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
    </script>
</body>
</html>