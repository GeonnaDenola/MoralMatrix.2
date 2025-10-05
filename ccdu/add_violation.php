<?php
// ccdu/add_violation.php

// Start output buffering FIRST so accidental output in included files won't break redirects.
// Long-term: make included files (like _scanner.php) silent instead of relying on buffering.
ob_start();

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/email_lib.php';

// Include scanner (it should be silent). If your scanner needs to run only for GET/HTML rendering,
// you can move this include further down (after POST handling). Keeping it here is fine when the scanner
// does not echo/print anything.
include __DIR__ . '/_scanner.php';

/* ---------- STUDENT ID FROM GET OR POST ---------- */
$studentId = $_GET['student_id'] ?? $_POST['student_id'] ?? '';
if (!$studentId && ($_SERVER['REQUEST_METHOD'] !== 'POST')) {
    // For GET views we need a student id; for POST we use the hidden input
    echo "<p>No student selected!</p>";
    // flush buffer and exit
    ob_end_flush();
    exit;
}

/* ---------- DB CONNECTION ---------- */
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    // flush buffer before dying
    ob_end_flush();
    die("Connection failed: " . $conn->connect_error);
}

/* ========= INSERT HANDLER (RUNS BEFORE ANY OUTPUT) ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_id       = $_POST['student_id']       ?? '';
    $offense_category = $_POST['offense_category'] ?? '';
    $offense_type     = $_POST['offense_type']     ?? '';
    $description      = $_POST['description']      ?? '';

    if ($student_id === '' || $offense_category === '' || $offense_type === '') {
        http_response_code(400);
        ob_end_flush();
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

    // ---- Photo upload (optional) ----
    $photo = "";
    if (isset($_FILES["photo"]) && is_uploaded_file($_FILES["photo"]["tmp_name"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
        // Ensure correct path with slash
        $uploadDir = dirname(__DIR__) . "/admin/uploads/";
        if (!is_dir($uploadDir)) {
            // create directory (restrict permissions if appropriate for your environment)
            mkdir($uploadDir, 0777, true);
        }

        // Safer filename: keep dots, dashes, underscores, alnum
        $original = basename($_FILES["photo"]["name"]);
        $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
        $photo = time() . "_" . $safeBase;

        $targetPath = $uploadDir . $photo;

        if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetPath)) {
            // If upload fails, don't block the whole request
            $photo = "";
        }
    }

    $submitted_by  = $_SESSION['actor_id'] ?? 'unknown';

    // Insert (no output before this!)
    $sql = "INSERT INTO student_violation
            (student_id, offense_category, offense_type, offense_details, description, photo, status, submitted_by)
            VALUES (?, ?, ?, ?, ?, ?, 'approved', ?)";

    $stmtIns = $conn->prepare($sql);
    if (!$stmtIns) {
        http_response_code(500);
        ob_end_flush();
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters: use strings; NULL will be converted to empty string by mysqli bind,
    // but if you want true SQL NULL you can use bind_result + explicit NULL handling.
    $detail_for_bind = $offense_details ?? null; // keep as-is; note: bind_param will treat null as empty string
    $stmtIns->bind_param(
        "sssssss",
        $student_id,
        $offense_category,
        $offense_type,
        $detail_for_bind,
        $description,
        $photo,
        $submitted_by
    );

    if (!$stmtIns->execute()) {
        http_response_code(500);
        $err = $stmtIns->error;
        $stmtIns->close();
        ob_end_flush();
        die("Insert failed: " . $err);
    }
    $stmtIns->close();

    $stmtStu = $conn->prepare("SELECT first_name, last_name, email FROM student_account WHERE student_id = ?");
$stmtStu->bind_param("s", $student_id);
$stmtStu->execute();
$resStu = $stmtStu->get_result();

if ($stu = $resStu->fetch_assoc()) {
    $toEmail = $stu['email'];
    $toName  = trim($stu['first_name'].' '.$stu['last_name']);

    if (!empty($toEmail)) {
        $mail = moralmatrix_mailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'Violation Recorded in Moral Matrix';

        $html = '
            <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;line-height:1.5">
                <h2>Dear '.htmlspecialchars($toName).',</h2>
                <p>A new violation has been recorded in your account.</p>
                <p><strong>Category:</strong> '.htmlspecialchars($violation_category).'</p>
                <p><strong>Details:</strong> '.nl2br(htmlspecialchars($offense_details ?? 'N/A')).'</p>
                <p><strong>Date:</strong> '.date("F j, Y g:i A").'</p>
                <p>You may log in to your Moral Matrix account for more details.</p>
            </div>';

        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);

        try {
            $mail->send();
            error_log("Violation email sent to $toEmail");
        } catch (Throwable $e) {
            error_log("Violation email error: ".$mail->ErrorInfo);
        }
    }
}


    header("Location: view_student.php?student_id=" . urlencode($student_id) . "&saved=1");
    ob_end_flush();
    exit;
}

/* ========= END INSERT HANDLER ========= */

