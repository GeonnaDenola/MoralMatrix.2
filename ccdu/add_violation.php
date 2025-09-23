<?php
include '../includes/header.php';
include '../config.php';

include 'page_buttons.php';

require_once '../config.php';
require_once '../includes/header.php';

/* ---------- STUDENT ID FROM GET OR POST ---------- */
$studentId = $_GET['student_id'] ?? $_POST['student_id'] ?? '';
if (!$studentId) {
    echo "<p>No student selected!</p>";
    exit;
}

/* ---------- DB CONNECTION ---------- */
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* ========= INSERT HANDLER ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_id       = $_POST['student_id']       ?? '';
    $offense_category = $_POST['offense_category'] ?? '';
    $offense_type     = $_POST['offense_type']     ?? '';
    $description      = $_POST['description']      ?? '';

    if ($student_id === '' || $offense_category === '' || $offense_type === '') {
        die("Missing required fields.");
    }

    // Collect all picked detail checkboxes into one array
    $detailGroups = [
        'id_offense','uniform_offense','civilian_offense','accessories_offense',
        'conduct_offense','gadget_offense','acts_offense',
        'substance_offense','integrity_offense','violence_offense',
        'property_offense','threats_offense'
    ];
    $picked = [];
    foreach ($detailGroups as $g) {
        if (!empty($_POST[$g]) && is_array($_POST[$g])) {
            $picked = array_merge($picked, $_POST[$g]);
        }
    }
    $offense_details = $picked ? json_encode($picked, JSON_UNESCAPED_UNICODE) : null;

$photo = "";
if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . "/uploads/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $photo = time() . "_" . basename($_FILES["photo"]["name"]);
    $targetPath = $uploadDir . $photo;

    if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetPath)) {
        $errorMsg = "⚠️ Error uploading photo.";
        $photo = "";
    }
}

$submitted_by = $_SESSION['actor_id'] ?? 'unknown';
$submitted_role= $_SESSION['actor_role'] ?? 'ccdu';

    $sql = "INSERT INTO student_violation
            (student_id, offense_category, offense_type, offense_details, description, photo, status, submitted_by, submitted_role)
            VALUES (?, ?, ?, ?, ?, ?, 'approved', ?, ?)";
    $stmtIns = $conn->prepare($sql);
    if (!$stmtIns) die("Prepare failed: " . $conn->error);

    $null = NULL;
    $stmtIns->bind_param("ssssssss",
        $student_id, $offense_category, $offense_type, $offense_details, $description, $photo, $submitted_by, $submitted_role

    );
    if ($photo !== null) {
        $stmtIns->send_long_data(5, $photo);
    }

    if (!$stmtIns->execute()) { die("Insert failed: " . $stmtIns->error); }
    $stmtIns->close();

    header("Location: view_student.php?student_id=" . urlencode($student_id) . "&saved=1");
    exit;
}
/* ========= END INSERT HANDLER ========= */

/* ---------- FETCH STUDENT FOR DISPLAY ---------- */
$sql = "SELECT * FROM student_account WHERE student_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add Violation</title>
  <link rel="stylesheet" href="/MoralMatrix/css/global.css"/>

  <style>
    /* ===========================
       Sidebar (top drawer)
       =========================== */
    .sidebar{
      position: fixed; top: 0; left: 0; right: 0;
      width: 100%; max-height: 70vh;
      background: #1f2937; color:#fff;
      border-bottom: 1px solid rgba(255,255,255,.12);
      box-shadow: 0 12px 30px rgba(0,0,0,.35);
      transform: translateY(-100%);           /* hidden by default */
      transition: transform .25s ease;
      z-index: 10000;
      display:flex; flex-direction:column;
      overflow-y:auto;
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

    /* Backdrop */
    .sidebar-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9985; }
    .sidebar-backdrop.hidden{ display:none !important; }

    /* Body scroll lock when open (optional) */
    body.modal-open{ overflow:hidden; }

    /* ===========================
       Menu button: CAMO with header, no size change, no edge
       =========================== */
    :root{ --header-bg:#2c3e50; --header-fg:#ffffff; } /* fallback values */

    #sidebarToggle{
      background-color: var(--header-bg) !important;
      color: var(--header-fg) !important;
      border-color: var(--header-bg) !important;

      /* remove the edge only (no size changes) */
      border: 0 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      outline: none !important;
    }
    #sidebarToggle:focus{ outline:none !important; box-shadow:none !important; }

    /* ===========================
       Page-specific bits
       =========================== */
    .form-container{ display:none; margin-top:1rem; }
    .profile-container{ max-width:900px; margin:0 auto; padding: 1rem; }
    .profile img{ width:120px; height:120px; object-fit:cover; border-radius:8px; }
    .profile p{ margin:.25rem 0; }
  </style>
