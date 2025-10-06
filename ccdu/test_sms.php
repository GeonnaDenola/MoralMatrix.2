<?php
require '../vendor/autoload.php'; // Twilio SDK
use Twilio\Rest\Client;

$sid   = getenv('TWILIO_TEST_SID') ?: '';
$token = getenv('TWILIO_TEST_TOKEN') ?: '';

// Twilio magic test number for successful create
$from = 'TWILIO_FROM_REDACTED';
$to   = 'TWILIO_FROM_REDACTED';

// mask debug helper
function mask($s){ if (!$s) return '(empty)'; $n=strlen($s); return str_repeat('*', max(0,$n-4)) . substr($s,-4); }
error_log("test_sms.php - using SID=".mask($sid)." TOKEN=".mask($token)." FROM={$from} TO={$to}");

$client = new Client($sid, $token);
try {
    $sms = $client->messages->create($to, [
        'from' => $from,
        'body' => "Hello! This is a Twilio test SMS for the Moral Matrix system."
    ]);
    echo "✅ Test message created successfully! SID: " . htmlspecialchars($sms->sid);
} catch (\Twilio\Exceptions\RestException $e) {
    // Twilio REST errors are more structured
    error_log("Twilio REST error: {$e->getCode()} - " . $e->getMessage());
    echo "❌ Twilio REST error: " . htmlspecialchars($e->getMessage());
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo "❌ Error sending test SMS: " . htmlspecialchars($e->getMessage());
}
