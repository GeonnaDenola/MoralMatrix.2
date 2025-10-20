<?php
require __DIR__ . '/config.php'; // to load BASE_PATH
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// clear all session data
session_unset();
session_destroy();

// redirect to login page
header("Location: " . BASE_PATH . "/login.php");
exit;
?>
