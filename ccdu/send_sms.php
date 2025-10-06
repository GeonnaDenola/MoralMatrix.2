<?php
// send_violation_sms.php
// Requirements:
//  - ../config.php should contain DB settings in $database_settings array
//  - Twilio credentials & FROM number should be set in environment variables
//    e.g. export TWILIO_SID=..., TWILIO_TOKEN=..., TWILIO_FROM=+1202555XXXX
//  - composer autoload must be available

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;

// ----------------- Configuration -----------------
$sid = $twilio_settings['twilio_sid']   ?? getenv('TWILIO_SID');
$token = $twilio_settings['twilio_token'] ?? getenv('TWILIO_TOKEN');
$from = $twilio_settings['twilio_from']  ?? getenv('TWILIO_FROM');

if (!$sid || !$token || !$from) {
    // Don't leak specifics in production — log instead.
    error_log("Twilio credentials or FROM number not configured.");
    http_response_code(500);
    echo "Server configuration error.";
    exit;
}

// ----------------- Inputs & Validation -----------------
$studentIdRaw   = $_GET['student_id']   ?? '';
$violationIdRaw = $_GET['violation_id'] ?? '';

// Validate numeric IDs (cast to int)
$studentId   = filter_var($studentIdRaw, FILTER_VALIDATE_INT);
$violationId = filter_var($violationIdRaw, FILTER_VALIDATE_INT);

if ($studentId === false || $violationId === false) {
    http_response_code(400);
    echo "Invalid parameters.";
    exit;
}

// ----------------- DB: fetch violation + guardian -----------------
$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);

if ($conn->connect_error) {
    error_log("DB connect error: " . $conn->connect_error);
    http_response_code(500);
    echo "Server error.";
    exit;
}

$sql = "SELECT v.offense_category, v.offense_type, v.reported_at,
               s.guardian, s.guardian_mobile
        FROM student_violation v
        JOIN student_account s ON v.student_id = s.student_id
        WHERE v.violation_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("DB prepare failed: " . $conn->error);
    $conn->close();
    http_response_code(500);
    echo "Server error.";
    exit;
}
$stmt->bind_param("i", $violationId);
$stmt->execute();
$result = $stmt->get_result();
$data = $result ? $result->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!$data) {
    http_response_code(404);
    echo "No data found.";
    exit;
}

// Ensure required fields exist
$guardianName = trim($data['guardian'] ?? '');
$guardianMobileRaw = trim($data['guardian_mobile'] ?? '');
$offenseCategory = $data['offense_category'] ?? '';
$offenseType = $data['offense_type'] ?? '';
$reportedAt = $data['reported_at'] ?? null;

if (!$guardianMobileRaw || !$guardianName || !$reportedAt) {
    error_log("Missing DB fields for violation_id={$violationId}");
    http_response_code(500);
    echo "Server error.";
    exit;
}

// ----------------- Normalize phone number to +63XXXXXXXXX -----------------
function normalizePHMobile($raw) {
    // Remove non-digits and plus
    $clean = preg_replace('/[^\d\+]/', '', $raw);

    // If it starts with a +, keep it temporarily
    if (strpos($clean, '+') === 0) {
        $clean = substr($clean, 1);
    }

    // Now $clean has only digits. Handle these cases:
    // - starts with 63  -> +63...
    // - starts with 0   -> 0xxxx -> +63xxxx (strip leading 0)
    // - starts with 9   -> local mobile w/o leading 0 -> +63...
    // - otherwise return false
    if (strpos($clean, '63') === 0) {
        return '+' . $clean;
    } elseif (strpos($clean, '0') === 0) {
        // drop leading 0
        return '+63' . substr($clean, 1);
    } elseif (strpos($clean, '9') === 0 && strlen($clean) >= 9) {
        return '+63' . $clean;
    } else {
        return false;
    }
}

$to = normalizePHMobile($guardianMobileRaw);
if (!$to) {
    error_log("Invalid guardian mobile for violation_id={$violationId}: raw={$guardianMobileRaw}");
    http_response_code(400);
    echo "Invalid guardian mobile.";
    exit;
}

// ----------------- Format date in Asia/Manila explicitly -----------------
try {
    $dt = new DateTime($reportedAt, new DateTimeZone('UTC'));
} catch (Exception $e) {
    // if DB stores local time already, try without UTC
    try {
        $dt = new DateTime($reportedAt);
    } catch (Exception $e2) {
        error_log("Invalid reported_at for violation_id={$violationId}: {$reportedAt}");
        http_response_code(500);
        echo "Server error.";
        exit;
    }
}
$dt->setTimezone(new DateTimeZone('Asia/Manila'));
$datePretty = $dt->format('M d, Y h:i A'); // e.g., Oct 06, 2025 09:45 PM

// ----------------- Prepare SMS message (be mindful of length) -----------------
$message = sprintf(
    "Dear %s, your child has committed a violation (%s - %s) on %s. Please come to school for a meeting.",
    $guardianName,
    $offenseCategory,
    $offenseType,
    $datePretty
);

// ----------------- Send SMS via Twilio -----------------
$twilio = new Client($sid, $token);

try {
    $sms = $twilio->messages->create($to, [
        'from' => $from,
        'body' => $message
    ]);
    $status = 'success';
} catch (RestException $e) {
    // Twilio REST exceptions
    error_log("Twilio RestException: " . $e->getMessage());
    $status = 'failed';
} catch (Exception $e) {
    // Generic fallback
    error_log("Twilio exception: " . $e->getMessage());
    $status = 'failed';
}

// ----------------- Redirect back (sanitized) -----------------
$redirect = 'view_student.php?student_id=' . rawurlencode($studentId) . '&sms_status=' . rawurlencode($status);
header("Location: {$redirect}");
exit;
