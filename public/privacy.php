<?php
require_once '../includes/config.php';
require_once '../includes/languages.php';

// Set default language
$lang = $_SESSION['lang'] ?? 'en';
$dir = $lang === 'ar' ? 'rtl' : 'ltr';
$t = $translations[$lang];

// Handle language switch
if (isset($_POST['switch_lang'])) {
    $_SESSION['lang'] = $_SESSION['lang'] === 'en' ? 'ar' : 'en';
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['privacy_policy'] ?> - Helpie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4bb543;
            --error-color: #dc3545;
        }

        body {
            background: linear-gradient(135deg, #f5f7ff 0%, #f1f4ff 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .privacy-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .brand {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .brand img {
            width: 120px;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .brand h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .section {
            margin-bottom: 2rem;
        }

        .section h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .section p {
            color: #444;
            line-height: 1.6;
        }

        .section ul {
            padding-left: 1.5rem;
            color: #444;
            margin-bottom: 1rem;
        }

        .section ul li {
            margin-bottom: 0.5rem;
        }

        .btn-back {
            margin-top: 2rem;
        }

        .table {
            margin: 1rem 0;
        }

        .table th {
            background-color: #f8f9fa;
            color: var(--primary-color);
        }

        /* Add RTL support */
        [dir="rtl"] {
            text-align: right;
        }

        [dir="rtl"] .section ul {
            padding-right: 1.5rem;
            padding-left: 0;
        }

        [dir="rtl"] .me-2 {
            margin-left: 0.5rem !important;
            margin-right: 0 !important;
        }

        .lang-switch {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1000;
        }

        [dir="rtl"] .lang-switch {
            right: auto;
            left: 1rem;
        }
    </style>
</head>
<body>
    <!-- Add language switcher button -->
    <form method="POST" class="lang-switch">
        <button type="submit" name="switch_lang" class="btn btn-outline-primary">
            <?= $lang === 'en' ? 'العربية' : 'English' ?>
        </button>
    </form>

    <div class="container privacy-container">
        <div class="brand">
            <img src="assets/images/logo.png" alt="Helpie Logo">
            <h1><?= $t['privacy_policy'] ?></h1>
            <p class="text-muted"><?= $t['last_updated'] ?>: March 18, 2025</p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <div class="section">
                    <h3>1. <?= $t['info_collect'] ?></h3>
                    <p><?= $t['we_collect'] ?></p>
                    <ul>
                        <li><strong><?= $t['account_info'] ?>:</strong> <?= $t['account_info_details'] ?></li>
                        <li><strong><?= $t['profile_info'] ?>:</strong> <?= $t['profile_info_details'] ?></li>
                        <li><strong><?= $t['medical_supply_info'] ?>:</strong> <?= $t['medical_supply_info_details'] ?></li>
                        <li><strong><?= $t['usage_data'] ?>:</strong> <?= $t['usage_data_details'] ?></li>
                    </ul>
                </div>

                <div class="section">
                    <h3>2. <?= $t['how_we_use'] ?></h3>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th><?= $t['purpose'] ?></th>
                                <th><?= $t['data_used'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= $t['account_management'] ?></td>
                                <td><?= $t['account_management_details'] ?></td>
                            </tr>
                            <tr>
                                <td><?= $t['platform_functionality'] ?></td>
                                <td><?= $t['platform_functionality_details'] ?></td>
                            </tr>
                            <tr>
                                <td><?= $t['communication'] ?></td>
                                <td><?= $t['communication_details'] ?></td>
                            </tr>
                            <tr>
                                <td><?= $t['security'] ?></td>
                                <td><?= $t['security_details'] ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="section">
                    <h3>3. <?= $t['data_protection'] ?></h3>
                    <p><?= $t['data_protection_details'] ?></p>
                    <ul>
                        <li><?= $t['encryption'] ?></li>
                        <li><?= $t['https'] ?></li>
                        <li><?= $t['security_audits'] ?></li>
                        <li><?= $t['access_controls'] ?></li>
                    </ul>
                </div>

                <div class="section">
                    <h3>4. <?= $t['your_rights'] ?></h3>
                    <p><?= $t['your_rights_details'] ?></p>
                    <ul>
                        <li><?= $t['access_data'] ?></li>
                        <li><?= $t['correct_info'] ?></li>
                        <li><?= $t['request_deletion'] ?></li>
                        <li><?= $t['withdraw_consent'] ?></li>
                        <li><?= $t['export_data'] ?></li>
                    </ul>
                </div>

                <div class="section">
                    <h3>5. <?= $t['cookie_policy'] ?></h3>
                    <p><?= $t['cookie_policy_details'] ?></p>
                    <ul>
                        <li><?= $t['maintain_session'] ?></li>
                        <li><?= $t['remember_preferences'] ?></li>
                        <li><?= $t['analyze_usage'] ?></li>
                        <li><?= $t['improve_experience'] ?></li>
                    </ul>
                </div>

                <div class="section">
                    <h3>6. <?= $t['contact_info'] ?></h3>
                    <p><?= $t['contact_info_details'] ?></p>
                    <ul>
                        <li><?= $t['email'] ?>: privacy@helpie.com</li>
                        <li><?= $t['phone'] ?>: (555) 123-4567</li>
                        <li><?= $t['address'] ?>: 123 Medical Drive, Healthcare City, HC 12345</li>
                    </ul>
                </div>

                <div class="section">
                    <h3>7. <?= $t['updates_policy'] ?></h3>
                    <p><?= $t['updates_policy_details'] ?></p>
                </div>

                <div class="d-grid gap-2 btn-back">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i><?= $t['back'] ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>