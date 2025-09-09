<?php 

include '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $id = intval($_POST['record_id']);
    $admin_id = $_POST['admin_id'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];

    // --- Insert file upload code here ---
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
    } else {
        $photo = null; // No new photo uploaded
    }

    $conn->begin_transaction();

    try {
        if ($photo){
    $stmt1 = $conn->prepare("UPDATE admin_account 
        SET first_name=?, middle_name=?, last_name=?, email=?, mobile=?, photo=? 
        WHERE record_id=?");
    $stmt1->bind_param("ssssssi", $first_name, $middle_name, $last_name, $email, $mobile, $photo, $id);
} else {
    $stmt1 = $conn->prepare("UPDATE admin_account 
        SET first_name=?, middle_name=?, last_name=?, email=?, mobile=? 
        WHERE record_id=?");
    $stmt1->bind_param("sssssi", $first_name, $middle_name, $last_name, $email, $mobile, $id);
}
        $stmt1->execute();
        $stmt1->close();

        $stmt2 = $conn->prepare("UPDATE accounts SET id_number=?, email=? WHERE record_id=?");
        $stmt2->bind_param("ssi", $admin_id, $email, $id);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();

        header("Location: dashboard.php");
        exit;

    }catch(Exception $e){
        $conn->rollback();
        echo "Error: " .$e->getMessage();
    }
}

$conn->close();
?>