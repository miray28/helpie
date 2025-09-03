<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Montserrat', sans-serif; 
            background-color: #f8f9fa;
        }
        
        .contact-form {
            background: white;
            border-radius: 2rem;
            padding: 3rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .form-control {
            border-radius: 1rem;
            padding: 0.8rem 1.2rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(45, 166, 100, 0.1);
            border-color: #2DA664;
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

        .contact-info-card {
            background: linear-gradient(145deg, #2DA664, #218c53);
            border-radius: 2rem;
            padding: 3rem;
            height: 100%;
            color: white;
        }

        .contact-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
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

        .shadow-custom {
            box-shadow: 0 4px 12px rgba(45, 166, 100, 0.2);
        }

        .btn-loading {
            position: relative;
            color: transparent !important;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 1rem;
            height: 1rem;
            top: 50%;
            left: 50%;
            margin: -0.5rem 0 0 -0.5rem;
            border: 2px solid #fff;
            border-radius: 50%;
            border-right-color: transparent;
            animation: spin 0.75s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Navigation (same as main page) -->
    <nav class="container py-4">
        <div class="d-flex justify-content-between align-items-center">
            <a href="intro.php" class="navbar-brand">
                <img src="assets/images/Untitled-1.svg" alt="Helpie Logo" height="28">
            </a>
            <div class="d-none d-md-flex gap-4 mx-auto">
                <a href="intro.php" class="text-secondary text-decoration-none nav-link">Home</a>
                <a href="contactus.php" class="text-secondary text-decoration-none nav-link active">Contact Us</a>
                <a href="aboutus.php" class="text-secondary text-decoration-none nav-link">About Us</a>
                <a href="terms.php" class="text-secondary text-decoration-none nav-link">Terms & Privacy</a>
            </div>
            <button class="btn btn-success rounded-pill px-4">Sign Up</button>
        </div>
    </nav>

    <!-- Contact Section -->
    <section class="container py-5">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <h1 class="display-4 fw-bold mb-4">Get in Touch</h1>
                <p class="lead text-secondary">Have questions about Helpie? We're here to help you make a difference.</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="contact-form">
                    <form action="process_contact.php" method="POST">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" name="subject" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message</label>
                                <textarea class="form-control" rows="5" name="message" required></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">Send Message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="contact-info-card">
                    <h3 class="fw-bold mb-4">Contact Information</h3>
                    <div class="mb-4">
                        <div class="contact-icon">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </div>
                        <p class="mb-0">+1 234 567 890</p>
                    </div>
                    <div class="mb-4">
                        <div class="contact-icon">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="mb-0">support@helpie.com</p>
                    </div>
                    <div>
                        <div class="contact-icon">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <p class="mb-0">123 Medical Drive<br>Healthcare City, HC 12345</p>
                    </div>
                </div>
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
                    <a href="aboutus.php" class="text-secondary text-decoration-none ms-3">About Us</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        
        // Show loading state
        submitButton.innerHTML = '';
        submitButton.disabled = true;
        submitButton.classList.add('btn-loading');

        fetch('process_contact.php', {
            method: 'POST',
            body: new FormData(form)
        })
        .then(response => response.json())
        .then(data => {
            // Remove any existing alerts
            const existingAlert = form.parentNode.querySelector('.alert');
            if (existingAlert) existingAlert.remove();

            // Create alert element
            const alert = document.createElement('div');
            alert.className = `alert alert-${data.success ? 'success' : 'danger'} mt-3`;
            alert.role = 'alert';
            alert.textContent = data.message;

            // Insert alert before form
            form.parentNode.insertBefore(alert, form);

            // Reset form if successful
            if (data.success) {
                form.reset();
            }

            // Remove alert after 5 seconds
            setTimeout(() => alert.remove(), 5000);
        })
        .catch(error => {
            console.error('Error:', error);
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger mt-3';
            alert.textContent = 'An error occurred. Please try again later.';
            form.parentNode.insertBefore(alert, form);
        })
        .finally(() => {
            // Restore button state
            submitButton.innerHTML = originalButtonText;
            submitButton.disabled = false;
            submitButton.classList.remove('btn-loading');
        });
    });
    </script>
</body>
</html>