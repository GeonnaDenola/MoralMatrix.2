<?php

session_unset();
session_destroy();

header("Location: /MoralMatrix/home.php");
exit(); 
?>