<?php
require '../auth.php';
require_role('security');

require '../config.php';

// Optional scanner (silent)
@include __DIR__ . '/_scanner.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h(?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function formatDate($dt) {
    if (empty($dt) || $dt === '0000-00-00 00:00:00' || strtotime($dt) === false) {
        return 'Date unavailable';
    }
    return date('F j, Y • g:i A', strtotime($dt));
}

/* ===== DB Connection ===== */
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$facultyId = $_SESSION['actor_id'] ?? $_SESSION['securty_id'] ?? null;
if (!$facultyId) {
    die('Unauthorized: Security not logged in.');
}

/* ===== Input ===== */
if (empty($_GET['student_id'])) {
  http_response_code(400);
  echo "No student selected.";
  exit;
}
$student_id = (string)$_GET['student_id'];

/* ===== Fetch Student ===== */
$stmt = $conn->prepare("SELECT * FROM student_account WHERE student_id=?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ===== Fetch Violations (Only by this security user) ===== */
$violations = [];
$stmtv = $conn->prepare("
  SELECT violation_id, offense_category, offense_type, offense_details, description, reported_at
  FROM student_violation
  WHERE student_id = ? 
    AND submitted_by = ?
  ORDER BY reported_at DESC, violation_id DESC
");
$stmtv->bind_param("ss", $student_id, $facultyId);

$stmtv->execute();
$resv = $stmtv->get_result();

while ($row = $resv->fetch_assoc()) {
  // Clean all labels using labelize()
  foreach ($row as $k => $v) {
    $row[$k] = labelize((string)$v);
  }
  $violations[] = $row;
}
$stmtv->close();
$conn->close();

/* ===== Paths ===== */
$uploadsUrl = rtrim(BASE_URL, '/') . '/admin/uploads/';
$cssUrl     = asset('css/security_view_student.css');
$selfDir    = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$addViolationUrl = asset('security/add_violation.php?student_id=' . urlencode($student_id));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Profile</title>
  <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body>

<?php include '../includes/security_header.php'; ?>

<div class="right-container">
  <?php if ($student): ?>
    <?php
      $first   = $student['first_name'] ?? '';
      $middle  = $student['middle_name'] ?? '';
      $last    = $student['last_name'] ?? '';
      $fullName = trim("$first $middle $last");

      $course  = labelize($student['course'] ?? '—');
      $level   = labelize($student['level'] ?? '—');
      $section = labelize($student['section'] ?? '—');
      $inst    = labelize($student['institute'] ?? '—');

      $photoFile = trim((string)($student['photo'] ?? ''));
      $photoSrc  = $photoFile !== '' ? $uploadsUrl . rawurlencode($photoFile) : $uploadsUrl . 'placeholder.png';
      $yearLabel = "Year $level $section";
    ?>

    <div class="profile-shell">
      <section class="profile-hero">
        <div class="hero-content">
          <div class="identity">
            <div class="portrait">
              <img
                src="<?= h($photoSrc) ?>"
                alt="Portrait of <?= h($fullName) ?>"
                onerror="this.onerror=null;this.src='<?= h($uploadsUrl) ?>placeholder.png';"
              >
            </div>
            <div class="headline">
              <span class="eyebrow">Student Profile</span>
              <h1><?= h($fullName) ?: 'Unnamed student' ?></h1>

              <div class="badge-row">
                <span class="badge">ID: <?= h($student['student_id'] ?? '') ?></span>
                <span class="badge"><?= h($inst) ?></span>
                <span class="badge"><?= h($course) ?></span>
                <span class="badge"><?= h($yearLabel) ?></span>
              </div>

              <div class="actions">
                <a class="primary-btn" href="<?= h($addViolationUrl) ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                  </svg>
                  Add Violation
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="detail-grid">
        <div class="info-card">
          <h2>Academic Details</h2>
          <div class="info-list">
            <div class="info-row"><span>Course</span><span><?= h($course) ?></span></div>
            <div class="info-row"><span>Institute</span><span><?= h($inst) ?></span></div>
            <div class="info-row"><span>Year</span><span><?= h($level) ?></span></div>
            <div class="info-row"><span>Section</span><span><?= h($section) ?></span></div>
          </div>
        </div>

        <div class="info-card">
          <h2>Contact</h2>
          <div class="info-list">
            <div class="info-row"><span>Email</span><span><?= h($student['email'] ?? '—') ?></span></div>
            <div class="info-row"><span>Mobile</span><span><?= h($student['mobile'] ?? '—') ?></span></div>
            <div class="info-row"><span>Guardian</span><span><?= h($student['guardian'] ?? '—') ?></span></div>
            <div class="info-row"><span>Guardian Mobile</span><span><?= h($student['guardian_mobile'] ?? '—') ?></span></div>
            <div class="info-row"><span>Address</span><span><?= h($student['address'] ?? '—') ?></span></div>
          </div>
        </div>
      </section>

      <section class="history-card">
        <header>
          <h2>Violation History</h2>
          <span class="badge neutral">
            <?= count($violations) ?> record<?= count($violations) === 1 ? '' : 's' ?>
          </span>
        </header>

        <?php if ($violations): ?>
          <div class="timeline">
            <?php foreach ($violations as $v): ?>
              <div class="timeline-item">
                <h3><?= h(labelize($v['offense_type'])) ?></h3>
                <div class="meta">
                  <span><?= h(labelize($v['offense_category'])) ?></span>
                </div>
                <p><?= h(labelize($v['offense_details'] ?: $v['description'])) ?></p>
                <p><?= h(formatDate($v['reported_at'])) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state"> You haven’t reported any violations for this student yet.</div>
        <?php endif; ?>
      </section>
    </div>
  <?php else: ?>
    <div class="not-found">Student not found.</div>
  <?php endif; ?>
</div>
</body>
</html>
