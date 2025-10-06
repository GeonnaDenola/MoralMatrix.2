<?php
$database_settings = array(
    'servername' => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname' => 'moralmatrix',
);

define('BASE_URL', '/MoralMatrix');

$smtp = [
  'host'       => 'smtp.gmail.com',
  'port'       => 587,                        // 587=STARTTLS, 465=SMTPS
  'user'       => 'geodenola@gmail.com',  // your mailbox
  'pass'       => 'oftnofxewexyqcfa',        // Gmail App Password (not normal pwd)
  'from'       => 'geodenola@gmail.com',  // usually same as user
  'from_name'  => 'Moral Matrix',
  // Optional while testing:
  'reply_to'       => null,
  'reply_to_name'  => null,
  'debug'      => 2, // set to 0 after testing
];

$twilio_settings = [
  'twilio_sid' => 'TWILIO_SID_REDACTED',
  'twilio_token' => 'TWILIO_TOKEN_REDACTED',
  'twilio_from' => '+15005550006',
];
?>