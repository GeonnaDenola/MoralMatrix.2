<?php
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $violationId = intval($_POST['id']); // convert to int for safety

    $conn = new mysqli(
        $database_settings['servername'],
        $database_settings['username'],
        $database_settings['password'],
        $database_settings['dbname']
    );

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "UPDATE student_violation SET status='approved' WHERE violation_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $violationId);

    if ($stmt->execute()) {
        header("Location: pending_reports.php?msg=approved");
        exit;
    } else {
        echo "Error updating record: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
} else {
    echo "❌ Missing violation ID.";
}
