<?php
require '../auth.php';
require_role('security');

require '../config.php';

// Optional scanner (must be silent)
@include __DIR__ . '/_scanner.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h(?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ===== DB ===== */
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset('utf8mb4');

/* ===== Input ===== */
if (!isset($_GET['student_id']) || $_GET['student_id'] === '') {
  http_response_code(400);
  echo "No student selected.";
  exit;
}
$student_id = (string)$_GET['student_id'];

/* ===== Student ===== */
$stmt = $conn->prepare("SELECT * FROM student_account WHERE student_id=?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ===== Violations (if you need to show later) ===== */
$violations = [];
$stmtv = $conn->prepare("
  SELECT violation_id, offense_category, offense_type, offense_details, description, reported_at
  FROM student_violation
  WHERE student_id = ?
  ORDER BY reported_at DESC, violation_id DESC
");
$stmtv->bind_param("s", $student_id);
$stmtv->execute();
$resv = $stmtv->get_result();
while ($row = $resv->fetch_assoc()) $violations[] = $row;
$stmtv->close();

$conn->close();

/* ===== Derived UI fields ===== */
$uploadsUrl = '/MoralMatrix/admin/uploads/';

$selfDir = rtrim(str_replace('\\','/', dirname($_SERVER['PHP_SELF'])), '/');
$cssUrl  = '/MoralMatrix/css/security_view_student.css';

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

<?php
// Header AFTER <body> so we can keep all redirects/header() above if needed in other pages
include '../includes/security_header.php';
?>

<div class="right-container">
  <?php if ($student): ?>
    <?php
      $first   = trim((string)($student['first_name']   ?? ''));
      $middle  = trim((string)($student['middle_name']  ?? ''));
      $last    = trim((string)($student['last_name']    ?? ''));
      $fullName = trim($first . ($middle ? " $middle" : '') . " $last");

      $course  = trim((string)($student['course']   ?? '')) ?: '—';
      $level   = trim((string)($student['level']    ?? '')) ?: '—';
      $section = trim((string)($student['section']  ?? '')) ?: '—';
      $inst    = trim((string)($student['institute']?? '')) ?: '—';

      $photoFile = trim((string)($student['photo'] ?? ''));
      $photoSrc  = $photoFile !== '' ? $uploadsUrl . rawurlencode($photoFile) : $uploadsUrl . 'placeholder.png';

      $yearParts = [];
      if ($level   !== '—') $yearParts[] = "Year $level";
      if ($section !== '—') $yearParts[] = $section;
      $yearLabel = $yearParts ? implode(' ', $yearParts) : 'Year/Section —';
    ?>
    <div class="profile-shell">
      <section class="profile-hero">
        <div class="hero-content">
          <div class="identity">
            <div class="portrait">
              <img
                src="<?= h($photoSrc) ?>"
                alt="Student portrait of <?= h($fullName) ?>"
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
                <a class="primary-btn" href="<?= h($selfDir) ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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
            <div class="info-row"><span>Email</span><span><?= h($student['email']   ?? '—') ?></span></div>
            <div class="info-row"><span>Mobile</span><span><?= h($student['mobile']  ?? '—') ?></span></div>
            <div class="info-row"><span>Guardian</span><span><?= h($student['guardian'] ?? '—') ?></span></div>
            <div class="info-row"><span>Guardian Mobile</span><span><?= h($student['guardian_mobile'] ?? '—') ?></span></div>
            <div class="info-row"><span>Address</span><span><?= h($student['address'] ?? '—') ?></span></div>
          </div>
        </div>
      </section>

      <section class="history-card">
        <header>
          <h2>Violation History</h2>
          <span class="badge neutral"><?= count($violations) ?> record<?= count($violations) === 1 ? '' : 's' ?></span>
        </header>

        <?php if ($violations): ?>
          <div class="timeline">
            <?php foreach ($violations as $v): ?>
              <?php
                $reportedAt = !empty($v['reported_at']) ? date('M d, Y', strtotime($v['reported_at'])) : 'Date unavailable';
                $category   = $v['offense_category'] ?: 'Uncategorized';
                $offense    = $v['offense_type']     ?: 'Violation';
                $detailsRaw = $v['offense_details'] ?: ($v['description'] ?: 'No additional details provided.');
              ?>
              <div class="timeline-item">
                <h3><?= h($offense) ?></h3>
                <div class="meta">
                  <span><?= h($category) ?></span>
                  <span><?= h($reportedAt) ?></span>
                  <span>#<?= h((string)$v['violation_id']) ?></span>
                </div>
                <p><?= h($detailsRaw) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">This student does not have any recorded violations yet.</div>
        <?php endif; ?>
      </section>
    </div>
  <?php else: ?>
    <div class="not-found">Student not found.</div>
  <?php endif; ?>
</div>

<script>
/* ======== LEFT Sidesheet: open/close + focus trap ======== */
(function(){
  const sheet   = document.getElementById('sideSheet');
  const scrim   = document.getElementById('sheetScrim');
  const openBtn = document.getElementById('openMenu');
  const closeBtn= document.getElementById('closeMenu');

  if (!sheet || !scrim || !openBtn || !closeBtn) return;

  let lastFocusedEl = null;

  function trapFocus(container, e){
    const focusables = container.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])');
    if (!focusables.length) return;
    const first = focusables[0];
    const last  = focusables[focusables.length - 1];

    if (e.key === 'Tab') {
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  }
  const focusTrapHandler = (e)=>trapFocus(sheet, e);

  function openSheet(){
    lastFocusedEl = document.activeElement;
    sheet.classList.add('open');
    scrim.classList.add('open');
    sheet.setAttribute('aria-hidden','false');
    scrim.setAttribute('aria-hidden','false');
    openBtn.setAttribute('aria-expanded','true');
    document.body.classList.add('no-scroll');
    setTimeout(()=>{
      const f = sheet.querySelector('#pageButtons a, #pageButtons button, [tabindex]:not([tabindex="-1"])');
      (f || sheet).focus();
    }, 10);
    sheet.addEventListener('keydown', focusTrapHandler);
  }

  function closeSheet(){
    sheet.classList.remove('open');
    scrim.classList.remove('open');
    sheet.setAttribute('aria-hidden','true');
    scrim.setAttribute('aria-hidden','true');
    openBtn.setAttribute('aria-expanded','false');
    document.body.classList.remove('no-scroll');
    sheet.removeEventListener('keydown', focusTrapHandler);
    if (lastFocusedEl) lastFocusedEl.focus();
  }

  openBtn.addEventListener('click', openSheet);
  closeBtn.addEventListener('click', closeSheet);
  scrim.addEventListener('click', closeSheet);
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeSheet(); });

  sheet.addEventListener('click', (e)=>{
    const link = e.target.closest('a[href]');
    if (!link) return;
    const sameTab = !(e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0);
    if (sameTab) closeSheet();
  });
})();
</script>
</body>
</html>
