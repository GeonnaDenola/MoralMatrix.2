<?php
include '../includes/header.php';
require '../config.php';
require __DIR__.'/_scanner.php';
require 'violation_hrs.php';

$hours = 0;

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* Accept either student_id (####-####) or k=qr_key, and be tolerant of legacy hex in student_id */
$origStudent = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$kParam      = isset($_GET['k']) ? trim($_GET['k']) : '';

$ID_PATTERN    = '/^\d{4}-\d{4}$/';
$KEY64_PATTERN = '/^[a-f0-9]{64}$/i';
$HEX_FLEX      = '/^[a-f0-9]{10,64}$/i'; // legacy shorter hex from old cards

$student_id = $origStudent;

/* 1) If k= is present and valid, resolve it */
if ($student_id === '' && $kParam !== '' && preg_match($KEY64_PATTERN, $kParam)) {
    $stmtK = $conn->prepare('SELECT student_id FROM student_qr_keys WHERE qr_key = ? LIMIT 1');
    $stmtK->bind_param('s', $kParam);
    $stmtK->execute();
    $rowK = $stmtK->get_result()->fetch_assoc();
    $stmtK->close();
    if (!empty($rowK['student_id'])) {
        $student_id = $rowK['student_id'];
    }
}

/* 2) If student_id looks hex-y (old behavior), try resolving as qr_key too */
if ($student_id !== '' && !preg_match($ID_PATTERN, $student_id) && preg_match($HEX_FLEX, $student_id)) {
    $legacyKey = $student_id;
    $stmtL = $conn->prepare('SELECT student_id FROM student_qr_keys WHERE qr_key = ? LIMIT 1');
    $stmtL->bind_param('s', $legacyKey);
    $stmtL->execute();
    $rowL = $stmtL->get_result()->fetch_assoc();
    $stmtL->close();
    if (!empty($rowL['student_id'])) {
        $student_id = $rowL['student_id'];
    }
}

/* 3) Guard */
if ($student_id === '') {
    http_response_code(400);
    die("No student selected.");
}

$hours = communityServiceHours($conn, $student_id);

/* 4) Canonicalize URL only if current URL is NOT already canonical */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // e.g. /MoralMatrix/ccdu

$canonicalPath = $base . '/view_student.php';
$canonicalQS   = 'student_id=' . rawurlencode($student_id);
$currentPath   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentQS     = (string)($_SERVER['QUERY_STRING'] ?? '');

/* Decide if we need to redirect: we used k=... or hex, OR the qs isn’t exactly student_id=... */
$triggeredByKey = ($kParam !== '') || ($origStudent !== '' && !preg_match($ID_PATTERN, $origStudent)) || ($origStudent !== '' && $origStudent !== $student_id);

/* Are we already at the canonical path+query? */
$alreadyCanonical = ($currentPath === $canonicalPath) && ($currentQS === $canonicalQS);

if ($triggeredByKey && !$alreadyCanonical) {
    header('Location: '.$scheme.'://'.$host.$canonicalPath.'?'.$canonicalQS, true, 302);
    $conn->close();
    exit;
}

/* === FETCH STUDENT === */
$sql = "SELECT * FROM student_account WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

/* ==== FETCH VIOLATIONS ==== */
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

/* --- Robust photo path (fallback if file is missing) --- */
$photoRel = 'placeholder.png';
if ($student && !empty($student['photo'])) {
    $tryRel = '../admin/uploads/' . $student['photo'];            // web path from /ccdu
    $tryAbs = __DIR__ . '/../admin/uploads/' . $student['photo']; // filesystem check
    if (is_file($tryAbs)) { $photoRel = $tryRel; }
}

/* Build a root-absolute directory path for this folder (e.g., /MoralMatrix/ccdu) */
$selfDir = rtrim(str_replace('\\','/', dirname($_SERVER['PHP_SELF'])), '/');

/* Now it’s safe to include files that output HTML */

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Profile</title>
  <link rel="stylesheet" href="/MoralMatrix/css/global.css">

  <style>
    /* ====== Only COLORS for the menu button (same as header). No size changes. ====== */
    :root { --header-bg:#b30000; --header-fg:#ffffff; } /* fallback if header is transparent */

    #sidebarToggle{
     #sidebarToggle {
    background-color: #2c3e50;
    color: var(--header-fg) !important;
    border-color: var(--header-bg) !important;
    box-shadow: none !important;
    border: 0 !important;
    border-radius: 0 !important;  
    box-shadow: none !important;   
    outline: none !important;
    }
  }

    /* ===== Buttons, chips, badges, cards ===== */
    a.btn{
      display:inline-block;
      padding:8px 12px;
      border-radius:8px;
      background:#2563eb;
      color:#fff;
      text-decoration:none;
      border:1px solid #1d4ed8;
      transition:filter .15s ease, transform .02s ease;
    }
    a.btn:hover{ filter:brightness(1.08); }
    a.btn:active{ transform:translateY(1px); }

    .cards-grid{
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap:14px;
      margin-top:14px;
    }

    a.profile-card{
      display:block;
      text-decoration:none;
      color:inherit;
      border:1px solid #e5e7eb;
      border-radius:12px;
      overflow:hidden;
      transition:box-shadow .2s ease, transform .05s ease;
      background:#fff;
    }
    a.profile-card:hover{ box-shadow:0 12px 28px rgba(0,0,0,.08); }
    a.profile-card:active{ transform:translateY(1px); }
    a.profile-card img{
      display:block;
      width:100%;
      height:180px;
      object-fit:cover;
      background:#f3f4f6;
    }
    a.profile-card .info{
      padding:12px;
      line-height:1.4;
    }
    a.profile-card *{ text-decoration:none; }

    .badge{
      display:inline-block;
      padding:2px 8px;
      border-radius:999px;
      font-size:.85em;
    }
    .badge-grave{ background:#fee2e2; color:#991b1b; }
    .badge-light{ background:#e0f2fe; color:#075985; }

    .chip{
      display:inline-block;
      padding:2px 8px;
      margin:0 6px 6px 0;
      border-radius:999px;
      background:#f3f4f6;
      font-size:.9em;
    }

    /* Keep the whole strip centered but full-width constrained */
    .right-container > .profile{
      width: min(1200px, 100%);
      margin: 0 auto;
    }

    /* Layout */
    .profile{
      display: grid;
      grid-template-columns: 120px 1fr 700px;
      grid-template-rows: auto auto;
      gap: 12px 24px;
      align-items: center;
      padding: 14px 0;
      border-bottom: 1px solid #e5e7eb;
    }

    .profile-left{
      display: grid;
      grid-auto-rows: max-content;
      justify-items: center;
      gap: 8px;
    }
    .profile-left img{
      width:96px; height:96px; object-fit:cover;
      border-radius:12px; border:1px solid #e5e7eb; background:#f3f4f6;
    }

    .profile-name{
      grid-column: 2;
      grid-row: 1 / span 2;
      margin: 0;
      text-align: center;
    }

    .profile-meta{
      grid-column: 3;
      display: grid;
      grid-template-columns: repeat(3, minmax(160px, 1fr));
      gap: 6px 24px;
    }
    .profile-meta p{ margin: 0; }

    .profile img{
      width:96px;
      height:96px;
      object-fit:cover;
      border-radius:12px;
      border:1px solid #e5e7eb;
      background:#f3f4f6;
    }
    .profile h2{ margin:0; }

    .add-violation-btn{
      width: 100%;
      display: flex;
      justify-content: center;
      margin: 16px 0;
    }

    /* ===== Modal/backdrop ===== */
    .modal-backdrop.hidden, .modal.hidden { display: none !important; }
    .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.50);z-index:9998}
    .modal{position:fixed;inset:0;display:grid;place-items:center;z-index:9999}
    .modal-content{max-width:780px;width:min(92vw,780px);max-height:85vh;overflow:auto;background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.25);padding:18px 20px}
    .modal-close{position:fixed;right:18px;top:18px;border:1px solid #e5e7eb;background:#fff;border-radius:8px;padding:4px 8px;cursor:pointer;z-index:10000}
    body.modal-open{overflow:hidden}

    .sr-only{
      position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;
    }

    /* ===== Page button Sidebar: top drawer ===== */
    .sidebar{
      position: fixed;
      top: 0; left: 0; right: 0;
      width: 100%;
      max-height: 70vh;
      background: #1f2937;
      color: #fff;
      border-bottom: 1px solid rgba(255,255,255,.12);
      box-shadow: 0 12px 30px rgba(0,0,0,.35);
      transform: translateY(-100%);
      transition: transform .25s ease;
      z-index: 10000;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
    }
    .sidebar.open{ transform: translateY(0); }

    .sidebar-header{
      display:flex; justify-content:space-between; align-items:center;
      padding:14px 16px;
      border-bottom:1px solid rgba(255,255,255,.12);
      font-weight:600;
    }
    .sidebar-close{
      border:1px solid rgba(255,255,255,.25);
      background:transparent; color:#fff;
      border-radius:8px; padding:2px 8px; cursor:pointer;
    }
    .sidebar-links{ padding:12px; display:grid; gap:10px; }

    .sidebar-backdrop.hidden { display: none !important; }
    .sidebar-backdrop{
      position: fixed; inset: 0;
      background: rgba(0,0,0,.45);
      z-index: 9985;
    }

    .right-container{ padding-left: 0; }
  </style>
</head>
<body>

<!-- Sidebar toggle (hamburger) -->
<button id="sidebarToggle" class="sidebar-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">☰</button>

<!-- Off-canvas top-drawer sidebar -->
<aside id="sidebar" class="sidebar" aria-hidden="true">
  <div class="sidebar-header">
    <span>Menu</span>
    <button id="sidebarClose" class="sidebar-close" aria-label="Close">✕</button>
  </div>
  <div id="pageButtons" class="sidebar-links">
    <?php include 'page_buttons.php' ?>
  </div>
</aside>

<!-- Backdrop for the sidebar -->
<div id="sidebarBackdrop" class="sidebar-backdrop hidden"></div>

<div class="right-container">
  <?php if($student): ?>
    <div class="profile">
      <!-- Left: photo + ID -->
      <div class="profile-left">
        <img src="<?= htmlspecialchars($photoRel) ?>" alt="Profile">
        <p><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
      </div>

      <!-- Center: big name -->
      <h2 class="profile-name">
        <?= htmlspecialchars($student['first_name'] . " " . $student['middle_name'] . " " . $student['last_name']) ?>
      </h2>

      <!-- Right: meta grid -->
      <div class="profile-meta">
        <p><strong>Course:</strong> <?= htmlspecialchars($student['course']) ?></p>
        <p><strong>Year Level:</strong> <?= htmlspecialchars($student['level']) ?></p>
        <p><strong>Section:</strong> <?= htmlspecialchars($student['section']) ?></p>

        <p><strong>Institute:</strong> <?= htmlspecialchars($student['institute']) ?></p>
        <p><strong>Guardian:</strong> <?= htmlspecialchars($student['guardian']) ?></p>
        <p><strong>Guardian Mobile:</strong> <?= htmlspecialchars($student['guardian_mobile']) ?></p>

        <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
        <p><strong>Mobile:</strong> <?= htmlspecialchars($student['mobile']) ?></p>
        <p><strong>Community Service:</strong> <?= htmlspecialchars((string)$hours) . ' ' . ($hours === 1 ? 'hour' : 'hours') ?></p>
      </div>
    </div>
  <?php else: ?>
    <p>Student not found.</p>
  <?php endif; ?>

  <div class="">
    <div class="add-violation-btn">
      <a class="btn" href="<?= $selfDir ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">Add Violation</a>
    </div>

    <div class="violationHistory-container" id="violationHistory">
      <?php if (empty($violations)): ?>
        <p>No Violations Recorded.</p>
      <?php else: ?>
        <div class="cards-grid">
          <?php foreach ($violations as $v):
            $cat  = htmlspecialchars($v['offense_category']);
            $type = htmlspecialchars($v['offense_type']);
            $desc = htmlspecialchars($v['description'] ?? '');
            $date = date('M d, Y h:i A', strtotime($v['reported_at']));
            $chips = [];
            if (!empty($v['offense_details'])) {
              $decoded = json_decode($v['offense_details'], true);
              if (is_array($decoded)) {
                foreach ($decoded as $d) { $chips[] = htmlspecialchars($d); }
              }
            }
            $href = $selfDir . "/violation_view.php?id=" . urlencode($v['violation_id']) . "&student_id=" . urlencode($student_id);
          ?>
            <a class="profile-card" data-violation-link href="<?= $href ?>">
              <img src="<?= $selfDir ?>/violation_photo.php?id=<?= urlencode($v['violation_id']) ?>" alt="Evidence" onerror="this.style.display='none'">
              <div class="info">
                <p><strong>Category: </strong>
                  <span class="badge badge-<?= $cat ?>"><?= ucfirst($cat) ?></span>
                </p>
                <p><strong>Type:</strong> <?= $type ?></p>
                <?php if (!empty($chips)): ?>
                  <p><strong>Details:</strong> <?= implode(', ', $chips) ?></p>
                <?php endif; ?>
                <p><strong>Reported:</strong> <?= $date ?></p>
                <?php if ($desc): ?>
                  <p><strong>Description:</strong> <?= $desc ?></p>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Backdrop for the violation modal -->
<div id="violationBackdrop" class="modal-backdrop hidden"></div>

<!-- Modal -->
<div id="violationModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="violationModalTitle">
  <button type="button" class="modal-close" id="violationClose" aria-label="Close">✕</button>
  <div id="violationContent" class="modal-content">
    <!-- violation_view.php?modal=1 will be injected here -->
  </div>
</div>

<!-- Make the menu button camouflage to the header's colors without changing size -->
<script>
(function(){
  const btn = document.getElementById('sidebarToggle');
  if (!btn) return;

  // Find the header rendered by header.php (try common selectors)
  const header = document.querySelector('header, .header, .navbar, .topbar, .site-header, #header');
  if (!header) return;

  const cs = getComputedStyle(header);
  const bg = cs.backgroundColor;
  const fg = cs.color;

  // If header has a real background color, copy it
  if (bg && bg !== 'transparent' && !/^rgba?\(\s*0\s*,\s*0\s*,\s*0\s*,\s*0\s*\)$/.test(bg)) {
    btn.style.backgroundColor = bg;
    btn.style.borderColor     = bg; // hide edge by matching border to bg
  }
  if (fg) {
    btn.style.color = fg; // ensure glyph matches header text color
  }
})();

/* Ensure modal is hidden at start */
window.addEventListener('DOMContentLoaded', () => {
  document.getElementById('violationBackdrop')?.classList.add('hidden');
  document.getElementById('violationModal')?.classList.add('hidden');
  document.body.classList.remove('modal-open');
});

(function () {
  const backdrop = document.getElementById('violationBackdrop');
  const modal    = document.getElementById('violationModal');
  const content  = document.getElementById('violationContent');
  const btnClose = document.getElementById('violationClose');

  function openModalWith(url) {
    fetch(url, { credentials: 'same-origin' })
      .then(r => { if (!r.ok) throw new Error('Failed to load violation'); return r.text(); })
      .then(html => {
        content.innerHTML = html;
        backdrop.classList.remove('hidden');
        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
        if (!history.state || history.state.modalOpen !== true) {
          history.pushState({ modalOpen: true }, '');
        }
      })
      .catch(err => alert('Unable to load violation: ' + err.message));
  }

  function closeModal() {
    backdrop.classList.add('hidden');
    modal.classList.add('hidden');
    document.body.classList.remove('modal-open');
    if (history.state && history.state.modalOpen === true) history.back();
  }

  // Intercept clicks ONLY on links that opted-in via data-violation-link
  document.addEventListener('click', function (e) {
    const link = e.target.closest('a[data-violation-link]');
    if (!link) return;

    // allow new-tab/middle-click
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

    e.preventDefault();
    const url = link.href + (link.href.includes('?') ? '&' : '?') + 'modal=1';
    openModalWith(url);
  });

  btnClose.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

  // Handle back/forward
  window.addEventListener('popstate', function () {
    if (!backdrop.classList.contains('hidden') || !modal.classList.contains('hidden')) {
      backdrop.classList.add('hidden');
      modal.classList.add('hidden');
      document.body.classList.remove('modal-open');
    }
  });
})();

//Sidebar open/close behavior (top drawer)

(function(){
  const sidebar   = document.getElementById('sidebar');
  const openBtn   = document.getElementById('sidebarToggle');
  const closeBtn  = document.getElementById('sidebarClose');
  const backdrop  = document.getElementById('sidebarBackdrop');

  if (!sidebar || !openBtn || !closeBtn || !backdrop) return;

  function openSidebar(){
    sidebar.classList.add('open');
    sidebar.setAttribute('aria-hidden','false');
    openBtn.setAttribute('aria-expanded','true');
    backdrop.classList.remove('hidden');
    document.body.classList.add('modal-open'); // lock scroll
  }
  function closeSidebar(){
    sidebar.classList.remove('open');
    sidebar.setAttribute('aria-hidden','true');
    openBtn.setAttribute('aria-expanded','false');
    backdrop.classList.add('hidden');
    document.body.classList.remove('modal-open');
  }

  openBtn.addEventListener('click', openSidebar);
  closeBtn.addEventListener('click', closeSidebar);
  backdrop.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') closeSidebar(); });
})();
</script>

</body>
</html>
