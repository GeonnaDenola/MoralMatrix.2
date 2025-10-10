<?php
// ccdu/add_violation.php

// Start output buffering FIRST so accidental output won't break redirects.
ob_start();
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/email_lib.php';

// Optional scanner; must not echo anything
@include __DIR__ . '/_scanner.php';

/* ---------- STUDENT ID FROM GET OR POST ---------- */
$studentId = $_GET['student_id'] ?? $_POST['student_id'] ?? '';
if ($studentId === '' && ($_SERVER['REQUEST_METHOD'] !== 'POST')) {
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

    // Required fields
    $student_id       = trim($_POST['student_id']       ?? '');
    $offense_category = trim($_POST['offense_category'] ?? '');
    $offense_type     = trim($_POST['offense_type']     ?? '');
    $description      = trim($_POST['description']      ?? '');

    if ($student_id === '' || $offense_category === '' || $offense_type === '') {
        http_response_code(400);
        ob_end_flush();
        die("Missing required fields.");
    }

    // Collect detail checkboxes (accept old grouped names and new offenses[])
    $detailGroups = [
        'id_offense','uniform_offense','civilian_offense','accessories_offense',
        'conduct_offense','gadget_offense','acts_offense',
        'substance_offense','integrity_offense','violence_offense',
        'property_offense','threats_offense'
    ];
    $picked = [];

    // New flat list used by this page:
    if (!empty($_POST['offenses']) && is_array($_POST['offenses'])) {
        $picked = array_merge($picked, $_POST['offenses']);
    }
    // Old grouped lists (if present)
    foreach ($detailGroups as $g) {
        if (!empty($_POST[$g]) && is_array($_POST[$g])) {
            $picked = array_merge($picked, $_POST[$g]);
        }
    }
    $picked = array_values(array_unique(array_map('strval', $picked)));
    $offense_details = $picked ? json_encode($picked, JSON_UNESCAPED_UNICODE) : null;

    // ---- Photo upload (optional) ----
    $photo = "";
    if (
        isset($_FILES["photo"]) &&
        is_uploaded_file($_FILES["photo"]["tmp_name"]) &&
        ($_FILES["photo"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    ) {
        $uploadDir = dirname(__DIR__) . "/admin/uploads/";
        if (!is_dir($uploadDir)) {
            // NOTE: adjust permissions to your environment
            @mkdir($uploadDir, 0775, true);
        }

        // Safer filename
        $original = basename($_FILES["photo"]["name"]);
        $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
        $photo = time() . "_" . $safeBase;
        $targetPath = $uploadDir . $photo;

        // Basic MIME/type guard (optional)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES["photo"]["tmp_name"]);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        if (!array_key_exists($mime, $allowed)) {
            // If not an image, discard
            $photo = "";
        } else {
            if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetPath)) {
                $photo = "";
            }
        }
    }

    $submitted_by  = $_SESSION['actor_id'] ?? 'unknown';

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

    $detail_for_bind = $offense_details ?? null; // will bind as string (empty if null)
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

    // Lookup student for email
    $stmtStu = $conn->prepare("SELECT first_name, last_name, email FROM student_account WHERE student_id = ?");
    if ($stmtStu) {
        $stmtStu->bind_param("s", $student_id);
        $stmtStu->execute();
        $resStu = $stmtStu->get_result();
        if ($stu = $resStu->fetch_assoc()) {
            $toEmail = $stu['email'] ?? '';
            $toName  = trim(($stu['first_name'] ?? '').' '.($stu['last_name'] ?? ''));
            if ($toEmail !== '') {
                $mail = moralmatrix_mailer();
                $mail->addAddress($toEmail, $toName);
                $mail->Subject = 'Violation Recorded in Moral Matrix';

                // Make details look nicer in the email
                $detailsText = 'N/A';
                if ($offense_details) {
                    $arr = json_decode($offense_details, true) ?: [];
                    $detailsText = $arr ? implode(', ', $arr) : 'N/A';
                }

                $html = '
                    <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;line-height:1.5">
                        <h2>Dear '.htmlspecialchars($toName).',</h2>
                        <p>A new violation has been recorded in your account.</p>
                        <p><strong>Category:</strong> '.htmlspecialchars($offense_category).'</p>
                        <p><strong>Type:</strong> '.htmlspecialchars($offense_type).'</p>
                        <p><strong>Details:</strong> '.htmlspecialchars($detailsText).'</p>
                        <p><strong>Description:</strong> '.nl2br(htmlspecialchars($description ?: '—')).'</p>
                        <p><strong>Date:</strong> '.date("F j, Y g:i A").'</p>
                        <p>You may log in to your Moral Matrix account for more details.</p>
                    </div>';

                $mail->Body    = $html;
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

                try {
                    $mail->send();
                    error_log("Violation email sent to $toEmail");
                } catch (Throwable $e) {
                    error_log("Violation email error: ".$mail->ErrorInfo);
                }
            }
        }
        $stmtStu->close();
    }

    header("Location: view_student.php?student_id=" . urlencode($student_id) . "&saved=1");
    ob_end_flush();
    exit;
}