/* From here on, it's safe to output HTML */
// include page header (this may echo HTML)
include __DIR__ . '/../includes/header.php';

/* ---------- FETCH STUDENT FOR DISPLAY ---------- */
if ($studentId) {
    $sql = "SELECT * FROM student_account WHERE student_id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $studentId);
        $stmt->execute();
        $result  = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
    } else {
        $student = null;
    }
} else {
    $student = null;
}

// rest of the HTML / form rendering goes here...
// (omit closing PHP tag to avoid accident
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add Violation</title>
  <link rel="stylesheet" href="../css/add_violation.css"/>
</head>
<body>

<main class="page">
  <section class="card page__body">
    <!-- Profile -->
    <div class="profile">
      <?php if (!empty($student)): ?>
        <div class="profile__media">
          <img
            src="<?= !empty($student['photo']) ? '../admin/uploads/'.htmlspecialchars($student['photo'], ENT_QUOTES) : 'placeholder.png' ?>"
            alt="Student photo"
            class="profile__img"
          >
        </div>
        <div class="profile__info">
          <div class="profile__name">
            <strong><?= htmlspecialchars(trim(($student['first_name'] ?? '').' '.($student['middle_name'] ?? '').' '.($student['last_name'] ?? ''))) ?></strong>
          </div>
          <div class="profile__meta">
            <span class="badge"><?= htmlspecialchars($student['student_id']) ?></span>
            <span class="divider" aria-hidden="true">•</span>
            <span><?= htmlspecialchars($student['course']) ?> —
              <?= htmlspecialchars(($student['level'] ?? '').($student['section'] ?? '')) ?>
            </span>
          </div>
        </div>
      <?php else: ?>
        <p>Student not found.</p>
      <?php endif; ?>
    </div>

    <!-- Category selector -->
    <div class="section">
      <h2 class="section__title">Add Violation</h2>
      <div class="form-row">
        <label for="offense_category" class="label">Offense Category <span class="req">*</span></label>
        <select id="offense_category" class="select" onchange="toggleForms()" required>
          <option value="">— Select —</option>
          <option value="light">Light</option>
          <option value="moderate">Moderate</option>
          <option value="grave">Grave</option>
        </select>
      </div>
    </div>

    <!-- LIGHT -->
    <div id="lightForm" class="offense-form" hidden>
      <form method="POST" enctype="multipart/form-data" class="form">
        <input type="hidden" name="offense_category" value="light">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id'] ?? '') ?>">

        <fieldset class="section">
          <legend class="section__title">Light Offenses</legend>

          <div class="form-row">
            <label for="lightOffenses" class="label">Type <span class="req">*</span></label>
            <select id="lightOffenses" name="offense_type" class="select" required>
              <option value="">— Select —</option>
              <option value="id">ID</option>
              <option value="uniform">Dress Code (Uniform)</option>
              <option value="civilian">Revealing Clothes (Civilian Attire)</option>
              <option value="accessories">Accessories</option>
            </select>
          </div>

          <div id="light_idCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="id_offense[]" value="no_id"> No ID</label>
            <label class="chip"><input type="checkbox" name="id_offense[]" value="borrowed"> Borrowed ID</label>
          </div>

          <div id="light_uniformCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="uniform_offense[]" value="socks"> Socks</label>
            <label class="chip"><input type="checkbox" name="uniform_offense[]" value="skirt"> Skirt</label>
          </div>

          <div id="light_civilianCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="civilian_offense[]" value="crop_top"> Crop Top</label>
            <label class="chip"><input type="checkbox" name="civilian_offense[]" value="sando"> Sando</label>
          </div>

          <div id="light_accessoriesCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="accessories_offense[]" value="piercings"> Piercing/s</label>
            <label class="chip"><input type="checkbox" name="accessories_offense[]" value="hair_color"> Loud Hair Color</label>
          </div>

          <div class="form-row">
            <label for="description_light" class="label">Report Description</label>
            <textarea id="description_light" name="description" class="input" rows="3" placeholder="Short description..."></textarea>
          </div>

          <div class="form-row">
            <label class="label">Attach Photo</label>
            <div class="upload">
              <input type="file" name="photo" accept="image/*" class="file" onchange="previewPhoto(this, 'lightPreview')">
              <div class="preview-wrap">
                <img id="lightPreview" class="preview-lg" alt="" hidden>
              </div>
            </div>
          </div>

          <div class="actions">
            <button type="submit" class="btn">Add Violation</button>
          </div>
        </fieldset>
      </form>
    </div>

    <!-- MODERATE -->
    <div id="moderateForm" class="offense-form" hidden>
      <form method="POST" enctype="multipart/form-data" class="form">
        <input type="hidden" name="offense_category" value="moderate">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id'] ?? '') ?>">

        <fieldset class="section">
          <legend class="section__title">Moderate Offenses</legend>

          <div class="form-row">
            <label for="moderateOffenses" class="label">Type <span class="req">*</span></label>
            <select id="moderateOffenses" name="offense_type" class="select" required>
              <option value="">— Select —</option>
              <option value="improper_conduct">Improper Language & Conduct</option>
              <option value="gadget_misuse">Gadget Misuse</option>
              <option value="unauthorized_acts">Unauthorized Acts</option>
            </select>
          </div>

          <div id="moderate_improper_conductCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="conduct_offense[]" value="vulgar"> Use of curses and vulgar words</label>
            <label class="chip"><input type="checkbox" name="conduct_offense[]" value="rough_behavior"> Roughness in behavior</label>
          </div>

          <div id="moderate_gadget_misuseCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="gadget_offense[]" value="cp_classes"> Use of cellular phones during classes</label>
            <label class="chip"><input type="checkbox" name="gadget_offense[]" value="gadgets_functions"> Use of gadgets during academic functions</label>
          </div>

          <div id="moderate_unauthorized_actsCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="acts_offense[]" value="illegal_posters"> Posting posters/streamers/banners without approval</label>
            <label class="chip"><input type="checkbox" name="acts_offense[]" value="pda"> PDA (Public Display of Affection)</label>
          </div>

          <div class="form-row">
            <label for="description_moderate" class="label">Report Description</label>
            <textarea id="description_moderate" name="description" class="input" rows="3" placeholder="Short description..."></textarea>
          </div>

          <div class="form-row">
            <label class="label">Attach Photo</label>
            <div class="upload">
              <input type="file" name="photo" accept="image/*" class="file" onchange="previewPhoto(this, 'moderatePreview')">
              <div class="preview-wrap">
                <img id="moderatePreview" class="preview-lg" alt="" hidden>
              </div>
            </div>
          </div>

          <div class="actions">
            <button type="submit" class="btn">Add Violation</button>
          </div>
        </fieldset>
      </form>
    </div>

    <!-- GRAVE -->
    <div id="graveForm" class="offense-form" hidden>
      <form method="POST" enctype="multipart/form-data" class="form">
        <input type="hidden" name="offense_category" value="grave">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id'] ?? '') ?>">

        <fieldset class="section">
          <legend class="section__title">Grave Offenses</legend>

          <div class="form-row">
            <label for="graveOffenses" class="label">Type <span class="req">*</span></label>
            <select id="graveOffenses" name="offense_type" class="select" required>
              <option value="">— Select —</option>
              <option value="substance_addiction">Substance & Addiction</option>
              <option value="integrity_dishonesty">Academic Integrity & Dishonesty</option>
              <option value="violence_misconduct">Violence & Misconduct</option>
              <option value="property_theft">Property & Theft</option>
              <option value="threats_disrespect">Threats & Disrespect</option>
            </select>
          </div>

          <div id="grave_substance_addictionCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="substance_offense[]" value="smoking"> Smoking</label>
            <label class="chip"><input type="checkbox" name="substance_offense[]" value="gambling"> Gambling</label>
          </div>

          <div id="grave_integrity_dishonestyCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="integrity_offense[]" value="forgery"> Forgery, falsifying, tampering of documents</label>
            <label class="chip"><input type="checkbox" name="integrity_offense[]" value="dishonesty"> Dishonesty</label>
          </div>

          <div id="grave_violence_misconductCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="violence_offense[]" value="assault"> Assault</label>
            <label class="chip"><input type="checkbox" name="violence_offense[]" value="hooliganism"> Hooliganism</label>
          </div>

          <div id="grave_property_theftCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="property_offense[]" value="theft"> Theft</label>
            <label class="chip"><input type="checkbox" name="property_offense[]" value="destruction_of_property"> Willful destruction of school property</label>
          </div>

          <div id="grave_threats_disrespectCheckbox" class="chips" hidden>
            <span class="chips__label">Select specific issue(s):</span>
            <label class="chip"><input type="checkbox" name="threats_offense[]" value="firearms"> Carrying deadly weapons/firearms/explosives</label>
            <label class="chip"><input type="checkbox" name="threats_offense[]" value="disrespect"> Offensive words / disrespectful deeds</label>
          </div>

          <div class="form-row">
            <label for="description_grave" class="label">Report Description</label>
            <textarea id="description_grave" name="description" class="input" rows="3" placeholder="Short description..."></textarea>
          </div>

          <div class="form-row">
            <label class="label">Attach Photo</label>
            <div class="upload">
              <input type="file" name="photo" accept="image/*" class="file" onchange="previewPhoto(this, 'gravePreview')">
              <div class="preview-wrap">
                <img id="gravePreview" class="preview-lg" alt="" hidden>
              </div>
            </div>
          </div>

          <div class="actions">
            <button type="submit" class="btn">Add Violation</button>
          </div>
        </fieldset>
      </form>
    </div>
  </section>
