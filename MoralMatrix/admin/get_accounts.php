<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
include '../config.php';

$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);

if ($conn->connect_error) {
    echo json_encode(["error" => $conn->connect_error]);
    exit;
}

$accounts = [];

// Fetch base accounts
$sql = "SELECT * FROM accounts";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $type = $row['account_type'];
    $record_id = (int)$row['record_id']; // force integer
    $details = [];

    switch ($type) {
        case 'faculty':
            $table = "faculty_account";
            break;
        case 'student':
            $table = "student_account";
            break;
        case 'ccdu':
            $table = "ccdu_account";
            break;
        case 'security':
            $table = "security_account";
            break;
        default:
            $table = null;
    }

    if ($table) {
        $stmt = $conn->prepare("SELECT * FROM $table WHERE record_id = ?");
        $stmt->bind_param("i", $record_id);
        $stmt->execute();
        $res2 = $stmt->get_result();
        if ($res2 && $res2->num_rows > 0) {
            $details = $res2->fetch_assoc();
        }
        $stmt->close();
    }

    $accounts[] = [
        "record_id" => $record_id,
        "id_number" => $row['id_number'],
        "email" => $row['email'],
        "account_type" => $row['account_type'],
        "details" => $details
    ];
}

echo json_encode($accounts, JSON_PRETTY_PRINT);
$conn->close();
