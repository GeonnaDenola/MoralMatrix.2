<?php
// set_community_service.php â€” no output before potential redirects
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
$datePretty = !empty($violation['reported_at']) ? date('M d, Y h:i A', strtotime($violation['reported_at'])) : 'â€”';
$cat        = htmlspecialchars($violation['offense_category'] ?? '');
$type       = htmlspecialchars($violation['offense_type'] ?? '');
$desc       = htmlspecialchars($violation['description'] ?? '');
$detailsText = 'â€”';
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
  <link rel="stylesheet" href="../css/set_community_service.css?v=1">
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

