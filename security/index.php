<?php
session_start();

require '../auth.php';
require_role('security');
header("Location: dashboard.php");
exit();
?>