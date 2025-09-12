<?php
require '../auth.php';
require_role('faculty');
header("Location: dashboard.php");
exit();
?>