</head>
<body>

<!-- Sidebar toggle (hamburger) -->
<button id="sidebarToggle" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">☰</button>

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

<div class="profile-container">

  <?php if ($student): ?>
    <div class="profile">
      <img src="<?= !empty($student['photo']) ? '../admin/uploads/'.htmlspecialchars($student['photo'], ENT_QUOTES) : 'placeholder.png' ?>" alt="Profile">
      <p><strong><?= htmlspecialchars($student['student_id']) ?></strong></p>
      <p><strong><?= htmlspecialchars($student['first_name']." ".$student['middle_name']." ".$student['last_name']) ?></strong></p>
      <p><strong><?= htmlspecialchars($student['course'])." - ".htmlspecialchars($student['level'].$student['section']) ?></strong></p>
    </div>

    <?php else: ?>
      <p>Student not found.</p>
    <?php endif; ?>

    <h3 style="margin-top:1rem">Add Violation</h3>

    <label>Offense Category: </label>
    <select id="offense_category" onchange="toggleForms()" required>
      <option value="">--SELECT--</option>
      <option value="light">Light</option>
      <option value="moderate">Moderate</option>
      <option value="grave">Grave</option>
    </select>

    <!-- LIGHT -->
    <div id="lightForm" class="form-container">

      <form method="POST" enctype="multipart/form-data">

        <p>Light Offenses</p>

        <input type="hidden" name="offense_category" value="light">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>">


        <select id="lightOffenses" name="offense_type" required>
          <option value="">--Select--</option>
          <option value="id">ID</option>
          <option value="uniform">Dress Code (Uniform)</option>
          <option value="civilian">Revealing Clothes (Civilian Attire)</option>
          <option value="accessories">Accessories</option>
        </select>

        <div id="light_idCheckbox" style="display:none">
          <label><input type="checkbox" name="id_offense[]" value="no_id">No ID</label>
          <label><input type="checkbox" name="id_offense[]" value="borrowed">Borrowed ID</label>
        </div>

        <div id="light_uniformCheckbox" style="display:none">
          <label><input type="checkbox" name="uniform_offense[]" value="socks">Socks</label>
          <label><input type="checkbox" name="uniform_offense[]" value="skirt">Skirt</label>
        </div>

        <div id="light_civilianCheckbox" style="display:none">
          <label><input type="checkbox" name="civilian_offense[]" value="crop_top">Crop Top</label>
          <label><input type="checkbox" name="civilian_offense[]" value="sando">Sando</label>
        </div>

        <div id="light_accessoriesCheckbox" style="display:none">
          <label><input type="checkbox" name="accessories_offense[]" value="piercings">Piercing/s</label>
          <label><input type="checkbox" name="accessories_offense[]" value="hair_color">Loud Hair Color</label>
        </div><br><br>

        <label>Report Description: </label><br>
        <input type="text" id="description_light" name="description"><br><br>

        <label>Attach Photo:</label>
        <input type="file" name="photo" accept="image/*" onchange="previewPhoto(this, 'lightPreview')">
            <img id="lightPreview" width="100">

        <br>
        <button type="submit">Add Violation</button>
      </form>
    </div>

    <!-- MODERATE -->
    <div id="moderateForm" class="form-container">
      <form method="POST" enctype="multipart/form-data">
        <p>Moderate Offenses</p>
        <input type="hidden" name="offense_category" value="moderate">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>">

        <select id="moderateOffenses" name="offense_type" required>
          <option value="">--Select--</option>
          <option value="improper_conduct">Improper Language & Conduct</option>
          <option value="gadget_misuse">Gadget Misuse</option>
          <option value="unauthorized_acts">Unauthorized Acts</option>
        </select>

        <div id="moderate_improper_conductCheckbox" style="display:none">
          <label><input type="checkbox" name="conduct_offense[]" value="vulgar">Use of curses and vulgar words</label>
          <label><input type="checkbox" name="conduct_offense[]" value="rough_behavior">Roughness in behavior</label>
        </div>

        <div id="moderate_gadget_misuseCheckbox" style="display:none">
          <label><input type="checkbox" name="gadget_offense[]" value="cp_classes">Use of cellular phones during classes</label>
          <label><input type="checkbox" name="gadget_offense[]" value="gadgets_functions">Use of gadgets during academic functions</label>
        </div>

        <div id="moderate_unauthorized_actsCheckbox" style="display:none">
          <label><input type="checkbox" name="acts_offense[]" value="illegal_posters">Posting posters/streamers/banners without approval</label>
          <label><input type="checkbox" name="acts_offense[]" value="pda">PDA (Public Display of Affection)</label>
        </div><br><br>

        <label>Report Description: </label><br>
        <input type="text" id="description_moderate" name="description"><br><br>

                <label>Attach Photo:</label>
                <input type="file" name="photo" accept="image/*" onchange="previewPhoto(this, 'moderatePreview')">
                <img id="moderatePreview" width="100">

                <br>
                <button type="submit">Add Violation</button>
        </form>

    </div>

    <!-- GRAVE -->
    <div id="graveForm" class="form-container">
      <form method="POST" enctype="multipart/form-data">
        <p>Grave Offenses</p>
        <input type="hidden" name="offense_category" value="grave">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>">

        <select id="graveOffenses" name="offense_type" required>
          <option value="">--Select--</option>
          <option value="substance_addiction">Substance & Addiction</option>
          <option value="integrity_dishonesty">Academic Integrity & Dishonesty</option>
          <option value="violence_misconduct">Violence & Misconduct</option>
          <option value="property_theft">Property & Theft</option>
          <option value="threats_disrespect">Threats & Disrespect</option>
        </select>

        <div id="grave_substance_addictionCheckbox" style="display:none">
          <label><input type="checkbox" name="substance_offense[]" value="smoking">Smoking</label>
          <label><input type="checkbox" name="substance_offense[]" value="gambling">Gambling</label>
        </div>

        <div id="grave_integrity_dishonestyCheckbox" style="display:none">
          <label><input type="checkbox" name="integrity_offense[]" value="forgery">Forgery, falsifying, tampering of documents</label>
          <label><input type="checkbox" name="integrity_offense[]" value="dishonesty">Dishonesty</label>
        </div>

        <div id="grave_violence_misconductCheckbox" style="display:none">
          <label><input type="checkbox" name="violence_offense[]" value="assault">Assault</label>
          <label><input type="checkbox" name="violence_offense[]" value="hooliganism">Hooliganism</label>
        </div>

        <div id="grave_property_theftCheckbox" style="display:none">
          <label><input type="checkbox" name="property_offense[]" value="theft">Theft</label>
          <label><input type="checkbox" name="property_offense[]" value="destruction_of_property">Willful destruction of school property</label>
        </div>

        <div id="grave_threats_disrespectCheckbox" style="display:none">
          <label><input type="checkbox" name="threats_offense[]" value="firearms">Carrying deadly weapons/firearms/explosives</label>
          <label><input type="checkbox" name="threats_offense[]" value="disrespect">Offensive words / disrespectful deeds</label>
        </div><br><br>

        <label>Report Description: </label><br>
        <input type="text" id="description_grave" name="description"><br><br>

        <label>Attach Photo:</label>
        <input type="file" name="photo" accept="image/*" onchange="previewPhoto(this, 'gravePreview')">
        <img id="gravePreview" width="100">

            <br>
            <button type="submit">Add Violation</button>
        </form>
    </div>

