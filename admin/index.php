<?php
require '../auth.php';
require_role('administrator');
header("Location: dashboard.php");
exit();
?>
