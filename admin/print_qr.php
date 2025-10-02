<?php
declare(strict_types=1);

// --- Runtime setup -----------------------------------------------------------
ini_set('display_errors', '0');   // don't corrupt image output
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// --- Helpers ----------------------------------------------------------------
/** Detect HTTPS reliably, including common reverse-proxy headers. */
function is_https(): bool {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
  if (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') return true;

  $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
  if ($xfp) {
    $proto = strtolower(trim(explode(',', $xfp)[0]));
    if ($proto === 'https') return true;
  }
  if (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on') return true;
  if (($_SERVER['HTTP_X_FORWARDED_PORT'] ?? '') === '443') return true;
  if (strtolower($_SERVER['REQUEST_SCHEME'] ?? '') === 'https') return true;

  return false;
}

/** Get/sanitize host for URL building. */
function get_host(): string {
  $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
  // allow letters, digits, dots, dashes, colons (and [] for IPv6)
  $host = preg_replace('/[^A-Za-z0-9\.\-\:\[\]]/', '', $host) ?? 'localhost';
  return $host;
}

/** Decode data URIs (svg/png) to raw bytes. */
function maybe_decode_data_uri(string $s): string {
  if (stripos($s, 'data:image/') !== 0) return $s;
  if (preg_match('#^data:image/(?:svg\+xml|png);base64,#i', $s, $m)) {
    return base64_decode(substr($s, strlen($m[0]))) ?: '';
  }
  return $s;
}

// --- Inputs -----------------------------------------------------------------
$studentId = $_GET['student_id'] ?? '';
$download  = isset($_GET['download']) ? (int)$_GET['download'] : 0;
$format    = strtolower($_GET['format'] ?? 'svg'); // 'svg' | 'png'

// Validate inputs
if ($studentId === '' || !preg_match('/^\d{4}-\d{4}$/', $studentId)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'missing/invalid student_id';
  exit;
}
if (!in_array($format, ['svg', 'png'], true)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'invalid format';
  exit;
}

// --- Build the URL to encode ------------------------------------------------
// If BASE_URL is a full URL (starts with http), prefer it; else detect scheme/host.
$scheme = is_https() ? 'https' : 'http';
$host   = get_host();

if (defined('BASE_URL') && is_string(constant('BASE_URL')) && preg_match('#^https?://#i', (string)BASE_URL)) {
  $baseOrigin = rtrim((string)BASE_URL, '/');
} else {
  $baseOrigin = $scheme.'://'.$host;
}

// Adjust the path if your app structure differs:
$targetPath = '/MoralMatrix/ccdu/view_student.php';
$query      = http_build_query(['student_id' => $studentId], '', '&', PHP_QUERY_RFC3986);
$resolveURL = $baseOrigin.$targetPath.'?'.$query;

// (Optional) Verify the student exists in DB here…

// --- Configure QR generation ------------------------------------------------
$useSvg = ($format === 'svg');

// Stronger ECC & clean rendering help physical scanners a lot
$options = new QROptions([
  'outputType'    => $useSvg ? QRCode::OUTPUT_MARKUP_SVG : QRCode::OUTPUT_IMAGE_PNG,
  'scale'         => 8,                 // bigger modules -> fewer decode errors
  'eccLevel'      => QRCode::ECC_H,     // was ECC_L (weak); H is robust
  'addQuietzone'  => true,
  'quietzoneSize' => 4,
  // If supported by your version, prevents "data:" output for images:
  'imageBase64'   => false,
]);

try{
  $binary = (new QRCode($options))->render($resolveURL);
} catch(Throwable $e){
  error_log('QR render error: '.$e->getMessage());
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'QR generation failed';
  exit;
}

// If library returned a data URI, decode to raw bytes/XML
$binary = maybe_decode_data_uri($binary);

// Always have an SVG copy on disk (predictable storage format)
if ($useSvg){
  $svgForDisk = $binary;
} else {
  $svgForDisk = (new QRCode(new QROptions([
    'outputType'    => QRCode::OUTPUT_MARKUP_SVG,
    'scale'         => 8,
    'eccLevel'      => QRCode::ECC_H,
    'addQuietzone'  => true,
    'quietzoneSize' => 4,
    'imageBase64'   => false,
  ])))->render($resolveURL);
  $svgForDisk = maybe_decode_data_uri($svgForDisk);
}

// Save SVG to disk
$qrDir = dirname(__DIR__).'/uploads/qrcodes';
if (!is_dir($qrDir)) {
  @mkdir($qrDir, 0755, true);
}
$file = $qrDir.'/'.$studentId.'.svg';
@file_put_contents($file, $svgForDisk);

// --- Stream the requested format --------------------------------------------
// Make sure response starts at byte 0
while (ob_get_level() > 0) { ob_end_clean(); }

// Security/caching
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($useSvg){
  header('Content-Type: image/svg+xml; charset=utf-8');
  if ($download === 1){
    header('Content-Disposition: attachment; filename="'.$studentId.'.svg"');
  }
  // Optional but nice: Content-Length
  header('Content-Length: '.strlen($binary));
  echo $binary; // raw SVG XML
} else {
  header('Content-Type: image/png');
  if ($download === 1){
    header('Content-Disposition: attachment; filename="'.$studentId.'.png"');
  }
  header('Content-Length: '.strlen($binary));
  echo $binary; // PNG bytes
}
exit;
