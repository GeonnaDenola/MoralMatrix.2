<?php
// faculty/dashboard.php
require '../auth.php';
require_role('faculty');

include '../config.php';
include '../includes/header.php';

// DB connect
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// current faculty id (id_number stored in session by your login)
$faculty_id = $_SESSION['actor_id'] ?? null;
if (!$faculty_id) {
    die("No faculty id in session. Please login again.");
}

/*
  Visibility rule:
  - Faculty sees APPROVED violations for students in their INSTITUTE.
  - This works even if Security submitted the violation.
  If instead you want "violations the faculty submitted", replace the JOIN/WHERE
  with: WHERE sv.submitted_by = ? AND sv.status='approved' and bind $faculty_id.
*/
$sql = "
SELECT sv.violation_id,
       sv.student_id,
       s.photo AS student_photo,
       s.first_name,
       s.last_name,
       sv.offense_category,
       sv.offense_type,
       sv.description,
       sv.reported_at,
       sv.status
FROM student_violation sv
JOIN student_account s ON sv.student_id = s.student_id
WHERE sv.submitted_by = ?
  AND sv.status = 'approved'
ORDER BY sv.reported_at DESC, sv.violation_id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("s", $faculty_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Faculty — Approved Violations</title>
<link rel="stylesheet" href="/MoralMatrix/css/global.css">
<style>
/* tiny card styles (page-local) */
.violations { padding: 12px; max-width: 980px; margin: 0 auto; }
.card-link { text-decoration:none; color:inherit; display:block; }
.card {
  border:1px solid #ddd;
  border-radius:10px;
  padding:12px;
  margin:10px 0;
  display:flex;
  align-items:center;
  gap:18px;
  transition:transform .12s, box-shadow .12s;
  background:#fff;
}
.card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.06); cursor:pointer; }
.card .left { flex: 0 0 120px; text-align:center; }
.card .left img { width:100px; height:100px; object-fit:cover; border-radius:50%; border:2px solid #eee; }
.card .info { flex:1; }
.meta { color:#666; font-size:0.92rem; }
</style>
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

<div class="violations">
  <?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <?php
        $studentPhotoFile = $row['student_photo'] ?? '';
        $studentPhotoSrc = $studentPhotoFile
            ? '../admin/uploads/' . htmlspecialchars($studentPhotoFile)
            : 'placeholder.png';
        $violationId = urlencode($row['violation_id']);
        $studentId = htmlspecialchars($row['student_id']);
      ?>
      <a class="card-link" href="view_violation_approved.php?id=<?= $violationId ?>">
        <div class="card">
          <div class="left">
            <img src="<?= $studentPhotoSrc ?>" alt="Student photo" onerror="this.src='placeholder.png'">
          </div>
          <div class="info">
            <h4><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?> (<?= $studentId ?>)</h4>
            <p><strong>Category:</strong> <?= htmlspecialchars($row['offense_category']) ?> &nbsp; • &nbsp;
               <strong>Type:</strong> <?= htmlspecialchars($row['offense_type']) ?></p>
            <?php if (!empty($row['description'])): ?>
              <p><?= nl2br(htmlspecialchars($row['description'])) ?></p>
            <?php endif; ?>
            <p class="meta"><strong>Status:</strong> <?= htmlspecialchars($row['status']) ?> —
               <em>Reported at <?= htmlspecialchars($row['reported_at']) ?></em></p>
          </div>
        </div>
      </a>
    <?php endwhile; ?>
  <?php else: ?>
    <p>No approved violations found.</p>
  <?php endif; ?>
</div>

<?php
$stmt->close();
$conn->close();
?>

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

  // Optional: close on same-tab nav
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
