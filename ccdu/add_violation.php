<?php
// add_violation.php (full page)

// Start session early (header.php used to do this, but we handle it here)
session_start();
// add_violation.php (full page)

// Start session early (header.php used to do this, but we handle it here)
session_start();
include '../includes/header.php';

require_once '../config.php';

// Accept student_id from GET (view) or POST (submit)
// Accept student_id from GET (view) or POST (submit)
$studentId = $_GET['student_id'] ?? $_POST['student_id'] ?? '';

// --- DB connect ---
// --- DB connect ---
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// ========= INSERT HANDLER =========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_id       = $_POST['student_id']       ?? '';
    $offense_category = $_POST['offense_category'] ?? '';
    $offense_type     = $_POST['offense_type']     ?? '';
    $description      = $_POST['description']      ?? '';

    if (!$student_id || !$offense_category || !$offense_type) {
        die("Missing required fields.");
    }

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

    $photo = null;
    if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $photo = file_get_contents($_FILES['photo']['tmp_name']);
    }
    $photo = null;
    if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $photo = file_get_contents($_FILES['photo']['tmp_name']);
    }

    $sql = "INSERT INTO student_violation
            (student_id, offense_category, offense_type, offense_details, description, photo)
            VALUES (?, ?, ?, ?, ?, ?)";
            (student_id, offense_category, offense_type, offense_details, description, photo)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmtIns = $conn->prepare($sql);
    if (!$stmtIns) die("Prepare failed: ".$conn->error);
    if (!$stmtIns) die("Prepare failed: ".$conn->error);

    $null = NULL;
    $stmtIns->bind_param("sssssb",
    $stmtIns->bind_param("sssssb",
        $student_id, $offense_category, $offense_type,
        $offense_details, $description, $null
        $offense_details, $description, $null
    );
    if ($photo !== null) {
        $stmtIns->send_long_data(5, $photo);
    }

    if (!$stmtIns->execute()) {
        die("Insert failed: ".$stmtIns->error);
    }
    if (!$stmtIns->execute()) {
        die("Insert failed: ".$stmtIns->error);
    }
    $stmtIns->close();

    header("Location: view_student.php?student_id=" . urlencode($student_id) . "&saved=1");
    exit;
}
// ========= END INSERT HANDLER =========

// For viewing (GET), require a student_id
if (!$studentId) {
    echo "<p>No student selected!</p>";
    exit;
}

// Fetch student data
// ========= END INSERT HANDLER =========

// For viewing (GET), require a student_id
if (!$studentId) {
    echo "<p>No student selected!</p>";
    exit;
}

// Fetch student data
$sql = "SELECT * FROM student_account WHERE student_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

/* Active page for toggle highlight */
$active = basename($_SERVER['PHP_SELF']);
function activeClass($file){ global $active; return $active === $file ? ' is-active' : ''; }

