<?php
// ccdu/add_violation.php

// Start output buffering FIRST so accidental output in included files won't break redirects.
ob_start();

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/email_lib.php';
require_once __DIR__ . '/../lib/notify.php';

// Include scanner (it should be silent)
include __DIR__ . '/_scanner.php';

// Helper to escape HTML consistently across templates.
if (!function_exists('h')) {
    function h(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * Helper: verify SMTP "from" is configured so moralmatrix_mailer() won't throw.
 * We read from $smtp['from'] or environment fallbacks used in email_lib.php.
 */
if (!function_exists('smtp_from_configured')) {
    function smtp_from_configured(): bool
    {
        $cfg  = $GLOBALS['smtp'] ?? [];
        $from = $cfg['from']
            ?? (getenv('MORALMATRIX_FROM_EMAIL')
            ?: ($cfg['user'] ?? getenv('MORALMATRIX_SMTP_USER') ?: ''));
        return $from && filter_var($from, FILTER_VALIDATE_EMAIL);
    }
}

$errorMessage  = null;
$student       = null;
$studentName   = '';
$studentCourse = '';
$studentLevel  = '';
$studentEmail  = '';
$studentPhoto  = '../admin/uploads/placeholder.png';

/* ---------- STUDENT ID FROM GET OR POST ---------- */
$studentId = $_GET['student_id'] ?? $_POST['student_id'] ?? '';
if (!$studentId && ($_SERVER['REQUEST_METHOD'] !== 'POST')) {
    // For GET views we need a student id; for POST we use the hidden input
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
        $uploadDir = dirname(__DIR__) . "/admin/uploads/";
        if (!is_dir($uploadDir)) {
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

    $submitted_by = $_SESSION['actor_id'] ?? 'unknown';

    // Insert
    $sql = "INSERT INTO student_violation
            (student_id, offense_category, offense_type, offense_details, description, photo, status, submitted_by)
            VALUES (?, ?, ?, ?, ?, ?, 'approved', ?)";

    $stmtIns = $conn->prepare($sql);
    if (!$stmtIns) {
        http_response_code(500);
        ob_end_flush();
        die("Prepare failed: " . $conn->error);
    }

    $detail_for_bind = $offense_details; // may be null
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

    $violationId = $conn->insert_id; 

    // BEFORE notifications:
    $studentFullName = 'Student ' . $student_id;  // default
    $toEmail = '';
    $toName  = '';

    // Fetch student recipient for notification
    $stmtStu = $conn->prepare("SELECT first_name, last_name, email FROM student_account WHERE student_id = ?");
    $stmtStu->bind_param("s", $student_id);
    $stmtStu->execute();
    $resStu = $stmtStu->get_result();

    if ($stu = $resStu->fetch_assoc()) {
        $toEmail = trim((string)$stu['email']);
        $toName  = trim((string)($stu['first_name'] ?? '') . ' ' . (string)($stu['last_name'] ?? ''));

        $recipientOk = $toEmail !== '' && filter_var($toEmail, FILTER_VALIDATE_EMAIL);
        $smtpOk      = smtp_from_configured();

        if ($recipientOk && $smtpOk) {
            $mail = moralmatrix_mailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = 'Violation Recorded in Moral Matrix';

            $html = '
                <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;line-height:1.5">
                    <h2>Dear '.htmlspecialchars($toName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').',</h2>
                    <p>A new violation has been recorded in your account.</p>
                    <p><strong>Category:</strong> '.htmlspecialchars($offense_category, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>
                    <p><strong>Details:</strong> '.nl2br(htmlspecialchars($offense_details ?? 'N/A', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>
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
        } else {
            if (!$recipientOk) {
                error_log("Skipping violation email: invalid or missing recipient for student_id=$student_id");
            }
            if (!$smtpOk) {
                error_log("Skipping violation email: SMTP FROM not configured (set \$smtp['from'] or env).");
            }
        }
    }
    $stmtStu->close();

    // === NOTIFICATIONS (create records) ===
$studentFullName = trim(
    (string)($stu['first_name'] ?? '') . ' ' . (string)($stu['last_name'] ?? '')
);
if ($studentFullName === '') $studentFullName = 'Student ' . $student_id;

$actorRole = strtolower($_SESSION['account_type'] ?? '');
$actorId   = $_SESSION['actor_id'] ?? 'unknown';

// 1) CCDU broadcast (everyone in CCDU sees it)
//    With Option A, read_at is shared for the row (one marks read = read for everyone).
Notify::create($conn, [
  'type'         => ($actorRole === 'ccdu') ? 'success' : 'warning',
  'target_role'  => 'ccdu',
  'title'        => ($actorRole === 'ccdu')
                      ? 'New violation added by CCDU'
                      : 'Violation reported by ' . ucfirst($actorRole ?: 'staff'),
  'body'         => $studentFullName . ' • Student ID: ' . $student_id,
  'url'          => '/MoralMatrix/ccdu/view_student.php?student_id=' . urlencode($student_id) . '#v' . $violationId,
  'violation_id' => $violationId,
  'created_by'   => $actorId,
]);

// 2) Student targeted (only that student sees it)
Notify::create($conn, [
  'type'           => 'info',
  'target_role'    => 'student',
  'target_user_id' => $student_id, // student logs in as their student_id
  'title'          => 'A violation was filed on your account',
  'body'           => 'Please review the entry for ' . $studentFullName . '.',
  'url'            => '/MoralMatrix/student/violations.php#v' . $violationId,
  'violation_id'   => $violationId,
  'created_by'     => $actorId,
]);


    header("Location: view_student.php?student_id=" . urlencode($student_id) . "&saved=1");
    ob_end_flush();
    exit;
}
/* ========= END INSERT HANDLER ========= */

/* From here on, it's safe to output HTML */
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

if (!$student) {
    $errorMessage = 'Student record not found.';
} else {
    $nameParts = array_filter([
        $student['first_name'] ?? '',
        $student['middle_name'] ?? '',
        $student['last_name'] ?? '',
    ], static fn($part) => $part !== null && trim((string)$part) !== '');

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
            <!-- Light -->
            <form id="lightForm" class="category-panel" method="POST" enctype="multipart/form-data" novalidate>
              <div class="panel-header">
                <span class="panel-eyebrow">Category - Light</span>
                <h3>Uniform, ID, and accessories</h3>
                <p>Flag dress-code concerns and pick every item that applies.</p>
              </div>

              <input type="hidden" name="offense_category" value="light">
              <input type="hidden" name="student_id" value="<?= h($student['student_id'] ?? ''); ?>">

              <div class="field">
                <label for="lightOffenses" class="field-label">Offense type</label>
                <div class="select-wrapper">
                  <select id="lightOffenses" name="offense_type" class="select-control" required>
                    <option value="">Select an offense type</option>
                    <option value="id">ID</option>
                    <option value="uniform">Dress code (uniform)</option>
                    <option value="civilian">Revealing clothes (civilian attire)</option>
                    <option value="accessories">Accessories</option>
                  </select>
                </div>
              </div>

              <div id="light_idCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">ID issues</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="id_offense[]" value="no_id">
                    <span>No ID</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="id_offense[]" value="borrowed">
                    <span>Borrowed ID</span>
                  </label>
                </div>
              </div>

              <div id="light_uniformCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Uniform reminders</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="uniform_offense[]" value="socks">
                    <span>Socks</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="uniform_offense[]" value="skirt">
                    <span>Skirt length</span>
                  </label>
                </div>
              </div>

              <div id="light_civilianCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Civilian attire</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="civilian_offense[]" value="crop_top">
                    <span>Crop top</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="civilian_offense[]" value="sando">
                    <span>Sando</span>
                  </label>
                </div>
              </div>

              <div id="light_accessoriesCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Accessories</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="accessories_offense[]" value="piercings">
                    <span>Piercing/s</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="accessories_offense[]" value="hair_color">
                    <span>Loud hair color</span>
                  </label>
                </div>
              </div>

              <div class="field">
                <label for="description_light" class="field-label">Report description</label>
                <textarea id="description_light" name="description" rows="3"
                          placeholder="Summarize what happened, when, and where."></textarea>
              </div>

              <div class="field upload-field">
                <label for="lightPhoto" class="field-label">Attach photo (optional)</label>
                <input type="file" id="lightPhoto" name="photo" accept="image/*"
                      onchange="previewPhoto(this, 'lightPreview')" class="file-control">
                <span class="helper-text">Accepted formats: JPG, PNG, or HEIC up to 5MB.</span>
                <img id="lightPreview" class="photo-preview" alt="Light offense preview" hidden>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit violation</button>
              </div>
            </form>

            <!-- Moderate -->
            <form id="moderateForm" class="category-panel" method="POST" enctype="multipart/form-data" novalidate>
              <div class="panel-header">
                <span class="panel-eyebrow">Category - Moderate</span>
                <h3>Conduct and gadget use</h3>
                <p>Document actions that disrupt the learning environment.</p>
              </div>

              <input type="hidden" name="offense_category" value="moderate">
              <input type="hidden" name="student_id" value="<?= h($student['student_id'] ?? ''); ?>">

              <div class="field">
                <label for="moderateOffenses" class="field-label">Offense type</label>
                <div class="select-wrapper">
                  <select id="moderateOffenses" name="offense_type" class="select-control" required>
                    <option value="">Select an offense type</option>
                    <option value="improper_conduct">Improper language &amp; conduct</option>
                    <option value="gadget_misuse">Gadget misuse</option>
                    <option value="unauthorized_acts">Unauthorized acts</option>
                  </select>
                </div>
              </div>

              <div id="moderate_improper_conductCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Improper language &amp; conduct</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="conduct_offense[]" value="vulgar">
                    <span>Use of curses and vulgar words</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="conduct_offense[]" value="rough_behavior">
                    <span>Roughness in behavior</span>
                  </label>
                </div>
              </div>

              <div id="moderate_gadget_misuseCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Gadget misuse</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="gadget_offense[]" value="cp_classes">
                    <span>Use of cellular phones during classes</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="gadget_offense[]" value="gadgets_functions">
                    <span>Use of gadgets during academic functions</span>
                  </label>
                </div>
              </div>

              <div id="moderate_unauthorized_actsCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Unauthorized acts</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="acts_offense[]" value="illegal_posters">
                    <span>Posting materials without approval</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="acts_offense[]" value="pda">
                    <span>Public display of affection</span>
                  </label>
                </div>
              </div>

              <div class="field">
                <label for="description_moderate" class="field-label">Report description</label>
                <textarea id="description_moderate" name="description" rows="3"
                          placeholder="Provide context, witnesses, or devices involved."></textarea>
              </div>

              <div class="field upload-field">
                <label for="moderatePhoto" class="field-label">Attach photo (optional)</label>
                <input type="file" id="moderatePhoto" name="photo" accept="image/*"
                      onchange="previewPhoto(this, 'moderatePreview')" class="file-control">
                <span class="helper-text">Attach screenshots, photos, or other supporting files.</span>
                <img id="moderatePreview" class="photo-preview" alt="Moderate offense preview" hidden>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit violation</button>
              </div>
            </form>

            <!-- Grave -->
            <form id="graveForm" class="category-panel" method="POST" enctype="multipart/form-data" novalidate>
              <div class="panel-header">
                <span class="panel-eyebrow">Category - Grave</span>
                <h3>Critical incidents</h3>
                <p>Escalate serious violations and capture all essential details.</p>
              </div>

              <input type="hidden" name="offense_category" value="grave">
              <input type="hidden" name="student_id" value="<?= h($student['student_id'] ?? ''); ?>">

              <div class="field">
                <label for="graveOffenses" class="field-label">Offense type</label>
                <div class="select-wrapper">
                  <select id="graveOffenses" name="offense_type" class="select-control" required>
                    <option value="">Select an offense type</option>
                    <option value="substance_addiction">Substance abuse &amp; addiction</option>
                    <option value="integrity_dishonesty">Academic integrity &amp; dishonesty</option>
                    <option value="violence_misconduct">Violence &amp; misconduct</option>
                    <option value="property_theft">Property damage or theft</option>
                    <option value="threats_disrespect">Threats &amp; disrespect</option>
                  </select>
                </div>
              </div>

              <div id="grave_substance_addictionCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Substance issues</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="substance_offense[]" value="smoking">
                    <span>Smoking on campus</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="substance_offense[]" value="gambling">
                    <span>Gambling</span>
                  </label>
                </div>
              </div>

              <div id="grave_integrity_dishonestyCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Integrity &amp; honesty</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="integrity_offense[]" value="forgery">
                    <span>Forgery, falsifying, or tampering documents</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="integrity_offense[]" value="dishonesty">
                    <span>Dishonesty</span>
                  </label>
                </div>
              </div>

              <div id="grave_violence_misconductCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Violence &amp; misconduct</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="violence_offense[]" value="assault">
                    <span>Assault</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="violence_offense[]" value="hooliganism">
                    <span>Hooliganism</span>
                  </label>
                </div>
              </div>

              <div id="grave_property_theftCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Property damage or theft</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="property_offense[]" value="theft">
                    <span>Theft</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="property_offense[]" value="destruction_of_property">
                    <span>Willful destruction of school property</span>
                  </label>
                </div>
              </div>

              <div id="grave_threats_disrespectCheckbox" class="chip-group is-hidden">
                <span class="chip-group__label">Threats &amp; disrespect</span>
                <div class="chip-list">
                  <label class="chip">
                    <input type="checkbox" name="threats_offense[]" value="firearms">
                    <span>Carrying deadly weapons/firearms/explosives</span>
                  </label>
                  <label class="chip">
                    <input type="checkbox" name="threats_offense[]" value="disrespect">
                    <span>Offensive words / disrespectful deeds</span>
                  </label>
                </div>
              </div>

              <div class="field">
                <label for="description_grave" class="field-label">Report description</label>
                <textarea id="description_grave" name="description" rows="3"
                          placeholder="Include witness names, locations, and immediate response."></textarea>
              </div>

              <div class="field upload-field">
                <label for="gravePhoto" class="field-label">Attach photo (optional)</label>
                <input type="file" id="gravePhoto" name="photo" accept="image/*"
                      onchange="previewPhoto(this, 'gravePreview')" class="file-control">
                <span class="helper-text">Add photos, documents, or screenshots that support the report.</span>
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
  function previewPhoto(input, previewId){
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

  function initOptionSwitch(selectId, prefix, values, suffix){
    const select = document.getElementById(selectId);
    if (!select) return;

    const hideAll = () => {
      values.forEach(value => {
        const group = document.getElementById(prefix + value + suffix);
        if (group) {
          group.classList.add('is-hidden');
        }
      });
    };

    const showSelected = () => {
      const group = document.getElementById(prefix + select.value + suffix);
      if (group) {
        group.classList.remove('is-hidden');
      }
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
        if (panel) {
          panel.classList.toggle('is-active', selected === type);
        }
      });
    };

    if (categorySelect) {
      categorySelect.addEventListener('change', syncPanels);
    }
    syncPanels();

    initOptionSwitch('lightOffenses', 'light_', ['id', 'uniform', 'civilian', 'accessories'], 'Checkbox');
    initOptionSwitch('moderateOffenses', 'moderate_', ['improper_conduct', 'gadget_misuse', 'unauthorized_acts'], 'Checkbox');
    initOptionSwitch('graveOffenses', 'grave_', ['substance_addiction', 'integrity_dishonesty', 'violence_misconduct', 'property_theft', 'threats_disrespect'], 'Checkbox');
  });

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
      if (lastFocusedEl) {
        lastFocusedEl.focus();
      }
    }

    openBtn.addEventListener('click', openSheet);
    closeBtn.addEventListener('click', closeSheet);
    scrim.addEventListener('click', closeSheet);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        closeSheet();
      }
    });

    sheet.addEventListener('click', e => {
      const link = e.target.closest('a[href]');
      if (!link) return;
      const sameTab = !(e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0);
      if (sameTab) {
        closeSheet();
      }
    });
  })();
  </script>

  <button id="openMenu" class="menu-launcher" aria-controls="sideSheet" aria-expanded="false"
          style="position:fixed;left:-9999px;opacity:0" tabindex="-1">Menu</button>

  </body>
  </html>
