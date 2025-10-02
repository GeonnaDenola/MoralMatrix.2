<?php
require '../auth.php';
require_role('student');
header("Location: dashboard.php");
exit();
?>