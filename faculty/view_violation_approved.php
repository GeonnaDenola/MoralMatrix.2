<?php
require '../auth.php';
require_role('faculty');
require '../config.php';

include '../includes/header.php';
include 'side_buttons.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$violation_id = $_GET['id'] ?? '';
if (!$violation_id) {
    die("Invalid violation ID.");
}

// include the photo column in query
$sql = "SELECT sv.violation_id, sv.student_id, sv.offense_category, sv.offense_type, 
               sv.description, sv.reported_at, sv.status, sv.photo, 
               sa.first_name, sa.last_name
        FROM student_violation sv
        JOIN student_account sa ON sv.student_id = sa.student_id
        WHERE sv.violation_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $violation_id);
$stmt->execute();
$result = $stmt->get_result();
$violation = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Violation Details</title>
</head>
<body>
<?php if ($violation): ?>
    <h2>Violation Report</h2>
    <p><strong>Student:</strong> <?= htmlspecialchars($violation['first_name']." ".$violation['last_name']) ?></p>
    <p><strong>Category:</strong> <?= htmlspecialchars($violation['offense_category']) ?></p>
    <p><strong>Type:</strong> <?= htmlspecialchars($violation['offense_type']) ?></p>
    <p><strong>Description:</strong> <?= htmlspecialchars($violation['description']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($violation['status']) ?></p>
    <p><em>Reported at: <?= htmlspecialchars($violation['reported_at']) ?></em></p>

    <?php if (!empty($violation['photo'])): ?>
        <p><strong>Photo Evidence:</strong></p>
        <img src="../uploads/<?= htmlspecialchars($violation['photo']) ?>" 
             alt="Evidence" 
             style="max-width:400px; height:auto; border:1px solid #ccc; border-radius:6px;">
    <?php else: ?>
        <p><em>No photo evidence uploaded.</em></p>
    <?php endif; ?>

<?php else: ?>
    <p>Violation not found.</p>
<?php endif; ?>
</body>
</html>
