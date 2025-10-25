<?php
// ccdu/approve_violation.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/notify.php';

$accountType = strtolower((string)($_SESSION['account_type'] ?? ''));
if ($accountType !== 'ccdu') {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$violationId = (int)($_POST['id'] ?? 0);
if ($violationId <= 0) {
    http_response_code(400);
    exit('Missing violation ID');
}

$sessionToken = $_SESSION['csrf_token'] ?? '';
$formToken    = $_POST['csrf_token'] ?? '';
if ($sessionToken === '' || $formToken === '' || !hash_equals($sessionToken, $formToken)) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$action = strtolower(trim((string)($_POST['action'] ?? 'approve')));
$statusMap = ['approve' => 'approved', 'reject' => 'rejected'];
if (!array_key_exists($action, $statusMap)) {
    http_response_code(400);
    exit('Unsupported action');
}
$newStatus = $statusMap[$action];

$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);

if ($conn->connect_error) {
    http_response_code(500);
    exit('Connection failed: ' . $conn->connect_error);
}

$meta = null;
$metaStmt = $conn->prepare("
    SELECT student_id, submitted_by, submitted_role, offense_category, offense_type
    FROM student_violation
    WHERE violation_id = ?
    LIMIT 1
");
if ($metaStmt) {
    $metaStmt->bind_param('i', $violationId);
    $metaStmt->execute();
    $result = $metaStmt->get_result();
    $meta = $result ? $result->fetch_assoc() : null;
    $metaStmt->close();
}

if (!$meta) {
    $conn->close();
    http_response_code(404);
    exit('Violation not found');
}

$updateStmt = $conn->prepare("UPDATE student_violation SET status = ? WHERE violation_id = ?");
if (!$updateStmt) {
    $conn->close();
    http_response_code(500);
    exit('Failed to prepare update statement');
}
$updateStmt->bind_param('si', $newStatus, $violationId);

if (!$updateStmt->execute()) {
    $updateStmt->close();
    $conn->close();
    http_response_code(500);
    exit('Unable to update status');
}
$updateStmt->close();

$submitterRoleRaw = strtolower((string)($meta['submitted_role'] ?? ''));
$roleMap = [
    'faculty'         => 'faculty',
    'faculty_member'  => 'faculty',
    'security'        => 'security',
    'security_guard'  => 'security',
    'security_officer'=> 'security',
];
$audienceRole = $roleMap[$submitterRoleRaw] ?? null;
$submitterId  = (string)($meta['submitted_by'] ?? '');
$studentId    = (string)($meta['student_id'] ?? '');

if ($submitterId !== '' && $audienceRole !== null) {
    $title = $newStatus === 'approved'
        ? 'Your violation report was approved'
        : 'Your violation report was rejected';
    $type  = $newStatus === 'approved' ? 'success' : 'warning';

    $details = [];
    if ($studentId !== '') {
        $details[] = 'Student ID: ' . $studentId;
    }
    if (!empty($meta['offense_type'])) {
        $details[] = 'Offense: ' . $meta['offense_type'];
    } elseif (!empty($meta['offense_category'])) {
        $details[] = 'Category: ' . ucfirst((string)$meta['offense_category']);
    }
    $body = implode(' | ', $details);

    $targetPath = $audienceRole . '/view_student.php?student_id=' . urlencode($studentId) . '#v' . $violationId;

    // Build correct absolute URL for both local and Hostinger
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';

    if (defined('BASE_URL') && strpos(BASE_URL, 'http') === 0) {
        // BASE_URL is already a full domain (Hostinger)
        $url = rtrim(BASE_URL, '/') . '/' . ltrim($targetPath, '/');
    } elseif (defined('BASE_URL')) {
        // Localhost with folder like /MoralMatrix
        $url = $scheme . $host . rtrim(BASE_URL, '/') . '/' . ltrim($targetPath, '/');
    } else {
        // Fallback absolute path
        $url = $scheme . $host . '/' . ltrim($targetPath, '/');
    }


    try {
        Notify::create($conn, [
            'target_role'    => $audienceRole,
            'target_user_id' => $submitterId,
            'type'           => $type,
            'title'          => $title,
            'body'           => $body,
            'url'            => $url,
            'violation_id'   => $violationId,
            'created_by'     => $_SESSION['actor_id'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('Notify::create failed in approve_violation.php: ' . $e->getMessage());
    }
}

$conn->close();

header('Location: pending_reports.php?msg=' . urlencode($newStatus));
exit;
