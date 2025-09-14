<?php
// /qr.php — redirect scanner to the student profile
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');

require __DIR__ . '/config.php';

function db(array $dsn){
  $c = @new mysqli($dsn['servername'], $dsn['username'], $dsn['password'], $dsn['dbname']);
  if($c->connect_error){
    http_response_code(500);
    exit('Database error');
  }
  $c->set_charset('utf8mb4');
  return $c;
}

function base_url(): string{
  // If you defined BASE_URL in config.php, we use it; otherwise derive from this script path
  return defined('BASE_URL')
    ? rtrim(BASE_URL, '/')
    : (rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') ?: '/');
}

function abs_url(string $path): string{
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  if($path === '' || $path[0] !== '/'){
    $path = '/' . ltrim($path, '/');
  }
  return $scheme . '://' . $host . $path;
}

$studentId = $_GET['student_id'] ?? $_GET['sid'] ?? '';
$key       = $_GET['k'] ?? ''; // optional legacy key mode

/* ---------- Preferred: direct student_id ---------- */
if ($studentId !== '') {
  if (!preg_match('/^\d{4}-\d{4}$/', $studentId)) {
    http_response_code(400);
    exit('Invalid student_id');
  }

  $conn = db($database_settings);
  $st = $conn->prepare('SELECT 1 FROM student_account WHERE student_id = ? LIMIT 1');
  $st->bind_param('s', $studentId);
  $st->execute();
  $exists = $st->get_result()->num_rows > 0;
  $st->close();
  $conn->close();

  if (!$exists) {
    http_response_code(404);
    exit('Student not found');
  }

  // CHANGE this path if your profile page lives elsewhere
  $dest = base_url() . '/ccdu/view_student.php?student_id=' . rawurlencode($studentId);
  header('Location: ' . abs_url($dest), true, 302);
  exit;
}

/* ---------- Legacy: 64-hex key maps to student_id ---------- */
if ($key !== '') {
  if (!preg_match('/^[a-f0-9]{64}$/i', $key)) {
    http_response_code(400);
    exit('Invalid key');
  }

  $conn = db($database_settings);
  $st = $conn->prepare('SELECT student_id FROM student_qr_keys WHERE qr_key = ? AND revoked = 0 LIMIT 1');
  $st->bind_param('s', $key);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  $conn->close();

  if (empty($row['student_id'])) {
    http_response_code(404);
    exit('Unknown/revoked key');
  }

  $dest = base_url() . '/ccdu/view_student.php?student_id=' . rawurlencode($row['student_id']);
  header('Location: ' . abs_url($dest), true, 302);
  exit;
}

/* ---------- Nothing provided ---------- */
http_response_code(400);
exit('Missing student_id');
