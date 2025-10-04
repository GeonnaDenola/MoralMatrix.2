<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function moralmatrix_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();

    $host = getenv('MORALMATRIX_SMTP_HOST') ?: 'smtp.gmail.com';
    $port = (int) (getenv('MORALMATRIX_SMTP_PORT') ?: 587);

    $mail->Host       = $host;
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('MORALMATRIX_SMTP_USER') ?: 'moralmatrix01@gmail.com';
    $mail->Password   = getenv('MORALMATRIX_SMTP_PASS') ?: 'ikwbepbmlbgiwmof'; // ← avoid hardcoding fallback secrets

    // Pick TLS mode based on port
    $mail->SMTPSecure = ($port === 465) ? PHPMailer::ENCRYPTION_SMTPS
                                        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $port;

    $fromEmail = getenv('MORALMATRIX_FROM_EMAIL') ?: $mail->Username;
    $fromName  = getenv('MORALMATRIX_FROM_NAME')  ?: 'Moral Matrix';
    $mail->setFrom($fromEmail, $fromName);

    // Optional: Reply-To
    if ($reply = getenv('MORALMATRIX_REPLY_TO')) {
        $mail->addReplyTo($reply, getenv('MORALMATRIX_REPLY_TO_NAME') ?: $fromName);
    }

    // Optional: enable debug via env while testing (0,1,2)
    $debug = (int)(getenv('MORALMATRIX_SMTP_DEBUG') ?: 0);
    if ($debug) $mail->SMTPDebug = $debug;

    $mail->isHTML(true);
    return $mail;
}
