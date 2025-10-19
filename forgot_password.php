<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moral Matrix | Forgot Password</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .forgot-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 0 12px rgba(0,0,0,0.1);
            width: 350px;
            text-align: center;
        }
        .forgot-container h2 {
            margin-bottom: 1rem;
            color: #333;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        button {
            background-color: #007bff;
            border: none;
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
        }
        button:hover { background-color: #0056b3; }
        .back-link {
            display: block;
            margin-top: 15px;
            color: #555;
            text-decoration: none;
        }
        .message {
            margin-top: 10px;
            color: green;
        }
        .error {
            margin-top: 10px;
            color: red;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <h2>Forgot Password</h2>
        <form action="send_reset_link.php" method="POST">
            <input type="email" name="email" placeholder="Enter your registered email" required>
            <button type="submit">Send Reset Link</button>
        </form>
        <a class="back-link" href="login.php">Back to Login</a>

        <?php if (isset($_GET['msg'])): ?>
            <p class="message"><?= htmlspecialchars($_GET['msg']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <p class="error"><?= htmlspecialchars($_GET['error']) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
