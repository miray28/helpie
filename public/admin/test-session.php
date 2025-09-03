<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
print_r([
    'Session Data' => $_SESSION,
    'Cookie Data' => $_COOKIE,
    'Server Path' => session_save_path(),
    'Session ID' => session_id(),
    'Session Status' => session_status()
]);
echo "</pre>";