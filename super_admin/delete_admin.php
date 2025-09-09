<?php
include '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error){
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'])){
    $id = intval($_POST['id']);

    $conn->begin_transaction();

    try {
        // 1. Get the admin_id linked to this record_id
        $stmtGet = $conn->prepare("SELECT admin_id FROM admin_account WHERE record_id = ?");
        $stmtGet->bind_param("i", $id);
        $stmtGet->execute();
        $stmtGet->bind_result($admin_id);
        $stmtGet->fetch();
        $stmtGet->close();

        if ($admin_id) {
            // 2. Delete from admin_account
            $stmt1 = $conn->prepare("DELETE FROM admin_account WHERE record_id = ?");
            $stmt1->bind_param("i", $id);
            $stmt1->execute();
            $stmt1->close();

            // 3. Delete from accounts using id_number = admin_id
            $stmt2 = $conn->prepare("DELETE FROM accounts WHERE id_number = ?");
            $stmt2->bind_param("s", $admin_id);
            $stmt2->execute();
            $stmt2->close();

            $conn->commit();

            echo json_encode(["success" => true]);
        } else {
            throw new Exception("Admin ID not found for record_id = $id");
        }
    } catch (Exception $e){
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Invalid request"]);
}

$conn->close();
?>