</main>

<script>
  // Show image preview centered under the chooser
  function previewPhoto(input, previewId){
    const img = document.getElementById(previewId);
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = e => {
        img.src = e.target.result;
        img.hidden = false;
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  // Toggle which category form is visible
  function toggleForms(){
    const selected = document.getElementById('offense_category').value;
    ['light','moderate','grave'].forEach(key=>{
      const el = document.getElementById(key+'Form');
      if (el) el.hidden = (selected !== key);
    });
  }

  // Handle sub-type checkbox groups
  window.addEventListener('DOMContentLoaded', () => {
    const lightSel = document.getElementById('lightOffenses');
    const modSel   = document.getElementById('moderateOffenses');
    const graveSel = document.getElementById('graveOffenses');

    if (lightSel) lightSel.addEventListener('change', function(){
      ['id','uniform','civilian','accessories'].forEach(k=>{
        const box = document.getElementById('light_'+k+'Checkbox');
        if (box) box.hidden = true;
      });
      const box = document.getElementById('light_'+this.value+'Checkbox');
      if (box) box.hidden = false;
    });

    if (modSel) modSel.addEventListener('change', function(){
      ['improper_conduct','gadget_misuse','unauthorized_acts'].forEach(k=>{
        const box = document.getElementById('moderate_'+k+'Checkbox');
        if (box) box.hidden = true;
      });
      const box = document.getElementById('moderate_'+this.value+'Checkbox');
      if (box) box.hidden = false;
    });

    if (graveSel) graveSel.addEventListener('change', function(){
      ['substance_addiction','integrity_dishonesty','violence_misconduct','property_theft','threats_disrespect'].forEach(k=>{
        const box = document.getElementById('grave_'+k+'Checkbox');
        if (box) box.hidden = true;
      });
      const box = document.getElementById('grave_'+this.value+'Checkbox');
      if (box) box.hidden = false;
    });

    toggleForms();
  });
</script>
</body>
</html>
