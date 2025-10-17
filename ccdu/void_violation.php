<?php
session_start();
require_once '../config.php';
require_once 'violation_hrs.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $violation_id = $_POST['violation_id'] ?? null;
    $student_id   = $_POST['student_id'] ?? null;
    $reason       = trim($_POST['void_reason'] ?? '');
    $returnUrl    = $_POST['return_url'] ?? '../ccdu/view_student.php?student_id=' . urlencode($student_id);

    // Ensure only CCDU can void
    if (empty($_SESSION['account_type']) || strtolower($_SESSION['account_type']) !== 'ccdu') {
        header("Location: $returnUrl&void_status=fail&msg=Unauthorized+Access");
        exit;
    }

    // Get CCDU user ID (or username)
    $voided_by = $_SESSION['record_id'] ?? 'Unknown';

    if (!$violation_id || !$student_id) {
        header("Location: $returnUrl&void_status=fail&msg=Missing+required+fields");
        exit;
    }

    $conn = new mysqli(
        $database_settings['servername'],
        $database_settings['username'],
        $database_settings['password'],
        $database_settings['dbname']
    );

    if ($conn->connect_error) {
        header("Location: $returnUrl&void_status=fail&msg=DB+connection+error");
        exit;
    }

    // ✅ Update violation to mark as voided
    $sql = "UPDATE student_violation
            SET status = 'voided',
                is_void = 1,
                void_reason = ?,
                voided_by = ?,
                voided_at = NOW()
            WHERE violation_id = ? AND student_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        header("Location: $returnUrl&void_status=fail&msg=Failed+to+prepare+query");
        exit;
    }

    $stmt->bind_param("ssis", $reason, $voided_by, $violation_id, $student_id);

    if ($stmt->execute()) {
        $stmt->close();

        // ✅ Recompute remaining hours dynamically
        $remaining = communityServiceRemaining($conn, $student_id);

        // Redirect back to the student's record
        header("Location: $returnUrl&void_status=ok&msg=Violation+voided+successfully");
        exit;
    } else {
        $stmt->close();
        header("Location: $returnUrl&void_status=fail&msg=Failed+to+void+violation");
        exit;
    }
}
?>
