<?php
// auth_check.php — include at top of protected pages to enforce login
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_login() {
    if (empty($_SESSION['validator_id'])) {
        // Not logged in
        header('Location: login.php?msg=' . urlencode('Please login to continue.'));
        exit();
    }

    // Optional: verify session expiry against DB value stored at login
    if (!empty($_SESSION['expires_at'])) {
        $now = new DateTime('now');
        try {
            $expires = new DateTime($_SESSION['expires_at']);
            if ($expires < $now) {
                // session expired
                session_unset();
                session_destroy();
                header('Location: login.php?msg=' . urlencode('Session expired. Please login again.'));
                exit();
            }
        } catch (Exception $e) {
            // if date parsing fails, force re-login
            session_unset();
            session_destroy();
            header('Location: login.php?msg=' . urlencode('Session error. Please login again.'));
            exit();
        }
    }
}

// Call this function in protected pages. Example:
// require_once 'auth_check.php';
// require_login();
