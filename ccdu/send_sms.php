<?php
// send_violation_whatsapp.php
// Sends a WhatsApp message to the guardian using Twilio's WhatsApp Sandbox
// Requirements:
//  - ../config.php should contain $database_settings and $twilio_settings arrays
//  - Twilio sandbox must be active and joined by the guardian
//  - composer autoload must include Twilio SDK

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;

/* ----------------- TWILIO CONFIGURATION ----------------- */
$sid   = $twilio_settings['twilio_sid']   ?? getenv('TWILIO_SID');
$token = $twilio_settings['twilio_token'] ?? getenv('TWILIO_TOKEN');
$from  = $twilio_settings['twilio_whatsapp_from'] ?? 'whatsapp:+14155238886'; // Twilio Sandbox number

if (!$sid || !$token || !$from) {
    error_log("Twilio WhatsApp credentials not configured properly.");
    http_response_code(500);
    echo "Server configuration error.";
    exit;
}

/* ----------------- INPUT VALIDATION ----------------- */
$studentIdRaw   = $_POST['student_id']   ?? $_GET['student_id']   ?? '';
$violationIdRaw = $_POST['violation_id'] ?? $_GET['violation_id'] ?? '';

$studentId   = trim($studentIdRaw);
$violationId = is_numeric($violationIdRaw) ? (int)$violationIdRaw : null;

if ($studentId === '' || $violationId === null) {
    http_response_code(400);
    echo "Invalid parameters.";
    exit;
}

/* ----------------- FETCH GUARDIAN + VIOLATION DATA ----------------- */
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

/* ----------------- SANITIZE AND VALIDATE FIELDS ----------------- */
$guardianName      = trim($data['guardian'] ?? '');
$guardianMobileRaw = trim($data['guardian_mobile'] ?? '');
$offenseCategory   = $data['offense_category'] ?? '';
$offenseType       = $data['offense_type'] ?? '';
$reportedAt        = $data['reported_at'] ?? null;

if (!$guardianMobileRaw || !$guardianName || !$reportedAt) {
    error_log("Missing DB fields for violation_id={$violationId}");
    http_response_code(500);
    echo "Server error.";
    exit;
}

/* ----------------- NORMALIZE TO +63 FORMAT ----------------- */
function normalizePHMobile($raw) {
    $clean = preg_replace('/[^\d\+]/', '', $raw);
    if (strpos($clean, '+') === 0) $clean = substr($clean, 1);
    if (strpos($clean, '63') === 0) return '+' . $clean;
    if (strpos($clean, '0') === 0) return '+63' . substr($clean, 1);
    if (strpos($clean, '9') === 0) return '+63' . $clean;
    return false;
}
$toNumber = normalizePHMobile($guardianMobileRaw);
if (!$toNumber) {
    http_response_code(400);
    echo "Invalid guardian mobile.";
    exit;
}

/* ----------------- FORMAT DATE ----------------- */
try {
    $dt = new DateTime($reportedAt, new DateTimeZone('UTC'));
} catch (Exception $e) {
    try {
        $dt = new DateTime($reportedAt);
    } catch (Exception $e2) {
        error_log("Invalid reported_at for violation_id={$violationId}");
        http_response_code(500);
        echo "Server error.";
        exit;
    }
}
$dt->setTimezone(new DateTimeZone('Asia/Manila'));
$datePretty = $dt->format('M d, Y h:i A');

/* ----------------- MESSAGE CONTENT ----------------- */
$message = sprintf(
    "Dear %s, this is the Center for Character Development Unit (Disciplinary Office) of Mabalacat City College. "
    . "This message is to inform you that your child was reported for a %s (%s) on %s. "
    . "Kindly visit the office within the week for further discussion. Thank you.",
    $guardianName,
    $offenseCategory,
    $offenseType,
    $datePretty
);

/* ----------------- SEND WHATSAPP MESSAGE ----------------- */
$client = new Client($sid, $token);

try {
    $msg = $client->messages->create(
        "whatsapp:" . $toNumber,
        [
            'from' => $from, // 'whatsapp:+14155238886' sandbox
            'body' => $message
        ]
    );

    error_log("WhatsApp sent: SID={$msg->sid}, To={$toNumber}, Status={$msg->status}");
    $status = 'success';

} catch (RestException $e) {
    error_log("❌ Twilio WhatsApp REST error: " . $e->getMessage());
    $status = 'failed';
} catch (Exception $e) {
    error_log("❌ Twilio WhatsApp general error: " . $e->getMessage());
    $status = 'failed';
}

/* ----------------- REDIRECT BACK ----------------- */
$redirect = 'view_student.php?student_id=' . rawurlencode($studentId) . '&wa_status=' . rawurlencode($status);
header("Location: {$redirect}");
exit;
?>
