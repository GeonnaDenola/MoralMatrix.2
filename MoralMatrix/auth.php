<?php
session_start();

function require_role($role) {
    if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== $role) {
        header("Location: /login.php");
        exit();
    }
}
