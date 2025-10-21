<?php
// Copy to config.php and fill real values. DO NOT COMMIT config.php.
$database_settings = array(
    'servername' => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname' => 'moralmatrix',
);

define('BASE_URL', '/MoralMatrix');

// Normalize the base path so that redirects work whether the app lives at root or in a sub-folder.
$baseUrlNormalized = rtrim((string)BASE_URL, '/');
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $baseUrlNormalized);
}

// Expose a scalar for legacy includes that expect $basePath from config.
$basePath = BASE_PATH;

if (!function_exists('mm_base_uri')) {
    /**
     * Prefix a relative path with the configured base.
     */
    function mm_base_uri(string $relative = ''): string
    {
        $suffix = '/' . ltrim($relative, '/');
        $base   = defined('BASE_PATH') ? (string)BASE_PATH : '';
        return $base === '' ? $suffix : $base . $suffix;
    }
}

return [
  'twilio' => [
    'sid'   => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'token' => 'your_auth_token_here',
    'from'  => '+1XXXXXXXXXX'
  ],
];
