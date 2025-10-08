<?php
// set_community_service.php — no output before potential redirects
include '../includes/header.php';
require '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* ---------- Inputs ---------- */
$student_id   = $_GET['student_id']   ?? null;
$violation_id = isset($_GET['violation_id']) ? (int)$_GET['violation_id'] : 0;

$defaultReturn = 'view_student.php?student_id=' . urlencode((string)$student_id);
$returnUrlIn   = $_GET['return'] ?? $defaultReturn;
/* allow only relative return URLs */
$returnUrl = (is_string($returnUrlIn) && $returnUrlIn !== '' && strpos($returnUrlIn, '://') === false)
  ? $returnUrlIn : $defaultReturn;

/* collect errors to display after header include */
$errorMsg = null;

/* ---------- Handle assignment POST before any output ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_validator'])) {
    $student_id   = $_POST['student_id']   ?? null;
    $violation_id = (int)($_POST['violation_id'] ?? 0);
    $validator_id = $_POST['validator_id'] ?? null;

    if ($student_id && $violation_id > 0 && $validator_id) {
        $sql  = "INSERT INTO validator_student_assignment (assignment_id, student_id, validator_id, assigned_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE validator_id = VALUES(validator_id), assigned_at = NOW()";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { die("Prepare failed: " . $conn->error); }
        $stmt->bind_param("isi", $violation_id, $student_id, $validator_id);

        if ($stmt->execute()) {
            if (!headers_sent()) {
                header("Location: " . $returnUrl, true, 302);
                exit;
            } else {
                // Fallback if something output unexpectedly
                echo '<script>location.replace(' . json_encode($returnUrl) . ');</script>';
                exit;
            }
        } else {
            $errorMsg = "Error assigning validator: " . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    } else {
        $errorMsg = "Missing required fields.";
    }
}

/* ---------- Guards ---------- */
if (!$student_id)       { die("No student selected."); }
if ($violation_id <= 0) { die("No violation selected."); }

/* ---------- Fetch student ---------- */
$sql = "
  SELECT
    student_id,
    CONCAT_WS(' ', first_name, middle_name, last_name) AS student_name,
    course,
    TRIM(CONCAT(COALESCE(level,''), CASE WHEN level IS NOT NULL AND section IS NOT NULL AND section <> '' THEN '-' ELSE '' END, COALESCE(section,''))) AS year_level
  FROM student_account
  WHERE student_id = ?
";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$student) { die("Student not found."); }

/* ---------- Fetch selected violation ---------- */
$vsql = "
  SELECT
    violation_id,
    offense_category,
    offense_type,
    offense_details,
    description,
    reported_at,
    photo
  FROM student_violation
  WHERE violation_id = ? AND student_id = ?
";
$stmtv = $conn->prepare($vsql);
if (!$stmtv) die("Prepare failed: " . $conn->error);
$stmtv->bind_param("is", $violation_id, $student_id);
$stmtv->execute();
$violation = $stmtv->get_result()->fetch_assoc();
$stmtv->close();
if (!$violation) { die("Selected violation not found for this student."); }

/* ---------- Pretty / safe values ---------- */
$datePretty = !empty($violation['reported_at']) ? date('M d, Y h:i A', strtotime($violation['reported_at'])) : '—';
$cat        = htmlspecialchars($violation['offense_category'] ?? '');
$type       = htmlspecialchars($violation['offense_type'] ?? '');
$desc       = htmlspecialchars($violation['description'] ?? '');
$detailsText = '—';
if (!empty($violation['offense_details'])) {
  $decoded = json_decode($violation['offense_details'], true);
  if (is_array($decoded) && count($decoded)) {
    $safe = array_map('htmlspecialchars', $decoded);
    $detailsText = implode(', ', $safe);
  }
}
/* Photo path */
$photoRel = null;
if (!empty($violation['photo'])) {
    $tryAbs = _DIR_ . '/uploads/' . $violation['photo'];
    if (is_file($tryAbs)) {
        $photoRel = 'uploads/' . rawurlencode($violation['photo']);
    }
}

