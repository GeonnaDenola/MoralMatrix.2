<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';


function moralmatrix_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();

    $mail->Host         = getenv('MORALMATRIX_SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth     = true;
    $mail->Username     = getenv('MORALMATRIX_SMTP_USER') ?: 'moralmatrix01@gmail.com';
    $mail->Password     = getenv('MORALMATRIX_SMTP_PASS') ?: 'ikwbepbmlbgiwmof';
    $mail->SMTPSecure   = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port         = (int) (getenv('MORALMATRIX_SMTP_PORT') ?: 587);

    $mail->setFrom(getenv('MORALMATRIX_FROM_EMAIL') ?: 'moralmatrix01@gmail.com', getenv('MORALMATRIX_FROM_NAME') ?: 'Moral Matrix');
    $mail->isHTML(true);
    return $mail;
}
?>