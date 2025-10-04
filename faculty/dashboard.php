<?php
// faculty/dashboard.php
require '../auth.php';
require_role('faculty');

include '../config.php';
include '../includes/faculty_header.php';

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
<<<<<<< HEAD
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Faculty — Approved Violations</title>
  <link rel="stylesheet" href="../css/faculty_dashboard.css">
=======
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Faculty — Approved Violations</title>
<link rel="stylesheet" href="/MoralMatrix/css/global.css">
<style>
/* tiny card styles (page-local) */
.violations { padding: 12px; max-width: 980px; margin: 0 auto; }
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
  background:#fff;
}
.card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.06); cursor:pointer; }
.card .left { flex: 0 0 120px; text-align:center; }
.card .left img { width:100px; height:100px; object-fit:cover; border-radius:50%; border:2px solid #eee; }
.card .info { flex:1; }
.meta { color:#666; font-size:0.92rem; }
</style>
>>>>>>> 6f84758ed57d6b35101077af008a06c758c22009
</head>
<body>

<main class="violations-page" aria-labelledby="pageTitle">
  <header class="page-intro">
    <h1 id="pageTitle">Approved Violations</h1>
    <p class="subtitle">These are the reports you submitted that have been approved.</p>
  </header>

  <?php if ($result && $result->num_rows > 0): ?>
    <section class="card-grid" aria-label="Approved violation cards">
      <?php while ($row = $result->fetch_assoc()): ?>
        <?php
          $studentPhotoFile = $row['student_photo'] ?? '';
          $studentPhotoSrc = $studentPhotoFile
              ? '../admin/uploads/' . htmlspecialchars($studentPhotoFile)
              : 'placeholder.png';
          $violationId = urlencode($row['violation_id']);
          $studentId   = htmlspecialchars($row['student_id']);
          $studentName = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
          $category    = htmlspecialchars($row['offense_category']);
          $type        = htmlspecialchars($row['offense_type']);
          $status      = strtolower($row['status'] ?? '');
          $desc        = trim($row['description'] ?? '');
          $reportedRaw = $row['reported_at'] ?? '';
          $reportedAt  = $reportedRaw ? date('M j, Y g:i a', strtotime($reportedRaw)) : '';
        ?>
        <article class="violation-card" role="article">
          <div class="vc-media">
            <img
              class="avatar"
              src="<?= $studentPhotoSrc ?>"
              alt="Photo of <?= $studentName ?>"
              width="76"
              height="76"
              loading="lazy"
              decoding="async"
              onerror="this.onerror=null;this.src='placeholder.png';"
            >
          </div>

          <div class="vc-body">
            <div class="vc-title-row">
              <h3 class="vc-title">
                <?= $studentName ?> <span class="muted">(<?= $studentId ?>)</span>
              </h3>
              <span class="badge badge-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($row['status']) ?></span>
            </div>

            <div class="vc-tags" aria-label="Tags">
              <span class="tag"><?= $category ?></span>
              <span class="sep" aria-hidden="true">•</span>
              <span class="tag tag-outline"><?= $type ?></span>
            </div>

            <?php if (!empty($desc)): ?>
              <p class="vc-desc"><?= nl2br(htmlspecialchars($desc)) ?></p>
            <?php endif; ?>

            <div class="vc-meta">
              <span class="when" title="<?= htmlspecialchars($reportedRaw) ?>">Reported <?= htmlspecialchars($reportedAt) ?></span>
              <a class="btn btn-primary" href="view_violation_approved.php?id=<?= $violationId ?>" aria-label="View details for <?= $studentName ?>">View details</a>
            </div>
          </div>
        </article>
      <?php endwhile; ?>
    </section>
  <?php else: ?>
    <section class="empty-state" aria-label="No data">
      <div class="empty-card">
        <div class="empty-illustration" aria-hidden="true">📄</div>
        <h2>No approved violations</h2>
        <p>Once a report you submitted is approved, it will appear here.</p>
      </div>
    </section>
  <?php endif; ?>
</main>

<?php
$stmt->close();
$conn->close();
?>
</body>
</html>
