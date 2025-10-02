<?php
include '../includes/header.php';
include '../config.php';

include 'menu_buttons.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT offense_category, offense_type, offense_details, description, photo, status, submitted_by, reported_at 
        FROM student_violation 
        WHERE student_id = ? 
        ORDER BY reported_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <div class="container mt-5">
  <h2 class="mb-4">Welcome, <?= htmlspecialchars($_SESSION['first_name']) ?>!</h2>
  <h4 class="mb-3">Your Violation History</h4>

  <div class="row g-4">
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100 border-0 rounded-4">
            <?php if (!empty($row['photo'])): ?>
              <img src="../uploads/<?= htmlspecialchars($row['photo']) ?>" 
                   class="card-img-top rounded-top-4" 
                   alt="evidence" 
                   style="object-fit:cover; height:180px;">
            <?php endif; ?>
            <div class="card-body">
              <h5 class="card-title mb-2"><?= htmlspecialchars($row['offense_category']) ?> - <?= htmlspecialchars($row['offense_type']) ?></h5>
              <p class="text-muted mb-1"><strong>Date:</strong> <?= htmlspecialchars($row['date_reported']) ?></p>
              <p class="mb-1"><strong>Details:</strong> <?= htmlspecialchars($row['offense_details']) ?></p>
              <p class="mb-1"><strong>Description:</strong> <?= htmlspecialchars($row['description']) ?></p>
            </div>
            <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
              <span class="badge bg-<?= $row['status'] === 'approved' ? 'success' : 'warning' ?>">
                <?= htmlspecialchars($row['status']) ?>
              </span>
              <small class="text-muted">Submitted by: <?= htmlspecialchars($row['submitted_by']) ?></small>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-info">No violations recorded.</div>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>