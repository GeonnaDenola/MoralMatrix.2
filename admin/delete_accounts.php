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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'], $_POST['type'])){
    $id = intval($_POST['id']);
    $type = $_POST['type'];

    // Map account type to table and ID column
    $accountMap = [
        'student'  => ['table' => 'student_account', 'id_col' => 'record_id', 'account_id_col' => 'student_id'],
        'faculty'  => ['table' => 'faculty_account', 'id_col' => 'record_id', 'account_id_col' => 'faculty_id'],
        'ccdu'     => ['table' => 'ccdu_account', 'id_col' => 'record_id', 'account_id_col' => 'ccdu_id'],
        'security' => ['table' => 'security_account', 'id_col' => 'record_id', 'account_id_col' => 'security_id']
    ];

    if (!isset($accountMap[$type])) {
        echo json_encode(["success" => false, "error" => "Invalid account type"]);
        exit;
    }

    $table = $accountMap[$type]['table'];
    $id_col = $accountMap[$type]['id_col'];
    $account_id_col = $accountMap[$type]['account_id_col'];

    $conn->begin_transaction();

    try {
        // 1. Get account_id and photo
        $stmtGet = $conn->prepare("SELECT $account_id_col, photo FROM $table WHERE $id_col = ?");
        $stmtGet->bind_param("i", $id);
        $stmtGet->execute();
        $stmtGet->bind_result($account_id, $photo);
        $stmtGet->fetch();
        $stmtGet->close();

        if (!$account_id) {
            throw new Exception("Account ID not found for record_id = $id");
        }

        // 2. Delete from specific account table
        $stmtDel = $conn->prepare("DELETE FROM $table WHERE $id_col = ?");
        $stmtDel->bind_param("i", $id);
        $stmtDel->execute();
        $stmtDel->close();

        // 3. Delete from main accounts table
        $stmtMain = $conn->prepare("DELETE FROM accounts WHERE id_number = ?");
        $stmtMain->bind_param("s", $account_id);
        $stmtMain->execute();
        $stmtMain->close();

        // 4. Delete uploaded photo file
        if ($photo && file_exists(__DIR__ . "/uploads/" . $photo)) {
            unlink(__DIR__ . "/uploads/" . $photo);
        }

        $conn->commit();
        echo json_encode(["success" => true]);

    } catch (Exception $e){
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }

} else {
    echo json_encode(["success" => false, "error" => "Invalid request"]);
}

$conn->close();
?>
