<?php
session_start();

// Check if logged in AND has the correct role
if (!empty($_SESSION['account_type']) && $_SESSION['account_type'] === 'super_admin') {
    header('Location: dashboard.php');
    exit();
}

// 🚫 Not logged in or wrong role — go to login
header('Location: ../login.php');
exit();
