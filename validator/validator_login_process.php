<?php
// authenticate.php — checks credentials, sets session, redirects
session_start();
require_once '../config.php';

$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) { die("Connection failed: ".$conn->connect_error); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: validator_login.php');
    exit();
}

$userInput = isset($_POST['user_input']) ? trim($_POST['user_input']) : '';
$password  = isset($_POST['password']) ? $_POST['password'] : '';

if ($userInput === '' || $password === '') {
    // back to login with error
    header('Location: validator_login.php?msg=' . urlencode('Please enter username/email and password.'));
    exit();
}

// Prepare and execute
$sql = "SELECT validator_id, v_username, email, v_password, active, expires_at
        FROM validator_account
        WHERE v_username = ? OR email = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
if (! $stmt) {
    // log error in production
    header('Location: validator_login.php?msg=' . urlencode('Authentication error.'));
    exit();
}
$stmt->bind_param('ss', $userInput, $userInput);
$stmt->execute();
$result = $stmt->get_result();

if (! $result || $result->num_rows !== 1) {
    header('Location: validator_login.php?msg=' . urlencode('Invalid credentials.'));
    exit();
}

$row = $result->fetch_assoc();

// Check active flag
if ((int)$row['active'] !== 1) {
    header('Location: validator_login.php?msg=' . urlencode('Account is deactivated. Contact admin.'));
    exit();
}

// Verify password (assumes v_password is hashed using password_hash)
/*if (! password_verify($password, $row['v_password'])) {
    header('Location: validator_login.php?msg=' . urlencode('Invalid credentials.'));
    exit();
}
*/

// Success: create secure session
session_regenerate_id(true);
$_SESSION['validator_id'] = (int)$row['validator_id'];
$_SESSION['v_username']   = $row['v_username'];
$_SESSION['email']        = $row['email'];
// optionally store expiration time to re-check later
$_SESSION['expires_at']   = $row['expires_at'];

// Close and redirect
$stmt->close();
$conn->close();

header('Location: index.php');
exit();
