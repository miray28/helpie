<?php
http_response_code(404);
require_once '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Page Not Found - Helpie</title>
    <!-- ...styles... -->
</head>
<body>
    <div class="container text-center py-5">
        <h1 class="display-1">404</h1>
        <p class="lead">Page not found</p>
        <a href="<?= PUBLIC_URL ?>/main.php" class="btn btn-primary">Go Home</a>
    </div>
</body>
</html>