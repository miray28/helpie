<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

use Helpie\Auth;

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// Check admin access
Auth\requireAdmin();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Site settings
        $stmt = $pdo->prepare("
            UPDATE settings SET 
            value = CASE name
                WHEN 'site_name' THEN ?
                WHEN 'site_description' THEN ?
                WHEN 'contact_email' THEN ?
                WHEN 'posts_per_page' THEN ?
                WHEN 'enable_registration' THEN ?
                WHEN 'maintenance_mode' THEN ?
            END
            WHERE name IN ('site_name', 'site_description', 'contact_email', 
                          'posts_per_page', 'enable_registration', 'maintenance_mode')
        ");

        $stmt->execute([
            $_POST['site_name'] ?? '',
            $_POST['site_description'] ?? '',
            $_POST['contact_email'] ?? '',
            $_POST['posts_per_page'] ?? 20,
            isset($_POST['enable_registration']) ? '1' : '0',
            isset($_POST['maintenance_mode']) ? '1' : '0'
        ]);

        $pdo->commit();
        $_SESSION['success'] = 'Settings updated successfully';
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Settings update error: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating settings';
    }
    
    header('Location: settings.php');
    exit;
}

// Get current settings
try {
    $stmt = $pdo->query("SELECT * FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    $settings = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="settings-card">
            <h2 class="mb-4">Site Settings</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form method="POST" class="card">
                <div class="card-body">
                    <!-- General Settings -->
                    <h5 class="card-title mb-4">General Settings</h5>
                    <div class="mb-3">
                        <label class="form-label">Site Name</label>
                        <input type="text" name="site_name" class="form-control" 
                               value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Site Description</label>
                        <textarea name="site_description" class="form-control" rows="3"><?= 
                            htmlspecialchars($settings['site_description'] ?? '') 
                        ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="contact_email" class="form-control" 
                               value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Posts Per Page</label>
                        <input type="number" name="posts_per_page" class="form-control" 
                               value="<?= htmlspecialchars($settings['posts_per_page'] ?? '20') ?>" 
                               min="1" max="100" required>
                    </div>

                    <hr class="my-4">

                    <!-- Site Features -->
                    <h5 class="card-title mb-4">Features</h5>
                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" name="enable_registration" class="form-check-input" 
                               id="enableRegistration" <?= ($settings['enable_registration'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enableRegistration">Enable User Registration</label>
                    </div>

                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" name="maintenance_mode" class="form-check-input" 
                               id="maintenanceMode" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="maintenanceMode">Maintenance Mode</label>
                        <div class="form-text text-danger">
                            Warning: This will make the site inaccessible to regular users
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="reset" class="btn btn-light">Reset</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.querySelector('input[name="contact_email"]').value;
            if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                e.preventDefault();
                alert('Please enter a valid email address');
            }
        });

        // Maintenance mode confirmation
        document.getElementById('maintenanceMode').addEventListener('change', function() {
            if (this.checked && !confirm('Enable maintenance mode? This will prevent regular users from accessing the site.')) {
                this.checked = false;
            }
        });
    </script>
</body>
</html>