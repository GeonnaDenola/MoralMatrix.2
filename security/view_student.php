<?php
include '../config.php';
include '../includes/security_header.php';
include __DIR__ . '/_scanner.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

if (!isset($_GET['student_id'])) { die("No student selected."); }

$student_id = $_GET['student_id'];
$sql = "SELECT * FROM student_account WHERE student_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

/* ==== FETCH VIOLATIONS (kept if you need later) ==== */
$violations = [];
$sqlv = "SELECT violation_id, offense_category, offense_type, offense_details, description, reported_at
         FROM student_violation
         WHERE student_id = ?
         ORDER BY reported_at DESC, violation_id DESC";
$stmtv = $conn->prepare($sqlv);
$stmtv->bind_param("s", $student_id);
$stmtv->execute();
$resv = $stmtv->get_result();
while ($row = $resv->fetch_assoc()) { $violations[] = $row; }
$stmtv->close();

$conn->close();

$selfDir = rtrim(str_replace('\\','/', dirname($_SERVER['PHP_SELF'])), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Profile</title>
  <link rel="stylesheet" href="../css/security_view_student.css">
</head>
<body>



<div class="right-container">
  <?php if($student): ?>
    <?php
      $fullName = trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name']);
      $course   = $student['course'] ?: 'â€”';
      $level    = $student['level'] ?: 'â€”';
      $section  = $student['section'] ?: 'â€”';
      $inst     = $student['institute'] ?: 'â€”';
      $photoSrc = !empty($student['photo']) ? '../admin/uploads/' . $student['photo'] : 'placeholder.png';
      $yearParts = [];
      if ($level !== 'â€”' && $level !== '') {
        $yearParts[] = 'Year ' . $level;
      }
      if ($section !== 'â€”' && $section !== '') {
        $yearParts[] = $section;
      }
      $yearLabel = $yearParts ? implode(' ', $yearParts) : 'Year/Section â€”';
    ?>
    <div class="profile-shell">
      <section class="profile-hero">
        <div class="hero-content">
          <div class="identity">
            <div class="portrait">
              <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Student portrait of <?= htmlspecialchars($fullName) ?>">
            </div>
            <div class="headline">
              <span class="eyebrow">Student Profile</span>
              <h1><?= htmlspecialchars($fullName) ?></h1>
              <div class="badge-row">
                <span class="badge">ID: <?= htmlspecialchars($student['student_id']) ?></span>
                <span class="badge"><?= htmlspecialchars($inst) ?></span>
                <span class="badge"><?= htmlspecialchars($course) ?></span>
                <span class="badge"><?= htmlspecialchars($yearLabel) ?></span>
              </div>
              <div class="actions">
                <a class="primary-btn" href="<?= htmlspecialchars($selfDir) ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">
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
            <div class="info-row">
              <span>Course</span>
              <span><?= htmlspecialchars($course) ?></span>
            </div>
            <div class="info-row">
              <span>Institute</span>
              <span><?= htmlspecialchars($inst) ?></span>
            </div>
            <div class="info-row">
              <span>Year</span>
              <span><?= htmlspecialchars($level) ?></span>
            </div>
            <div class="info-row">
              <span>Section</span>
              <span><?= htmlspecialchars($section) ?></span>
            </div>
          </div>
        </div>

        <div class="info-card">
          <h2>Contact</h2>
          <div class="info-list">
            <div class="info-row">
              <span>Email</span>
              <span><?= htmlspecialchars($student['email'] ?: 'â€”') ?></span>
            </div>
            <div class="info-row">
              <span>Mobile</span>
              <span><?= htmlspecialchars($student['mobile'] ?: 'â€”') ?></span>
            </div>
            <div class="info-row">
              <span>Guardian</span>
              <span><?= htmlspecialchars($student['guardian'] ?: 'â€”') ?></span>
            </div>
            <div class="info-row">
              <span>Guardian Mobile</span>
              <span><?= htmlspecialchars($student['guardian_mobile'] ?: 'â€”') ?></span>
            </div>
            <div class="info-row">
              <span>Address</span>
              <span><?= htmlspecialchars($student['address'] ?? 'â€”') ?></span>
            </div>
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

        <?php if (count($violations)): ?>
          <div class="timeline">
            <?php foreach ($violations as $violation): ?>
              <?php
                $reportedAt = !empty($violation['reported_at']) ? date('M d, Y', strtotime($violation['reported_at'])) : 'Date unavailable';
                $category   = $violation['offense_category'] ?: 'Uncategorized';
                $offense    = $violation['offense_type'] ?: 'Violation';
                $details    = $violation['offense_details'] ?: ($violation['description'] ?: 'No additional details provided.');
              ?>
              <div class="timeline-item">
                <h3><?= htmlspecialchars($offense) ?></h3>
                <div class="meta">
                  <span><?= htmlspecialchars($category) ?></span>
                  <span><?= htmlspecialchars($reportedAt) ?></span>
                  <span>#<?= htmlspecialchars($violation['violation_id']) ?></span>
                </div>
                <p><?= htmlspecialchars($details) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            This student does not have any recorded violations yet.
          </div>
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
    const focusables = container.querySelectorAll(
      'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
    );
    if (!focusables.length) return;
    const first = focusables[0];
    const last  = focusables[focusables.length - 1];

    if (e.key === 'Tab') {
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault(); last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault(); first.focus();
      }
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

  // Optional: close when clicking a same-tab nav link
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

