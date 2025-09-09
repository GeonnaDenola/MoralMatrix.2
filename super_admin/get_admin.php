<?php

include '../config.php';

$servername = $database_settings['servername'];
$username = $database_settings['username'];
$password = $database_settings['password'];
$dbname = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error){
        die("Connection failed: " .$conn->connect_error);
    }

//Fetch all admins

$sql = "SELECT record_id, admin_id, first_name, last_name, middle_name, mobile, email, photo FROM admin_account ORDER BY admin_id ASC";
$result = $conn->query($sql);

$admins = [];
    if($result &&  $result->num_rows > 0){
        while ($row = $result->fetch_assoc()){
            $admins[] = $row;
        }
    }

// Return JSON
header('Content-Type: application/json');
echo json_encode($admins);

$conn->close();
?>