<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function moralmatrix_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();

    // Prefer config.php's $smtp array; fall back to getenv()
    $cfg  = $GLOBALS['smtp'] ?? [];
    $host = $cfg['host']      ?? (getenv('MORALMATRIX_SMTP_HOST') ?: 'smtp.gmail.com');
    $port = (int)($cfg['port']?? (getenv('MORALMATRIX_SMTP_PORT') ?: 587));
    $user = $cfg['user']      ??  getenv('MORALMATRIX_SMTP_USER');
    $pass = $cfg['pass']      ??  getenv('MORALMATRIX_SMTP_PASS');

    $mail->Host     = $host;
    $mail->SMTPAuth = true;
    $mail->Username = (string)$user;
    $mail->Password = (string)$pass; // no hardcoded fallback

    $mail->SMTPSecure = ($port === 465) ? PHPMailer::ENCRYPTION_SMTPS
                                        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $port;

    $fromEmail = $cfg['from']      ?? (getenv('MORALMATRIX_FROM_EMAIL') ?: $mail->Username);
    $fromName  = $cfg['from_name'] ?? (getenv('MORALMATRIX_FROM_NAME')  ?: 'Moral Matrix');
    $mail->setFrom($fromEmail, $fromName);

    if (!empty($cfg['reply_to'])) {
        $mail->addReplyTo($cfg['reply_to'], $cfg['reply_to_name'] ?? $fromName);
    }

    // Optional debug to error_log while testing
    $debug = (int)($cfg['debug'] ?? (getenv('MORALMATRIX_SMTP_DEBUG') ?: 0));
    if ($debug) {
        $mail->SMTPDebug = $debug; // 2 = verbose
        $mail->Debugoutput = function($str, $level){ error_log("SMTP[$level] $str"); };
    }

    $mail->isHTML(true);

    if ($mail->Username === '' || $mail->Password === '') {
        error_log('SMTP user/password missing. Set in config.php ($smtp) or environment.');
    }

    return $mail;
}
?>