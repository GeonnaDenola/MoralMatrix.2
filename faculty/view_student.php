<?php
include '../config.php';
include '../includes/header.php';

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
  <link rel="stylesheet" href="/MoralMatrix/css/global.css">
</head>
<body>

<!-- ======= LEFT Sidesheet trigger + panel (uses global.css) ======= -->
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

  <div class="sidesheet-rail">
    <div id="pageButtons" class="drawer-pages">
      <?php include 'side_buttons.php'; ?>
    </div>
  </div>
</nav>
<!-- ======= /LEFT Sidesheet ======= -->

<div class="right-container">
  <?php if($student): ?>
    <div class="profile">
      <img src="<?= !empty($student['photo']) ? '../admin/uploads/'.htmlspecialchars($student['photo']) : 'placeholder.png' ?>" alt="Profile">
      <p><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
      <h2><?= htmlspecialchars($student['first_name'] . " " . $student['middle_name'] . " " . $student['last_name']) ?></h2>
      <p><strong>Course:</strong> <?= htmlspecialchars($student['course']) ?></p>
      <p><strong>Year Level:</strong> <?= htmlspecialchars($student['level']) ?></p>
      <p><strong>Section:</strong> <?= htmlspecialchars($student['section']) ?></p>
      <p><strong>Institute:</strong> <?= htmlspecialchars($student['institute']) ?></p>
      <p><strong>Guardian:</strong> <?= htmlspecialchars($student['guardian']) ?> (<?= htmlspecialchars($student['guardian_mobile']) ?>)</p>
      <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
      <p><strong>Mobile:</strong> <?= htmlspecialchars($student['mobile']) ?></p>
    </div>
  <?php else: ?>
    <p>Student not found.</p>
  <?php endif; ?>

  <div class="add-violation-btn">
    <a class="btn" href="<?= htmlspecialchars($selfDir) ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">Add Violation</a>
  </div>
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
