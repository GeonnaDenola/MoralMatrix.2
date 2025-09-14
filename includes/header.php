<?php
session_start(); // keep session active for logout handling
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moral Matrix</title>
    <style>
        body { margin:0; font-family: Arial, sans-serif; }
        header {
            background:#2c3e50;
            color:white;
            padding:10px 20px;
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        header a.logo {
            font-size:20px;
            font-weight:bold;
            text-decoration:none;
            color:white;
        }
        header form {
            margin:0;
        }
        header button {
            background:#e74c3c;
            color:white;
            border:none;
            padding:8px 15px;
            border-radius:5px;
            cursor:pointer;
        }
        header button:hover {
            background:#c0392b;
        }
    </style>
</head>
<body>
<header>
    <a href="/dashboard.php" class="logo">MORAL MATRIX</a>
    <form action="../logout.php" method="post">
        <button type="submit" name="logout">Logout</button>
    </form>
</header>
