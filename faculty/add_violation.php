<?php
// faculty/add_violation.php
// All POST handling happens BEFORE any output to avoid "headers already sent".

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once '../config.php';

$studentId = $_GET['student_id'] ?? '';
if (!$studentId) {
  header('Location: dashboard.php');
  exit;
}

$servername = $database_settings['servername'] ?? 'localhost';
$username   = $database_settings['username']   ?? '';
$password   = $database_settings['password']   ?? '';
$dbname     = $database_settings['dbname']     ?? '';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  http_response_code(500);
  echo 'Database connection failed.';
  exit;
}

$errorMsg = "";

/* ========= INSERT HANDLER ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $student_id       = trim($_POST['student_id']       ?? '');
  $offense_category = trim($_POST['offense_category'] ?? '');
  $offense_type     = trim($_POST['offense_type']     ?? '');
  $description      = trim($_POST['description']      ?? '');

  if (!$student_id || !$offense_category || !$offense_type) {
    $errorMsg = "Please select a category and a specific offense type.";
  } else {
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
    // Always store JSON, even if empty array
    $offense_details = json_encode(array_values(array_unique($picked)), JSON_UNESCAPED_UNICODE);

    // Optional photo upload
    $photo = '';
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
      $allowed = ['jpg','jpeg','png','gif','webp'];
      $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
      if (in_array($ext, $allowed, true)) {
        $baseUploads = realpath(__DIR__ . '/../uploads');
        if ($baseUploads === false) { $baseUploads = __DIR__ . '/../uploads'; }
        $uploadDir = rtrim($baseUploads, '/\\') . '/violations/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0775, true); }

        $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
        $targetPath = $uploadDir . $safeName;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
          $photo = 'violations/' . $safeName; // store relative path
        } else {
          $errorMsg = "Error uploading photo. Please try again.";
        }
      } else {
        $errorMsg = "Invalid photo type. Allowed: jpg, jpeg, png, gif, webp.";
      }
    }

    if (!$errorMsg) {
      $submitted_by   = $_SESSION['actor_id']   ?? 'unknown';
      $submitted_role = $_SESSION['actor_role'] ?? 'faculty';

      $sql = "INSERT INTO student_violation
              (student_id, offense_category, offense_type, offense_details, description, photo, status, submitted_by, submitted_role)
              VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
      $stmtIns = $conn->prepare($sql);
      if (!$stmtIns) {
        http_response_code(500);
        echo 'Prepare failed.';
        exit;
      }

      $stmtIns->bind_param(
        "ssssssss",
        $student_id, $offense_category, $offense_type,
        $offense_details, $description, $photo,
        $submitted_by, $submitted_role
      );

      if (!$stmtIns->execute()) {
        http_response_code(500);
        echo 'Insert failed.';
        exit;
      }
      $stmtIns->close();

      header('Location: view_student.php?student_id=' . urlencode($student_id) . '&saved=1');
      exit;
    }
  }
}
/* ========= END INSERT HANDLER ========= */

/* ---------- FETCH STUDENT ---------- */
$sql = "SELECT student_id, first_name, middle_name, last_name, course, level, section, photo
        FROM student_account WHERE student_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

include '../includes/faculty_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add Violation</title>
  <link rel="stylesheet" href="../css/faculty_add_violation.css?v=5">
</head>
<body>

