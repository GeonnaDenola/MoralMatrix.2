<?php
session_start();

// If the user is already logged in, go to dashboard
if (!empty($_SESSION['validator_id'])) {
    header('Location: dashboard.php');
    exit();
}

// If not logged in, send them to login
header('Location: login.php');
exit();
