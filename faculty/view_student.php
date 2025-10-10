<?php
include '../config.php';
include '../includes/faculty_header.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (!isset($_GET['student_id'])) {
    die('No student selected.');
}

$studentId = $_GET['student_id'];
$stmt = $conn->prepare('SELECT * FROM student_account WHERE student_id = ?');
$stmt->bind_param('s', $studentId);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

$violations = [];
$stmtv = $conn->prepare('
    SELECT violation_id, offense_category, offense_type, offense_details, description, reported_at
    FROM student_violation
    WHERE student_id = ?
    ORDER BY reported_at DESC, violation_id DESC
');
$stmtv->bind_param('s', $studentId);
$stmtv->execute();
$resv = $stmtv->get_result();
while ($row = $resv->fetch_assoc()) {
    $violations[] = $row;
}
$stmtv->close();

$conn->close();

$selfDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Profile</title>
  <link rel="stylesheet" href="../css/faculty_view_student.css">
</head>
<body>
<div class="right-container">
  <?php if ($student): ?>
    <?php
      $fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . ($student['last_name'] ?? ''));
      if ($fullName === '') {
          $fullName = 'Unnamed student';
      }
      $course   = $student['course']   ?: '—';
      $level    = $student['level']    ?: '—';
      $section  = $student['section']  ?: '—';
      $inst     = $student['institute']?: '—';
      $photoSrc = !empty($student['photo']) ? '../admin/uploads/' . $student['photo'] : 'placeholder.png';
      $yearParts = [];
      if ($level !== '—') {
          $yearParts[] = 'Year ' . $level;
      }
      if ($section !== '—') {
          $yearParts[] = $section;
      }
      $yearLabel = $yearParts ? implode(' ', $yearParts) : 'Year/Section —';
    ?>
    <div class="profile-shell">
      <section class="profile-hero">
        <div class="hero-content">
          <div class="identity">
            <div class="portrait">
              <img src="<?= htmlspecialchars($photoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Student portrait of <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="headline">
              <span class="eyebrow">Student Profile</span>
              <h1><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></h1>
              <div class="badge-row">
                <span class="badge">ID: <?= htmlspecialchars($student['student_id'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="badge"><?= htmlspecialchars($inst, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="badge"><?= htmlspecialchars($course, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="badge"><?= htmlspecialchars($yearLabel, ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="actions">
                <a class="primary-btn" href="<?= htmlspecialchars($selfDir, ENT_QUOTES, 'UTF-8'); ?>/add_violation.php?student_id=<?= urlencode($studentId); ?>">
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
              <span><?= htmlspecialchars($course, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-row">
              <span>Institute</span>
              <span><?= htmlspecialchars($inst, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-row">
              <span>Year</span>
              <span><?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-row">
              <span>Section</span>
              <span><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
        </div>

        <div class="info-card">
          <h2>Contact</h2>
          <div class="info-list">
            <div class="info-row">
              <span>Email</span>
              <span><?= htmlspecialchars($student['email'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-row">
              <span>Mobile</span>
              <span><?= htmlspecialchars($student['mobile'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-row">
              <span>Guardian</span>
              <span><?= htmlspecialchars($student['guardian'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-row">
              <span>Guardian Mobile</span>
              <span><?= htmlspecialchars($student['guardian_mobile'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-row">
              <span>Address</span>
              <span><?= htmlspecialchars($student['address'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
        </div>
      </section>

      <section class="history-card">
        <header>
          <h2>Violation History</h2>
          <span class="badge neutral">
            <?= count($violations); ?> record<?= count($violations) === 1 ? '' : 's'; ?>
          </span>
        </header>

        <?php if ($violations): ?>
          <div class="timeline">
            <?php foreach ($violations as $violation): ?>
              <?php
                $reportedAt = !empty($violation['reported_at']) ? date('M d, Y', strtotime($violation['reported_at'])) : 'Date unavailable';
                $category   = $violation['offense_category'] ?: 'Uncategorized';
                $offense    = $violation['offense_type'] ?: 'Violation';
                $details    = $violation['offense_details'] ?: ($violation['description'] ?: 'No additional details provided.');
              ?>
              <div class="timeline-item">
                <h3><?= htmlspecialchars($offense, ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="meta">
                  <span><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></span>
                  <span><?= htmlspecialchars($reportedAt, ENT_QUOTES, 'UTF-8'); ?></span>
                  <span>#<?= htmlspecialchars($violation['violation_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <p><?= htmlspecialchars($details, ENT_QUOTES, 'UTF-8'); ?></p>
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
