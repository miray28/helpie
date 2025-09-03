<?php
namespace Helpie\CSRF;

use function Helpie\Auth\generateCSRFToken;

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