<!-- MAIN RAIL: accounts for your fixed sidebar and centers the content area -->
<div class="main-rail">
  <main class="wrap">
    <section class="card profile-card">
      <?php if ($student): ?>
        <div class="profile">
          <img
            src="<?= !empty($student['photo']) ? '../admin/uploads/' . htmlspecialchars($student['photo']) : '../images/placeholder.png' ?>"
            alt="Profile photo"
            class="avatar"
          >
          <div class="profile-meta">
            <div class="student-id"><?= htmlspecialchars($student['student_id']) ?></div>
            <div class="student-name">
              <?= htmlspecialchars(trim(($student['first_name'] ?? '').' '.($student['middle_name'] ?? '').' '.($student['last_name'] ?? ''))) ?>
            </div>
            <div class="student-course">
              <?= htmlspecialchars($student['course'] ?? '') ?> —
              <?= htmlspecialchars(($student['level'] ?? '').($student['section'] ?? '')) ?>
            </div>
          </div>
        </div>
      <?php else: ?>
        <p class="page-note error">Student not found.</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-header">
        <h3>Add Violation</h3>

        <div class="category-picker">
          <label for="offense_category" class="label">Offense Category</label>
          <select id="offense_category" class="select" onchange="toggleForms()" required>
            <option value="">-- SELECT --</option>
            <option value="light">Light</option>
            <option value="moderate">Moderate</option>
            <option value="grave">Grave</option>
          </select>
        </div>
      </div>

      <?php if (!empty($errorMsg)): ?>
        <div class="alert"><?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>

      <!-- LIGHT -->
      <div id="lightForm" class="form-container">
        <form method="POST" enctype="multipart/form-data" class="violation-form">
          <input type="hidden" name="offense_category" value="light">
          <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id'] ?? $studentId) ?>">

          <div class="form-row">
            <label for="lightOffenses" class="label">Light Offense Type</label>
            <select id="lightOffenses" name="offense_type" class="select" required>
              <option value="">-- Select --</option>
              <option value="id">ID</option>
              <option value="uniform">Dress Code (Uniform)</option>
              <option value="civilian">Revealing Clothes (Civilian Attire)</option>
              <option value="accessories">Accessories</option>
            </select>
          </div>

          <div id="light_idCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="id_offense[]" value="no_id">No ID</label>
            <label><input type="checkbox" name="id_offense[]" value="borrowed">Borrowed ID</label>
          </div>

          <div id="light_uniformCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="uniform_offense[]" value="socks">Socks</label>
            <label><input type="checkbox" name="uniform_offense[]" value="skirt">Skirt</label>
          </div>

          <div id="light_civilianCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="civilian_offense[]" value="crop_top">Crop Top</label>
            <label><input type="checkbox" name="civilian_offense[]" value="sando">Sando</label>
          </div>

          <div id="light_accessoriesCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="accessories_offense[]" value="piercings">Piercing/s</label>
            <label><input type="checkbox" name="accessories_offense[]" value="hair_color">Loud Hair Color</label>
          </div>

          <div class="form-row">
            <label for="description_light" class="label">Report Description</label>
            <textarea id="description_light" name="description" class="input" rows="3" placeholder="Add brief details…"></textarea>
          </div>

          <!-- File input stacked; large centered preview BELOW it -->
          <div class="form-row">
            <label class="label">Attach Photo (optional)</label>
            <input type="file" name="photo" accept="image/*" class="file" onchange="previewPhoto(this, 'lightPreview')">
            <img id="lightPreview" class="preview-large" style="display:none" alt="Selected preview">
          </div>

          <div class="actions">
            <button type="submit" class="btn">Add Violation</button>
          </div>
        </form>
      </div>

      <!-- MODERATE -->
      <div id="moderateForm" class="form-container">
        <form method="POST" enctype="multipart/form-data" class="violation-form">
          <input type="hidden" name="offense_category" value="moderate">
          <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id'] ?? $studentId) ?>">

          <div class="form-row">
            <label for="moderateOffenses" class="label">Moderate Offense Type</label>
            <select id="moderateOffenses" name="offense_type" class="select" required>
              <option value="">-- Select --</option>
              <option value="improper_conduct">Improper Language & Conduct</option>
              <option value="gadget_misuse">Gadget Misuse</option>
              <option value="unauthorized_acts">Unauthorized Acts</option>
            </select>
          </div>

          <div id="moderate_improper_conductCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="conduct_offense[]" value="vulgar">Use of curses and vulgar words</label>
            <label><input type="checkbox" name="conduct_offense[]" value="rough_behavior">Roughness in behavior</label>
          </div>

          <div id="moderate_gadget_misuseCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="gadget_offense[]" value="cp_classes">Use of cellular phones during classes</label>
            <label><input type="checkbox" name="gadget_offense[]" value="gadgets_functions">Use of gadgets during academic functions</label>
          </div>

          <div id="moderate_unauthorized_actsCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="acts_offense[]" value="illegal_posters">Posting posters/streamers/banners without approval</label>
            <label><input type="checkbox" name="acts_offense[]" value="pda">PDA (Public Display of Affection)</label>
          </div>

          <div class="form-row">
            <label for="description_moderate" class="label">Report Description</label>
            <textarea id="description_moderate" name="description" class="input" rows="3" placeholder="Add brief details…"></textarea>
          </div>

          <div class="form-row">
            <label class="label">Attach Photo (optional)</label>
            <input type="file" name="photo" accept="image/*" class="file" onchange="previewPhoto(this, 'moderatePreview')">
            <img id="moderatePreview" class="preview-large" style="display:none" alt="Selected preview">
          </div>

          <div class="actions">
            <button type="submit" class="btn">Add Violation</button>
          </div>
        </form>
      </div>

      <!-- GRAVE -->
      <div id="graveForm" class="form-container">
        <form method="POST" enctype="multipart/form-data" class="violation-form">
          <input type="hidden" name="offense_category" value="grave">
          <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id'] ?? $studentId) ?>">

          <div class="form-row">
            <label for="graveOffenses" class="label">Grave Offense Type</label>
            <select id="graveOffenses" name="offense_type" class="select" required>
              <option value="">-- Select --</option>
              <option value="substance_addiction">Substance & Addiction</option>
              <option value="integrity_dishonesty">Academic Integrity & Dishonesty</option>
              <option value="violence_misconduct">Violence & Misconduct</option>
              <option value="property_theft">Property & Theft</option>
              <option value="threats_disrespect">Threats & Disrespect</option>
            </select>
          </div>

          <div id="grave_substance_addictionCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="substance_offense[]" value="smoking">Smoking</label>
            <label><input type="checkbox" name="substance_offense[]" value="gambling">Gambling</label>
          </div>

          <div id="grave_integrity_dishonestyCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="integrity_offense[]" value="forgery">Forgery, falsifying, tampering of documents</label>
            <label><input type="checkbox" name="integrity_offense[]" value="dishonesty">Dishonesty</label>
          </div>

          <div id="grave_violence_misconductCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="violence_offense[]" value="assault">Assault</label>
            <label><input type="checkbox" name="violence_offense[]" value="hooliganism">Hooliganism</label>
          </div>

          <div id="grave_property_theftCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="property_offense[]" value="theft">Theft</label>
            <label><input type="checkbox" name="property_offense[]" value="destruction_of_property">Willful destruction of school property</label>
          </div>

          <div id="grave_threats_disrespectCheckbox" class="checkbox-grid" style="display:none">
            <label><input type="checkbox" name="threats_offense[]" value="firearms">Carrying deadly weapons/firearms/explosives</label>
            <label><input type="checkbox" name="threats_offense[]" value="disrespect">Offensive words / disrespectful deeds</label>
          </div>

          <div class="form-row">
            <label for="description_grave" class="label">Report Description</label>
            <textarea id="description_grave" name="description" class="input" rows="3" placeholder="Add brief details…"></textarea>
          </div>

          <div class="form-row">
            <label class="label">Attach Photo (optional)</label>
            <input type="file" name="photo" accept="image/*" class="file" onchange="previewPhoto(this, 'gravePreview')">
            <img id="gravePreview" class="preview-large" style="display:none" alt="Selected preview">
          </div>

          <div class="actions">
            <button type="submit" class="btn">Add Violation</button>
          </div>
        </form>
      </div>
    </section>
  </main>
