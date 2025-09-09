<?php

include '../../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $id = intval($_POST['record_id']);
    $faculty_id = $_POST['faculty_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $mobile = $_POST['mobile'];
    $email = $_POST['email'];
    $institute = $_POST['institute'];

    $photo = "";
    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES["photo"]["type"], $allowedTypes)) {
            die("⚠️ Only JPG, PNG, GIF files are allowed.");
        }

        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $photo = time() . "_" . basename($_FILES["photo"]["name"]);
        $targetFile = $targetDir . $photo;

        if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
            die("⚠️ Error uploading photo.");
        } 
    }

    $conn->begin_transaction();

    try{
        if($photo){
            $stmt = $conn->prepare("UPDATE faculty_account
                SET faculty_id=?, first_name=?, last_name=?, mobile=?, email=?, institute=?, photo=?
                WHERE record_id=?");
            $stmt->bind_param("sssssssi", $faculty_id, $first_name, $last_name, $mobile, $email, $institute, $photo, $id);
        } else {
            $stmt = $conn->prepare("UPDATE faculty_account
                SET faculty_id=?, first_name=?, last_name=?, mobile=?, email=?, institute=?
                WHERE record_id=?");
            $stmt->bind_param("ssssssi", $faculty_id, $first_name, $last_name, $mobile, $email, $institute, $id);
        }
        $stmt->execute();
        $stmt->close();

        $stmt2 = $conn->prepare("UPDATE accounts SET id_number=?, email=? WHERE id_number=(SELECT faculty_id FROM faculty_account WHERE record_id=?)");
        $stmt2->bind_param("ssi", $faculty_id, $email, $id);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();

        header("Location: ../dashboard.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}

$conn->close();
?>