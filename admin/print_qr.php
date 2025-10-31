<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/* ---------------- Helper Functions ---------------- */
function is_https(): bool {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
  if (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') return true;
  if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return true;
  if (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on') return true;
  if (($_SERVER['HTTP_X_FORWARDED_PORT'] ?? '') === '443') return true;
  if (strtolower($_SERVER['REQUEST_SCHEME'] ?? '') === 'https') return true;
  return false;
}

function get_host(): string {
  $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
  return preg_replace('/[^A-Za-z0-9\.\-\:\[\]]/', '', $host) ?: 'localhost';
}

function maybe_decode_data_uri(string $s): string {
  if (stripos($s, 'data:image/') !== 0) return $s;
  if (preg_match('#^data:image/(?:svg\+xml|png);base64,#i', $s, $m)) {
    return base64_decode(substr($s, strlen($m[0]))) ?: '';
  }
  return $s;
}

/* ---------------- Input Validation ---------------- */
$studentId = $_GET['student_id'] ?? '';
$download  = isset($_GET['download']) ? (int)$_GET['download'] : 0;
$format    = strtolower($_GET['format'] ?? 'svg');

if ($studentId === '' || !preg_match('/^\d{4}-\d{4}$/', $studentId)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Missing or invalid student_id');
}

if (!in_array($format, ['svg', 'png'], true)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Invalid format');
}

/* ---------------- Build Correct URL ---------------- */
session_start(); // optional — you can keep or remove this, it doesn’t affect the QR

$scheme = is_https() ? 'https' : 'http';
$host   = get_host();
$base   = defined('BASE_URL') ? trim(BASE_URL) : '';

// Normalize domain and base path
if (preg_match('#^https?://#i', $base)) {
  $root = rtrim($base, '/');
} else {
  $root = $scheme . '://' . rtrim($host, '/');
  if ($base !== '') {
    $root .= '/' . ltrim($base, '/');
  }
}

// ✅ Only this line should remain:
$query = http_build_query(['student_id' => $studentId], '', '&', PHP_QUERY_RFC3986);
$resolveURL = rtrim($root, '/') . '/qr.php?' . $query;

/* ---------------- Generate QR Code ---------------- */
$useSvg = ($format === 'svg');
$options = new QROptions([
  'outputType'    => $useSvg ? QRCode::OUTPUT_MARKUP_SVG : QRCode::OUTPUT_IMAGE_PNG,
  'scale'         => 8,
  'eccLevel'      => QRCode::ECC_H,
  'addQuietzone'  => true,
  'quietzoneSize' => 4,
  'imageBase64'   => false,
]);

try {
  $binary = (new QRCode($options))->render($resolveURL);
} catch (Throwable $e) {
  error_log('QR render error: ' . $e->getMessage());
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  exit('QR generation failed');
}

$binary = maybe_decode_data_uri($binary);

// ✅ Always save to project_root/uploads/qrcodes
$qrDir = dirname(__DIR__) . '/uploads/qrcodes';

if (!is_dir($qrDir)) {
    if (!mkdir($qrDir, 0755, true) && !is_dir($qrDir)) {
        throw new RuntimeException("❌ Cannot create directory: $qrDir");
    }
}

$file = $qrDir . '/' . $studentId . '.svg';

// Save the SVG file
if (file_put_contents($file, $binary) === false) {
    throw new RuntimeException("❌ Failed to save QR SVG to: $file");
}

error_log("✅ QR SVG saved to: " . realpath($file));


/* ---------------- Output ---------------- */
while (ob_get_level() > 0) ob_end_clean();

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($useSvg) {
  header('Content-Type: image/svg+xml; charset=utf-8');
  if ($download === 1) {
    header('Content-Disposition: attachment; filename="' . $studentId . '.svg"');
  }
  echo $binary;
} else {
  header('Content-Type: image/png');
  if ($download === 1) {
    header('Content-Disposition: attachment; filename="' . $studentId . '.png"');
  }
  echo $binary;
}
exit;
