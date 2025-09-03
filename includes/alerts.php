<?php
function displayAlert($type, $message) {
    $icon = '';
    switch($type) {
        case 'success':
            $icon = '<i class="fas fa-check-circle me-2"></i>';
            break;
        case 'error':
        case 'danger':
            $icon = '<i class="fas fa-exclamation-circle me-2"></i>';
            break;
        case 'warning':
            $icon = '<i class="fas fa-exclamation-triangle me-2"></i>';
            break;
        case 'info':
            $icon = '<i class="fas fa-info-circle me-2"></i>';
            break;
        default:
            $icon = '';
    }
    
    return <<<HTML
    <div class="alert alert-{$type} alert-dismissible fade show" role="alert">
        {$icon}{$message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    HTML;
}

// Single alert (legacy way)
$_SESSION['success'] = "Operation completed successfully";
$_SESSION['error'] = "An error occurred";

// Multiple alerts (new way)
$_SESSION['alerts'][] = [
    'type' => 'success',
    'message' => 'First operation completed'
];
$_SESSION['alerts'][] = [
    'type' => 'warning',
    'message' => 'Please review the changes'
];
?>

<?php if (isset($_SESSION['alerts']) && is_array($_SESSION['alerts'])): ?>
    <?php foreach ($_SESSION['alerts'] as $alert): ?>
        <?= displayAlert($alert['type'], $alert['message']) ?>
    <?php endforeach; ?>
    <?php unset($_SESSION['alerts']); ?>
<?php else: ?>
    <?php if (isset($_SESSION['success'])): ?>
        <?= displayAlert('success', $_SESSION['success']) ?>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <?= displayAlert('danger', $_SESSION['error']) ?>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            }
        }, 5000);
    });
});
</script>