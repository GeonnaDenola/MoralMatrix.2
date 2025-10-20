<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/../config.php';

$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$role = strtolower($_SESSION['account_type'] ?? '');
if (!in_array($role, ['ccdu','administrator','super_admin'])) {
  http_response_code(403);
  die("Unauthorized access");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['student_id'])) {
  $student_id = trim($_POST['student_id']);

  // Make sure a tracking table exists
  $conn->query("CREATE TABLE IF NOT EXISTS community_service_completed (
    student_id VARCHAR(32) PRIMARY KEY,
    completed_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )");

  // Record completion
  $stmt = $conn->prepare("REPLACE INTO community_service_completed (student_id) VALUES (?)");
  $stmt->bind_param("s", $student_id);
  $stmt->execute();
  $stmt->close();

  $_SESSION['flash'] = "Student $student_id marked as complete.";
  header("Location: community_service.php");
  exit;
}

http_response_code(400);
echo "Invalid request.";
