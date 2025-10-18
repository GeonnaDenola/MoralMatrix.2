<?php
session_start(); // <-- ensure this is before any output

include '../config.php';
require __DIR__.'/_scanner.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_token'];

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

// --- detect void/audit columns ---
$hasIsVoid = $hasVoidReason = $hasVoidedBy = $hasVoidedAt = false;

if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'is_void'"))     { $hasIsVoid = (bool)$res->num_rows; $res->close(); }
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'void_reason'")) { $hasVoidReason = (bool)$res->num_rows; $res->close(); }
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'voided_by'"))   { $hasVoidedBy = (bool)$res->num_rows; $res->close(); }
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'voided_at'"))   { $hasVoidedAt = (bool)$res->num_rows; $res->close(); }

/* Fetch violation */
$r = null;
if ($violationId > 0) {
  $cols = "violation_id, student_id, offense_category, offense_type, offense_details, description, reported_at, photo";
  if ($hasReportedBy) $cols .= ", reported_by";
  if ($hasStatus)     $cols .= ", status";
  if ($hasIsVoid)     $cols .= ", is_void";
  if ($hasVoidReason) $cols .= ", void_reason";
  if ($hasVoidedBy)   $cols .= ", voided_by";
  if ($hasVoidedAt)   $cols .= ", voided_at";

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

/* ===== Community Service status for THIS violation ===== */
$csAssigned      = false;
$assignedToName  = null;
$assignedAt      = null;
$requiredForThis = 0;   // per-violation required hours
$loggedForThis   = 0.0; // sum of entries tied to this violation
$remainingForThis= 0.0;
$hasCsEntriesTable = false;

if ($res = $conn->query("SHOW TABLES LIKE 'community_service_entries'")) {
  $hasCsEntriesTable = ($res->num_rows > 0);
  $res->close();
}

/* Is this violation assigned to community service? (schema-robust) */
if ($r) {
  $hasAssignCol = false;
  $hasViolCol   = false;

  if ($res = $conn->query("SHOW COLUMNS FROM validator_student_assignment LIKE 'assignment_id'")) {
    $hasAssignCol = ($res->num_rows > 0); $res->close();
  }
  if ($res = $conn->query("SHOW COLUMNS FROM validator_student_assignment LIKE 'violation_id'")) {
    $hasViolCol = ($res->num_rows > 0); $res->close();
  }

  if ($hasAssignCol && $hasViolCol) {
    $sqlA = "SELECT a.validator_id, a.assigned_at, v.v_username
             FROM validator_student_assignment a
             LEFT JOIN validator_account v ON v.validator_id = a.validator_id
             WHERE a.student_id = ? AND (a.assignment_id = ? OR a.violation_id = ?) LIMIT 1";
    $stA = $conn->prepare($sqlA);
    if (!$stA) { die('Prepare failed: ' . $conn->error); }
    $stA->bind_param("sii", $r['student_id'], $violationId, $violationId);

  } elseif ($hasViolCol) {
    $sqlA = "SELECT a.validator_id, a.assigned_at, v.v_username
             FROM validator_student_assignment a
             LEFT JOIN validator_account v ON v.validator_id = a.validator_id
             WHERE a.violation_id = ? AND a.student_id = ? LIMIT 1";
    $stA = $conn->prepare($sqlA);
    if (!$stA) { die('Prepare failed: ' . $conn->error); }
    $stA->bind_param("is", $violationId, $r['student_id']);

  } else { // fallback: assignment_id only
    $sqlA = "SELECT a.validator_id, a.assigned_at, v.v_username
             FROM validator_student_assignment a
             LEFT JOIN validator_account v ON v.validator_id = a.validator_id
             WHERE a.assignment_id = ? AND a.student_id = ? LIMIT 1";
    $stA = $conn->prepare($sqlA);
    if (!$stA) { die('Prepare failed: ' . $conn->error); }
    $stA->bind_param("is", $violationId, $r['student_id']);
  }

  $stA->execute();
  $as = $stA->get_result()->fetch_assoc();
  $stA->close();

  if ($as) {
    $csAssigned     = true;
    $assignedAt     = $as['assigned_at'] ?? null;
    $assignedToName = $as['v_username'] ?? null;
  }
}

/* Required hours (simple per-violation rule) */
$rawCat = strtolower((string)($r['offense_category'] ?? ''));
$isGrave = (bool)(preg_match('/\bgrave\b/', $rawCat) && !preg_match('/\bless\b/', $rawCat));
$requiredForThis = $isGrave ? 20 : 10;

/* Logged hours */
if ($hasCsEntriesTable) {
  $stL = $conn->prepare("SELECT COALESCE(SUM(hours),0) AS total FROM community_service_entries WHERE student_id=? AND violation_id=?");
  if ($stL) {
    $stL->bind_param("si", $r['student_id'], $violationId);
    $stL->execute();
    $loggedForThis = (float)($stL->get_result()->fetch_assoc()['total'] ?? 0);
    $stL->close();
  }
} else {
  $loggedForThis = 0.0;
}
$remainingForThis = max(0, $requiredForThis - $loggedForThis);

/* Not found */
if (!$r) {
  if (isset($_GET['modal']) && $_GET['modal'] == '1') {
    echo '<div class="violation-view"><div class="nv-empty">Violation not found.</div></div>';
    $conn->close();
    exit;
  } else {
    http_response_code(404); ?>
<!DOCTYPE html>
  <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Not found</title>
      <style>
        :root{--bg:#f7f8fb;--card:#ffffff;--text:#0f172a;--muted:#64748b;--border:#e5e7eb;--accent:#dc2626}
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--text);font:16px/1.6 system-ui,Segoe UI,Roboto,Arial,sans-serif;display:grid;place-items:center;height:100dvh}
        .wrap{max-width:560px;width:92%;background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.06)}
        h1{margin:0 0 6px;font-size:22px}
        p{margin:0 0 16px;color:var(--muted)}
        a{display:inline-block;text-decoration:none;color:#fff;background:var(--accent);padding:10px 14px;border-radius:10px;font-weight:600}
        a:hover{opacity:.92}
      </style>
    </head>
    <body>
      <div class="wrap">
        <h1>Violation not found</h1>
        <p>The record you’re trying to view doesn’t exist or may have been removed.</p>
        <a href="view_student.php?student_id=<?= htmlspecialchars($studentId) ?>">← Back to Student</a>
      </div>
    </body>
    </html>
    <?php
    $conn->close();
    exit;
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
$isVoided = false;
if ($hasIsVoid) {
  $isVoided = ((int)($r['is_void'] ?? 0) === 1);
} else {
  $isVoided = (strtolower($r['status'] ?? '') === 'void');
}

/* Details / chips */
$detailsText = '—';
$detailsArr  = [];
if (!empty($r['offense_details'])) {
  $decoded = json_decode($r['offense_details'], true);
  if (is_array($decoded) && count($decoded)) {
    $safe = array_map(fn($x) => htmlspecialchars($x), $decoded);
    $detailsArr  = $safe;
    $detailsText = implode(', ', $safe);
  }
}

/* URLs */
$backTo   = 'view_student.php?student_id=' . rawurlencode($studentId ?: $r['student_id']);
$setCsUrl = 'set_community_service.php?student_id=' . urlencode($r['student_id'])
          . '&violation_id=' . urlencode((string)$violationNo)
          . '&return=' . urlencode($backTo);

$photoRel = 'uploads/placeholder.png';
if (!empty($r['photo'])) {
  $tryAbs = __DIR__ . '/uploads/' . $r['photo'];
  if (is_file($tryAbs)) $photoRel = 'uploads/' . $r['photo'];
}

/* Badge classes */
function statusBadgeClass($status){
  $s = strtolower((string)$status);
  return [
    'active'   => 'badge badge-ok',
    'open'     => 'badge badge-ok',
    'pending'  => 'badge badge-warn',
    'resolved' => 'badge badge-info',
    'closed'   => 'badge badge-info',
    'void'     => 'badge badge-danger',
  ][$s] ?? 'badge badge-muted';
}

/* Progress percent for CS */
$pct = $requiredForThis > 0 ? min(100, max(0, (int)round(($loggedForThis / $requiredForThis) * 100))) : 0;

ob_start(); ?>

<!-- ======= Scoped UI Styles (modal-safe) ======= -->
<style>
  .violation-view *{box-sizing:border-box}
  .violation-view{
    --bg:#ffffff;
    --surface:#ffffff;
    --text:#0f172a;
    --muted:#64748b;
    --border:#e5e7eb;
    --accent:#dc2626;    /* red to match MORAL MATRIX header */
    --ok:#16a34a; --warn:#b45309; --info:#0e7490; --danger:#b91c1c;
    color:var(--text);
    font:14.5px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  }

  /* Sticky toolbar at top of the scroll container */
  .nv-toolbar{
    position: sticky; top: 0; z-index: 40;
    background: linear-gradient(to bottom, var(--surface), rgba(255,255,255,.92));
    backdrop-filter: saturate(1.2) blur(2px);
    padding: 8px 0 12px;
    margin: -4px 0 12px;
    border-bottom: 1px solid var(--border);
  }
  .nv-toolbar .btn-back{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px; border-radius:12px;
    border:1px solid var(--border); background:#fff; text-decoration:none; color:inherit;
    font-weight:600;
  }
  .nv-toolbar .btn-back:hover{ border-color:var(--accent) }

  .nv-shell{display:grid; gap:14px}
  .nv-card{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:16px;
    box-shadow:0 10px 24px rgba(15,23,42,.04);
    padding:18px;
  }

  .nv-header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px}
  .nv-title{margin:0;font-size:20px;line-height:1.25}
  .nv-subtle{color:var(--muted)}
  .badge{display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:4px 10px;border-radius:999px;border:1px solid var(--border);background:#fff;white-space:nowrap}
  .badge-ok{border-color:#bbf7d0;color:#166534;background:#ecfdf5}
  .badge-info{border-color:#bae6fd;color:#075985;background:#eff6ff}
  .badge-warn{border-color:#fde68a;color:#92400e;background:#fffbeb}
  .badge-danger{border-color:#fecaca;color:#7f1d1d;background:#fee2e2}
  .badge-muted{color:var(--muted);background:#f8fafc}

  .nv-meta{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:10px}
  @media (max-width:1024px){.nv-meta{grid-template-columns:repeat(2,minmax(160px,1fr))}}
  @media (max-width:520px){.nv-meta{grid-template-columns:1fr}}

  .nv-item{border:1px dashed var(--border);border-radius:12px;padding:10px;min-height:64px;background:#fff}
  .nv-item b{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:6px}
  .nv-item span{font-size:15px}

  .nv-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:14px}
  @media (max-width:1024px){.nv-grid{grid-template-columns:1fr}}

  .chipset{display:flex;flex-wrap:wrap;gap:8px}
  .chip{display:inline-flex;align-items:center;border:1px solid var(--border);padding:6px 10px;border-radius:999px;font-size:13px;background:#fff}

  .section{display:grid;gap:12px}

  .btn{appearance:none;border:1px solid var(--border);background:#fff;color:var(--text);padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px}
  .btn:hover{border-color:var(--accent)}
  .btn:focus{outline:none;box-shadow:0 0 0 3px rgba(220,38,38,.20)}
  .btn-primary{background:var(--accent);color:#fff;border-color:transparent}
  .btn-primary:hover{filter:brightness(.98)}
  .btn-danger{border-color:#fecaca;color:var(--danger);background:#fee2e2}
  .btn[disabled]{opacity:.65;cursor:not-allowed}

  .nv-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}

  /* Photo panel */
  .nv-photo .ph-caption{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}
  .nv-photo img{max-width:100%;border-radius:12px;display:block;border:1px solid var(--border)}

  .nv-empty{padding:14px;border:1px dashed var(--border);border-radius:12px;color:var(--muted);text-align:center}

  /* Progress bar for CS */
  .bar{height:10px;border-radius:999px;background:#f1f5f9;border:1px solid var(--border);overflow:hidden}
  .bar>i{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--accent),#ef4444);transition:width .25s ease}

  /* Floating Back FAB (shows when sticky toolbar is off-screen) */
  .back-fab{
    position:fixed; right:22px; bottom:22px; z-index:900;
    display:none; align-items:center; gap:10px;
    padding:10px 14px; border-radius:999px; border:1px solid var(--border);
    background:#fff; text-decoration:none; color:var(--text); font-weight:700;
    box-shadow:0 10px 24px rgba(15,23,42,.12);
  }
  .back-fab svg{width:18px;height:18px}
  .back-fab.show{display:inline-flex}
</style>

<div class="violation-view">
  <!-- Sticky toolbar (works inside scrollable panel) -->
  <div class="nv-toolbar" id="nvToolbar">
    <a class="btn-back" href="<?= htmlspecialchars($backTo) ?>" aria-label="Back to student">
      <span aria-hidden="true">←</span>
      Back to Student
    </a>
  </div>

  <div class="nv-shell">
    <div class="nv-card">
      <div class="nv-header">
        <h1 class="nv-title">Violation <span class="nv-subtle">#<?= $violationNo ?></span></h1>
        <?php if ($hasStatus): ?>
          <span class="<?= statusBadgeClass($statusVal) ?>" title="Status">● <?= htmlspecialchars(ucfirst($statusVal)) ?></span>
        <?php endif; ?>
      </div>

      <!-- Summary grid -->
      <div class="nv-meta" role="group" aria-label="Summary">
        <div class="nv-item"><b>Student ID</b><span><?= htmlspecialchars($r['student_id']) ?></span></div>
        <div class="nv-item"><b>Ordinal</b><span>#<?= (int)$studentNo ?> for this student</span></div>
        <div class="nv-item"><b>Category</b><span><?= htmlspecialchars(ucfirst($cat)) ?></span></div>
        <div class="nv-item"><b>Type</b><span><?= $type ?: '—' ?></span></div>

        <!-- Community Service status -->
        <div class="nv-item" style="grid-column: 1 / -1">
          <b>Community Service</b>
          <?php if ($remainingForThis <= 0): ?>
            <div class="chipset">
              <span class="badge badge-ok">Completed · <?= (int)$requiredForThis ?>h</span>
              <?php if ($csAssigned && $assignedToName): ?>
                <span class="badge badge-info">Validator: <?= htmlspecialchars($assignedToName) ?></span>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="chipset" style="margin-bottom:6px">
              <?php if ($csAssigned): ?>
                <span class="badge badge-warn">In progress</span>
                <?php if ($assignedToName): ?>
                  <span class="badge badge-info">Validator: <?= htmlspecialchars($assignedToName) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-muted">Not assigned</span>
              <?php endif; ?>
              <span class="badge">Required: <?= (int)$requiredForThis ?>h</span>
              <span class="badge">Logged: <?= number_format($loggedForThis, 2) ?>h</span>
              <span class="badge badge-warn">Remaining: <?= number_format($remainingForThis, 2) ?>h</span>
            </div>
            <div class="bar" role="progressbar"
                 aria-label="Community service progress"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>">
              <i style="width:<?= $pct ?>%"></i>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Details chips -->
      <div class="nv-item" style="margin-top:12px">
        <b>Details</b>
        <?php if (count($detailsArr)): ?>
          <div class="chipset" aria-label="Offense details">
            <?php foreach ($detailsArr as $chip): ?>
              <span class="chip"><?= $chip ?></span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <span class="nv-subtle">—</span>
        <?php endif; ?>
      </div>

      <!-- Two-column content -->
      <div class="nv-grid" style="margin-top:14px">
        <!-- Left: Description + meta -->
        <section class="section nv-card" aria-labelledby="desc-label">
          <h2 id="desc-label" class="nv-subtle" style="margin:0 0 4px;font-size:13px;letter-spacing:.06em;text-transform:uppercase">Description</h2>
          <div class="nv-item" style="border:none;padding:0">
            <p style="white-space:pre-wrap;margin:0"><?= $desc ? nl2br($desc) : '<span class="nv-subtle">—</span>' ?></p>
          </div>

          <div class="nv-item" style="border:none;border-top:1px dashed var(--border);margin-top:10px;padding-top:10px">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
              <div><b>Reported On</b> <span><time datetime="<?= htmlspecialchars($r['reported_at'] ?? '') ?>"><?= $datePretty ?></time></span></div>
              <div><b>Reported By</b> <span><?= $reportedBy ?></span></div>
            </div>
          </div>

          <!-- Actions -->
          <div class="nv-actions">
            <?php $telClean = preg_replace('/[^+\\d]/', '', (string)$guardianMobile); ?>

            <!-- Contact Guardian -->
            <?php if (!empty($guardianMobile) && !empty($telClean)): ?>
              <form method="POST" id="contactGuardianForm" action="send_sms.php"
                    onsubmit="return confirm('Send SMS to guardian?');" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="student_id" value="<?= htmlspecialchars($r['student_id']) ?>">
                <input type="hidden" name="violation_id" value="<?= htmlspecialchars((string)$violationNo) ?>">
                <button type="submit" class="btn">📞 Contact Guardian<?= $guardianName ? ' — '.htmlspecialchars($guardianName) : '' ?></button>
              </form>
            <?php else: ?>
              <button class="btn" disabled title="No guardian mobile on file">📞 Contact Guardian</button>
            <?php endif; ?>

            <?php
              $logUrl = 'student_details.php?student_id=' . urlencode($r['student_id'])
                       . '&violation_id=' . urlencode((string)$violationNo)
                       . '&return=' . urlencode('violation_view.php?id='.$violationNo.'&student_id='.$r['student_id']);
            ?>

            <?php if ($isVoided): ?>
              <a class="btn btn-primary" href="#" disabled title="Violation is voided">🧹 Set for Community Service (Unavailable)</a>
            <?php elseif ($csAssigned): ?>
              <a class="btn btn-primary" href="<?= htmlspecialchars($logUrl) ?>">🧾 View Community Service Progress</a>
            <?php elseif ($remainingForThis <= 0): ?>
              <a class="btn" href="<?= htmlspecialchars($logUrl) ?>">🧾 View / Add Notes</a>
            <?php else: ?>
              <a class="btn btn-primary" href="<?= htmlspecialchars($setCsUrl) ?>">🧹 Set for Community Service</a>
            <?php endif; ?>

            <!-- Void Button -->
            <?php if (empty($isVoided)): ?>
              <form method="POST" action="void_violation.php"
                    onsubmit="return confirm('Void this violation?');"
                    style="display:inline">
                <input type="hidden" name="violation_id" value="<?= (int)$violationNo ?>">
                <input type="hidden" name="student_id" value="<?= htmlspecialchars($r['student_id']) ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                <button type="submit" class="btn btn-danger">🛑 Void</button>
              </form>
            <?php else: ?>
              <span class="badge badge-danger" title="Voided">● Voided</span>
            <?php endif; ?>
          </div>
        </section>

        <!-- Right: Photo -->
        <section class="nv-photo nv-card" aria-labelledby="photo-label">
          <div class="ph-caption">
            <h2 id="photo-label" style="margin:0;font-size:15px;font-weight:700">Photo Evidence</h2>
            <?php if ($photoRel === 'uploads/placeholder.png'): ?>
              <span class="badge badge-muted">No photo on file</span>
            <?php endif; ?>
          </div>
          <figure style="margin:0">
            <a class="js-lightbox" href="<?= htmlspecialchars($photoRel) ?>" target="_blank" rel="noopener">
              <img src="<?= htmlspecialchars($photoRel) ?>" alt="Evidence photo for violation #<?= $violationNo ?>">
            </a>
          </figure>
        </section>
      </div>
    </div>
  </div>

  <!-- Floating Back FAB (always available; toggles on scroll) -->
  <a id="backFab" class="back-fab" href="<?= htmlspecialchars($backTo) ?>" aria-label="Back to student">
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 11.5l5-5m-5 5l5 5M7 11.5h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Back to Student
  </a>
</div>

<?php
$inner = ob_get_clean();

/* Wrapper page (kept minimal so it still fits your app) */

if (isset($_GET['modal']) && $_GET['modal'] == '1') {
  echo $inner;
  $conn->close();
  exit;
}

// Show SMS status alert if redirected from send_sms.php
$smsAlert = '';
if (isset($_GET['sms_status'])) {
    $status = (int)$_GET['sms_status'];
    if ($status === 200 || $status === 201) {
        $smsAlert = "<div style='padding:10px;background:#d1fae5;color:#065f46;border:1px solid #059669;
                      margin:10px 0;border-radius:8px'>
                        ✅ SMS sent successfully to guardian.
                     </div>";
    } else {
        $smsAlert = "<div style='padding:10px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;
                      margin:10px 0;border-radius:8px'>
                        ⚠️ Failed to send SMS (code: {$status}). Please check guardian number or API logs.
                     </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Violation #<?= $violationNo ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--bg:#f7f8fb;--surface:#ffffff;--text:#0f172a;--muted:#64748b;--border:#e5e7eb;--accent:#dc2626}
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:var(--text);font:15px/1.6 system-ui,Segoe UI,Roboto,Arial,sans-serif}
    .container{max-width:1120px;margin:24px auto;padding:0 16px}
    .page{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:18px 16px 24px;box-shadow:0 12px 30px rgba(0,0,0,.06)}
    .page > header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px}
    .page h1{margin:0;font-size:20px}
    .sub{color:var(--muted);font-size:14px}
    .backline{margin-bottom:12px}
    .backline a{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:inherit;border:1px solid var(--border);padding:8px 12px;border-radius:10px;background:#fff}
    .backline a:hover{border-color:var(--accent)}
    footer{margin-top:18px;color:var(--muted);font-size:13px;text-align:center}
    @media print{.backline,footer{display:none}.page{box-shadow:none;border:1px solid #000}}

    /* Lightbox */
    .lb{position:fixed;inset:0;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;padding:20px;z-index:1000}
    .lb[hidden]{display:none}
    .lb img{max-width:100%;max-height:90vh;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.40)}
    .lb .lb-close{position:absolute;top:12px;right:12px;font-size:24px;background:#fff;border:1px solid #e5e7eb;border-radius:999px;width:36px;height:36px;line-height:34px;text-align:center;cursor:pointer}
  </style>
</head>
<body>
  <div class="container">
    <div class="backline">
      <a href="<?= htmlspecialchars($backTo) ?>" aria-label="Back to student"><span aria-hidden="true">←</span> Back to Student</a>
    </div>

    <div class="page">
      <header>
        <div>
          <h1>Student Violation</h1>
          <div class="sub">Record #<?= $violationNo ?> • Student <?= htmlspecialchars($r['student_id']) ?></div>
        </div>
      </header>

      <?= $smsAlert ?>

      <?php if (isset($_GET['void_status'])): ?>
        <?php if ($_GET['void_status'] === 'ok'): ?>
          <div style="padding:10px;background:#d1fae5;color:#065f46;border:1px solid #059669;margin:10px 0;border-radius:8px">
            ✅ Violation voided.
          </div>
        <?php else: ?>
          <div style="padding:10px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;margin:10px 0;border-radius:8px">
            ⚠️ Couldn’t void: <?= htmlspecialchars($_GET['msg'] ?? 'Unknown error') ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?= $inner ?>
    </div>

    <footer>© <?= date('Y') ?> Discipline Management</footer>
  </div>

  <!-- Lightbox -->
  <div id="photo-lightbox" class="lb" hidden>
    <button class="lb-close" aria-label="Close">×</button>
    <img src="" alt="">
  </div>

  <script>
  (function(){
    // Image Lightbox
    var lb = document.getElementById('photo-lightbox');
    if(lb){
      var imgEl = lb.querySelector('img');
      function closeLB(){ lb.setAttribute('hidden',''); imgEl.src=''; imgEl.alt=''; document.body.style.overflow=''; }
      document.addEventListener('click', function(e){
        var a = e.target.closest && e.target.closest('a.js-lightbox');
        if(!a) return;
        if (e.button === 1 || e.metaKey || e.ctrlKey) return;
        e.preventDefault();
        imgEl.src = a.getAttribute('href');
        var insideImg = a.querySelector('img');
        imgEl.alt = insideImg ? (insideImg.getAttribute('alt')||'Photo') : 'Photo';
        lb.removeAttribute('hidden');
        document.body.style.overflow='hidden';
      });
      lb.addEventListener('click', function(e){ if(e.target === lb || e.target.classList.contains('lb-close')) closeLB(); });
      document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && !lb.hasAttribute('hidden')) closeLB(); });
    }

    // Back FAB toggling (appears when sticky toolbar is not visible)
    var toolbar = document.getElementById('nvToolbar');
    var backFab = document.getElementById('backFab');

    function showFab(){ backFab && backFab.classList.add('show'); }
    function hideFab(){ backFab && backFab.classList.remove('show'); }

    // If IntersectionObserver is supported, toggle based on toolbar visibility.
    if ('IntersectionObserver' in window && toolbar && backFab) {
      var io = new IntersectionObserver(function(entries){
        var e = entries[0];
        if (!e || !e.isIntersecting) { showFab(); } else { hideFab(); }
      }, { threshold: 0, root: null });
      io.observe(toolbar);
    } else {
      // Fallback: always show FAB; better to have it than lose navigation.
      showFab();
    }
  })();
  </script>
</body>
</html>
<?php
$conn->close();
