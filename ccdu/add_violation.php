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
  <meta charset="utf-8" />
  <title>CCDU • Add Violation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../css/add_violation.css">
</head>
<body>

  <main class="page">
    <div class="violation-shell">

      <!-- LEFT: Student summary card -->
      <aside class="student-card">
        <div class="student-card__header">
          <span class="card-label">CCDU</span>
          <h1>Add Violation</h1>
          <p class="student-card__subtitle">
            Community Conduct &amp; Discipline Unit • Record new entry
          </p>
        </div>

        <div class="student-card__body">
          <div class="student-card__photo" id="studentPhoto">
            <img src="https://via.placeholder.com/280x280.png?text=Student" alt="Student photo">
          </div>

          <dl class="student-meta">
            <div>
              <dt>Student Name</dt>
              <dd id="studentName">Juan Dela Cruz</dd>
            </div>
            <div>
              <dt>Student ID</dt>
              <dd id="studentId">2025-0001</dd>
            </div>
            <div>
              <dt>Course / Yr &amp; Sec</dt>
              <dd id="studentCys">BSN 3A</dd>
            </div>
          </dl>

          <p class="student-card__empty is-hidden" id="noStudentMsg">
            No student loaded. Use the fields on the right to select one.
          </p>
        </div>
      </aside>

      <!-- RIGHT: Violation form (UI only) -->
      <section class="form-card">
        <header class="form-card__header">
          <div class="badge badge--accent">CCDU ACTIONS</div>
          <h2>Violation Details</h2>
          <p>Fill out the details below. This page captures the UI only; connect your own PHP later.</p>
        </header>

        <!-- Context row -->
        <div class="form-context">
          <div class="field">
            <label class="field-label" for="ay">Academic Year</label>
            <div class="select-wrapper">
              <select id="ay" class="select-control">
                <option>2025–2026</option>
                <option>2024–2025</option>
                <option>2023–2024</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label class="field-label" for="sem">Semester / Term</label>
            <div class="select-wrapper">
              <select id="sem" class="select-control">
                <option>1st Semester</option>
                <option>2nd Semester</option>
                <option>Summer</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-context">
          <div class="field">
            <label class="field-label" for="category">Violation Category</label>
            <div class="select-wrapper">
              <select id="category" class="select-control" data-panel-target>
                <option value="uniform" selected>Uniform / ID</option>
                <option value="behavior">Behavior</option>
                <option value="attendance">Attendance</option>
              </select>
            </div>
          </div>

          <div class="context-note">
            Select a category to reveal its specific offenses below, then pick one or more items.
          </div>
        </div>

        <!-- Stacked panels -->
        <div class="forms-stack">

          <!-- Panel: Uniform/ID -->
          <div class="category-panel is-active" data-panel="uniform">
            <div class="panel-header">
              <span class="panel-eyebrow">Category</span>
              <h3>Uniform / ID</h3>
              <p>Pick the observed non-compliance items.</p>
            </div>

            <div class="chip-group">
              <span class="chip-group__label">Select offenses</span>
              <div class="chip-list">
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="no-id">
                  <span>No ID</span>
                </label>
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="improper-uniform">
                  <span>Improper uniform</span>
                </label>
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="slippers">
                  <span>Wearing slippers</span>
                </label>
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="haircut">
                  <span>Non-compliant haircut</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Panel: Behavior -->
          <div class="category-panel" data-panel="behavior">
            <div class="panel-header">
              <span class="panel-eyebrow">Category</span>
              <h3>Behavior</h3>
              <p>Observed misconduct within campus.</p>
            </div>

            <div class="chip-group">
              <span class="chip-group__label">Select offenses</span>
              <div class="chip-list">
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="disrespect">
                  <span>Disrespect / discourtesy</span>
                </label>
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="vandalism">
                  <span>Vandalism</span>
                </label>
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="littering">
                  <span>Littering</span>
                </label>
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="noise">
                  <span>Disruptive noise</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Panel: Attendance -->
          <div class="category-panel" data-panel="attendance">
            <div class="panel-header">
              <span class="panel-eyebrow">Category</span>
              <h3>Attendance</h3>
              <p>Late / absent cases subject to CCDU policy.</p>
            </div>

            <div class="chip-group">
              <span class="chip-group__label">Select offenses</span>
              <div class="chip-list">
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="late">
                  <span>Late</span>
                </label>
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="cutting">
                  <span>Cutting classes</span>
                </label>
                <label class="chip">
                  <input type="checkbox" name="offenses[]" value="absent">
                  <span>Unexcused absence</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Narrative -->
          <div class="field">
            <label class="field-label" for="narrative">Narrative / Notes</label>
            <textarea id="narrative" placeholder="Write brief details of the incident..."></textarea>
          </div>

          <!-- Evidence upload -->
          <div class="field upload-field">
            <label class="field-label" for="evidence">Evidence Photo (optional)</label>
            <input id="evidence" type="file" accept="image/*" class="file-control">
            <span class="helper-text">JPG/PNG, max ~5MB recommended.</span>
            <img id="preview" class="photo-preview is-hidden" alt="Preview">
          </div>

          <!-- Actions -->
          <div class="form-actions">
            <a href="../ccdu/" class="btn btn-ghost">Cancel</a>
            <button type="button" class="btn btn-primary">Save</button>
          </div>

        </div>
      </section>
    </div>
  </main>

  <script>
    // Panel switching based on category select
    (function () {
      const select = document.querySelector('[data-panel-target]');
      const panels = document.querySelectorAll('.category-panel');
      function syncPanels() {
        const val = select.value;
        panels.forEach(p => {
          p.classList.toggle('is-active', p.getAttribute('data-panel') === val);
        });
      }
      select.addEventListener('change', syncPanels);
      syncPanels();
    })();

    // Image preview for evidence
    (function () {
      const input = document.getElementById('evidence');
      const preview = document.getElementById('preview');
      input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) { preview.classList.add('is-hidden'); preview.src=''; return; }
        const url = URL.createObjectURL(file);
        preview.src = url;
        preview.classList.remove('is-hidden');
      });
    })();
  </script>
</body>
</html>

