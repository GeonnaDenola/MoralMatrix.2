<?php
// ccdu/add_violation.php
ob_start();
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php'; // adjust path as needed

require_once __DIR__ . '/../lib/email_lib.php';
require_once __DIR__ . '/../lib/notify.php';

// Include scanner (silent)
include __DIR__ . '/_scanner.php';

// HTML escape helper
if (!function_exists('h')) {
    function h(?string $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Check SMTP "from" configured
if (!function_exists('smtp_from_configured')) {
    function smtp_from_configured(): bool {
        $cfg  = $GLOBALS['smtp'] ?? [];
        $from = $cfg['from']
            ?? (getenv('MORALMATRIX_FROM_EMAIL')
            ?: ($cfg['user'] ?? getenv('MORALMATRIX_SMTP_USER') ?: ''));
        return $from && filter_var($from, FILTER_VALIDATE_EMAIL);
    }
}

$errorMessage = null;
$student      = null;
$studentName  = '';
$studentCourse= '';
$studentLevel = '';
$studentEmail = '';
$studentPhoto = '../admin/uploads/placeholder.png';

/* ---------- STUDENT ID ---------- */
$studentId = $_GET['student_id'] ?? $_POST['student_id'] ?? '';
if (!$studentId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<p>No student selected!</p>";
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
    ob_end_flush();
    die("Connection failed: " . $conn->connect_error);
}

/* ========= INSERT HANDLER ========= */
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

    // Collect offense details
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
    $offense_details = json_encode($picked ?: [], JSON_UNESCAPED_UNICODE);


    // ---- Photo upload ----
    $photo = "";
    if (
        isset($_FILES["photo"]) &&
        is_uploaded_file($_FILES["photo"]["tmp_name"]) &&
        $_FILES["photo"]["error"] === UPLOAD_ERR_OK
    ) {
        $uploadDir = __DIR__ . "/uploads/"; // ✅ save under ccdu/uploads/
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $original = basename($_FILES["photo"]["name"]);
        $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
        $photo = time() . "_" . $safeBase;

        $targetPath = $uploadDir . $photo;

        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetPath)) {
            // ✅ store relative path in DB (good practice)
            $photo = "ccdu/uploads/" . $photo;
        } else {
            error_log("❌ Failed to move uploaded file from temp: " . $_FILES["photo"]["tmp_name"]);
            $photo = "";
        }
    }

    $submitted_by = $_SESSION['actor_id'] ?? 'unknown';

    // Insert violation
    $sql = "INSERT INTO student_violation
            (student_id, offense_category, offense_type, offense_details, description, photo, status, submitted_by)
            VALUES (?, ?, ?, ?, ?, ?, 'approved', ?)";
    $stmtIns = $conn->prepare($sql);
    if (!$stmtIns) {
        http_response_code(500);
        ob_end_flush();
        die("Prepare failed: " . $conn->error);
    }

    $detail_for_bind = $offense_details;
    $stmtIns->bind_param("sssssss",
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
    $violationId = $conn->insert_id;

    // Fetch student email info
    $stmtStu = $conn->prepare("SELECT first_name, last_name, email FROM student_account WHERE student_id = ?");
    $stmtStu->bind_param("s", $student_id);
    $stmtStu->execute();
    $resStu = $stmtStu->get_result();
    $stu = $resStu->fetch_assoc();
    $stmtStu->close();

    $toEmail = trim((string)($stu['email'] ?? ''));
    $toName  = trim((string)($stu['first_name'] ?? '') . ' ' . (string)($stu['last_name'] ?? ''));
    $studentFullName = $toName ?: 'Student ' . $student_id;

    // --- EMAIL NOTIFICATION ---
    $recipientOk = $toEmail !== '' && filter_var($toEmail, FILTER_VALIDATE_EMAIL);
    $smtpOk      = smtp_from_configured();

    if ($recipientOk && $smtpOk) {
        $mail = moralmatrix_mailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'Violation Recorded in Moral Matrix';
        $html = '
            <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;line-height:1.5">
                <h2>Dear '.h($toName).',</h2>
                <p>A new violation has been recorded in your account.</p>
                <p><strong>Category:</strong> '.h($offense_category).'</p>
                <p><strong>Details:</strong> '.nl2br(h($offense_details ?? 'N/A')).'</p>
                <p><strong>Date:</strong> '.date("F j, Y g:i A").'</p>
                <p>You may log in to your Moral Matrix account for more details.</p>
            </div>';
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);
        try {
            $mail->send();
        } catch (Throwable $e) {
            error_log("Mailer error: ".$mail->ErrorInfo);
        }
    }

    // --- NOTIFY CCDU & STUDENT ---
    $actorRole = strtolower($_SESSION['account_type'] ?? '');
    $actorId   = $_SESSION['actor_id'] ?? 'unknown';

