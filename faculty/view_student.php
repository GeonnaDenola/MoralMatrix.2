<?php
include '../config.php';
include '../includes/faculty_header.php';

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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Profile</title>
  <link rel="stylesheet" href="../css/faculty_view_student.css" />
</head>
<body>

<!-- ======= LEFT Sidesheet trigger + panel (uses your global.css) ======= -->
<button id="openMenu" class="menu-launcher" aria-controls="sideSheet" aria-expanded="false">Menu</button>
<div class="page-top-pad"></div>

<!-- Scrim -->
<div id="sheetScrim" class="sidesheet-scrim" aria-hidden="true"></div>

<!-- LEFT Sidesheet (drawer) -->
<nav id="sideSheet" class="sidesheet" aria-hidden="true" role="dialog" aria-label="Main menu" tabindex="-1">
  <div class="sidesheet-header">
    <span>Menu</span>
    <button id="closeMenu" class="sidesheet-close" aria-label="Close menu">✕</button>
  </div>
  <!-- Put your menu links here if needed -->
</nav>
<!-- ======= /LEFT Sidesheet ======= -->

<!-- ======= Right content container (centered) ======= -->
<div class="right-container">
  <?php if ($student): ?>
    <section class="profile" aria-labelledby="student-name">
      <div class="avatar">
        <img
          src="<?= !empty($student['photo']) ? '../admin/uploads/'.htmlspecialchars($student['photo']) : 'placeholder.png' ?>"
          alt="Student photo"
          loading="lazy"
        />
      </div>

      <div class="profile-body">
        <p class="student-id">
          <span>Student ID</span>
          <b><?= htmlspecialchars($student['student_id']) ?></b>
        </p>

        <h2 id="student-name" class="name">
          <?= htmlspecialchars(trim($student['first_name'] . " " . $student['middle_name'] . " " . $student['last_name'])) ?>
        </h2>

        <div class="details">
          <p><strong>Course</strong><span><?= htmlspecialchars($student['course']) ?></span></p>
          <p><strong>Year Level</strong><span><?= htmlspecialchars($student['level']) ?></span></p>
          <p><strong>Section</strong><span><?= htmlspecialchars($student['section']) ?></span></p>
          <p><strong>Institute</strong><span><?= htmlspecialchars($student['institute']) ?></span></p>
          <p><strong>Guardian</strong><span><?= htmlspecialchars($student['guardian']) ?> (<?= htmlspecialchars($student['guardian_mobile']) ?>)</span></p>
          <p><strong>Email</strong><span><?= htmlspecialchars($student['email']) ?></span></p>
          <p><strong>Mobile</strong><span><?= htmlspecialchars($student['mobile']) ?></span></p>
        </div>

        <div class="actions">
          <a class="btn" href="<?= htmlspecialchars($selfDir) ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">
            Add Violation
          </a>
        </div>
      </div>
    </section>
  <?php else: ?>
    <p class="empty-state">Student not found.</p>
  <?php endif; ?>
</div>
<!-- ======= /Right content container ======= -->

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
