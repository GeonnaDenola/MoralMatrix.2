<?php
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
    echo '<div class="violation-view"><div class="nv-empty">Violation not found.</div></div>';
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
        :root{--bg:#f7f8fb;--card:#ffffff;--text:#0f172a;--muted:#64748b;--border:#e5e7eb;--accent:#2563eb}
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
$detailsArr  = [];
if (!empty($r['offense_details'])) {
  $decoded = json_decode($r['offense_details'], true);
  if (is_array($decoded) && count($decoded)) {
    $safe = array_map(fn($x) => htmlspecialchars($x), $decoded);
    $detailsArr  = $safe;
    $detailsText = implode(', ', $safe);
  }
}

/* Build return + Set CS URL */
$backTo  = 'view_student.php?student_id=' . rawurlencode($studentId ?: $r['student_id']);
$setCsUrl = 'set_community_service.php?student_id=' . urlencode($r['student_id'])
          . '&violation_id=' . urlencode((string)$violationNo)
          . '&return=' . urlencode($backTo);

/* Determine photo path */
$photoRel = 'uploads/placeholder.png';
if (!empty($r['photo'])) {
  $tryAbs = __DIR__ . '/uploads/' . $r['photo'];
  if (is_file($tryAbs)) $photoRel = 'uploads/' . $r['photo'];
}

/* Helper: map status to badge classes */
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

ob_start(); ?>

<!-- Scoped styles to ensure good look when used as modal content (light theme only) -->
<style>
  .violation-view *{box-sizing:border-box}
  .violation-view{--bg:#ffffff;--card:#ffffff;--text:#0f172a;--muted:#64748b;--border:#e5e7eb;--accent:#2563eb;--ok:#16a34a;--warn:#b45309;--info:#0e7490;--danger:#b91c1c}
  .violation-view{color:var(--text)}
  .nv-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px}
  .nv-header{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:14px}
  .nv-title{font-size:22px;line-height:1.2;margin:0}
  .nv-subtle{color:var(--muted)}
  .nv-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:6px 0 0}
  .nv-item{background:#fff;border:1px dashed var(--border);border-radius:12px;padding:10px}
  .nv-item b{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:6px}
  .nv-item span{font-size:15px}
  .nv-chips{display:flex;flex-wrap:wrap;gap:8px}
  .chip{display:inline-flex;align-items:center;border:1px solid var(--border);padding:6px 10px;border-radius:999px;font-size:13px;background:#fff}
  .nv-desc{margin-top:10px}
  .nv-desc p{white-space:pre-wrap;margin:0}
  .nv-grid{display:grid;grid-template-columns:1fr;gap:12px}
  @media (min-width:900px){.nv-grid{grid-template-columns:1.2fr .8fr}}
  .nv-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
  .btn{appearance:none;border:1px solid var(--border);background:#fff;color:var(--text);padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px}
  .btn:hover{border-color:var(--accent)}
  .btn:focus{outline:none;box-shadow:0 0 0 3px rgba(37,99,235,.25)}
  .btn-primary{background:var(--accent);color:#fff;border-color:transparent}
  .btn-primary:hover{opacity:.95}
  .btn-danger{border-color:#fecaca;color:var(--danger);background:#fee2e2}
  .btn-danger:hover{filter:brightness(.98)}
  .btn[disabled]{opacity:.65;cursor:not-allowed}
  .badge{display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:4px 10px;border-radius:999px;border:1px solid var(--border);background:#fff}
  .badge-ok{border-color:#bbf7d0;color:#166534;background:#ecfdf5}
  .badge-info{border-color:#bae6fd;color:#075985;background:#eff6ff}
  .badge-warn{border-color:#fde68a;color:#92400e;background:#fffbeb}
  .badge-danger{border-color:#fecaca;color:#7f1d1d;background:#fee2e2}
  .badge-muted{color:var(--muted);background:#f8fafc}
  .nv-photo{background:#fff;border:1px solid var(--border);border-radius:16px;padding:12px}
  .nv-photo img{max-width:100%;border-radius:12px;display:block}
  .nv-photo .ph-caption{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}
  .nv-photo a{display:block}
  .nv-empty{padding:14px;border:1px dashed var(--border);border-radius:12px;color:var(--muted);text-align:center}
  .nv-toolbar{position:sticky;top:0;z-index:3;background:#ffffff;backdrop-filter:saturate(1.2);padding:10px 0 14px;margin:-6px 0 12px}
  .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
  @media print{.nv-actions,.nv-toolbar a{display:none}.violation-view{color:black}.nv-card{border:1px solid #000}}
</style>

<div class="violation-view">
  <div class="nv-toolbar">
    <a class="btn" href="<?= htmlspecialchars($backTo) ?>" aria-label="Back to student">
      <span aria-hidden="true">←</span> Back to Student
    </a>
  </div>

  <div class="nv-card">
    <div class="nv-header">
      <h1 class="nv-title">Violation <span class="nv-subtle">#<?= $violationNo ?></span></h1>
      <?php if ($hasStatus): ?>
        <span class="<?= statusBadgeClass($statusVal) ?>" title="Status">● <?= htmlspecialchars(ucfirst($statusVal)) ?></span>
      <?php endif; ?>
    </div>

    <div class="nv-meta" role="group" aria-label="Summary">
      <div class="nv-item"><b>Student ID</b><span><?= htmlspecialchars($r['student_id']) ?></span></div>
      <div class="nv-item"><b>Ordinal</b><span>#<?= (int)$studentNo ?> for this student</span></div>
      <div class="nv-item"><b>Category</b><span><?= htmlspecialchars(ucfirst($cat)) ?></span></div>
      <div class="nv-item"><b>Type</b><span><?= $type ?: '—' ?></span></div>
    </div>

    <div class="nv-item" style="margin-top:10px">
      <b>Details</b>
      <?php if (count($detailsArr)): ?>
        <div class="nv-chips" aria-label="Offense details">
          <?php foreach ($detailsArr as $chip): ?>
            <span class="chip"><?= $chip ?></span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <span class="nv-subtle">—</span>
      <?php endif; ?>
    </div>

    <div class="nv-grid" style="margin-top:12px">
      <section class="nv-desc nv-card" aria-labelledby="desc-label">
        <h2 id="desc-label" class="sr-only">Description</h2>
        <div class="nv-item" style="border:none;padding:0">
          <b>Description</b>
          <p><?= $desc ? nl2br($desc) : '<span class="nv-subtle">—</span>' ?></p>
        </div>
        <div class="nv-item" style="border:none;border-top:1px dashed var(--border);margin-top:12px;padding-top:12px">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
            <div><b>Reported On</b> <span><time datetime="<?= htmlspecialchars($r['reported_at'] ?? '') ?>"><?php echo $datePretty; ?></time></span></div>
            <div><b>Reported By</b> <span><?= $reportedBy ?></span></div>
          </div>
        </div>

        <div class="nv-actions">
          <?php
            $telClean = preg_replace('/[^+\\d]/', '', (string)$guardianMobile);
          ?>
          <?php if (!empty($guardianMobile) && !empty($telClean)): ?>
            <a class="btn" href="tel:<?= htmlspecialchars($telClean) ?>">📞 Contact Guardian<?= $guardianName ? ' — '.htmlspecialchars($guardianName) : '' ?></a>
          <?php else: ?>
            <button class="btn" disabled title="No guardian mobile on file">📞 Contact Guardian</button>
          <?php endif; ?>

          <a class="btn btn-primary" href="<?= $setCsUrl ?>">🧹 Set for Community Service</a>
          <a class="btn" href="violation_edit.php?id=<?= $violationNo ?>&student_id=<?= urlencode($r['student_id']) ?>">✏️ Edit</a>

          <?php if ($hasStatus && strtolower($statusVal) !== 'void'): ?>
            <form method="POST" action="violation_void.php" onsubmit="return confirm('Void this violation?');" style="display:inline">
              <input type="hidden" name="id" value="<?= $violationNo ?>">
              <input type="hidden" name="student_id" value="<?= htmlspecialchars($r['student_id']) ?>">
              <button type="submit" class="btn btn-danger">🛑 Void</button>
            </form>
          <?php elseif ($hasStatus): ?>
            <span class="badge badge-danger" title="Voided">● Voided</span>
          <?php endif; ?>
        </div>
      </section>

      <section class="nv-photo nv-card" aria-labelledby="photo-label">
        <div class="ph-caption">
          <h2 id="photo-label" style="margin:0;font-size:15px">Photo Evidence</h2>
          <?php if ($photoRel === 'uploads/placeholder.png'): ?>
            <span class="badge badge-muted">No photo on file</span>
          <?php endif; ?>
        </div>
        <figure style="margin:0">
          <!-- Clicking opens new tab by default; if full page (not modal), JS lightbox will also intercept -->
          <a class="js-lightbox" href="<?= htmlspecialchars($photoRel) ?>" target="_blank" rel="noopener">
            <img src="<?= htmlspecialchars($photoRel) ?>" alt="Evidence photo for violation #<?= $violationNo ?>">
          </a>
        </figure>
      </section>
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
    :root{--bg:#f7f8fb;--surface:#ffffff;--text:#0f172a;--muted:#64748b;--border:#e5e7eb}
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;background:linear-gradient(180deg,#f9fafb, #f7f8fb);color:var(--text);font:16px/1.6 system-ui,Segoe UI,Roboto,Arial,sans-serif}
    .container{max-width:1040px;margin:24px auto;padding:0 16px}
    .page{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:18px 16px 24px;box-shadow:0 12px 30px rgba(0,0,0,.06)}
    .page > header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px}
    .page h1{margin:0;font-size:20px}
    .sub{color:var(--muted);font-size:14px}
    .backline{margin-bottom:12px}
    .backline a{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:inherit;border:1px solid var(--border);padding:8px 12px;border-radius:10px;background:#fff}
    .backline a:hover{border-color:#2563eb}
    footer{margin-top:18px;color:var(--muted);font-size:13px;text-align:center}
    @media print{.backline,footer{display:none}.page{box-shadow:none;border:1px solid #000}}

    /* Simple lightbox */
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
      <?= $inner ?>
    </div>
    <footer>© <?= date('Y') ?> Discipline Management</footer>
  </div>

  <!-- Lightbox root (only used on full page, not inside modal=1) -->
  <div id="photo-lightbox" class="lb" hidden>
    <button class="lb-close" aria-label="Close">×</button>
    <img src="" alt="">
  </div>

  <script>
  (function(){
    var lb = document.getElementById('photo-lightbox');
    if(!lb) return;
    var imgEl = lb.querySelector('img');
    function closeLB(){ lb.setAttribute('hidden',''); imgEl.src=''; imgEl.alt=''; document.body.style.overflow=''; }
    document.addEventListener('click', function(e){
      var a = e.target.closest && e.target.closest('a.js-lightbox');
      if(!a) return;
      // If user explicitly wants new tab, let default happen if they middle-click / use modifier keys
      if (e.button === 1 || e.metaKey || e.ctrlKey) return; 
      e.preventDefault();
      imgEl.src = a.getAttribute('href');
      var insideImg = a.querySelector('img');
      imgEl.alt = insideImg ? (insideImg.getAttribute('alt')||'Photo') : 'Photo';
      lb.removeAttribute('hidden');
      document.body.style.overflow='hidden';
    });
    lb.addEventListener('click', function(e){
      if(e.target === lb || e.target.classList.contains('lb-close')) closeLB();
    });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && !lb.hasAttribute('hidden')) closeLB(); });
  })();
  </script>
</body>
</html>
