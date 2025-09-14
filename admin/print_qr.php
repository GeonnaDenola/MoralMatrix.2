<?php
declare(strict_types=1);

// Turn off error display to avoid corrupting image output
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

$studentId = $_GET['student_id'] ?? '';
$download  = isset($_GET['download']) ? (int)$_GET['download'] : 0;
$format    = strtolower($_GET['format'] ?? 'svg'); // svg|png

if ($studentId === '' || !preg_match('/^\d{4}-\d{4}$/', $studentId)) {
  http_response_code(400);
  exit('missing/invalid student_id');
}

// (optional) verify student exists... (your DB code here)

// Build URL to encode
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';   // no /admin here
$resolveURL = $scheme . '://' . $host . $base . '/qr.php?student_id=' . rawurlencode($studentId);


// Configure QR
$useSvg  = ($format !== 'png');
$options = new QROptions([
  'outputType' => $useSvg ? QRCode::OUTPUT_MARKUP_SVG : QRCode::OUTPUT_IMAGE_PNG,
  'scale'      => 6,
  'eccLevel'   => QRCode::ECC_L,
]);

// Render once
$binary = (new QRCode($options))->render($resolveURL);

/* --- FIX: if the library (or anything upstream) returns a data URI,
   strip "data:image/svg+xml;base64," and decode to raw XML before saving/streaming --- */
if ($useSvg && strncasecmp($binary, 'data:image/svg+xml;base64,', 26) === 0) {
    $binary = base64_decode(substr($binary, 26));
}

/* --- ALWAYS save an SVG to disk (predictable on-disk format) --- */
$svgForDisk = $useSvg ? $binary : (new QRCode(new QROptions([
    'outputType' => QRCode::OUTPUT_MARKUP_SVG,
    'scale'      => 6,
    'eccLevel'   => QRCode::ECC_L,
])))->render($resolveURL);

/* Decode if that SVG also came back as data URI */
if (strncasecmp($svgForDisk, 'data:image/svg+xml;base64,', 26) === 0) {
    $svgForDisk = base64_decode(substr($svgForDisk, 26));
}

$qrDir = dirname(__DIR__).'/uploads/qrcodes';
if (!is_dir($qrDir)) { @mkdir($qrDir, 0777, true); }
$file  = $qrDir.'/'.$studentId.'.svg';
@file_put_contents($file, $svgForDisk);

/* --- Stream the requested format to the browser --- */
// Clean any buffered output so our response starts at byte 0
while (ob_get_level()) { ob_end_clean(); }

if ($useSvg) {
    header('Content-Type: image/svg+xml; charset=utf-8');
    if ($download === 1) header('Content-Disposition: attachment; filename="'.$studentId.'.svg"');
    echo $binary; // raw SVG XML (not data:)
} else {
    header('Content-Type: image/png');
    if ($download === 1) header('Content-Disposition: attachment; filename="'.$studentId.'.png"');
    echo $binary; // PNG bytes
}
exit;


// Headers
if ($useSvg) {
  header('Content-Type: image/svg+xml; charset=utf-8');
} else {
  header('Content-Type: image/png');
}
if ($download === 1) {
  header('Content-Disposition: attachment; filename="'.$studentId.($useSvg ? '.svg' : '.png').'"');
}

// Body
echo $binary;
exit;
