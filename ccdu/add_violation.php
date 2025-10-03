<?php
include '../includes/header.php';
include '../config.php';

include __DIR__ . '/_scanner.php';

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
        $uploadDir = __DIR__ . "../admin/uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $photo = time() . "_" . basename($_FILES["photo"]["name"]);
        $targetPath = $uploadDir . $photo;

        if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetPath)) {
            $errorMsg = "⚠️ Error uploading photo.";
            $photo = "";
        }
    }

    $submitted_by  = $_SESSION['actor_id']   ?? 'unknown';

    // CHANGED: removed submitted_role; align columns, placeholders, bind types/args
   $sql = "INSERT INTO student_violation
        (student_id, offense_category, offense_type, offense_details, description, photo, status, submitted_by)
        VALUES (?, ?, ?, ?, ?, ?, 'approved', ?)";

    $stmtIns = $conn->prepare($sql);
    if (!$stmtIns) die("Prepare failed: " . $conn->error);

    $stmtIns->bind_param("sssssss",
        $student_id, $offense_category, $offense_type,
        $offense_details, $description, $photo, $submitted_by
    );


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
      <?php include 'page_buttons.php'; ?>
    </div>
  </div>
</nav>
<!-- ======= /LEFT Sidesheet ======= -->

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
      <img id="lightPreview" width="100" style="display:none">

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
      <img id="moderatePreview" width="100" style="display:none">

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
      <img id="gravePreview" width="100" style="display:none">

      <br>
      <button type="submit">Add Violation</button>
    </form>
  </div>

</div> <!-- /.profile-container -->

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
    ['id','uniform','civilian','accessories'].forEach(k=>{
      const box = document.getElementById('light_'+k+'Checkbox');
      if (box) box.style.display = 'none';
    });
    const box = document.getElementById('light_'+this.value+'Checkbox');
    if (box) box.style.display = 'block';
  });

  if (modSel) modSel.addEventListener('change', function(){
    ['improper_conduct','gadget_misuse','unauthorized_acts'].forEach(k=>{
      const box = document.getElementById('moderate_'+k+'Checkbox');
      if (box) box.style.display = 'none';
    });
    const box = document.getElementById('moderate_'+this.value+'Checkbox');
    if (box) box.style.display = 'block';
  });

  if (graveSel) graveSel.addEventListener('change', function(){
    ['substance_addiction','integrity_dishonesty','violence_misconduct','property_theft','threats_disrespect'].forEach(k=>{
      const box = document.getElementById('grave_'+k+'Checkbox');
      if (box) box.style.display = 'none';
    });
    const box = document.getElementById('grave_'+this.value+'Checkbox');
    if (box) box.style.display = 'block';
  });

  toggleForms();
});

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
    document.body.classList.add('no-scroll'); // provided by global.css

    // Focus first interactive element for a11y
    setTimeout(()=>{
      const f = sheet.querySelector('.nav-tile, #pageButtons a, #pageButtons button, [tabindex]:not([tabindex="-1"])');
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
