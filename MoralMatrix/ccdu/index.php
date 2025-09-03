<?php
require '../auth.php';
require_role('ccdu');
header("Location: dashboard.php");
exit();
?>