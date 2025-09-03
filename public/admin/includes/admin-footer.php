    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($extra_scripts)): ?>
        <?= $extra_scripts ?>
    <?php endif; ?>
    
    <script>
    // Sidebar toggle for mobile
    document.querySelector('.toggle-sidebar')?.addEventListener('click', function() {
        document.querySelector('.admin-sidebar').classList.toggle('show');
    });

    // Hide sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            const sidebar = document.querySelector('.admin-sidebar');
            const toggleBtn = document.querySelector('.toggle-sidebar');
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
    </script>
</body>
</html>