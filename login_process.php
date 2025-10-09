<?php
// login_process.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/config.php';

// DB connect
$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) {
  $_SESSION['error'] = "Invalid email or password.";
  header("Location: /login.php"); exit;
}
$conn->set_charset('utf8mb4');

// Helpers
function bounce_with_error(string $email = ''): never {
  $_SESSION['error'] = "Invalid email or password.";
  $_SESSION['old_email'] = $email;
  header("Location: /MoralMatrix/login.php"); exit;
}

// Require POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  bounce_with_error();
}

// Inputs
$emailRaw = $_POST['email'] ?? '';
$passRaw  = $_POST['password'] ?? '';

$email = trim($emailRaw);
$inputPassword = (string)$passRaw;

// Basic guard
if ($email === '' || $inputPassword === '') {
  bounce_with_error($email);
}

// Fetch account
$stmt = $conn->prepare("
  SELECT record_id, id_number, email, password, account_type, change_pass
  FROM accounts
  WHERE email = ?
  LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows !== 1) {
  $stmt->close();
  $conn->close();
  bounce_with_error($email);
}

$row = $res->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($inputPassword, $row['password'])) {
  $conn->close();
  bounce_with_error($email);
}

// Good login
session_regenerate_id(true);

// Common session keys
$_SESSION['record_id']    = $row['record_id'];                 // PK
$_SESSION['actor_id']     = $row['id_number'];                 // e.g. 2024-1234
$_SESSION['email']        = $row['email'];
$_SESSION['account_type'] = $row['account_type'];
$_SESSION['actor_role']   = strtolower((string)$row['account_type']);

// Optional: convenience for some roles (won't error if column wasn't selected)
if ($_SESSION['actor_role'] === 'student') {
  $_SESSION['student_id'] = $row['id_number'];
  $_SESSION['first_name'] = $row['first_name'] ?? '';
}
if ($_SESSION['actor_role'] === 'security') {
  $_SESSION['security_id'] = $row['id_number'];
  $_SESSION['first_name']  = $row['first_name'] ?? '';
}

// Force password change?
if ((int)$row['change_pass'] === 1) {
  $conn->close();
  header("Location: /MoralMatrix/change_password.php"); exit;
}

// Route by role
$role = $_SESSION['actor_role'];
$conn->close();

switch ($role) {
  case 'super_admin':
    header("Location: /MoralMatrix/super_admin/dashboard.php"); exit;
  case 'administrator':
    header("Location: /MoralMatrix/admin/index.php"); exit;
  case 'faculty':
    header("Location: /MoralMatrix/faculty/index.php"); exit;
  case 'student':
    header("Location: /MoralMatrix/student/index.php"); exit;
  case 'ccdu':
    header("Location: /MoralMatrix/ccdu/index.php"); exit;
  case 'security':
    header("Location: /MoralMatrix/security/index.php"); exit;
  default:
    $_SESSION['error'] = "Invalid email or password.";
    header("Location: /MoralMatrix/login.php"); exit;
}
