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
  'user'       => 'moralmatrix01@gmail.com',  // your mailbox
  'pass'       => 'ikwbepbmlbgiwmof',        // Gmail App Password (not normal pwd)
  'from'       => 'moralmatrix01@gmail.com',  // usually same as user
  'from_name'  => 'Moral Matrix',
  // Optional while testing:
  'reply_to'       => null,
  'reply_to_name'  => null,
  'debug'      => 2, // set to 0 after testing
];
?>