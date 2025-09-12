<?php
include '../config.php';
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

if (!isset($_GET['student_id'])) {
    die("No student selected.");
}

$student_id = $_GET['student_id'];
$sql = "SELECT * FROM student_account WHERE student_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

/* ==== FETCH VIOLATIONS ==== */
$violations = [];
$sqlv = "SELECT violation_id, offense_category, offense_type, offense_details, description, reported_at
         FROM student_violation
         WHERE student_id = ?
         ORDER BY reported_at DESC, violation_id DESC";

$stmtv = $conn->prepare($sqlv);
$stmtv->bind_param("s", $student_id);
$stmtv->execute();
$resv = $stmtv->get_result();
while ($row = $resv->fetch_assoc()) {
    $violations[] = $row;
}
$stmtv->close();

$conn->close();

$selfDir = rtrim(str_replace('\\','/', dirname($_SERVER['PHP_SELF'])), '/');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

<div class="right-container">
  <?php if($student): ?>
      <div class="profile">
          <img src="<?= !empty($student['photo']) ? '../admin/uploads/'.$student['photo'] : 'placeholder.png' ?>" alt="Profile">
          <p><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
          <h2><?= htmlspecialchars($student['first_name'] . " " . $student['middle_name'] . " " . $student['last_name']) ?></h2>
          <p><strong>Course:</strong> <?= htmlspecialchars($student['course']) ?></p>
          <p><strong>Year Level:</strong> <?= htmlspecialchars($student['level']) ?></p>
          <p><strong>Section:</strong> <?= htmlspecialchars($student['section']) ?></p>
          <p><strong>Institute:</strong> <?= htmlspecialchars($student['institute']) ?></p>
          <p><strong>Guardian:</strong> <?= htmlspecialchars($student['guardian']) ?> (<?= htmlspecialchars($student['guardian_mobile']) ?>)</p>
          <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
          <p><strong>Mobile:</strong> <?= htmlspecialchars($student['mobile']) ?></p>
      </div>
  <?php else: ?>
      <p>Student not found.</p>
  <?php endif; ?>

  <div class="add-violation-btn">
  <a class="btn" href="<?= htmlspecialchars($selfDir) ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">
    Add Violation
  </a>
</div>

    
</body>
</html>