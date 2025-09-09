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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['record_id']);
    $student_id = $_POST['student_id'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $institute = $_POST['institute'];
    $course = $_POST['course'];
    $level = $_POST['level'];
    $section = $_POST['section'];
    $guardian = $_POST['guardian'];
    $guardian_mobile = $_POST['guardian_mobile'];

    // Handle photo upload
    $photo = null;
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

    try {
        // Update student_account
        if ($photo) {
            $stmt = $conn->prepare("UPDATE student_account 
                SET student_id=?, first_name=?, middle_name=?, last_name=?, mobile=?, email=?, institute=?, course=?, level=?, section=?, guardian=?, guardian_mobile=?, photo=? 
                WHERE record_id=?");
            $stmt->bind_param("ssssssssissssi", $student_id, $first_name, $middle_name, $last_name, $mobile, $email, $institute, $course, $level, $section, $guardian, $guardian_mobile, $photo, $id);
        } else {
            $stmt = $conn->prepare("UPDATE student_account 
                SET student_id=?, first_name=?, middle_name=?, last_name=?, mobile=?, email=?, institute=?, course=?, level=?, section=?, guardian=?, guardian_mobile=? 
                WHERE record_id=?");
            $stmt->bind_param("ssssssssisssi", $student_id, $first_name, $middle_name, $last_name, $mobile, $email, $institute, $course, $level, $section, $guardian, $guardian_mobile, $id);
        }
        $stmt->execute();
        $stmt->close();

        // Update accounts table
        $stmt2 = $conn->prepare("UPDATE accounts SET id_number=?, email=? WHERE id_number=(SELECT student_id FROM student_account WHERE record_id=?)");
        $stmt2->bind_param("ssi", $student_id, $email, $id);
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
