<?php
// admin/update_admin.php
require '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

function fail($msg){
  global $conn;
  $conn->close();
  die($msg);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST"){
  fail("Invalid request.");
}

$record_id   = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;
$admin_id    = trim($_POST['admin_id'] ?? '');
$first_name  = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name   = trim($_POST['last_name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$mobile      = trim($_POST['mobile'] ?? '');

if (!$record_id || !$admin_id || !$first_name || !$middle_name || !$last_name || !$email || !$mobile){
  fail("Missing required fields.");
}

// Load current admin row to get the original admin_id (id_number in accounts) and existing photo
$st = $conn->prepare("SELECT admin_id, email, photo FROM admin_account WHERE record_id = ?");
$st->bind_param("i", $record_id);
$st->execute();
$cur = $st->get_result()->fetch_assoc();
$st->close();

if (!$cur){ fail("Admin not found."); }

$original_admin_id = $cur['admin_id']; // this maps to accounts.id_number
$existing_photo    = $cur['photo'] ?? '';

// --- Optional photo upload ---
$newPhotoName = ''; // empty means "don't change"
if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
  $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
  if (!in_array($_FILES["photo"]["type"], $allowedTypes)) {
    fail("⚠️ Only JPG, PNG, GIF files are allowed.");
  }

  $uploadDir = realpath(__DIR__ . "/../uploads");
  if ($uploadDir === false) {
    $uploadDir = __DIR__ . "/../uploads";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
  }

  $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES["photo"]["name"]));
  $newPhotoName = time() . "_" . $safeBase;
  $targetFile   = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newPhotoName;

  if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
    fail("⚠️ Error uploading photo.");
  }
}

// Begin transaction
$conn->begin_transaction();

try {
  // 1) Duplicate email check in accounts, excluding THIS admin’s account
  $st = $conn->prepare("SELECT 1 FROM accounts WHERE email = ? AND id_number <> ?");
  $st->bind_param("ss", $email, $original_admin_id);
  $st->execute();
  $dupEmail = $st->get_result()->num_rows > 0;
  $st->close();

  if ($dupEmail) {
    throw new Exception("⚠️ Email already used by another account.");
  }

  // 2) If admin_id changed, ensure the new id_number doesn’t clash
  if ($admin_id !== $original_admin_id) {
    $st = $conn->prepare("SELECT 1 FROM accounts WHERE id_number = ?");
    $st->bind_param("s", $admin_id);
    $st->execute();
    $idClash = $st->get_result()->num_rows > 0;
    $st->close();
    if ($idClash) {
      throw new Exception("⚠️ Another account already uses ID Number {$admin_id}.");
    }
  }

  // 3) Update admin_account (include admin_id change; only set photo if new uploaded)
  if ($newPhotoName !== '') {
    $sql = "UPDATE admin_account
            SET admin_id=?, first_name=?, middle_name=?, last_name=?, email=?, mobile=?, photo=?
            WHERE record_id=?";
    $st  = $conn->prepare($sql);
    $st->bind_param("sssssssi",
      $admin_id, $first_name, $middle_name, $last_name, $email, $mobile, $newPhotoName, $record_id
    );
  } else {
    $sql = "UPDATE admin_account
            SET admin_id=?, first_name=?, middle_name=?, last_name=?, email=?, mobile=?
            WHERE record_id=?";
    $st  = $conn->prepare($sql);
    $st->bind_param("ssssssi",
      $admin_id, $first_name, $middle_name, $last_name, $email, $mobile, $record_id
    );
  }
  if (!$st->execute()) {
    throw new Exception("Failed to update admin_account: " . $st->error);
  }
  $st->close();

  // 4) Update accounts row (match by original id_number; then set new id_number/email)
  if ($admin_id !== $original_admin_id) {
    $sql = "UPDATE accounts SET id_number = ?, email = ? WHERE id_number = ?";
    $st  = $conn->prepare($sql);
    $st->bind_param("sss", $admin_id, $email, $original_admin_id);
  } else {
    $sql = "UPDATE accounts SET email = ? WHERE id_number = ?";
    $st  = $conn->prepare($sql);
    $st->bind_param("ss", $email, $admin_id);
  }
  if (!$st->execute()) {
    throw new Exception("Failed to update accounts: " . $st->error);
  }
  $st->close();

  $conn->commit();

  header("Location: dashboard.php?updated=1");
  exit;

} catch (Exception $e) {
  $conn->rollback();
  fail($e->getMessage());
}
