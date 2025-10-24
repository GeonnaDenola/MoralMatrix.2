<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function require_role(string $role): void {
    if (empty($_SESSION['account_type']) || $_SESSION['account_type'] !== $role) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}