</div>

<script>
// Centered form switching
function toggleForms(){
  const selected = document.getElementById("offense_category").value;
  ['light', 'moderate', 'grave'].forEach(t=>{
    const el = document.getElementById(t+'Form');
    if (el) el.style.display = (selected===t)?'block':'none';
  });
}

window.addEventListener('DOMContentLoaded', ()=>{
  const lightSel = document.getElementById('lightOffenses');
  if (lightSel) lightSel.addEventListener('change', function(){
    ['id','uniform','civilian','accessories'].forEach(k=>{
      const box = document.getElementById('light_'+k+'Checkbox');
      if (box) box.style.display = 'none';
    });
    const box = document.getElementById('light_'+this.value+'Checkbox');
    if (box) box.style.display = 'block';
  });

  const modSel = document.getElementById('moderateOffenses');
  if (modSel) modSel.addEventListener('change', function(){
    ['improper_conduct','gadget_misuse','unauthorized_acts'].forEach(k=>{
      const box = document.getElementById('moderate_'+k+'Checkbox');
      if (box) box.style.display = 'none';
    });
    const box = document.getElementById('moderate_'+this.value+'Checkbox');
    if (box) box.style.display = 'block';
  });

  const graveSel = document.getElementById('graveOffenses');
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

// Large, centered photo preview below the file input
function previewPhoto(input, previewId){
  const preview = document.getElementById(previewId);
  if (input.files && input.files[0]){
    const reader = new FileReader();
    reader.onload = e => {
      preview.src = e.target.result;
      preview.style.display='block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

</body>
</html>
