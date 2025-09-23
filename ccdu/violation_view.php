<?php
// violation_view.php
include '../config.php';
require __DIR__.'/_scanner.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$violationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$studentId   = $_GET['student_id'] ?? '';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  http_response_code(500);
  echo "Database connection failed.";
  exit;
}

/* Detect optional columns */
$hasReportedBy = false;
$hasStatus     = false;
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'reported_by'")) {
  $hasReportedBy = (bool)$res->num_rows; $res->close();
}
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'status'")) {
  $hasStatus = (bool)$res->num_rows; $res->close();
}

/* Fetch violation */
$r = null;
if ($violationId > 0) {
  $cols = "violation_id, student_id, offense_category, offense_type, offense_details, description, reported_at, photo";
  if ($hasReportedBy) $cols .= ", reported_by";
  if ($hasStatus)     $cols .= ", status";

  $sql = "SELECT $cols FROM student_violation WHERE violation_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $violationId);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

/* Per-student ordinal */
$studentNo = 1;
if ($r) {
  $stmtN = $conn->prepare(
    "SELECT COUNT(*) AS earlier
       FROM student_violation
      WHERE student_id = ?
        AND (reported_at < ? OR (reported_at = ? AND violation_id < ?))"
  );
  $stmtN->bind_param("sssi", $r['student_id'], $r['reported_at'], $r['reported_at'], $r['violation_id']);
  $stmtN->execute();
  $rowN = $stmtN->get_result()->fetch_assoc();
  $stmtN->close();
  $studentNo = (int)($rowN['earlier'] ?? 0) + 1;
}

/* Guardian info */
$guardianName = $guardianMobile = '';
if ($r) {
  $st2 = $conn->prepare("SELECT guardian, guardian_mobile FROM student_account WHERE student_id = ?");
  $st2->bind_param("s", $r['student_id']);
  $st2->execute();
  $acc = $st2->get_result()->fetch_assoc();
  $st2->close();
  if ($acc) {
    $guardianName   = $acc['guardian'] ?? '';
    $guardianMobile = $acc['guardian_mobile'] ?? '';
  }
}
$conn->close();

/* Not found */
if (!$r) {
  if (isset($_GET['modal']) && $_GET['modal'] == '1') {
    echo '<div style="padding:12px">Violation not found.</div>';
    exit;
  } else {
    http_response_code(404); ?>
    <!DOCTYPE html>
    <html lang="en"><head><meta charset="utf-8"><title>Not found</title></head>
    <body>
      <p>Violation not found.</p>
      <p><a href="view_student.php?student_id=<?= htmlspecialchars($studentId) ?>">Back to student</a></p>
    </body></html>
    <?php exit;
  }
}

/* Prep display */
$violationNo = (int)$r['violation_id'];
$cat         = htmlspecialchars($r['offense_category'] ?? '');
$type        = htmlspecialchars($r['offense_type'] ?? '');
$desc        = htmlspecialchars($r['description'] ?? '');
$datePretty  = !empty($r['reported_at']) ? date('M d, Y h:i A', strtotime($r['reported_at'])) : '—';
$reportedBy  = $hasReportedBy ? htmlspecialchars($r['reported_by'] ?? '—') : '—';
$statusVal   = $hasStatus ? htmlspecialchars($r['status'] ?? 'active') : 'active';

/* Flatten chips */
$detailsText = '—';
if (!empty($r['offense_details'])) {
  $decoded = json_decode($r['offense_details'], true);
  if (is_array($decoded) && count($decoded)) {
    $safe = array_map(fn($x) => htmlspecialchars($x), $decoded);
    $detailsText = implode(', ', $safe);
  }
}

/* Build return + Set CS URL */
$backTo  = 'view_student.php?student_id=' . rawurlencode($studentId ?: $r['student_id']);
$setCsUrl = 'set_community_service.php?student_id=' . urlencode($r['student_id'])
          . '&violation_id=' . urlencode((string)$violationNo)
          . '&return=' . urlencode($backTo);

/* Determine photo path */
$photoRel = 'placeholder.png';
if (!empty($r['photo'])) {
    $tryAbs = __DIR__ . '/uploads/' . $r['photo'];
    if (is_file($tryAbs)) $photoRel = 'uploads/' . $r['photo'];
}

ob_start(); ?>
<div class="violation-view">
  <p><strong>violation #</strong> <?= $studentNo ?></p>
  <p><strong>Category:</strong> <?= ucfirst($cat) ?></p>
  <p><strong>Type:</strong> <?= $type ?></p>
  <p><strong>Details:</strong> <?= $detailsText ?></p>

  <br>

  <p><strong>description:</strong></p>
  <p><?= nl2br($desc) ?: '—' ?></p>

  <p><strong>reported on:</strong> <?= $datePretty ?></p>
  <p><strong>reported by:</strong> <?= $reportedBy ?></p>

  <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
    <?php if (!empty($guardianMobile)): ?>
      <a class="btn" href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $guardianMobile)) ?>">📞 Contact Guardian</a>
    <?php else: ?>
      <button class="btn" disabled title="No guardian mobile on file">📞 Contact Guardian</button>
    <?php endif; ?>

    <a class="btn" href="<?= $setCsUrl ?>">Set for Community Service</a>
    <a class="btn" href="violation_edit.php?id=<?= $violationNo ?>&student_id=<?= urlencode($r['student_id']) ?>">✏️ Edit</a>

    <?php if ($hasStatus && $statusVal !== 'void'): ?>
      <form method="POST" action="violation_void.php" onsubmit="return confirm('Void this violation?');" style="display:inline;">
        <input type="hidden" name="id" value="<?= $violationNo ?>">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($r['student_id']) ?>">
        <button type="submit" class="btn btn-danger">🛑 Void</button>
      </form>
    <?php elseif ($hasStatus): ?>
      <span class="tag tag-void">Voided</span>
    <?php endif; ?>
  </div>

  <div style="margin-top:14px;">
    <p><strong>photo evidence:</strong></p>
    <div class="photo-wrap" style="margin-top:8px">
      <img src="<?= htmlspecialchars($photoRel) ?>" alt="Evidence photo"
           style="max-width:100%;border-radius:10px;display:block">
    </div>
  </div>
</div>
<?php
$inner = ob_get_clean();

if (isset($_GET['modal']) && $_GET['modal'] == '1') {
  echo $inner;
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Violation #<?= $violationNo ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:20px}
    .btn{display:inline-block;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;text-decoration:none;color:#111}
    .btn:hover{background:#f9fafb}
    .btn-danger{border-color:#ef4444;color:#991b1b}
    .btn-danger:hover{background:#fee2e2}
    .tag-void{display:inline-block;padding:4px 8px;border-radius:999px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
  </style>
</head>
<body>
  <p><a href="<?= htmlspecialchars($backTo) ?>">← Back to Student</a></p>
  <?= $inner ?>
</body>
</html>
