<?php
// /qr.php — redirect scanner to the correct view page (based on logged-in role)
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/config.php';
session_start();

/* ---------- Helpers ---------- */
function db(array $dsn) {
  $c = @new mysqli($dsn['servername'], $dsn['username'], $dsn['password'], $dsn['dbname']);
  if ($c->connect_error) {
    http_response_code(500);
    exit('Database error');
  }
  $c->set_charset('utf8mb4');
  return $c;
}

function abs_url(string $path): string {
  $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

  if (preg_match('#^https?://#i', $base)) {
    // BASE_URL is a full origin (production)
    $origin = $base; // e.g. https://mccmoralmatrix.com
  } else {
    // BASE_URL is a path (localhost)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $origin = $scheme . '://' . $host . $base; // e.g. http://localhost/MoralMatrix
  }

  return $origin . '/' . ltrim($path, '/');
}


/* ---------- Input ---------- */
$studentId = $_GET['student_id'] ?? $_GET['sid'] ?? '';
$key       = $_GET['k'] ?? ''; // optional QR key mode

if ($studentId === '' && $key === '') {
  http_response_code(400);
  exit('Missing student_id');
}

/* ---------- Validate student ---------- */
$conn = db($database_settings);
if ($studentId !== '') {
  $st = $conn->prepare('SELECT 1 FROM student_account WHERE student_id = ? LIMIT 1');
  $st->bind_param('s', $studentId);
  $st->execute();
  $exists = $st->get_result()->num_rows > 0;
  $st->close();
  if (!$exists) {
    http_response_code(404);
    exit('Student not found');
  }
}
elseif ($key !== '') {
  $st = $conn->prepare('SELECT student_id FROM student_qr_keys WHERE qr_key = ? AND revoked = 0 LIMIT 1');
  $st->bind_param('s', $key);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  if (empty($row['student_id'])) {
    http_response_code(404);
    exit('Unknown/revoked key');
  }
  $studentId = $row['student_id'];
}
$conn->close();

/* ---------- Detect role ---------- */
$role = $_SESSION['account_type'] ?? '';

switch (strtolower($role)) {
  case 'security':
    $target = 'security/view_student.php';
    break;
  case 'faculty':
    $target = 'faculty/view_student.php';
    break;
  case 'ccdu':
  case 'super_admin':
  default:
    $target = 'ccdu/view_student.php';
    break;
}

/* ---------- Redirect ---------- */
$dest = abs_url($target . '?student_id=' . rawurlencode($studentId));
header('Location: ' . $dest, true, 302);
exit;
