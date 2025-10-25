<?php
require __DIR__ . '/config.php'; // load BASE_PATH / BASE_URL

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* -------------------------------
   1. Clear all session data
--------------------------------*/
$_SESSION = [];

/* -------------------------------
   2. Destroy the session cookie
--------------------------------*/
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/* -------------------------------
   3. Fully destroy session
--------------------------------*/
session_destroy();

/* -------------------------------
   4. Block browser caching so “Back” can’t reopen pages
--------------------------------*/
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

/* -------------------------------
   5. Redirect to login
--------------------------------*/
$loginPath = defined('BASE_PATH') ? BASE_PATH . '/login.php' : '/login.php';
header("Location: $loginPath");
exit;
