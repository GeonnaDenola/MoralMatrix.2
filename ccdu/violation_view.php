<?php
// violation_view.php
include '../config.php';

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

/* Detect optional columns so we can show them if present */
$hasReportedBy = false;
$hasStatus     = false;
/* FIX: table name -> student_violations */
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'reported_by'")) {
  $hasReportedBy = (bool)$res->num_rows;
  $res->close();
}
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'status'")) {
  $hasStatus = (bool)$res->num_rows;
  $res->close();
}

/* Fetch violation */
$r = null;
if ($violationId > 0) {
  /* include a computed has_photo flag so we don't fetch the BLOB */
  $cols = "violation_id, student_id, offense_category, offense_type, offense_details, description, reported_at,
           (photo IS NOT NULL AND OCTET_LENGTH(photo) > 0) AS has_photo";
  if ($hasReportedBy) $cols .= ", reported_by";
  if ($hasStatus)     $cols .= ", status";

  /* FIX: table name -> student_violations */
  $sql = "SELECT $cols FROM student_violation WHERE violation_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $violationId);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

/* Guardian info from student_account */
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

/* Not found handling */
if (!$r) {
  if (isset($_GET['modal']) && $_GET['modal'] == '1') {
    echo '<div style="padding:12px">Violation not found.</div>';
    exit;
  } else {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en"><head><meta charset="utf-8"><title>Not found</title></head>
    <body>
      <p>Violation not found.</p>
      <p><a href="view_student.php?student_id=<?= htmlspecialchars($studentId) ?>">Back to student</a></p>
    </body></html>
    <?php
    exit;
  }
}

/* Prepare display values */
$violationNo = (int)$r['violation_id'];
$cat         = htmlspecialchars($r['offense_category'] ?? '');
$type        = htmlspecialchars($r['offense_type'] ?? '');
$desc        = htmlspecialchars($r['description'] ?? '');
$datePretty  = !empty($r['reported_at']) ? date('M d, Y h:i A', strtotime($r['reported_at'])) : '—';
$reportedBy  = $hasReportedBy ? htmlspecialchars($r['reported_by'] ?? '—') : '—';
$statusVal   = $hasStatus ? htmlspecialchars($r['status'] ?? 'active') : 'active';

/* NEW: derive hasPhoto safely from the selected alias */
$hasPhoto = !empty($r['has_photo']) && (int)$r['has_photo'] === 1;

/* Flatten offense_details chips */
$detailsText = '—';
if (!empty($r['offense_details'])) {
  $decoded = json_decode($r['offense_details'], true);
  if (is_array($decoded) && count($decoded)) {
    $safe = array_map(fn($x) => htmlspecialchars($x), $decoded);
    $detailsText = implode(', ', $safe);
  }
}

/* Build inner content (used by modal and full page) */
ob_start(); ?>
<div class="violation-view">
  <p><strong>violation #</strong> <?= $violationNo ?></p>
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
      <a class="btn" href="sms:<?= htmlspecialchars(preg_replace('/\s+/', '', $guardianMobile)) ?>?&body=Regarding%20violation%20%23<?= $violationNo ?>">💬 SMS Guardian</a>
    <?php else: ?>
      <button class="btn" disabled title="No guardian mobile on file">📞 Contact Guardian</button>
    <?php endif; ?>

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
    <p><strong>photo evidence:</strong> <?= $hasPhoto ? '' : '—' ?></p>
    <?php if ($hasPhoto): ?>
      <div class="photo-wrap" style="margin-top:8px">
        <img src="violation_photo.php?id=<?= $violationNo ?>" alt="Evidence photo"
             style="max-width:100%;border-radius:10px;display:block">
        <div class="photo-actions" style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
          <a class="btn" href="violation_photo.php?id=<?= $violationNo ?>" target="_blank" rel="noopener">🔎 View full size</a>
          <a class="btn" href="violation_photo.php?id=<?= $violationNo ?>&download=1" download="violation_<?= $violationNo ?>.jpg">⬇️ Download</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$inner = ob_get_clean();

/* If modal, output fragment only */
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
  <p><a href="view_student.php?student_id=<?= htmlspecialchars($studentId ?: $r['student_id']) ?>">← Back to Student</a></p>
  <?= $inner ?>
</body>
</html>
