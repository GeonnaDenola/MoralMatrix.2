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

$id   = $_GET['id'] ?? null;
$type = $_GET['type'] ?? null;

if(!$id || !$type){
    die("Invalid request.");
}

switch($type){
    case 'student':
        $sql = "SELECT * FROM student_account WHERE record_id = ?";
        break;
    case 'faculty':
        $sql = "SELECT * FROM faculty_account WHERE record_id = ?";
        break;
    case 'security':
        $sql = "SELECT * FROM security_account WHERE record_id = ?";
        break;
    case 'ccdu':
        $sql = "SELECT * FROM ccdu_account WHERE record_id = ?";
        break;
    default:
        die("Unknown account type.");
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0){
    die("Record not found.");
}

$data = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Details</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f7f9fc; padding:20px; }
        .card { max-width:500px; margin:0 auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.2); }
        .card img { width:120px; height:120px; border-radius:50%; object-fit:cover; display:block; margin:0 auto; }
        .info { margin-top:15px; }
        .info p { margin:5px 0; }
        a { display:inline-block; margin-top:15px; color:#3498db; text-decoration:none; }
    </style>
</head>
<body>
    <div class="card">
        <img src="<?php echo $data['photo'] ? 'uploads/' . $data['photo'] : 'placeholder.png'; ?>" alt="Profile">
        <div class="info">
            <?php foreach($data as $key => $value): ?>
                <p><strong><?php echo ucfirst(str_replace("_"," ",$key)); ?>:</strong> <?php echo htmlspecialchars($value); ?></p>
            <?php endforeach; ?>
        </div>
        <a href="javascript:history.back()">⬅ Back</a>
    </div>
</body>
</html>
