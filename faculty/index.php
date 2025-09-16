<?php
session_start();

require '../auth.php';
require_role('faculty');
header("Location: dashboard.php");
exit();
?>