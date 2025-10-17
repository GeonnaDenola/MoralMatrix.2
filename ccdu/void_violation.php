<?php
session_start();
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// ==== AUTHORIZATION ====
if (empty($_SESSION['record_id']) || empty($_SESSION['account_type'])) {
    http_response_code(403);
    exit('Not authenticated');
}

$role = strtolower($_SESSION['account_type']);
if ($role !== 'ccdu') {
    http_response_code(403);
    exit('Not authorized: Only CCDU can void violations');
}

// ==== CSRF PROTECTION ====
if (empty($_POST['csrf']) || empty($_SESSION['csrf_token']) || $_POST['csrf'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

// ==== CONNECT TO DATABASE ====
$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);
if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed: ' . $conn->connect_error);
}

// ==== COLLECT INPUTS ====
$violation_id = (int)($_POST['violation_id'] ?? 0);
$student_id   = trim($_POST['student_id'] ?? '');
$void_reason  = trim($_POST['void_reason'] ?? 'Voided by CCDU');
$returnUrl    = $_POST['return'] ?? 'view_student.php?student_id=' . urlencode($student_id);

if ($violation_id <= 0 || $student_id === '') {
    header("Location: $returnUrl&void_status=fail&msg=Missing+parameters");
    exit;
}

// ==== VERIFY VIOLATION EXISTS ====
$stmt = $conn->prepare("SELECT violation_id, is_void FROM student_violation WHERE violation_id = ? AND student_id = ?");
$stmt->bind_param("is", $violation_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$violation = $result->fetch_assoc();
$stmt->close();

if (!$violation) {
    header("Location: $returnUrl&void_status=fail&msg=Violation+not+found");
    exit;
}

if ((int)$violation['is_void'] === 1) {
    header("Location: $returnUrl&void_status=fail&msg=Already+voided");
    exit;
}

// ==== PERFORM VOID ====
$user_id = $_SESSION['record_id'];

$sql = "UPDATE student_violation
        SET is_void = 1,
            void_reason = ?,
            voided_by = ?,
            voided_at = NOW(),
            status = 'rejected',
            updated_at = NOW()
        WHERE violation_id = ? AND student_id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    header("Location: $returnUrl&void_status=fail&msg=SQL+prepare+error");
    exit;
}

$stmt->bind_param("ssis", $void_reason, $user_id, $violation_id, $student_id);

if ($stmt->execute()) {
    $stmt->close();

    // ==== RECOMPUTE COMMUNITY SERVICE HOURS ====
    require_once '../lib/violation_hrs.php';

    // Recalculate based on remaining valid (non-voided) violations
    $remaining = communityServiceRemaining($conn, $student_id);

    // Optional: If you keep a record of hours in a table like `student_record`
    // you can update it automatically here:
    if ($remaining !== null) {
        $upd = $conn->prepare("UPDATE student_record SET remaining_hours = ? WHERE student_id = ?");
        if ($upd) {
            $upd->bind_param("ds", $remaining, $student_id);
            $upd->execute();
            $upd->close();
        }
    }

    header("Location: $returnUrl&void_status=ok&msg=Violation+voided+successfully");
    exit;
} else {
    $msg = urlencode("Database error: " . $stmt->error);
    $stmt->close();
    header("Location: $returnUrl&void_status=fail&msg=$msg");
    exit;
}
