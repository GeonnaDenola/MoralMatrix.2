<?php
// faculty/pending.php — show ONLY my pending violations (faculty)
declare(strict_types=1);

require '../auth.php';
require_role('faculty');

include '../config.php';
include '../includes/header.php';

// --- DB connect ---
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

// --- current faculty id (same as your dashboard) ---
$faculty_id = $_SESSION['actor_id'] ?? null;
if (!$faculty_id) { die("No faculty id in session. Please login again."); }

// --- ONLY my pending violations ---
// NOTE: match dashboard style: filter by submitted_by only; make status case-insensitive
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
  AND LOWER(sv.status) = 'pending'
ORDER BY sv.reported_at DESC, sv.violation_id DESC
";
$stmt = $conn->prepare($sql) ?: die('Prepare failed: '.$conn->error);
$stmt->bind_param('s', $faculty_id);          // keep 's' to match your dashboard
$stmt->execute() || die('Execute failed: '.$stmt->error);
$result = $stmt->get_result();

// --- Optional: debug snapshot by role/status for THIS user (visit ?debug=1 to see) ---
$debugRows = [];
if (!empty($_GET['debug'])) {
  $d = $conn->prepare("
    SELECT COALESCE(sv.submitted_role,'(null)') AS role,
           LOWER(sv.status) AS status_norm,
           COUNT(*) AS c
    FROM student_violation sv
    WHERE sv.submitted_by = ?
    GROUP BY sv.submitted_role, LOWER(sv.status)
    ORDER BY c DESC
  ");
  $d->bind_param('s', $faculty_id);
  $d->execute();
  $debugRows = $d->get_result()->fetch_all(MYSQLI_ASSOC);
  $d->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Faculty — My Pending Violations</title>
<link rel="stylesheet" href="/MoralMatrix/css/global.css">
<style>
  .violations { padding: 12px; max-width: 980px; margin: 0 auto; }
  .card-link { text-decoration:none; color:inherit; display:block; }
  .card {
    border:1px solid #ddd; border-radius:10px; padding:12px; margin:10px 0;
    display:flex; align-items:center; gap:18px; background:#fff;
    transition:transform .12s, box-shadow .12s;
  }
  .card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.06); cursor:pointer; }
  .card .left { flex: 0 0 120px; text-align:center; }
  .card .left img { width:100px; height:100px; object-fit:cover; border-radius:50%; border:2px solid #eee; }
  .card .info { flex:1; }
  .meta { color:#666; font-size:0.92rem; }
  .debug { background:#fff7ed; border:1px solid #fed7aa; padding:8px 10px; border-radius:8px; margin:12px auto; max-width:980px; font-size:.9rem; }
  .debug table { border-collapse:collapse; }
  .debug th, .debug td { border:1px solid #ddd; padding:4px 8px; }
</style>
</head>
<body>

<!-- Optional menu like on your dashboard -->
<button id="openMenu" class="menu-launcher" aria-controls="sideSheet" aria-expanded="false">Menu</button>
<div class="page-top-pad"></div>
<div id="sheetScrim" class="sidesheet-scrim" aria-hidden="true"></div>
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

<?php if ($debugRows): ?>
  <div class="debug">
    <strong>Debug (per-role/status for your submissions)</strong>
    <div>actor_id: <code><?= htmlspecialchars((string)$faculty_id) ?></code></div>
    <table>
      <tr><th>submitted_role</th><th>status (normalized)</th><th>count</th></tr>
      <?php foreach ($debugRows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['role']) ?></td>
          <td><?= htmlspecialchars($r['status_norm']) ?></td>
          <td style="text-align:right"><?= (int)$r['c'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <div>Tip: If you see statuses like <em>pending approval</em> or roles not equal to <em>faculty</em>, that explains empty results.</div>
  </div>
<?php endif; ?>

<div class="violations">
  <h3>My Pending Violations</h3>

  <?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <?php
        $studentPhotoFile = $row['student_photo'] ?? '';
        $studentPhotoSrc = $studentPhotoFile
            ? '../admin/uploads/' . htmlspecialchars($studentPhotoFile)
            : 'placeholder.png';
        $violationId = (int)$row['violation_id'];
        $studentId = htmlspecialchars($row['student_id']);
      ?>
      <a class="card-link" href="view_violation_pending.php?id=<?= $violationId ?>">
        <div class="card">
          <div class="left">
            <img src="<?= $studentPhotoSrc ?>" alt="Student photo" onerror="this.src='placeholder.png'">
          </div>
          <div class="info">
            <h4><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?> (<?= $studentId ?>)</h4>
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
    <p>No pending violations found.</p>
    <p class="meta">If you expect items here, try <a href="?debug=1">debug view</a> to see actual statuses/roles stored.</p>
  <?php endif; ?>
</div>

<?php
$stmt->close();
$conn->close();
?>

<script>
// same sidesheet JS as your dashboard (optional)
(function(){
  const sheet=document.getElementById('sideSheet'),scrim=document.getElementById('sheetScrim'),
        openBtn=document.getElementById('openMenu'),closeBtn=document.getElementById('closeMenu');
  if(!sheet||!scrim||!openBtn||!closeBtn) return;
  let last=null;
  function trap(c,e){
    const f=c.querySelectorAll('a[href],button:not([disabled]),textarea,input,select,[tabindex]:not([tabindex="-1"])');
    if(!f.length) return; const first=f[0], lastf=f[f.length-1];
    if(e.key==='Tab'){ if(e.shiftKey&&document.activeElement===first){e.preventDefault();lastf.focus();}
    else if(!e.shiftKey&&document.activeElement===lastf){e.preventDefault();first.focus();}}
  }
  const handler=e=>trap(sheet,e);
  function open(){ last=document.activeElement; sheet.classList.add('open'); scrim.classList.add('open');
    sheet.setAttribute('aria-hidden','false'); scrim.setAttribute('aria-hidden','false');
    openBtn.setAttribute('aria-expanded','true'); document.body.classList.add('no-scroll');
    setTimeout(()=>{ (sheet.querySelector('#pageButtons a, #pageButtons button, [tabindex]:not([tabindex="-1"])')||sheet).focus(); },10);
    sheet.addEventListener('keydown',handler);}
  function close(){ sheet.classList.remove('open'); scrim.classList.remove('open');
    sheet.setAttribute('aria-hidden','true'); scrim.setAttribute('aria-hidden','true');
    openBtn.setAttribute('aria-expanded','false'); document.body.classList.remove('no-scroll');
    sheet.removeEventListener('keydown',handler); if(last) last.focus(); }
  openBtn.addEventListener('click',open); closeBtn.addEventListener('click',close); scrim.addEventListener('click',close);
  document.addEventListener('keydown',e=>{ if(e.key==='Escape') close(); });
  sheet.addEventListener('click',e=>{ const link=e.target.closest('a[href]'); if(!link) return;
    const sameTab=!(e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||e.button!==0); if(sameTab) close(); });
})();
</script>
</body>
</html>
