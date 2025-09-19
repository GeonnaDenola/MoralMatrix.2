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

$student_id   = $_GET['student_id']   ?? null;
$violation_id = isset($_GET['violation_id']) ? (int)$_GET['violation_id'] : 0;
$returnUrl    = $_GET['return'] ?? ('view_student.php?student_id=' . urlencode((string)$student_id));


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_validator'])) {
    $student_id   = $_POST['student_id']   ?? null;
    $violation_id = (int)($_POST['violation_id'] ?? 0);
    $validator_id = $_POST['validator_id'] ?? null;

    if ($student_id && $violation_id > 0 && $validator_id) {
        $sql = "INSERT INTO validator_student_assignment (assignment_id, student_id, validator_id, assigned_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE validator_id = VALUES(validator_id), assigned_at = NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $violation_id, $student_id, $validator_id);
        if ($stmt->execute()) {
            header("Location: " . $returnUrl);
            exit;
        } else {
            echo "<p style='color:red'>Error assigning validator: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>Missing required fields.</p>";
    }
}

if (!$student_id) { die("No student selected."); }
if ($violation_id <= 0) { die("No violation selected."); }

/* Fetch student (build name + year_level = level + section) */
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

/* Fetch ONLY that violation, ensure it belongs to this student */
$vsql = "
  SELECT
    violation_id,
    offense_category,
    offense_type,
    offense_details,
    description,
    reported_at,
    (photo IS NOT NULL AND OCTET_LENGTH(photo) > 0) AS has_photo
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

/* Pretty values */
$datePretty = !empty($violation['reported_at']) ? date('M d, Y h:i A', strtotime($violation['reported_at'])) : '—';
$cat        = htmlspecialchars($violation['offense_category'] ?? '');
$type       = htmlspecialchars($violation['offense_type'] ?? '');
$desc       = htmlspecialchars($violation['description'] ?? '');
$hasPhoto   = !empty($violation['has_photo']) && (int)$violation['has_photo'] === 1;

/* Flatten offense_details JSON */
$detailsText = '—';
if (!empty($violation['offense_details'])) {
  $decoded = json_decode($violation['offense_details'], true);
  if (is_array($decoded) && count($decoded)) {
    $safe = array_map('htmlspecialchars', $decoded);
    $detailsText = implode(', ', $safe);
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assign Validator</title>

  <style>
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 1rem;
}

.validator-card {
  cursor: pointer;
  display: block;
}

.validator-card input[type="radio"] {
  display: none; /* hide radio button */
}

.card-content {
  border: 2px solid #ccc;
  border-radius: 12px;
  padding: 1rem;
  background: #fff;
  transition: all 0.2s ease;
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.validator-card:hover .card-content {
  border-color: #007bff;
  background: #f9f9ff;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.validator-card input[type="radio"]:checked + .card-content {
  border-color: #007bff;
  background: #eef4ff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.card-content h4 {
  margin: 0 0 8px;
  font-size: 1.1rem;
}
.card-content p {
  margin: 4px 0;
  font-size: 0.9rem;
}
</style>

</head>
<body>

  <p><a href="<?= htmlspecialchars($returnUrl) ?>">← Back</a></p>

  <h2>Assign Validator for Student</h2>
<div class="student-info">
  <!-- Student info (plain display) -->
  <p><b>ID:</b> <?= htmlspecialchars($student['student_id']) ?></p>
  <p><b>Name:</b> <?= htmlspecialchars($student['student_name']) ?></p>
  <p><b>Course:</b> <?= htmlspecialchars($student['course']) ?></p>
  <p><b>Year Level:</b> <?= htmlspecialchars($student['year_level']) ?></p>
</div>

<div class = "violation-info">
  <!-- Selected violation (plain display) -->
  <h3>Selected Violation #<?= htmlspecialchars((string)$violation_id) ?></h3>
  <p><b>Category:</b> <?= ucfirst($cat) ?></p>
  <p><b>Type:</b> <?= $type ?></p>
  <p><b>Details:</b> <?= $detailsText ?></p>
  <p><b>Reported on:</b> <?= $datePretty ?></p>
  <p><b>Description:</b><br><?= nl2br($desc) ?: '—' ?></p>

  <?php if ($hasPhoto): ?>
    <p><b>Photo evidence:</b></p>
    <img src="violation_photo.php?id=<?= urlencode((string)$violation_id) ?>" alt="Evidence photo">
  <?php endif; ?>
</div>

<div class="validators-container">
  <h3>Select Validator</h3>
  <form method="post" action="">
    <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
    <input type="hidden" name="violation_id" value="<?= htmlspecialchars((string)$violation_id) ?>">

    <div class="cards-grid">
      <?php
      $vsql = "SELECT validator_id, CONCAT(v_username) AS validator_name, designation, email
               FROM validator_account
               ORDER BY validator_name ASC";
      $vres = $conn->query($vsql);
      if ($vres && $vres->num_rows > 0) {
        while ($v = $vres->fetch_assoc()) {
          $id    = htmlspecialchars($v['validator_id']);
          $name  = htmlspecialchars($v['validator_name']);
          $org   = htmlspecialchars($v['designation']);
          $email = htmlspecialchars($v['email']);
          ?>
          <label class="validator-card">
            <input type="radio" name="validator_id" value="<?= $id ?>" required>
            <div class="card-content">
              <h4><?= $name ?></h4>
              <p><b>Designation:</b> <?= $org ?></p>
              <p><b>Email:</b> <?= $email ?></p>
            </div>
          </label>
          <?php
        }
      } else {
        echo "<p>No validators available.</p>";
      }
      ?>
    </div>

    <br>
    <button type="submit" name="assign_validator">Assign Validator</button>
  </form>
</div>

</body>
</html>
