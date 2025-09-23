<?php
include '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die("Database connection failed.");
}

$stmt = $conn->prepare("SELECT photo FROM student_violation WHERE violation_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($photo);
    $stmt->fetch();

    if (!empty($photo)) {
        // Detect MIME type (fallback to jpeg)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->buffer($photo) ?: 'image/jpeg';

        header("Content-Type: $mime");
        echo $photo;
        exit;
    }
}

http_response_code(404);
echo "Image not found";
