<?php
session_start();
require 'config.php';

$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) { die("Connection failed: ".$conn->connect_error); }

if (!isset($_POST['email'], $_POST['password'])) {
  $_SESSION['error'] = "❌ Invalid request.";
  header("Location: /login.php"); exit;
}

$email = trim($_POST['email']);
$inputPassword = (string)$_POST['password'];

// SELECT only columns that exist in `accounts`
$stmt = $conn->prepare("SELECT record_id, id_number, email, password, account_type, change_pass FROM accounts WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$genericError = "❌ Invalid email or password.";

if ($result && $result->num_rows === 1) {
  $row = $result->fetch_assoc();

  if (password_verify($inputPassword, $row['password'])) {
    session_regenerate_id(true);

    // Standard session keys for the rest of your app
    $_SESSION['record_id']   = $row['record_id'];              // primary key
    $_SESSION['actor_id']    = $row['id_number'];              // e.g., 2024-1234
    $_SESSION['actor_role']  = strtolower($row['account_type']); // e.g., 'ccdu','faculty'
    $_SESSION['email']       = $row['email'];
    $_SESSION['account_type']= $row['account_type'];

    // 🔹 If flagged, force password change before dashboard
    if ($row['change_pass'] == 1) {
      header("Location: /MoralMatrix/change_password.php"); exit;
    }

    // Otherwise, redirect by role
    switch ($_SESSION['actor_role']) {
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
        $_SESSION['error'] = "❌ Account type not allowed.";
        header("Location: /login.php"); exit;
    }
  } else {
    $_SESSION['error'] = $genericError;
    header("Location: /login.php"); exit;
  }
} else {
  $_SESSION['error'] = $genericError;
  header("Location: /login.php"); exit;
}

$stmt->close();
$conn->close();