/* Active page for toggle highlight */
$active = basename($_SERVER['PHP_SELF']);
function activeClass($file){ global $active; return $active === $file ? ' is-active' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Violation</title>
  <link rel="stylesheet" href="/MoralMatrix/css/global.css">
  <!-- added: small spacing for the injected checklist slot -->
  <link rel="stylesheet" href="/MoralMatrix/css/global.css">
  <!-- added: small spacing for the injected checklist slot -->
  <style>
    .checklist-slot { margin: .75rem 0; }
    .checklist-slot { margin: .75rem 0; }
  </style>
</head>
<body>

  <!-- Toggle launcher -->
  <button id="openMenu" class="menu-launcher" aria-controls="topSheet" aria-expanded="false">Menu</button>
  <div class="page-top-pad"></div>
  <!-- Toggle launcher -->
  <button id="openMenu" class="menu-launcher" aria-controls="topSheet" aria-expanded="false">Menu</button>
  <div class="page-top-pad"></div>

  <!-- Scrim -->
  <div id="sheetScrim" class="topsheet-scrim" aria-hidden="true"></div>

  <!-- Top sheet menu -->
  <div id="topSheet" class="topsheet" aria-hidden="true" role="dialog" aria-label="Main menu">
    <div class="topsheet-header">
      <span>Menu</span>
      <button id="closeMenu" class="topsheet-close" aria-label="Close menu">✕</button>
    </div>
    <div class="topsheet-rail">
      <a class="nav-tile<?php echo activeClass('dashboard.php'); ?>" href="dashboard.php" <?php echo $active==='dashboard.php'?'aria-current="page"':''; ?>>Dashboard</a>
      <a class="nav-tile<?php echo activeClass('pending_reports.php'); ?>" href="pending_reports.php" <?php echo $active==='pending_reports.php'?'aria-current="page"':''; ?>>Pending Reports</a>
      <a class="nav-tile<?php echo activeClass('community_validators.php'); ?>" href="community_validators.php" <?php echo $active==='community_validators.php'?'aria-current="page"':''; ?>>Community Service Validators</a>
      <a class="nav-tile<?php echo activeClass('summary_report.php'); ?>" href="summary_report.php" <?php echo $active==='summary_report.php'?'aria-current="page"':''; ?>>Summary Report</a>
    </div>
  <!-- Scrim -->
  <div id="sheetScrim" class="topsheet-scrim" aria-hidden="true"></div>

  <!-- Top sheet menu -->
  <div id="topSheet" class="topsheet" aria-hidden="true" role="dialog" aria-label="Main menu">
    <div class="topsheet-header">
      <span>Menu</span>
      <button id="closeMenu" class="topsheet-close" aria-label="Close menu">✕</button>
    </div>
    <div class="topsheet-rail">
      <a class="nav-tile<?php echo activeClass('dashboard.php'); ?>" href="dashboard.php" <?php echo $active==='dashboard.php'?'aria-current="page"':''; ?>>Dashboard</a>
      <a class="nav-tile<?php echo activeClass('pending_reports.php'); ?>" href="pending_reports.php" <?php echo $active==='pending_reports.php'?'aria-current="page"':''; ?>>Pending Reports</a>
      <a class="nav-tile<?php echo activeClass('community_validators.php'); ?>" href="community_validators.php" <?php echo $active==='community_validators.php'?'aria-current="page"':''; ?>>Community Service Validators</a>
      <a class="nav-tile<?php echo activeClass('summary_report.php'); ?>" href="summary_report.php" <?php echo $active==='summary_report.php'?'aria-current="page"':''; ?>>Summary Report</a>
    </div>
  </div>

  <div class="profile-container">
    <?php if($student): ?>
      <div class="profile">
        <img src="<?= !empty($student['photo']) ? '../admin/uploads/'.htmlspecialchars($student['photo']) : 'placeholder.png' ?>" alt="Profile">
        <div>
          <p><strong><?= htmlspecialchars($student['student_id']) ?></strong></p>
          <p><strong><?= htmlspecialchars(trim($student['first_name'] . " " . $student['middle_name'] . " " . $student['last_name'])) ?></strong></p>
          <p><strong><?= htmlspecialchars($student['course']. " - ".  $student['level'].$student['section']) ?></strong></p>
        </div>
      </div>
  <div class="profile-container">
    <?php if($student): ?>
      <div class="profile">
        <img src="<?= !empty($student['photo']) ? '../admin/uploads/'.htmlspecialchars($student['photo']) : 'placeholder.png' ?>" alt="Profile">
        <div>
          <p><strong><?= htmlspecialchars($student['student_id']) ?></strong></p>
          <p><strong><?= htmlspecialchars(trim($student['first_name'] . " " . $student['middle_name'] . " " . $student['last_name'])) ?></strong></p>
          <p><strong><?= htmlspecialchars($student['course']. " - ".  $student['level'].$student['section']) ?></strong></p>
        </div>
      </div>
    <?php else: ?>
      <p>Student not found.</p>
    <?php endif; ?>

    <h3>Add Violation</h3>
    <h3>Add Violation</h3>

    <label>Offense Category: </label>
    <select id="offense_category" onchange="toggleForms()" required>
      <option value="">--SELECT--</option>
      <option value="light">Light</option>
      <option value="moderate">Moderate</option>
      <option value="grave">Grave</option>
    </select>

    <!-- LIGHT -->
    <div id="lightForm" class="form-container">
      <form method="POST" enctype="multipart/form-data"
            action="<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?student_id=' . urlencode($studentId) ?>">
        <p><strong>Light Offenses</strong></p>
      <form method="POST" enctype="multipart/form-data"
            action="<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?student_id=' . urlencode($studentId) ?>">
        <p><strong>Light Offenses</strong></p>

        <input type="hidden" name="offense_category" value="light">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">

        <select id="lightOffenses" name="offense_type">
        <select id="lightOffenses" name="offense_type">
          <option value="">--Select--</option>
          <option value="id">ID</option>
          <option value="uniform">Dress Code (Uniform)</option>
          <option value="civilian">Revealing Clothes (Civilian Attire)</option>
          <option value="accessories">Accessories</option>
        </select>

        <!-- added: checklist slot to place the selected checklist just under the selector -->
        <div id="lightChecklistSlot" class="checklist-slot" aria-live="polite"></div>

        <!-- added: checklist slot to place the selected checklist just under the selector -->
        <div id="lightChecklistSlot" class="checklist-slot" aria-live="polite"></div>

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
        </div>
        </div>

        <label>Report Description: </label>
        <input type="text" id="description_light" name="description">
        <label>Report Description: </label>
        <input type="text" id="description_light" name="description">

        <label>Attach Photo:</label>
        <input type="file" name="photo" accept="image/*">
        <input type="file" name="photo" accept="image/*">

        <button type="submit">Add Violation</button>
      </form>
    </div>

    <!-- MODERATE -->
    <div id="moderateForm" class="form-container">
      <form method="POST" enctype="multipart/form-data"
            action="<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?student_id=' . urlencode($studentId) ?>">
        <p><strong>Moderate Offenses</strong></p>

        <input type="hidden" name="offense_category" value="moderate">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">

        <select id="moderateOffenses" name="offense_type">
          <option value="">--Select--</option>
          <option value="improper_conduct">Improper Language & Conduct</option>
          <option value="gadget_misuse">Gadget Misuse</option>
          <option value="unauthorized_acts">Unauthorized Acts</option>
        </select>

        <!-- added: checklist slot -->
        <div id="moderateChecklistSlot" class="checklist-slot" aria-live="polite"></div>

        <div id="moderate_improper_conductCheckbox" style="display:none">
          <label><input type="checkbox" name="conduct_offense[]" value="vulgar">Use of curses and vulgar words</label>
          <label><input type="checkbox" name="conduct_offense[]" value="rough_behavior">Roughness in behavior</label>
        </div>

        <div id="moderate_gadget_misuseCheckbox" style="display:none">
          <label><input type="checkbox" name="gadget_offense[]" value="cp_classes">Use of cellular phones during classes</label>
          <label><input type="checkbox" name="gadget_offense[]" value="gadgets_functions">Use of gadgets during academic functions</label>
        </div>

        <div id="moderate_unauthorized_actsCheckbox" style="display:none">
          <label><input type="checkbox" name="acts_offense[]" value="illegal_posters">Posting posters, streamers, banners without approval</label>
          <label><input type="checkbox" name="acts_offense[]" value="pda">PDA (Public Display of Affection)</label>
        </div>

        <label>Report Description: </label><br>
        <input type="text" id="description_moderate" name="description"><br><br>

        <label>Attach Photo:</label>
        <input type="file" name="photo" accept="image/*">

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
      <form method="POST" enctype="multipart/form-data"
            action="<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?student_id=' . urlencode($studentId) ?>">
        <p><strong>Grave Offenses</strong></p>

        <input type="hidden" name="offense_category" value="grave">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">

        <select id="graveOffenses" name="offense_type">
          <option value="">--Select--</option>
          <option value="substance_addiction">Substance & Addiction</option>
          <option value="integrity_dishonesty">Academic Integrity & Dishonesty</option>
          <option value="violence_misconduct">Violence & Misconduct</option>
          <option value="property_theft">Property & Theft</option>
          <option value="threats_disrespect">Threats & Disrespect</option>
        </select>

        <!-- added: checklist slot -->
        <div id="graveChecklistSlot" class="checklist-slot" aria-live="polite"></div>

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
          <label><input type="checkbox" name="threats_offense[]" value="firearms">Carrying deadly weapons, firearms, explosives</label>
          <label><input type="checkbox" name="threats_offense[]" value="disrespect">Offensive words / disrespectful deeds</label>
        </div>

        <label>Report Description: </label><br>
        <input type="text" id="description_grave" name="description"><br><br>

        <label>Attach Photo:</label>
        <input type="file" name="photo" accept="image/*" onchange="previewPhoto(this, 'gravePreview')">
        <img id="gravePreview" width="100">

            <br>
            <button type="submit">Add Violation</button>
        </form>
    </div>

  <script>
    // Top sheet open/close behavior
    const sheet = document.getElementById('topSheet');
    const scrim = document.getElementById('sheetScrim');
    const openBtn = document.getElementById('openMenu');
    const closeBtn = document.getElementById('closeMenu');

    function openSheet(){
      sheet.classList.add('open');
      scrim.classList.add('open');
      sheet.setAttribute('aria-hidden','false');
      scrim.setAttribute('aria-hidden','false');
      openBtn.setAttribute('aria-expanded','true');
      document.body.classList.add('no-scroll');
    }
    function closeSheet(){
      sheet.classList.remove('open');
      scrim.classList.remove('open');
      sheet.setAttribute('aria-hidden','true');
      scrim.setAttribute('aria-hidden','true');
      openBtn.setAttribute('aria-expanded','false');
      document.body.classList.remove('no-scroll');
    }
    openBtn.addEventListener('click', openSheet);
    closeBtn.addEventListener('click', closeSheet);
    scrim.addEventListener('click', closeSheet);
    document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') closeSheet(); });

    // Forms show/hide
    function toggleForms(){
      const selected = document.getElementById("offense_category").value;
      ['light', 'moderate', 'grave'].forEach(t=>{
        const el = document.getElementById(t+'Form');
        if (el) el.style.display = (selected===t)?'block':'none';
      });
    }
    // set default (optional)
    window.addEventListener('load', toggleForms);

    // Light group toggle
    document.getElementById('lightOffenses').addEventListener('change', function() {
      document.getElementById('light_idCheckbox').style.display = 'none';
      document.getElementById('light_uniformCheckbox').style.display = 'none';
      document.getElementById('light_civilianCheckbox').style.display = 'none';
      document.getElementById('light_accessoriesCheckbox').style.display = 'none';
      const selected = this.value;
      if(selected) {
        const checkboxDiv = document.getElementById('light_' + selected + 'Checkbox');
        if(checkboxDiv) checkboxDiv.style.display = 'block';
      }
    });

    // Moderate group toggle
    document.getElementById('moderateOffenses').addEventListener('change', function() {
      document.getElementById('moderate_improper_conductCheckbox').style.display = 'none';
      document.getElementById('moderate_gadget_misuseCheckbox').style.display = 'none';
      document.getElementById('moderate_unauthorized_actsCheckbox').style.display = 'none';
      const selected = this.value;
      if(selected) {
        const checkboxDiv = document.getElementById('moderate_' + selected + 'Checkbox');
        if(checkboxDiv) checkboxDiv.style.display = 'block';
      }
    });

    // Grave group toggle
    document.getElementById('graveOffenses').addEventListener('change', function() {
      document.getElementById('grave_substance_addictionCheckbox').style.display = 'none';
      document.getElementById('grave_integrity_dishonestyCheckbox').style.display = 'none';
      document.getElementById('grave_violence_misconductCheckbox').style.display = 'none';
      document.getElementById('grave_property_theftCheckbox').style.display = 'none';
      document.getElementById('grave_threats_disrespectCheckbox').style.display = 'none';
      const selected = this.value;
      if(selected) {
        const checkboxDiv = document.getElementById('grave_' + selected + 'Checkbox');
        if(checkboxDiv) checkboxDiv.style.display = 'block';
      }
    });
 
    (function(){
      function setupChecklistRelocation(group, keys, selectId){
        const slot = document.getElementById(group + 'ChecklistSlot');
        if (!slot) return;

        // Create a hidden warehouse right after the slot
        const warehouse = document.createElement('div');
        warehouse.id = group + 'ChecklistWarehouse';
        warehouse.style.display = 'none';
        slot.insertAdjacentElement('afterend', warehouse);

        // Move existing checklists into the warehouse initially
        keys.forEach(k => {
          const pane = document.getElementById(`${group}_${k}Checkbox`);
          if (pane) {
            warehouse.appendChild(pane);
            pane.style.display = 'none';
          }
        });

        // Helper to mount selected checklist into the slot
        function mount(selected){
          // return any current child back to warehouse
          while (slot.firstChild) warehouse.appendChild(slot.firstChild);

          // hide all panes
          keys.forEach(k => {
            const p = document.getElementById(`${group}_${k}Checkbox`);
            if (p) p.style.display = 'none';
          });

          if (!selected) return;

          const active = document.getElementById(`${group}_${selected}Checkbox`);
          if (active) {
            active.style.display = 'block';
            slot.appendChild(active); // move into slot
          }
        }

        // Hook to the selector (run after original listeners)
        const sel = document.getElementById(selectId);
        if (sel) {
          sel.addEventListener('change', function(){ mount(this.value); });
          // initial mount if a value is preselected
          mount(sel.value);
        }
      }

      // initialize for each group
      setupChecklistRelocation('light', ['id','uniform','civilian','accessories'], 'lightOffenses');
      setupChecklistRelocation('moderate', ['improper_conduct','gadget_misuse','unauthorized_acts'], 'moderateOffenses');
      setupChecklistRelocation('grave', [
        'substance_addiction','integrity_dishonesty','violence_misconduct','property_theft','threats_disrespect'
      ], 'graveOffenses');
    })();
  </script>
</body>
</html>
