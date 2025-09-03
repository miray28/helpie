<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="admin-sidebar d-flex flex-column">
    <div class="sidebar-header mb-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0" style="color:var(--primary);font-family:'Montserrat',Arial,sans-serif;letter-spacing:1px;">Admin Panel</h4>
        <div class="d-flex align-items-center">
            <button class="theme-toggle-btn" id="themeToggleBtn" aria-label="Toggle dark/light mode" type="button">
                <i id="themeToggleIcon" class="fas fa-moon"></i>
            </button>
            <button class="btn btn-outline-primary d-md-none toggle-sidebar ms-2" aria-label="Toggle sidebar" type="button" onclick="document.querySelector('.admin-sidebar').classList.toggle('show');">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
    <nav class="nav flex-column gap-1">
        <a href="../main.php" class="nav-link d-flex align-items-center gap-2 <?= $current_page === 'main.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Main Page</span>
        </a>
        <a class="nav-link d-flex align-items-center gap-2 <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a class="nav-link d-flex align-items-center gap-2 <?= $current_page === 'users.php' ? 'active' : '' ?>" href="users.php">
            <i class="fas fa-users"></i> <span>Users</span>
        </a>
        <a class="nav-link d-flex align-items-center gap-2 <?= $current_page === 'categories.php' ? 'active' : '' ?>" href="categories.php">
            <i class="fas fa-tags"></i> <span>Categories</span>
        </a>
        <a class="nav-link d-flex align-items-center gap-2 <?= $current_page === 'posts.php' ? 'active' : '' ?>" href="posts.php">
            <i class="fas fa-file-alt"></i> <span>Posts</span>
        </a>
        <a class="nav-link d-flex align-items-center gap-2 <?= $current_page === 'reports.php' ? 'active' : '' ?>" href="reports.php">
            <i class="fas fa-flag"></i> <span>Reports</span>
        </a>
        <a class="nav-link d-flex align-items-center gap-2 <?= $current_page === 'settings.php' ? 'active' : '' ?>" href="settings.php">
            <i class="fas fa-cog"></i> <span>Settings</span>
        </a>
        <div class="border-top my-3"></div>
        <a class="nav-link d-flex align-items-center gap-2 text-danger" href="../logout.php">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </nav>
</div>
<script>
// Sidebar toggle for mobile
if (window.innerWidth < 768) {
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            document.querySelector('.admin-sidebar').classList.remove('show');
        });
    });
}
// Theme toggle logic
const html = document.documentElement;
const themeToggleBtn = document.getElementById('themeToggleBtn');
const themeToggleIcon = document.getElementById('themeToggleIcon');
function setTheme(mode) {
    if (mode === 'dark') {
        html.setAttribute('data-theme', 'dark');
        themeToggleIcon.classList.remove('fa-sun');
        themeToggleIcon.classList.add('fa-moon');
    } else {
        html.setAttribute('data-theme', 'light');
        themeToggleIcon.classList.remove('fa-moon');
        themeToggleIcon.classList.add('fa-sun');
    }
    localStorage.setItem('admin-theme', mode);
}
// Detect preference
(function() {
    let theme = localStorage.getItem('admin-theme');
    if (!theme) {
        theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    setTheme(theme);
})();
themeToggleBtn.addEventListener('click', function() {
    const current = html.getAttribute('data-theme');
    setTheme(current === 'dark' ? 'light' : 'dark');
});
</script>