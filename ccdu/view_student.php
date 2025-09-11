<?php
include '../config.php';
include '../includes/header.php';

$servername = $database_settings['servername'];
$username = $database_settings['username'];
$password = $database_settings['password'];
$dbname = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if(!isset($_GET['student_id'])){
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

/* ====FETCH VIOLATION ====*/ 

$violations =[];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Profile</title>
</head>
<body>

<div class="left-container">
  <div id="pageButtons">
    <?php include 'page_buttons.php' ?>
  </div>
</div>

<div class="right-container">
  <?php if($student): ?>
      <div class="profile">
          <img src="<?= !empty($student['photo']) ? '../admin/uploads/'.$student['photo'] : 'placeholder.png' ?>" alt="Profile">
          <p><strong>Student ID:</strong> <?= $student['student_id'] ?></p>
          <h2><?= $student['first_name'] . " " . $student['middle_name'] . " " . $student['last_name'] ?></h2>
          <p><strong>Course:</strong> <?= $student['course'] ?></p>
          <p><strong>Year Level:</strong> <?= $student['level'] ?></p>
          <p><strong>Section:</strong> <?= $student['section'] ?></p>
          <p><strong>Institute:</strong> <?= $student['institute'] ?></p>
          <p><strong>Guardian:</strong> <?= $student['guardian'] ?> (<?= $student['guardian_mobile'] ?>)</p>
          <p><strong>Email:</strong> <?= $student['email'] ?></p>
          <p><strong>Mobile:</strong> <?= $student['mobile'] ?></p>
      </div>
  <?php else: ?>
      <p>Student not found.</p>
  <?php endif; ?>

  <div class="">
    <div class="add-violation-btn">
      <a href="add_violation.php?student_id=<?= $student['student_id'] ?>">
        <button>Add Violation</button>
      </a>
    </div>

    <div class="violationHistory-container" id="violationHistory">
      <?php if (empty($violations)): ?>
        <p>No Violations Recorded.</p>
      <?php else: ?>
        <div class="cards-grid">
          <?php foreach ($violations as $v):
            $cat  = htmlspecialchars($v['offense_category']);
            $type = htmlspecialchars($v['offense_type']);
            $desc = htmlspecialchars($v['description'] ?? '');
            $date = date('M d, Y h:i A', strtotime($v['reported_at']));
            $chips = [];
            if (!empty($v['offense_details'])) {
              $decoded = json_decode($v['offense_details'], true);
              if (is_array($decoded)) {
                foreach ($decoded as $d) { $chips[] = htmlspecialchars($d); }
              }
            }
            $href = "violation_view.php?id=" . urlencode($v['violation_id']) . "&student_id=" . urlencode($student_id);
          ?>
            <a class="profile-card" href="<?= $href ?>">
              <img src="violation_photo.php?id=<?= urlencode($v['violation_id']) ?>" alt="Evidence" onerror="this.style.display='none'">
              <div class="info">
                <p><strong>Category: </strong><span class="badge badge-<?= $cat ?>"><?= ucfirst($cat) ?></span></p>
                <p><strong>Type:</strong> <?= $type ?></p>
                 <?php if (!empty($chips)): ?>
                  <p><strong>Details:</strong> <?= implode(', ', $chips) ?></p>
                <?php endif; ?>
                <p><strong>Reported:</strong> <?= $date ?></p>
                <?php if ($desc): ?>
                  <p><strong>Description:</strong> <?= $desc ?></p>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
        </div>
    </div>
  </div>
</div>

<script>
  function viewViolation(id, studentId){
    location.href = `violation_view.php?id=${encodeURIComponent(id)}&student_id=${encodeURIComponent(studentId)}`;
  }
  function editViolation(id, studentId){
    // TODO: route to your edit page
    location.href = `violation_edit.php?id=${encodeURIComponent(id)}&student_id=${encodeURIComponent(studentId)}`;
  }
  function deleteViolation(id, studentId){
    if (!confirm('Delete this violation?')) return;
    // TODO: point to your delete endpoint
    location.href = `violation_delete.php?id=${encodeURIComponent(id)}&student_id=${encodeURIComponent(studentId)}`;
  }
</script>

</body>
</html>
