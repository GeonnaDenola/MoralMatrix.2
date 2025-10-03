<?php
include '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error){
   die("Connection failed: " .$conn->connect_error);
}

$sql = "SELECT record_id, student_id, first_name, middle_name, last_name, mobile, email, photo, institute, course, level, section, guardian, guardian_mobile
         FROM student_account";
$result = $conn->query($sql);

$students = [];
   if($result && $result->num_rows > 0){
      while ($row = $result->fetch_assoc()){
         $students[] = $row;
      }
   }

//return json
header('Content-Type: application/json');
echo json_encode($students);

$conn->close();

?>