Notify::create($conn, [
    'type'         => ($actorRole === 'ccdu') ? 'success' : 'warning',
    'target_role'  => 'ccdu',
    'title'        => ($actorRole === 'ccdu')
                      ? 'New violation added by CCDU'
                      : 'Violation reported by ' . ucfirst($actorRole ?: 'staff'),
    'body'         => $studentFullName . ' • Student ID: ' . $student_id,
    'url'          => asset('ccdu/view_student.php?student_id=' . urlencode($student_id) . '#v' . $violationId),
    'violation_id' => $violationId,
    'created_by'   => $actorId,
]);

Notify::create($conn, [
    'type'           => 'info',
    'target_role'    => 'student',
    'target_user_id' => $student_id,
    'title'          => 'A violation was filed on your account',
    'body'           => 'Please review the entry for ' . $studentFullName . '.',
    'url'            => asset('student/violations.php#v' . $violationId),
    'violation_id'   => $violationId,
    'created_by'     => $actorId,
]);

header('Location: ' . asset('ccdu/view_student.php?student_id=' . urlencode($student_id) . '&saved=1'));
ob_end_flush();
exit;

}
/* ========= END INSERT HANDLER ========= */

include __DIR__ . '/../includes/header.php';

/* ---------- RECONNECT (fix for closed $conn) ---------- */
// Reconnect if $conn is missing or already closed
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli(
        $database_settings['servername'],
        $database_settings['username'],
        $database_settings['password'],
        $database_settings['dbname']
    );
} else {
    // ping safely only if still an active mysqli object
    try {
        if (!$conn->ping()) {
            $conn = new mysqli(
                $database_settings['servername'],
                $database_settings['username'],
                $database_settings['password'],
                $database_settings['dbname']
            );
        }
    } catch (Throwable $e) {
        // object was already closed or invalid, reconnect
        $conn = new mysqli(
            $database_settings['servername'],
            $database_settings['username'],
            $database_settings['password'],
            $database_settings['dbname']
        );
    }
}

if ($conn->connect_error) {
    http_response_code(500);
    die("Reconnection failed: " . $conn->connect_error);
}


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
    }
}