<!-- Camouflage the toggle to the header color without changing size -->
<script>


    function previewPhoto(input, previewId){
        const preview = document.getElementById(previewId);
            if(input.files && input.files[0]){
                const reader = new FileReader();
                reader.onload = function(e){ preview.src=e.target.result; preview.style.display='block'; }
                 reader.readAsDataURL(input.files[0]);
        }
    }


(function(){
  const btn = document.getElementById('sidebarToggle');
  if (!btn) return;

  const header = document.querySelector('header, .header, .navbar, .topbar, .site-header, #header');
  if (!header) return;

  const cs = getComputedStyle(header);
  const bg = cs.backgroundColor;
  const fg = cs.color;

  if (bg && bg !== 'transparent' && !/^rgba?\(\s*0\s*,\s*0\s*,\s*0\s*,\s*0\s*\)$/.test(bg)) {
    btn.style.backgroundColor = bg;
    btn.style.borderColor     = bg;
  }
  if (fg) { btn.style.color = fg; }
})();


// Sidebar open/close behavior 

(function(){
  const sidebar  = document.getElementById('sidebar');
  const openBtn  = document.getElementById('sidebarToggle');
  const closeBtn = document.getElementById('sidebarClose');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (!sidebar || !openBtn || !closeBtn || !backdrop) return;

  function openSidebar(){
    sidebar.classList.add('open');
    sidebar.setAttribute('aria-hidden','false');
    openBtn.setAttribute('aria-expanded','true');
    backdrop.classList.remove('hidden');
    document.body.classList.add('modal-open');
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
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
})();

//Offense forms behavior

function toggleForms(){
  const selected = document.getElementById("offense_category").value;
  ['light', 'moderate', 'grave'].forEach(t=>{
    const el = document.getElementById(t+'Form');
    if (el) el.style.display = (selected===t)?'block':'none';
  });
}
window.addEventListener('DOMContentLoaded', ()=>{
  const lightSel = document.getElementById('lightOffenses');
  const modSel   = document.getElementById('moderateOffenses');
  const graveSel = document.getElementById('graveOffenses');

  if (lightSel) lightSel.addEventListener('change', function(){
    document.getElementById('light_idCheckbox').style.display = 'none';
    document.getElementById('light_uniformCheckbox').style.display = 'none';
    document.getElementById('light_civilianCheckbox').style.display = 'none';
    document.getElementById('light_accessoriesCheckbox').style.display = 'none';
    const sel = this.value;
    if (sel) {
      const box = document.getElementById('light_'+sel+'Checkbox');
      if (box) box.style.display = 'block';
    }
  });

  if (modSel) modSel.addEventListener('change', function(){
    document.getElementById('moderate_improper_conductCheckbox').style.display = 'none';
    document.getElementById('moderate_gadget_misuseCheckbox').style.display = 'none';
    document.getElementById('moderate_unauthorized_actsCheckbox').style.display = 'none';
    const sel = this.value;
    if (sel) {
      const box = document.getElementById('moderate_'+sel+'Checkbox');
      if (box) box.style.display = 'block';
    }
  });

  if (graveSel) graveSel.addEventListener('change', function(){
    document.getElementById('grave_substance_addictionCheckbox').style.display = 'none';
    document.getElementById('grave_integrity_dishonestyCheckbox').style.display = 'none';
    document.getElementById('grave_violence_misconductCheckbox').style.display = 'none';
    document.getElementById('grave_property_theftCheckbox').style.display = 'none';
    document.getElementById('grave_threats_disrespectCheckbox').style.display = 'none';
    const sel = this.value;
    if (sel) {
      const box = document.getElementById('grave_'+sel+'Checkbox');
      if (box) box.style.display = 'block';
    }
  });

  toggleForms();
});

</script>
</body>
</html>
 