/* ---------- Build validator list (filter active if columns exist) ---------- */
$hasActive = $hasIsActive = $hasAccountStatus = false;
if ($res = $conn->query("SHOW COLUMNS FROM validator_account LIKE 'active'"))        { $hasActive = ($res->num_rows > 0); $res->close(); }
if ($res = $conn->query("SHOW COLUMNS FROM validator_account LIKE 'is_active'"))     { $hasIsActive = ($res->num_rows > 0); $res->close(); }
if ($res = $conn->query("SHOW COLUMNS FROM validator_account LIKE 'account_status'")){ $hasAccountStatus = ($res->num_rows > 0); $res->close(); }

$filters = [];
if ($hasActive)        $filters[] = "va.active = 1";
if ($hasIsActive)      $filters[] = "va.is_active = 1";
if ($hasAccountStatus) $filters[] = "LOWER(va.account_status) = 'active'";

$countSub = "
  SELECT validator_id, COUNT(DISTINCT student_id) AS assigned_count
  FROM validator_student_assignment
  GROUP BY validator_id
";

$vlistSql = "
  SELECT
    va.validator_id,
    va.v_username AS validator_name,
    va.designation,
    va.email,
    COALESCE(vs.assigned_count, 0) AS assigned_count
  FROM validator_account AS va
  LEFT JOIN ($countSub) AS vs ON vs.validator_id = va.validator_id
" . ($filters ? " WHERE " . implode(" AND ", $filters) : "") . "
  ORDER BY validator_name ASC
";

