<?php
include '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error){
    die(json_encode(["error" => "Connection failed: " .$conn->connect_error]));
}

$accounts = [];

function fetchAccounts($conn, $sql, $type) {
    $rows = [];
    if($result = $conn->query($sql)) {
        while($row = $result->fetch_assoc()) {
            $row['account_type'] = $type;
            $rows[] = $row;
        }
    } else {
        error_log("SQL error ($type): " . $conn->error);
    }
    return $rows;
}

// Adjusted: removed middle_name
$accounts = array_merge($accounts,
    fetchAccounts($conn, "SELECT record_id, student_id AS user_id, first_name, last_name, mobile, email, photo FROM student_account", "student"),
    fetchAccounts($conn, "SELECT record_id, faculty_id AS user_id, first_name, last_name, mobile, email, photo FROM faculty_account", "faculty"),
    fetchAccounts($conn, "SELECT record_id, security_id AS user_id, first_name, last_name, mobile, email, photo FROM security_account", "security"),
    fetchAccounts($conn, "SELECT record_id, ccdu_id AS user_id, first_name, last_name, mobile, email, photo FROM ccdu_account", "ccdu")
);

header('Content-Type: application/json');
echo json_encode($accounts);

$conn->close();
?>
