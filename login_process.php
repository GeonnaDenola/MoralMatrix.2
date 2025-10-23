<?php
// login_process.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config.php';


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
  header("Location: /login.php"); exit; // ✅ Removed /MoralMatrix
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

if (!password_verify($inputPassword, $row['password'])) {
  $conn->close();
  bounce_with_error($email);
}

session_regenerate_id(true);

// Common session keys
$_SESSION['record_id']    = $row['record_id'];
$_SESSION['actor_id']     = $row['id_number'];
$_SESSION['email']        = $row['email'];
$_SESSION['account_type'] = $row['account_type'];
$_SESSION['actor_role']   = strtolower((string)$row['account_type']);

if ($_SESSION['actor_role'] === 'student') {
  $_SESSION['student_id'] = $row['id_number'];
}
if ($_SESSION['actor_role'] === 'security') {
  $_SESSION['security_id'] = $row['id_number'];
}

if ((int)$row['change_pass'] === 1) {
  $conn->close();
  header("Location: $basePath/change_password.php"); exit; // ✅ Removed /MoralMatrix
}

$role = $_SESSION['actor_role'];
$conn->close();

// ✅ Route by role (no /MoralMatrix prefix)
switch ($role) {
  case 'super_admin':
    header("Location: $basePath/super_admin/dashboard.php"); exit;
  case 'administrator':
    header("Location: $basePath/admin/index.php"); exit;
  case 'faculty':
    header("Location: $basePath/faculty/index.php"); exit;
  case 'student':
    header("Location: $basePath/student/index.php"); exit;
  case 'ccdu':
    header("Location: $basePath/ccdu/index.php"); exit;
  case 'security':
    header("Location: $basePath/security/index.php"); exit;
  default:
    $_SESSION['error'] = "Invalid email or password.";
    header("Location: $basePath/login.php"); exit;
}

?>
