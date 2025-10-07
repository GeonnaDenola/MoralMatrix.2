<?php
// authenticate.php — checks credentials, sets session, redirects
declare(strict_types=1);
session_start();

require_once '../config.php';

// --- simple throttle (per-session) ---
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$_SESSION['login_blocked_until'] = $_SESSION['login_blocked_until'] ?? 0;

$now = time();
if ($now < (int)$_SESSION['login_blocked_until']) {
    header('Location: validator_login.php?msg=' . urlencode('Too many attempts. Try again in a minute.'));
    exit();
}

$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);
if ($conn->connect_error) {
    // Avoid leaking details
    header('Location: validator_login.php?msg=' . urlencode('Service temporarily unavailable.'));
    exit();
}
$conn->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: validator_login.php');
    exit();
}

$userInput = trim($_POST['user_input'] ?? '');
$password  = (string)($_POST['password'] ?? '');

if ($userInput === '' || $password === '') {
    header('Location: validator_login.php?msg=' . urlencode('Please enter username/email and password.'));
    exit();
}

// Prepare and execute
$sql = "SELECT validator_id, v_username, email, v_password, active, expires_at
        FROM validator_account
        WHERE v_username = ? OR email = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    header('Location: validator_login.php?msg=' . urlencode('Authentication error.'));
    exit();
}
$stmt->bind_param('ss', $userInput, $userInput);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    // throttle
    $_SESSION['login_attempts']++;
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_blocked_until'] = $now + 60; // 1 minute
        $_SESSION['login_attempts'] = 0;
    }
    header('Location: validator_login.php?msg=' . urlencode('Invalid credentials.'));
    exit();
}

$row = $result->fetch_assoc();

// Check active flag
if ((int)$row['active'] !== 1) {
    header('Location: validator_login.php?msg=' . urlencode('Account is deactivated. Contact admin.'));
    exit();
}

// Optional: check expiration
/*if (!empty($row['expires_at']) && strtotime($row['expires_at']) !== false && strtotime($row['expires_at']) <= $now) {
    header('Location: validator_login.php?msg=' . urlencode('Account has expired.'));
    exit();
} */

// ----- Verify password (handles both hashed and legacy plaintext) -----
$stored = (string)$row['v_password'];
$ok = false;

$info = password_get_info($stored);
if ($info['algo'] !== 0) {
    // Stored value looks like a password_hash() hash
    $ok = password_verify($password, $stored);

    // Opportunistic rehash if algorithm/cost updated
    if ($ok && password_needs_rehash($stored, PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE validator_account SET v_password = ? WHERE validator_id = ?");
        if ($upd) {
            $vid = (int)$row['validator_id'];
            $upd->bind_param("si", $newHash, $vid);
            $upd->execute();
            $upd->close();
        }
    }
} else {
    // Legacy plaintext in DB — compare safely, then migrate to hash
    if (hash_equals($stored, $password)) {
        $ok = true;
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE validator_account SET v_password = ? WHERE validator_id = ?");
        if ($upd) {
            $vid = (int)$row['validator_id'];
            $upd->bind_param("si", $newHash, $vid);
            $upd->execute();
            $upd->close();
        }
    }
}

if (!$ok) {
    // throttle
    $_SESSION['login_attempts']++;
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_blocked_until'] = $now + 60;
        $_SESSION['login_attempts'] = 0;
    }
    header('Location: validator_login.php?msg=' . urlencode('Invalid credentials.'));
    exit();
}

// Success: create secure session
$_SESSION['login_attempts'] = 0;
$_SESSION['login_blocked_until'] = 0;

session_regenerate_id(true);
$_SESSION['validator_id'] = (int)$row['validator_id'];
$_SESSION['v_username']   = $row['v_username'];
$_SESSION['email']        = $row['email'];
//$_SESSION['expires_at']   = $row['expires_at'];

// Close and redirect
$stmt->close();
$conn->close();

header('Location: index.php');
exit();
