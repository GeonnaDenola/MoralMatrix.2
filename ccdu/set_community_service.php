<?php
include '../includes/header.php';
include '../config.php';
include 'page_buttons.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* ---------- Inputs ---------- */
$student_id   = $_GET['student_id']   ?? null;
$violation_id = isset($_GET['violation_id']) ? (int)$_GET['violation_id'] : 0;
$returnUrl    = $_GET['return'] ?? ('view_student.php?student_id=' . urlencode((string)$student_id));

/* ---------- Handle assignment POST ---------- */
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
            header("Location: " . $returnUrl);
            exit;
        } else {
            echo "<p style='color:red'>Error assigning validator: " . htmlspecialchars($stmt->error) . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Missing required fields.</p>";
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
    CONCAT(COALESCE(level,''), COALESCE(section,'')) AS year_level
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

/* ---------- Fetch the specific violation (include photo filename) ---------- */
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

/* Flatten offense_details JSON safely */
$detailsText = '—';
if (!empty($violation['offense_details'])) {
  $decoded = json_decode($violation['offense_details'], true);
  if (is_array($decoded) && count($decoded)) {
    $safe = array_map('htmlspecialchars', $decoded);
    $detailsText = implode(', ', $safe);
  }
}

/* ---------- Determine photo path (same logic as your working view) ---------- */
$photoRel = null; // show nothing if none
if (!empty($violation['photo'])) {
    // Reference behavior uses current folder's /uploads/
    $tryAbs = __DIR__ . '/uploads/' . $violation['photo'];   // filesystem
    if (is_file($tryAbs)) {
        $photoRel = 'uploads/' . $violation['photo'];        // web path
    } else {
        // Optional: fallback to placeholder if file missing
        // $photoRel = 'placeholder.png';
        $photoRel = null;
    }
}

/* ---------- Build validator list: only ACTIVE + assigned count ---------- */
$hasActive = $hasIsActive = $hasAccountStatus = false;

if ($res = $conn->query("SHOW COLUMNS FROM validator_account LIKE 'active'")) {
    $hasActive = ($res->num_rows > 0); $res->close();
}
if ($res = $conn->query("SHOW COLUMNS FROM validator_account LIKE 'is_active'")) {
    $hasIsActive = ($res->num_rows > 0); $res->close();
}
if ($res = $conn->query("SHOW COLUMNS FROM validator_account LIKE 'account_status'")) {
    $hasAccountStatus = ($res->num_rows > 0); $res->close();
}

$conditions = [];
if ($hasActive)        $conditions[] = "va.active = 1";
if ($hasIsActive)      $conditions[] = "va.is_active = 1";
if ($hasAccountStatus) $conditions[] = "LOWER(va.account_status) = 'active'";

/* Count DISTINCT students per validator */
$countSub = "
  SELECT validator_id, COUNT(DISTINCT student_id) AS assigned_count
  FROM validator_student_assignment
  GROUP BY validator_id
";

/* Main query */
$vlistSql = "
  SELECT
    va.validator_id,
    va.v_username AS validator_name,
    va.designation,
    va.email,
    COALESCE(vs.assigned_count, 0) AS assigned_count
  FROM validator_account AS va
  LEFT JOIN ($countSub) AS vs ON vs.validator_id = va.validator_id
";
$vlistSql .= $conditions ? (" WHERE (" . implode(" OR ", $conditions) . ") ") : " WHERE 1=0 ";
$vlistSql .= " ORDER BY validator_name ASC";

$validators = $conn->query($vlistSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assign Validator</title>
 <style>
  /* remove all outer gutters */
  html, body { margin: 0; padding: 0; }
  * { box-sizing: border-box; }

  /* (optional) add inner padding only where you want it */
  /* .page { padding: 16px; } */

  a { text-decoration: none; }
  .btn { display:inline-block; padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; color:#111; }
  .btn:hover { background:#f9fafb; }

  .student-info p, .violation-info p { margin: 4px 0; }
  .violation-info img { max-width: 100%; border-radius: 10px; display: block; margin-top: 8px; }

  .cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem; margin-top: .5rem;
  }
  .validator-card { cursor: pointer; display: block; }
  .validator-card input[type="radio"] { display: none; }
  .card-content {
    border: 2px solid #ccc; border-radius: 12px; padding: 1rem; background: #fff;
    transition: all .2s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.05);
  }
  .validator-card:hover .card-content {
    border-color: #007bff; background:#f9f9ff; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  }
  .validator-card input[type="radio"]:checked + .card-content {
    border-color: #007bff; background:#eef4ff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  }
  .card-content h4 { margin: 0 0 8px; font-size: 1.1rem; }
  .card-content p { margin: 4px 0; font-size: 0.9rem; }
</style>

</head>
<body>

  <p><a href="<?= htmlspecialchars($returnUrl) ?>">← Back</a></p>

  <h2>Assign Validator for Student</h2>

  <div class="student-info">
    <p><b>ID:</b> <?= htmlspecialchars($student['student_id']) ?></p>
    <p><b>Name:</b> <?= htmlspecialchars($student['student_name']) ?></p>
    <p><b>Course:</b> <?= htmlspecialchars($student['course']) ?></p>
    <p><b>Year Level:</b> <?= htmlspecialchars($student['year_level']) ?></p>
  </div>

  <div class="violation-info" style="margin-top:14px;">
    <h3>Selected Violation #<?= htmlspecialchars((string)$violation_id) ?></h3>
    <p><b>Category:</b> <?= ucfirst($cat) ?></p>
    <p><b>Type:</b> <?= $type ?></p>
    <p><b>Details:</b> <?= $detailsText ?></p>
    <p><b>Reported on:</b> <?= $datePretty ?></p>
    <p><b>Description:</b><br><?= nl2br($desc) ?: '—' ?></p>

    <?php if ($photoRel): ?>
      <p><b>Photo evidence:</b></p>
      <div class="photo-wrap" style="margin-top:8px">
        <img src="<?= htmlspecialchars($photoRel) ?>" alt="Evidence photo">
      </div>
    <?php endif; ?>
  </div>

  <div class="validators-container" style="margin-top:20px;">
    <h3>Select Validator</h3>
    <form method="post" action="" onsubmit="return confirmAssign();">
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
                <p><b>Designation:</b> <?= $org ?: '—' ?></p>
                <p><b>Email:</b> <?= $email ?: '—' ?></p>
                <p><b>Assigned students:</b> <?= $cnt ?></p>
              </div>
            </label>
            <?php
          }
          $validators->free();
        } else {
          echo "<p>No active validators available.</p>";
        }
        ?>
      </div>

      <br>
      <button type="submit" name="assign_validator">Assign Validator</button>
    </form>
  </div>

  <script>
    function confirmAssign() { return confirm("Assign validator to student?"); }
  </script>

</body>
</html>
<?php
$conn->close();
