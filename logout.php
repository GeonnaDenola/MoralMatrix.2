<?php
session_start();  
session_unset();
session_destroy();

header("Location: /MoralMatrix/home.php");
exit(); 
?>