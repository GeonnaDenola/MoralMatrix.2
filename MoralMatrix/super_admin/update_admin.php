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
    $id = intval($_POST['admin_id']);
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];

    $photo = null;
    if(!empty($_FILES['photo']['name'])){
        $photo = time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_files($_FILES['photo']['tmp_name'], "uploads/" .$photo);
    }

    $conn->begin_transaction();

    try {
        if ($photo){
            $stmt1 = $conn->prepare("UPDATE admin_account SET first_name=?, middle_name=?, last_name=? email=?. mobile=?, photo=? WHERE admin_id=?");
            $stmt1->bind_param("ssssssi", $first_name, $middle_name, $last_name, $email, $mobile, $photo, $id);
        } else {
            $stmt1 = $conn->prepare("UPDATE admin_account SET first_name=?, middle_name=?, last_name=? email=?. mobile=? WHERE admin_id=?");
            $stmt1->bind_param("ssssssi", $first_name, $middle_name, $last_name, $email, $mobile, $id);
        }
        $stmt1->execute();
        $stmt1->close();

        $stmt2 = $conn->prepare("UPDATE accounts SET first_name=?, middle_name=?, last_name=?, email=?, mobile=? WHERE record_id=?");
        $stms2->bind_param("sssssi", $first_name, $middle_name, $last_name, $email, $mobile, $id);
        $stmt2->execute();
        $stms2->close();

        $conn->commit();

        header("Location: dashboard.php");
        exit;

    }catch(Exception $e){
        $conn->rollback();
    }
}
?>