$validators = $conn->query($vlistSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assign Validator</title>
  <style>
    :root {
      --mm-primary: #8C1C13;
      --mm-primary-hover: #74140E;
      --mm-muted: #6B7280;
      --mm-border: #E5E7EB;
      --mm-surface: #ffffff;
      --mm-surface-alt: #f8f9fb;
      --mm-shadow-lg: 0 25px 55px -25px rgba(15, 23, 42, 0.35);
      --mm-shadow-sm: 0 12px 30px -18px rgba(15, 23, 42, 0.25);
      --mm-radius-lg: 18px;
      --mm-radius-md: 14px;
      --mm-radius-sm: 10px;
    }

    * {
      box-sizing: border-box;
    }

    html, body {
      margin: 0;
      padding: 0;
      font-family: "Segoe UI", "Inter", "Helvetica Neue", Arial, sans-serif;
      background: var(--mm-surface-alt);
      color: #1F2937;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    main.page-wrapper {
      margin: 0;
      margin-left: var(--sidebar-w, 0px);
      padding: calc(var(--header-h, 64px) + 36px) 38px 60px;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: calc(100vh - var(--header-h, 64px));
    }

    .page-frame {
      background: var(--mm-surface);
      border-radius: var(--mm-radius-lg);
      box-shadow: var(--mm-shadow-lg);
      padding: 42px 48px 48px;
      max-width: 1200px;
      width: 100%;
    }

    .page-header {
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin-bottom: 32px;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 8px 18px;
      width: fit-content;
      border-radius: 999px;
      background: #1F2937;
      color: #fff;
      font-size: 0.92rem;
      font-weight: 500;
      transition: background 0.2s ease, transform 0.2s ease;
      box-shadow: var(--mm-shadow-sm);
    }

    .back-link:hover {
      background: #111827;
      transform: translateY(-1px);
    }

    .page-header h1 {
      margin: 0;
      font-size: 1.95rem;
      font-weight: 700;
      color: #111827;
    }

    .page-header p {
      margin: 0;
      color: var(--mm-muted);
      font-size: 0.98rem;
      max-width: 680px;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 26px;
      margin-bottom: 40px;
    }

    .info-card {
      background: linear-gradient(135deg, rgba(140, 28, 19, 0.04), rgba(48, 57, 82, 0.06));
      padding: 1px;
      border-radius: var(--mm-radius-lg);
    }

    .info-card__inner {
      background: var(--mm-surface);
      border-radius: inherit;
      padding: 28px 26px 30px;
      height: 100%;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 0.75rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 600;
      background: rgba(140, 28, 19, 0.12);
      color: var(--mm-primary);
    }

    .detail-stack {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .detail-row {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      align-items: flex-start;
    }

    .detail-label {
      font-size: 0.78rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--mm-muted);
      font-weight: 600;
    }

    .detail-value {
      font-size: 1.02rem;
      font-weight: 600;
      color: #111827;
      text-align: right;
    }

    .detail-value span {
      display: block;
      line-height: 1.45;
    }

    .violation-notes {
      margin-top: 4px;
      color: #374151;
      font-size: 0.95rem;
      line-height: 1.5;
    }

    .violation-notes p {
      margin: 0 0 12px;
    }

    .photo-preview {
      border-radius: var(--mm-radius-md);
      background: var(--mm-surface-alt);
      padding: 18px;
      border: 1px dashed rgba(140, 28, 19, 0.2);
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .photo-preview img {
      max-width: 100%;
      height: auto;
      border-radius: var(--mm-radius-sm);
      display: block;
    }

    .section-heading {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 24px;
    }

    .section-heading h2 {
      margin: 0;
      font-size: 1.45rem;
      font-weight: 700;
      color: #111827;
    }

    .section-heading span {
      color: var(--mm-muted);
      font-size: 0.94rem;
    }

    form.assign-form {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 22px;
    }

    .validator-card {
      cursor: pointer;
      display: block;
    }

    .validator-card input[type="radio"] {
      display: none;
    }

    .card-content {
      border-radius: var(--mm-radius-md);
      padding: 22px 24px 24px;
      border: 1px solid rgba(17, 24, 39, 0.08);
      background: var(--mm-surface);
      box-shadow: 0 15px 35px -28px rgba(15, 23, 42, 0.45);
      transition: border 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
      height: 100%;
    }

    .card-content h4 {
      margin: 0 0 10px;
      font-size: 1.12rem;
      font-weight: 700;
      color: #111827;
    }

    .card-content p {
      margin: 6px 0;
      font-size: 0.93rem;
      color: #374151;
    }

    .card-content p b {
      color: #111827;
    }

    .validator-card:hover .card-content {
      border-color: rgba(140, 28, 19, 0.45);
      box-shadow: 0 18px 38px -22px rgba(140, 28, 19, 0.45);
      transform: translateY(-2px);
    }

    .validator-card input[type="radio"]:checked + .card-content {
      border-color: var(--mm-primary);
      box-shadow: 0 20px 50px -25px rgba(140, 28, 19, 0.55);
      background: linear-gradient(135deg, rgba(140, 28, 19, 0.06), rgba(255, 255, 255, 0.85));
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 12px 28px;
      border-radius: 999px;
      background: var(--mm-primary);
      color: #fff;
      font-size: 1rem;
      font-weight: 600;
      border: none;
      cursor: pointer;
      box-shadow: 0 20px 40px -25px rgba(140, 28, 19, 0.75);
      transition: background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }

    .btn-primary:hover {
      background: var(--mm-primary-hover);
      box-shadow: 0 24px 45px -22px rgba(116, 20, 14, 0.65);
      transform: translateY(-2px);
    }

    .empty-state {
      padding: 24px;
      text-align: center;
      border-radius: var(--mm-radius-md);
      border: 1px dashed rgba(140, 28, 19, 0.25);
      background: rgba(140, 28, 19, 0.05);
      color: rgba(140, 28, 19, 0.85);
      font-weight: 600;
    }

    @media (max-width: 900px) {
      main.page-wrapper {
        padding: calc(var(--header-h, 64px) + 24px) 22px 48px;
        align-items: stretch;
      }

      .page-frame {
        padding: 32px 26px 38px;
      }

      .detail-row {
        flex-direction: column;
        align-items: flex-start;
      }

      .detail-value {
        text-align: left;
      }
    }

    @media (max-width: 640px) {
      main.page-wrapper {
        margin-left: 0;
        padding: calc(var(--header-h, 64px) + 18px) 18px 48px;
      }

      .page-header h1 {
        font-size: 1.65rem;
      }

      .cards-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>

</head>
<body>

  <main class="page-wrapper">
    <div class="page-frame">
      <div class="page-header">
        <a class="back-link" href="<?= htmlspecialchars($returnUrl) ?>" aria-label="Back to previous page">
          <span>&larr;</span>
          <span>Back to Student Profile</span>
        </a>
        <div>
          <h1>Assign Validator</h1>
          <p>Review the violation details and assign the validator who will monitor the community service progress.</p>
        </div>
      </div>

      <section class="info-grid">
        <article class="info-card">
          <div class="info-card__inner">
            <span class="badge">Student</span>
            <div class="detail-stack">
              <div class="detail-row">
                <span class="detail-label">Student ID</span>
                <span class="detail-value"><span><?= htmlspecialchars($student['student_id']) ?></span></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Name</span>
                <span class="detail-value"><span><?= htmlspecialchars($student['student_name']) ?></span></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Course</span>
                <span class="detail-value"><span><?= htmlspecialchars($student['course'] ?: 'None') ?></span></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Year Level</span>
                <span class="detail-value"><span><?= htmlspecialchars($student['year_level'] ?: 'None') ?></span></span>
              </div>
            </div>
          </div>
        </article>

        <article class="info-card">
          <div class="info-card__inner">
            <span class="badge">Violation</span>
            <div class="detail-stack">
              <div class="detail-row">
                <span class="detail-label">Violation ID</span>
                <span class="detail-value"><span>#<?= htmlspecialchars((string)$violation_id) ?></span></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Category</span>
                <span class="detail-value"><span><?= $cat ? ucfirst($cat) : 'None' ?></span></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Type</span>
                <span class="detail-value"><span><?= $type ?: 'None' ?></span></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Reported On</span>
                <span class="detail-value"><span><?= $datePretty ?></span></span>
              </div>
            </div>
            <div class="violation-notes">
              <p><strong>Details:</strong> <?= $detailsText ?></p>
              <p><strong>Description:</strong><br><?= $desc ? nl2br($desc) : 'None' ?></p>
            </div>
            <?php if ($photoRel): ?>
              <div class="violation-notes">
                <p><strong>Photo Evidence:</strong></p>
                <div class="photo-preview">
                  <img src="<?= htmlspecialchars($photoRel) ?>" alt="Evidence photo for violation #<?= htmlspecialchars((string)$violation_id) ?>">
                </div>
              </div>
            <?php endif; ?>
          </div>
        </article>
      </section>

      <section class="validators-section">
        <div class="section-heading">
          <h2>Select Validator</h2>
          <span>Choose the validator who will oversee this student's community service assignment.</span>
        </div>

        <form class="assign-form" method="post" action="" onsubmit="return confirmAssign();">
          <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
          <input type="hidden" name="violation_id" value="<?= htmlspecialchars((string)$violation_id) ?>">

          <div class="cards-grid">
            <?php
            if ($validators && $validators->num_rows > 0) {
              while ($v = $validators->fetch_assoc()) {
                $id    = htmlspecialchars($v['validator_id']);
                $name  = htmlspecialchars($v['validator_name']);
                $org   = htmlspecialchars($v['designation']);
                $email = htmlspecialchars($v['email']);
                $cnt   = (int)$v['assigned_count'];
                ?>
                <label class="validator-card">
                  <input type="radio" name="validator_id" value="<?= $id ?>" required>
                  <div class="card-content">
                    <h4><?= $name ?></h4>
                    <p><b>Designation:</b> <?= $org ?: 'None' ?></p>
                    <p><b>Email:</b> <?= $email ?: 'None' ?></p>
                    <p><b>Assigned students:</b> <?= $cnt ?></p>
                  </div>
                </label>
                <?php
              }
              $validators->free();
            } else {
              echo '<div class="empty-state">No active validators are available at this time.</div>';
            }
            ?>
          </div>

          <div class="form-actions">
            <button class="btn-primary" type="submit" name="assign_validator">
              Assign Validator
            </button>
          </div>
        </form>
      </section>
    </div>
  </main>

  <script>
    function confirmAssign() { return confirm("Assign validator to student?"); }
  </script>

</body>
</html>
<?php
$conn->close();
