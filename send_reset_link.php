<?php
require 'config.php';
require __DIR__ . '/lib/email_lib.php'; // moralmatrix_mailer()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $conn = new mysqli(
        $database_settings['servername'],
        $database_settings['username'],
        $database_settings['password'],
        $database_settings['dbname']
    );

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT record_id FROM accounts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        header("Location: forgot_password.php?error=Email not found.");
        exit;
    }
    $stmt->close(); // ✅ CLOSE before reusing

    // Generate token
    $token = bin2hex(random_bytes(32));
    $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

    // Save token in database
    $stmt = $conn->prepare("UPDATE accounts SET reset_token=?, reset_expires=? WHERE email=?");
    $stmt->bind_param("sss", $token, $expires, $email);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        die("❌ Failed to save reset token. Check column names in DB.");
    }

    // Detect base URL dynamically (works on localhost + Hostinger)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    // Build full reset link
    $reset_link = $scheme . '://' . $host . $base . '/reset_password.php?token=' . urlencode($token);


    // Send email
    $subject = "Moral Matrix Password Reset";
    $body = "
    <p>Hello,</p>
    <p>Click the link below to reset your password (expires in 1 hour):</p>
    <p><a href='$reset_link'>$reset_link</a></p>
    <p>If you didn’t request this, you can ignore this email.</p>
    ";

    if (moralmatrix_send_mail($email, $subject, $body)) {
        header("Location: forgot_password.php?msg=Password reset link sent! Check your email.");
    } else {
        header("Location: forgot_password.php?error=Failed to send email. Check mail logs.");
    }
}
?>
