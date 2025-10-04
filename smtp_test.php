<?php
require __DIR__.'/vendor/autoload.php';
require __DIR__.'/config.php';       // loads $smtp
require __DIR__.'/lib/email_lib.php';

$mail = moralmatrix_mailer();        // uses $smtp
$mail->addAddress('geonnadenola@gmail.com', 'SMTP Test');
$mail->Subject = 'PHPMailer SMTP smoke test';
$mail->Body    = '<p>If you can read this, SMTP works.</p>';
$mail->AltBody = 'If you can read this, SMTP works.';

try {
  $mail->send();
  echo 'SENT';
} catch (Throwable $e) {
  echo 'ERROR: '.$mail->ErrorInfo;
}
?>