/* ========= END INSERT HANDLER ========= */

/* ---------- FETCH STUDENT FOR DISPLAY ---------- */
$student = null;
if ($studentId !== '') {
    $stmt = $conn->prepare("SELECT * FROM student_account WHERE student_id=?");
    if ($stmt) {
        $stmt->bind_param("s", $studentId);
        $stmt->execute();
        $result  = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
    }
}

// From here, output HTML
include __DIR__ . '/../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>CCDU • Add Violation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../css/add_violation.css">
  <style>
    /* Minimal safety styles if your CSS isn’t loaded */
    .btn{padding:.6rem 1rem;border:1px solid #ccc;cursor:pointer}
    .btn-primary{border-color:#2266ff}
    .btn-ghost{background:transparent}
    .field{margin-bottom:1rem}
    .field-label{display:block;font-weight:600;margin-bottom:.25rem}
    .chip{display:inline-flex;align-items:center;gap:.4rem;border:1px solid #ddd;border-radius:999px;padding:.25rem .6rem;margin:.2rem}
    .form-actions{display:flex;gap:.5rem}
    .is-hidden{display:none}
  </style>
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
              <dd id="studentName">
                <?php
                  if ($student) {
                      echo htmlspecialchars(trim(($student['first_name'] ?? '').' '.($student['last_name'] ?? '')));
                  } else {
                      echo '—';
                  }
                ?>
              </dd>
            </div>
            <div>
              <dt>Student ID</dt>
              <dd id="studentId"><?php echo htmlspecialchars($studentId ?: '—'); ?></dd>
            </div>
            <div>
              <dt>Course / Yr &amp; Sec</dt>
              <dd id="studentCys">
                <?php
                  if ($student) {
                      $cys = trim(($student['course'] ?? '').' '.($student['year_section'] ?? ''));
                      echo htmlspecialchars($cys ?: '—');
                  } else {
                      echo '—';
                  }
                ?>
              </dd>
            </div>
          </dl>

          <p class="student-card__empty <?php echo $student ? 'is-hidden' : ''; ?>" id="noStudentMsg">
            No student loaded. Use the fields on the right to select one.
          </p>
        </div>
      </aside>

      <!-- RIGHT: Violation form -->
      <section class="form-card">
        <header class="form-card__header">
          <div class="badge badge--accent">CCDU ACTIONS</div>
          <h2>Violation Details</h2>
          <p>Fill out the details below then press Save.</p>
        </header>

        <!-- FORM START -->
        <form id="violationForm" method="POST" enctype="multipart/form-data" action="">
          <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($studentId); ?>">

          <!-- Context row -->
          <div class="form-context">
            <div class="field">
              <label class="field-label" for="ay">Academic Year</label>
              <div class="select-wrapper">
                <select id="ay" class="select-control" name="ay">
                  <option>2025–2026</option>
                  <option>2024–2025</option>
                  <option>2023–2024</option>
                </select>
              </div>
            </div>

            <div class="field">
              <label class="field-label" for="sem">Semester / Term</label>
              <div class="select-wrapper">
                <select id="sem" class="select-control" name="sem">
                  <option>1st Semester</option>
                  <option>2nd Semester</option>
                  <option>Summer</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-context">
            <div class="field" style="min-width:220px">
              <label class="field-label" for="category">Violation Category</label>
              <div class="select-wrapper">
                <select id="category" class="select-control" name="offense_category" data-panel-target>
                  <option value="uniform" selected>Uniform / ID</option>
                  <option value="behavior">Behavior</option>
                  <option value="attendance">Attendance</option>
                </select>
              </div>
            </div>

            <div class="field" style="min-width:220px">
              <label class="field-label" for="offense_type">Offense Type</label>
              <div class="select-wrapper">
                <select id="offense_type" class="select-control" name="offense_type" required>
                  <option value="minor" selected>Minor</option>
                  <option value="major">Major</option>
                  <option value="warning">Warning</option>
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
              <textarea id="narrative" name="description" placeholder="Write brief details of the incident..."></textarea>
            </div>

            <!-- Evidence upload -->
            <div class="field upload-field">
              <label class="field-label" for="evidence">Evidence Photo (optional)</label>
              <input id="evidence" name="photo" type="file" accept="image/*" class="file-control">
              <span class="helper-text">JPG/PNG/WebP/GIF, max ~5MB recommended.</span>
              <img id="preview" class="photo-preview is-hidden" alt="Preview">
            </div>

            <!-- Actions -->
            <div class="form-actions">
              <a href="../ccdu/" class="btn btn-ghost">Cancel</a>
              <button type="submit" class="btn btn-primary">Save</button>
            </div>

          </div>
        </form>
        <!-- FORM END -->
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
