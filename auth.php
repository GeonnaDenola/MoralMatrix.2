<?php
/**
 * auth.php — Universal authentication guard for MoralMatrix
 * Include this at the top of every protected page.
 */

require_once __DIR__ . '/config.php';

// ---------------- SESSION SETUP ----------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 day session
        'cookie_httponly' => true,  // safer cookies
        'use_strict_mode' => true,  // prevents session fixation
        'cookie_samesite' => 'Lax'
    ]);
}

// ---------------- SESSION VALIDATION ----------------
if (empty($_SESSION['account_type']) || empty($_SESSION['actor_id'])) {
    // ❌ No active session — force logout
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/login.php?unauthorized=1");
    exit;
}

// ---------------- SESSION TIMEOUT (optional) ----------------
$timeoutSeconds = 1800; // 30 minutes
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeoutSeconds) {
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/login.php?expired=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time(); // update timestamp

// ---------------- ROLE ACCESS CONTROL ----------------
/**
 * Restrict access by role.
 * Example: require_role('ccdu');
 */
if (!function_exists('require_role')) {
    function require_role(string $role): void {
        $current = strtolower($_SESSION['account_type'] ?? '');
        if ($current !== strtolower($role)) {
            http_response_code(403);
            echo "<h2 style='text-align:center; margin-top:20%; color:red;'>Access Denied: Unauthorized Role</h2>";
            exit;
        }
    }
}

// ---------------- OPTIONAL HELPER ----------------
/**
 * Check if a user is logged in.
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        return !empty($_SESSION['account_type']) && !empty($_SESSION['actor_id']);
    }
}
?>
