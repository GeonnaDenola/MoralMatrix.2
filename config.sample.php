<?php
// Copy to config.php and fill real values. DO NOT COMMIT config.php.
$database_settings = array(
    'servername' => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname' => 'moralmatrix',
);

define('BASE_URL', '/MoralMatrix');
return [
  'twilio' => [
    'sid'   => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'token' => 'your_auth_token_here',
    'from'  => '+1XXXXXXXXXX'
  ],
];