if (!$student) {
    $errorMessage = 'Student record not found.';
} else {
    $nameParts = array_filter([
        $student['first_name'] ?? '',
        $student['middle_name'] ?? '',
        $student['last_name'] ?? '',
    ], fn($p) => trim((string)$p) !== '');

    $studentName   = trim(implode(' ', $nameParts)) ?: 'Unnamed student';
    $studentCourse = trim((string)($student['course'] ?? ''));
    $studentLevel  = trim((string)($student['level'] ?? '') . ' ' . (string)($student['section'] ?? ''));
    $studentEmail  = trim((string)($student['email'] ?? ''));
    if (!empty($student['photo'])) {
        $studentPhoto = '../admin/uploads/' . rawurlencode((string)$student['photo']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Violation</title>
  <link rel="stylesheet" href="../css/add_violation.css">
</head>
<body>
<!-- your HTML below stays identical -->

  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Violation</title>
    <link rel="stylesheet" href="../css/add_violation.css">
  </head>
  <body>

  <main class="page">
    <div class="violation-shell">
      <aside class="student-card">
        <div class="student-card__header">
          <span class="card-label">Student</span>
          <h1><?= $student ? h($studentName) : 'Profile unavailable'; ?></h1>
          <p class="student-card__subtitle">
            <?= $student ? 'Verify the details before filing a report.' : 'Choose a student from the roster to begin.'; ?>
          </p>
        </div>

        <div class="student-card__body">
          <div class="student-card__photo">
            <img src="<?= h($studentPhoto); ?>" alt="Student photo"
                onerror="this.src='../admin/uploads/placeholder.png'; this.onerror=null;">
          </div>

          <?php if ($student): ?>
            <dl class="student-meta">
              <div>
                <dt>ID number</dt>
                <dd><?= h($student['student_id'] ?? '-'); ?></dd>
              </div>
              <div>
                <dt>Course</dt>
                <dd><?= $studentCourse !== '' ? h($studentCourse) : 'Not provided'; ?></dd>
              </div>
              <?php if ($studentLevel !== ''): ?>
                <div>
                  <dt>Year &amp; section</dt>
                  <dd><?= h($studentLevel); ?></dd>
                </div>
              <?php endif; ?>
              <div>
                <dt>Email</dt>
                <dd><?= $studentEmail !== '' ? h($studentEmail) : 'Not listed'; ?></dd>
              </div>
            </dl>
          <?php else: ?>
            <div class="student-card__empty">
              <p>We couldn't load any information for this student.</p>
            </div>
          <?php endif; ?>
        </div>
      </aside>

      <section class="form-card">
        <header class="form-card__header">
          <span class="badge badge--accent">CCDU action</span>
          <h2>Log a student violation</h2>
          <p>Capture the incident details so the review board can take the right next steps.</p>
        </header>

        <?php if ($errorMessage !== null): ?>
          <div class="empty-state">
            <h3>Unable to display the form</h3>
            <p><?= h($errorMessage); ?></p>
            <a class="btn btn-ghost" href="dashboard.php">Return to dashboard</a>
          </div>
        <?php else: ?>
          <div class="form-context">
            <div class="field">
              <label for="offense_category" class="field-label">Offense category</label>
              <div class="select-wrapper">
                <select id="offense_category" class="select-control" required>
                  <option value="">Choose a category</option>
                  <option value="light" selected>Light</option>
                  <option value="moderate">Moderate</option>
                  <option value="grave">Grave</option>
                </select>
              </div>
            </div>
            <div class="context-note">
              <strong>Reminder:</strong> Entries submitted here are approved immediately and visible to CCDU records.
            </div>
          </div>

          <div class="forms-stack">
<!-- LIGHT OFFENSES -->
<form id="lightForm" class="category-panel" method="POST" enctype="multipart/form-data" novalidate>
  <div class="panel-header">
    <span class="panel-eyebrow">Category — Light</span>
    <h3>Wearing of ID, School Uniform, and Personal Attire</h3>
    <p>Minor infractions related to proper uniform, ID use, grooming, and facility use.</p>
  </div>

  <input type="hidden" name="offense_category" value="light">
  <input type="hidden" name="student_id" value="<?= h($student['student_id'] ?? ''); ?>">

  <div class="field">
    <label for="lightOffenses" class="field-label">Offense type</label>
    <div class="select-wrapper">
      <select id="lightOffenses" name="offense_type" class="select-control" required>
        <option value="">Select an offense type</option>
        <option value="id_policy">ID Policy</option>
        <option value="uniform_policy">Uniform Policy</option>
        <option value="civilian_attire">Civilian Attire</option>
        <option value="accessories_and_hair">Accessories and Hair</option>
        <option value="school_facilities">Use of School Facilities</option>
        <option value="loitering">Loitering</option>
      </select>
    </div>
  </div>

  <!-- ID POLICY -->
  <div id="light_id_policyCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">ID Policy</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="id_offense[]" value="Failure to wear ID"><span>Failure to wear ID within school premises</span></label>
      <label class="chip"><input type="checkbox" name="id_offense[]" value="No official ID lace"><span>Not using the official ID lace</span></label>
      <label class="chip"><input type="checkbox" name="id_offense[]" value="Borrowed or lent ID"><span>Use of borrowed or lent ID</span></label>
      <label class="chip"><input type="checkbox" name="id_offense[]" value="Failure to report lost ID"><span>Failure to report lost ID to ODS for replacement or permit</span></label>
    </div>
  </div>

  <!-- UNIFORM POLICY -->
  <div id="light_uniform_policyCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Uniform Policy</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="uniform_offense[]" value="Not wearing prescribed uniform"><span>Not wearing prescribed school uniform</span></label>
      <label class="chip"><input type="checkbox" name="uniform_offense[]" value="Incomplete uniform"><span>Incomplete or improper uniform (missing ID, wrong shoes, etc.)</span></label>
      <label class="chip"><input type="checkbox" name="uniform_offense[]" value="PE uniform in class"><span>Wearing PE or NSTP uniform during academic classes</span></label>
      <label class="chip"><input type="checkbox" name="uniform_offense[]" value="Wearing slippers"><span>Wearing slippers (except during floods or major weather disturbances)</span></label>
      <label class="chip"><input type="checkbox" name="uniform_offense[]" value="Marked absent due to improper uniform"><span>Attending class improperly dressed and marked absent</span></label>
    </div>
  </div>

  <!-- CIVILIAN ATTIRE -->
  <div id="light_civilian_attireCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Civilian Attire</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="civilian_offense[]" value="Shorts or sleeveless for men"><span>Wearing shorts, muscle shirts, tattered jeans, or slippers (men)</span></label>
      <label class="chip"><input type="checkbox" name="civilian_offense[]" value="Indecent attire for women"><span>Wearing sleeveless, hanging blouses, plunging necklines, mini-skirts, tattered jeans, or slippers (women)</span></label>
    </div>
  </div>

  <!-- ACCESSORIES AND HAIR -->
  <div id="light_accessories_and_hairCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Accessories and Hair</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="accessories_offense[]" value="Body piercings"><span>Having visible body piercings</span></label>
      <label class="chip"><input type="checkbox" name="accessories_offense[]" value="Excessive accessories"><span>Excessive use of accessories</span></label>
      <label class="chip"><input type="checkbox" name="accessories_offense[]" value="Loud hair color"><span>Loud or heavy hair coloring</span></label>
      <label class="chip"><input type="checkbox" name="accessories_offense[]" value="Dangling or large earrings"><span>Wearing dangling or very large earrings (female)</span></label>
      <label class="chip"><input type="checkbox" name="accessories_offense[]" value="Improper haircut"><span>Failure to observe prescribed short haircut (male)</span></label>
    </div>
  </div>

  <!-- SCHOOL FACILITIES -->
  <div id="light_school_facilitiesCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Use of School Facilities</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="facility_offense[]" value="Violation of library rules"><span>Violation of library or laboratory rules</span></label>
      <label class="chip"><input type="checkbox" name="facility_offense[]" value="Wasting water or electricity"><span>Wasting water or electricity (not turning off lights/faucets)</span></label>
      <label class="chip"><input type="checkbox" name="facility_offense[]" value="Mishandling lab equipment"><span>Mishandling laboratory equipment</span></label>
      <label class="chip"><input type="checkbox" name="facility_offense[]" value="Improper use of comfort rooms"><span>Improper use of comfort rooms</span></label>
      <label class="chip"><input type="checkbox" name="facility_offense[]" value="Littering or vandalism"><span>Littering or minor vandalism within campus</span></label>
    </div>
  </div>

  <!-- LOITERING -->
  <div id="light_loiteringCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Loitering</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="loiter_offense[]" value="Loitering during class hours"><span>Loitering along hallways during class hours</span></label>
    </div>
  </div>

  <div class="field">
    <label for="description_light" class="field-label">Report description</label>
    <textarea id="description_light" name="description" rows="3" placeholder="Summarize what happened, when, and where."></textarea>
  </div>

  <div class="field upload-field">
    <label for="lightPhoto" class="field-label">Attach photo (optional)</label>
    <input type="file" id="lightPhoto" name="photo" accept="image/*" onchange="previewPhoto(this, 'lightPreview')" class="file-control">
    <span class="helper-text">Accepted formats: JPG, PNG, or WEBP up to 5MB.</span>
    <img id="lightPreview" class="photo-preview" alt="Light offense preview" hidden>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Submit violation</button>
  </div>
</form>

<!-- MODERATE OFFENSES -->
<form id="moderateForm" class="category-panel" method="POST" enctype="multipart/form-data" novalidate>
  <div class="panel-header">
    <span class="panel-eyebrow">Category — Moderate</span>
    <h3>Improper Conduct, Gadget Misuse, and Unauthorized Acts</h3>
    <p>Offenses that disrupt the learning environment or involve minor misconduct.</p>
  </div>

  <input type="hidden" name="offense_category" value="moderate">
  <input type="hidden" name="student_id" value="<?= h($student['student_id'] ?? ''); ?>">

  <div class="field">
    <label for="moderateOffenses" class="field-label">Offense type</label>
    <div class="select-wrapper">
      <select id="moderateOffenses" name="offense_type" class="select-control" required>
        <option value="">Select an offense type</option>
        <option value="improper_conduct">Improper Conduct</option>
        <option value="gadget_misuse">Gadget Misuse</option>
        <option value="unauthorized_acts">Unauthorized Acts</option>
      </select>
    </div>
  </div>

  <div id="moderate_improper_conductCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Improper Conduct</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="conduct_offense[]" value="Use of curses and vulgar words"><span>Use of curses or vulgar language</span></label>
      <label class="chip"><input type="checkbox" name="conduct_offense[]" value="Roughness in behavior"><span>Roughness or discourtesy in behavior</span></label>
      <label class="chip"><input type="checkbox" name="conduct_offense[]" value="Disruptive behavior"><span>Performing disruptive acts during class hours</span></label>
    </div>
  </div>

  <div id="moderate_gadget_misuseCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Gadget Misuse</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="gadget_offense[]" value="Using phones during class"><span>Using cellular phones during classes</span></label>
      <label class="chip"><input type="checkbox" name="gadget_offense[]" value="Using gadgets during functions"><span>Using gadgets during academic functions</span></label>
      <label class="chip"><input type="checkbox" name="gadget_offense[]" value="Playing loud music"><span>Playing loud music in class or corridors during break time</span></label>
    </div>
  </div>

  <div id="moderate_unauthorized_actsCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Unauthorized Acts</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="acts_offense[]" value="Posting without approval"><span>Posting posters, streamers, or banners without prior approval</span></label>
      <label class="chip"><input type="checkbox" name="acts_offense[]" value="Public display of affection"><span>Public display of intimacy or affection</span></label>
      <label class="chip"><input type="checkbox" name="acts_offense[]" value="Cutting classes"><span>Deliberate cutting of classes or walking out</span></label>
    </div>
  </div>

  <div class="field">
    <label for="description_moderate" class="field-label">Report description</label>
    <textarea id="description_moderate" name="description" rows="3" placeholder="Provide context, witnesses, or devices involved."></textarea>
  </div>

  <div class="field upload-field">
    <label for="moderatePhoto" class="field-label">Attach photo (optional)</label>
    <input type="file" id="lightPhoto" name="photo" accept="image/*" onchange="previewPhoto(this, 'moderatePreview')" class="file-control">
    <span class="helper-text">Accepted formats: JPG, PNG, or WEBP up to 5MB.</span>
    <img id="moderatePreview" class="photo-preview" alt="Light offense preview" hidden>
  </div>

    <div class="form-actions">
    <button type="submit" class="btn btn-primary">Submit violation</button>
  </div>
</form>

<!-- GRAVE OFFENSES -->
<form id="graveForm" class="category-panel" method="POST" enctype="multipart/form-data" novalidate>
  <div class="panel-header">
    <span class="panel-eyebrow">Category — Grave</span>
    <h3>Critical and Major Violations</h3>
    <p>Serious offenses that endanger others, damage property, or discredit the College.</p>
  </div>

  <input type="hidden" name="offense_category" value="grave">
  <input type="hidden" name="student_id" value="<?= h($student['student_id'] ?? ''); ?>">

  <div class="field">
    <label for="graveOffenses" class="field-label">Offense type</label>
    <div class="select-wrapper">
      <select id="graveOffenses" name="offense_type" class="select-control" required>
        <option value="">Select an offense type</option>
        <option value="substance_addiction">Substance and Addiction</option>
        <option value="property_theft">Property and Theft</option>
        <option value="violence_misconduct">Violence and Misconduct</option>
        <option value="integrity_dishonesty">Integrity and Academic Dishonesty</option>
        <option value="threats_disrespect">Threats and Disrespect</option>
        <option value="cyber_reputation">Cyber and Reputational Offenses</option>
      </select>
    </div>
  </div>

  <!-- A. Substance and Addiction -->
  <div id="grave_substance_addictionCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Substance and Addiction</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="substance_offense[]" value="Smoking in uniform"><span>Smoking while in school uniform (even outside campus)</span></label>
      <label class="chip"><input type="checkbox" name="substance_offense[]" value="Gambling in uniform"><span>Gambling while in school uniform (even outside campus)</span></label>
      <label class="chip"><input type="checkbox" name="substance_offense[]" value="Drinking alcohol in uniform"><span>Drinking hard drinks or alcoholic beverages while in uniform</span></label>
      <label class="chip"><input type="checkbox" name="substance_offense[]" value="Illegal drugs violation"><span>Violation of the Dangerous Drugs Law (possession or use of illegal drugs)</span></label>
    </div>
  </div>

  <!-- B. Property and Theft -->
  <div id="grave_property_theftCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Property and Theft</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="property_offense[]" value="Vandalism"><span>Vandalism or willful defacement of school property</span></label>
      <label class="chip"><input type="checkbox" name="property_offense[]" value="Theft"><span>Theft of school equipment or personal property</span></label>
      <label class="chip"><input type="checkbox" name="property_offense[]" value="Destruction of property"><span>Willful destruction of school facilities or belongings</span></label>
    </div>
  </div>

  <!-- C. Violence and Misconduct -->
  <div id="grave_violence_misconductCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Violence and Misconduct</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="violence_offense[]" value="Hooliganism"><span>Hooliganism, brawls, or physical fights within the campus</span></label>
      <label class="chip"><input type="checkbox" name="violence_offense[]" value="Assault"><span>Assaulting a co-student or school personnel</span></label>
      <label class="chip"><input type="checkbox" name="violence_offense[]" value="Cyber harassment"><span>Harassment, bullying, or threats via electronic means</span></label>
      <label class="chip"><input type="checkbox" name="violence_offense[]" value="Hazing"><span>Hazing or initiation that causes harm or humiliation</span></label>
      <label class="chip"><input type="checkbox" name="violence_offense[]" value="Drunkenness"><span>Drunkenness or bringing intoxicating beverages inside the campus</span></label>
      <label class="chip"><input type="checkbox" name="violence_offense[]" value="Gross misconduct"><span>Gross misconduct or indecent behavior</span></label>
    </div>
  </div>

  <!-- D. Integrity and Academic Dishonesty -->
  <div id="grave_integrity_dishonestyCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Integrity and Academic Dishonesty</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="integrity_offense[]" value="Forgery"><span>Forgery, falsification, or tampering of official school documents</span></label>
      <label class="chip"><input type="checkbox" name="integrity_offense[]" value="Dishonesty"><span>Dishonesty and cheating in any form</span></label>
    </div>
  </div>

  <!-- E. Threats and Disrespect -->
  <div id="grave_threats_disrespectCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Threats and Disrespect</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="threats_offense[]" value="Offensive language to faculty"><span>Use of offensive or disrespectful words toward faculty, staff, or co-students</span></label>
      <label class="chip"><input type="checkbox" name="threats_offense[]" value="Carrying deadly weapons"><span>Carrying firearms, explosives, or deadly weapons on school premises</span></label>
      <label class="chip"><input type="checkbox" name="threats_offense[]" value="Illegal strikes"><span>Instigating or leading illegal strikes or similar activities disrupting classes</span></label>
      <label class="chip"><input type="checkbox" name="threats_offense[]" value="Blocking school entry"><span>Preventing or threatening students or staff from entering or performing duties</span></label>
    </div>
  </div>

  <!-- F. Cyber and Reputational Offenses -->
  <div id="grave_cyber_reputationCheckbox" class="chip-group is-hidden">
    <span class="chip-group__label">Cyber and Reputational Offenses</span>
    <div class="chip-list">
      <label class="chip"><input type="checkbox" name="cyber_offense[]" value="Cybercrime against school"><span>Any form of cybercrime against students, faculty, staff, or the institution</span></label>
      <label class="chip"><input type="checkbox" name="cyber_offense[]" value="Posting obscene content"><span>Posting obscene or sexually-oriented photos or videos online</span></label>
      <label class="chip"><input type="checkbox" name="cyber_offense[]" value="Defaming the college"><span>Acts that bring the College’s name into disrepute</span></label>
      <label class="chip"><input type="checkbox" name="cyber_offense[]" value="Illegal use of school logo"><span>Illegal use of school name, seal, or logo for solicitation or unlawful acts</span></label>
      <label class="chip"><input type="checkbox" name="cyber_offense[]" value="Unaccredited recruitment"><span>Recruitment into unaccredited organizations or clubs</span></label>
      <label class="chip"><input type="checkbox" name="cyber_offense[]" value="Extortion or blackmail"><span>Extortion, blackmail, or coercion among students</span></label>
      <label class="chip"><input type="checkbox" name="cyber_offense[]" value="Entering bars in uniform"><span>Entering movie houses, bars, or similar establishments while in uniform</span></label>
      <label class="chip"><input type="checkbox" name="cyber_offense[]" value="Other unlawful acts"><span>Any other unlawful act violating school or national laws</span></label>
    </div>
  </div>

  <div class="field">
    <label for="description_grave" class="field-label">Report description</label>
    <textarea id="description_grave" name="description" rows="3" placeholder="Include witness names, locations, and immediate response."></textarea>
  </div>

  <div class="field upload-field">
    <label for="gravePhoto" class="field-label">Attach photo (optional)</label>
    <input type="file" id="gravePhoto" name="photo" accept="image/*" onchange="previewPhoto(this, 'gravePreview')" class="file-control">
    <span class="helper-text">Attach photos, documents, or screenshots that support the report.</span>
    <img id="gravePreview" class="photo-preview" alt="Grave offense preview" hidden>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Submit violation</button>
  </div>
</form>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

<script>
function previewPhoto(input, previewId) {
  const preview = document.getElementById(previewId);
  if (!preview) return;

  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = event => {
      preview.src = event.target?.result || '';
      preview.hidden = false;
    };
    reader.readAsDataURL(input.files[0]);
  } else {
    preview.hidden = true;
    preview.removeAttribute('src');
  }
}

function initOptionSwitch(selectId, prefix, values, suffix) {
  const select = document.getElementById(selectId);
  if (!select) return;

  const hideAll = () => {
    values.forEach(value => {
      const group = document.getElementById(prefix + value + suffix);
      if (group) group.classList.add('is-hidden');
    });
  };

  const showSelected = () => {
    const group = document.getElementById(prefix + select.value + suffix);
    if (group) group.classList.remove('is-hidden');
  };

  hideAll();
  showSelected();

  select.addEventListener('change', () => {
    hideAll();
    showSelected();
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const categorySelect = document.getElementById('offense_category');
  const panels = ['light', 'moderate', 'grave'];

  const syncPanels = () => {
    const selected = categorySelect ? categorySelect.value : '';
    panels.forEach(type => {
      const panel = document.getElementById(type + 'Form');
      if (panel) panel.classList.toggle('is-active', selected === type);
    });
  };

  if (categorySelect) categorySelect.addEventListener('change', syncPanels);
  syncPanels();

  /* === LIGHT OFFENSES === */
  initOptionSwitch('lightOffenses', 'light_', [
    'id_policy',
    'uniform_policy',
    'civilian_attire',
    'accessories_and_hair',
    'school_facilities',
    'loitering'
  ], 'Checkbox');

  /* === MODERATE OFFENSES === */
  initOptionSwitch('moderateOffenses', 'moderate_', [
    'improper_conduct',
    'gadget_misuse',
    'unauthorized_acts'
  ], 'Checkbox');

  /* === GRAVE OFFENSES === */
  initOptionSwitch('graveOffenses', 'grave_', [
    'substance_addiction',
    'property_theft',
    'violence_misconduct',
    'integrity_dishonesty',
    'threats_disrespect',
    'cyber_reputation'
  ], 'Checkbox');
});


/* === SIDESHEET BEHAVIOR (unchanged) === */
(function(){
  const sheet = document.getElementById('sideSheet');
  const scrim = document.getElementById('sheetScrim');
  const openBtn = document.getElementById('openMenu');
  const closeBtn = document.getElementById('closeMenu');
  if (!sheet || !scrim || !openBtn || !closeBtn) return;

  let lastFocusedEl = null;

  function trapFocus(container, e){
    const focusables = container.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])');
    if (!focusables.length) return;
    const first = focusables[0];
    const last  = focusables[focusables.length - 1];

    if (e.key === 'Tab') {
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  const focusTrapHandler = e => trapFocus(sheet, e);

  function openSheet(){
    lastFocusedEl = document.activeElement;
    sheet.classList.add('open');
    scrim.classList.add('open');
    sheet.setAttribute('aria-hidden', 'false');
    scrim.setAttribute('aria-hidden', 'false');
    openBtn.setAttribute('aria-expanded', 'true');
    document.body.classList.add('no-scroll');

    setTimeout(() => {
      const firstFocusable = sheet.querySelector('#pageButtons a, #pageButtons button, [tabindex]:not([tabindex="-1"])');
      (firstFocusable || sheet).focus();
    }, 10);

    sheet.addEventListener('keydown', focusTrapHandler);
  }

  function closeSheet(){
    sheet.classList.remove('open');
    scrim.classList.remove('open');
    sheet.setAttribute('aria-hidden', 'true');
    scrim.setAttribute('aria-hidden', 'true');
    openBtn.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('no-scroll');

    sheet.removeEventListener('keydown', focusTrapHandler);
    if (lastFocusedEl) lastFocusedEl.focus();
  }

  openBtn.addEventListener('click', openSheet);
  closeBtn.addEventListener('click', closeSheet);
  scrim.addEventListener('click', closeSheet);
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeSheet();
  });

  sheet.addEventListener('click', e => {
    const link = e.target.closest('a[href]');
    if (!link) return;
    const sameTab = !(e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0);
    if (sameTab) closeSheet();
  });
})();
</script>

  <button id="openMenu" class="menu-launcher" aria-controls="sideSheet" aria-expanded="false"
          style="position:fixed;left:-9999px;opacity:0" tabindex="-1">Menu</button>

  </body>
  </html>
