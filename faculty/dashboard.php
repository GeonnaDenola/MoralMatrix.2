<?php
// faculty/dashboard.php
require '../auth.php';
require_role('faculty');

include '../config.php';
include '../includes/header.php';
include 'side_buttons.php';

// DB connect
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// current faculty id (id_number stored in session by your login)
$faculty_id = $_SESSION['actor_id'] ?? null;
if (!$faculty_id) {
    die("No faculty id in session. Please login again.");
}

// make sure $sql is defined
$sql = "
SELECT sv.violation_id,
       sv.student_id,
       s.photo AS student_photo,
       s.first_name,
       s.last_name,
       sv.offense_category,
       sv.offense_type,
       sv.description,
       sv.reported_at,
       sv.status
FROM student_violation sv
JOIN student_account s ON sv.student_id = s.student_id
WHERE sv.submitted_by = ?
  AND sv.status = 'approved'
ORDER BY sv.reported_at DESC, sv.violation_id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("s", $faculty_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Faculty — Approved Violations</title>
<style>
/* tiny card styles (move to your CSS file if you prefer) */
.violations { padding: 12px; }
.card-link { text-decoration:none; color:inherit; display:block; }
.card {
  border:1px solid #ddd;
  border-radius:10px;
  padding:12px;
  margin:10px 0;
  display:flex;
  align-items:center;
  gap:18px;
  transition:transform .12s, box-shadow .12s;
}
.card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.06); cursor:pointer; }
.card .left { flex: 0 0 120px; text-align:center; }
.card .left img { width:100px; height:100px; object-fit:cover; border-radius:50%; border:2px solid #eee; }
.card .info { flex:1; }
.meta { color:#666; font-size:0.92rem; }
</style>
</head>
<body>

<div class="violations">
  <?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <?php
        // build student photo path (adjust if your uploads path is different)
        $studentPhotoFile = $row['student_photo'] ?? '';
        $studentPhotoSrc = $studentPhotoFile
            ? '../admin/uploads/' . htmlspecialchars($studentPhotoFile)
            : 'placeholder.png';

        // sanity check: you can test the URL in browser if image not found
        $violationId = urlencode($row['violation_id']);
        $studentId = htmlspecialchars($row['student_id']);
      ?>
      <a class="card-link" href="view_violation_approved.php?id=<?= $violationId ?>">
        <div class="card">
          <div class="left">
            <img src="<?= $studentPhotoSrc ?>" alt="Student photo" onerror="this.src='placeholder.png'">
          </div>

          <div class="info">
            <h4><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?> (<?= $studentId ?>)</h4>
            <p><strong>Category:</strong> <?= htmlspecialchars($row['offense_category']) ?> &nbsp; • &nbsp;
               <strong>Type:</strong> <?= htmlspecialchars($row['offense_type']) ?></p>

            <?php if (!empty($row['description'])): ?>
              <p><?= nl2br(htmlspecialchars($row['description'])) ?></p>
            <?php endif; ?>

            <p class="meta"><strong>Status:</strong> <?= htmlspecialchars($row['status']) ?> —
               <em>Reported at <?= htmlspecialchars($row['reported_at']) ?></em></p>
          </div>
        </div>
      </a>
    <?php endwhile; ?>
  <?php else: ?>
    <p>No approved violations found.</p>
  <?php endif; ?>
</div>

<?php
$stmt->close();
$conn->close();
?>
</body>
</html>
