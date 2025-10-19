<?php
require 'config.php';

$token = $_GET['token'] ?? '';

$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Check token validity
$stmt = $conn->prepare("SELECT record_id, email FROM accounts WHERE reset_token=? AND reset_expires > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<p style='color:red; text-align:center;'>Invalid or expired token.</p>");
}

$user = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE accounts SET password=?, reset_token=NULL, reset_expires=NULL
    WHERE record_id=?");
    $stmt->bind_param("si", $new_pass, $user['record_id']);
    $stmt->execute();

    echo "<p style='color:green; text-align:center;'>Password updated successfully. <a href='login.php'>Login here</a>.</p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | Moral Matrix</title>
    <style>
        body { font-family: Arial; background: #f4f6f8; display:flex; align-items:center; justify-content:center; height:100vh; }
        form { background:#fff; padding:2rem; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1); width:350px; }
        input { width:100%; padding:10px; margin-bottom:10px; border:1px solid #ccc; border-radius:8px; }
        button { width:100%; padding:10px; background:#007bff; color:white; border:none; border-radius:8px; }
        button:hover { background:#0056b3; }
    </style>
</head>
<body>
    <form method="POST">
        <h2>Reset Your Password</h2>
        <input type="password" name="password" placeholder="Enter new password" required minlength="6">
        <button type="submit">Update Password</button>
    </form>
</body>
</html>
