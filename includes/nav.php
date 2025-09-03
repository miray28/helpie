<?php
if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}
?>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

<!-- Custom CSS -->
<style>
    /* Custom active link indicator */
    .list-group-item a.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        height: 60%;
        width: 3px;
        background-color: #0d6efd;
        border-radius: 0 2px 2px 0;
    }

    /* Dark mode overrides */
    @media (prefers-color-scheme: dark) {
        .sidebar-dark {
            background-color: #1f2937;
            border-right-color: #374151 !important;
        }
        
        .list-group-item-dark {
            background-color: #1f2937;
            border-color: #374151;
        }
        
        .list-group-item-dark a {
            color: #e5e7eb;
        }
        
        .list-group-item-dark a.active {
            background-color: #374151 !important;
        }
    }
    
    /* Sidebar styles */
    #sidebar {
        width: 256px;
    }

    .offcanvas-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background: var(--bs-white);
    }

    .offcanvas-body {
        background: var(--bs-white);
    }

    .sidebar-content {
        min-height: calc(100vh - 56px);
    }

    /* Remove responsive hiding */
    .navbar .btn {
        display: block !important;
    }

    /* Reset container padding */
    .container, .container-fluid {
        padding-left: 15px;
        padding-right: 15px;
    }

    .list-group-item a {
        color: #212529;
        position: relative;
        transition: background-color 0.2s;
        text-decoration: none;
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
    }

    .list-group-item a:hover {
        background-color: #f8f9fa;
    }

    .list-group-item a.active {
        background-color: #f8f9fa;
    }

    .list-group-item-action {
        padding: 0.875rem 1.5rem;
        border: none;
        color: var(--bs-gray-700);
        transition: all 0.2s ease;
    }

    .list-group-item-action:hover {
        background-color: var(--bs-gray-100);
        color: var(--bs-primary);
    }

    .list-group-item-action:hover i {
        color: var(--bs-primary);
    }

    .list-group-item-action.active {
        background-color: var(--bs-gray-100);
        color: var(--bs-primary);
        font-weight: 500;
    }

    .list-group-item-action i {
        color: var(--bs-gray-600);
        transition: color 0.2s ease;
        width: 20px;
        text-align: center;
    }

    .text-danger {
        color: var(--bs-danger) !important;
    }

    .text-danger:hover {
        background-color: var(--bs-danger-bg-subtle) !important;
        color: var(--bs-danger) !important;
    }

    .text-danger:hover i {
        color: var(--bs-danger) !important;
    }

    .border-top {
        border-top: 1px solid var(--bs-gray-200) !important;
    }

    /* Ensure burger menu is always visible */
    .navbar-toggler {
        display: block !important;
        padding: 0.5rem;
        font-size: 1.25rem;
        border: none;
        background: transparent;
        z-index: 2;
    }

    /* Adjust container padding to prevent button overlap */
    .container-fluid {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    /* Center the brand/logo */
    .navbar-brand {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        margin: 0;
        padding: 0;
        z-index: 1;
    }

    .navbar-brand img {
        vertical-align: middle;
    }

    /* Ensure content doesn't overlap with navbar */
    body {
        padding-top: 56px;
    }

    /* Remove any media queries that might affect visibility */
    @media (min-width: 992px) {
        .navbar-expand-lg .navbar-toggler {
            display: block !important;
        }
    }

    /* Update navbar styles */
    .navbar {
        padding: 0.5rem 1rem;
        backdrop-filter: blur(10px);
        background-color: rgba(255, 255, 255, 0.9) !important;
    }

    .navbar-toggler {
        width: 40px;
        height: 40px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: background-color 0.2s ease;
    }

    .navbar-toggler:hover {
        background-color: var(--bs-gray-100);
    }

    /* Remove conflicting media queries */
    @media (min-width: 992px) {
        .navbar-toggler {
            display: block;
        }
    }

    /* Optimize sidebar transitions */
    .offcanvas-start {
        transition: transform 0.3s ease-in-out;
    }

    /* Sidebar styles */
    .offcanvas-start {
        width: 256px;
        transition: transform 0.3s ease-in-out;
        border-right: 1px solid rgba(0, 0, 0, 0.1);
    }

    .navbar-toggler {
        display: block;
        padding: 0.5rem;
        font-size: 1.25rem;
        border: none;
        background: transparent;
        z-index: 2;
    }

    /* Remove conflicting media queries */
    @media (min-width: 992px) {
        .navbar-toggler {
            display: block;
        }
    }
</style>

<!-- Replace the existing navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top border-bottom">
    <div class="container-fluid">
        <button class="navbar-toggler border-0" type="button" 
                data-bs-toggle="offcanvas" 
                data-bs-target="#sidebar" 
                aria-controls="sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <a class="navbar-brand mx-auto" href="<?= SITE_URL ?>/public/main.php">
            <img src="<?= SITE_URL ?>/public/assets/images/logo.png" alt="Helpie" height="30">
        </a>
        <button id="theme-toggle" class="btn btn-outline-secondary ms-2" type="button" aria-label="Toggle dark mode" style="border-radius: 50%; width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease;">
            <i class="fas fa-moon"></i>
        </button>
    </div>
</nav>

<!-- Replace the existing sidebar content -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header">
        <a class="navbar-brand" href="<?= SITE_URL ?>/public/main.php">
            <img src="<?= SITE_URL ?>/public/assets/images/logo.png" alt="Helpie" height="30">
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body px-0">
        <div class="list-group list-group-flush">
            <a href="<?= SITE_URL ?>/public/main.php" class="list-group-item list-group-item-action">
                <i class="fas fa-home fa-fw me-3"></i> Home
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= SITE_URL ?>/public/profile.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-user fa-fw me-3"></i> Profile
                </a>
                
                <a href="<?= SITE_URL ?>/public/my-activities.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-history fa-fw me-3"></i> My Activities
                </a>

                <a href="<?= SITE_URL ?>/public/donation-requests.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-hand-holding-heart fa-fw me-3"></i> Donation Requests
                </a>

                <a href="<?= SITE_URL ?>/public/members.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-users fa-fw me-3"></i> Members
                </a>

                <div class="border-top my-3"></div>

                <a href="<?= SITE_URL ?>/public/account-settings.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-cog fa-fw me-3"></i> Settings
                </a>

                <a href="<?= SITE_URL ?>/public/logout.php" class="list-group-item list-group-item-action text-danger">
                    <i class="fas fa-sign-out-alt fa-fw me-3"></i> Logout
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Content Spacer -->
<div class="h-16"></div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    
    if (sidebar) {
        // Initialize Bootstrap offcanvas
        const bsOffcanvas = new bootstrap.Offcanvas(sidebar);
        
        // Handle swipe to close
        let touchStartX = 0;
        const SWIPE_THRESHOLD = 50;

        sidebar.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });

        sidebar.addEventListener('touchend', (e) => {
            const swipeDistance = touchStartX - e.changedTouches[0].screenX;
            if (swipeDistance > SWIPE_THRESHOLD) {
                bsOffcanvas.hide();
            }
        });

        // Close sidebar on link clicks (mobile)
        sidebar.addEventListener('click', (e) => {
            if (e.target.closest('a')) {
                bsOffcanvas.hide();
            }
        });
    }
});
</script>
<!-- Dark mode toggle script moved to end of body for reliability -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const html = document.documentElement;
    const toggleBtn = document.getElementById('theme-toggle');
    const darkIcon = '<i class="fas fa-moon"></i>';
    const lightIcon = '<i class="fas fa-sun"></i>';
    if (!toggleBtn) {
        console.log('Dark mode toggle button not found!');
        return;
    }
    function setTheme(theme) {
        if (theme === 'dark') {
            html.setAttribute('data-theme', 'dark');
            toggleBtn.innerHTML = lightIcon;
        } else {
            html.removeAttribute('data-theme');
            toggleBtn.innerHTML = darkIcon;
        }
    }
    // Load preference
    const saved = localStorage.getItem('theme');
    if (saved === 'dark' || (saved !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        setTheme('dark');
    } else {
        setTheme('light');
    }
    toggleBtn.addEventListener('click', function() {
        const isDark = html.getAttribute('data-theme') === 'dark';
        setTheme(isDark ? 'light' : 'dark');
        localStorage.setItem('theme', isDark ? 'light' : 'dark');
        console.log('Theme switched to', isDark ? 'light' : 'dark');
    });
    console.log('Dark mode toggle script initialized.');
});
</script>