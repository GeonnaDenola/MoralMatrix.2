<?php
// admin/view_account.php

require_once __DIR__ . '/../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  http_response_code(500);
  die("Connection failed.");
}

$id   = isset($_GET['id'])   ? (int)$_GET['id'] : 0;       // record_id in the specific table
$type = isset($_GET['type']) ? trim($_GET['type']) : '';   // student|faculty|security|ccdu

if (!$id || !$type) {
  http_response_code(400);
  die("Invalid request.");
}

switch ($type) {
  case 'student':
    $sql = "SELECT * FROM student_account WHERE record_id = ?";
    break;
  case 'faculty':
    $sql = "SELECT * FROM faculty_account WHERE record_id = ?";
    break;
  case 'security':
    $sql = "SELECT * FROM security_account WHERE record_id = ?";
    break;
  case 'ccdu':
    $sql = "SELECT * FROM ccdu_account WHERE record_id = ?";
    break;
  default:
    http_response_code(400);
    die("Unknown account type.");
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
  $stmt->close();
  $conn->close();
  http_response_code(404);
  die("Record not found.");
}
$data = $res->fetch_assoc();
$stmt->close();
$conn->close();

/* ----- PHOTO PATH (files live in /admin/uploads) ----- */
$photoPath = 'placeholder.png'; // keep a light placeholder in /admin/
if (!empty($data['photo'])) {
  $rel = 'uploads/' . $data['photo'];            // /admin/uploads/<file>
  $abs = __DIR__ . '/' . $rel;
  if (is_file($abs)) {
    $photoPath = $rel;
  }
}

/* ----- FIELDS TO HIDE ----- */
$hiddenKeys = ['password', 'photo'];

/* ----- ORDERING: show common/important fields first if present ----- */
$priorityByType = [
  'student' => [
    'student_id','first_name','middle_name','last_name','full_name',
    'email','contact_no','course','year_level','section','address',
    'gender','birthdate','status'
  ],
  'faculty' => [
    'employee_id','first_name','last_name','full_name','email',
    'contact_no','department','position','address','status'
  ],
  'security' => [
    'guard_id','first_name','last_name','full_name','email','contact_no','shift','status'
  ],
  'ccdu' => [
    'staff_id','first_name','last_name','full_name','email','contact_no','department','position','status'
  ],
];

$priority = $priorityByType[$type] ?? [];
$allKeys  = array_keys($data);

/* remove hidden and non-existent from priority */
$priority = array_values(array_filter($priority, function($k) use ($data, $hiddenKeys) {
  return array_key_exists($k, $data) && !in_array($k, $hiddenKeys, true);
}));

/* remaining keys in their original order, minus hidden and minus priority */
$remaining = array_values(array_filter($allKeys, function($k) use ($priority, $hiddenKeys) {
  return !in_array($k, $hiddenKeys, true) && !in_array($k, $priority, true);
}));

$orderedKeys = array_merge($priority, $remaining);

/* label helper */
function pretty_label(string $k): string {
  $k = str_replace('_', ' ', $k);
  return ucwords($k);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(ucfirst($type)) ?> Account</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Keep this path; file provided below -->
  <link rel="stylesheet" href="../css/view_account.css">
</head>
<body>
  <!-- No extra header/nav here—this fits inside your existing layout or modal -->
  <main class="mm-wrap" role="main" aria-labelledby="mm-title">
    <section class="mm-card">
      <div class="mm-card__header">
        <div class="mm-photo">
          <img src="<?= htmlspecialchars($photoPath) ?>"
               alt="Profile photo"
               loading="lazy">
        </div>
        <div class="mm-heading">
          <h1 id="mm-title" class="mm-title">
            <?= htmlspecialchars(ucfirst($type)) ?> Account
          </h1>
          <div class="mm-subtitle">
            <span class="mm-chip">Record #<?= (int)$data['record_id'] ?></span>
            <?php if (!empty($data['status'])): ?>
              <span class="mm-chip mm-chip--muted"><?= htmlspecialchars((string)$data['status']) ?></span>
            <?php endif; ?>
          </div>

          <?php if($type === 'student' && !empty($data['student_id'])): ?>
            <div class="mm-actions">
              <a class="mm-btn"
                 target="_blank" rel="noopener"
                 href="qr_id_card.php?student_id=<?= urlencode((string)$data['student_id']) ?>">
                Print Student ID
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="mm-divider" role="presentation"></div>

      <div class="mm-grid" aria-describedby="mm-title">
        <?php foreach ($orderedKeys as $k): ?>
          <div class="mm-field">
            <div class="mm-label"><?= htmlspecialchars(pretty_label($k)) ?></div>
            <div class="mm-value">
              <?= htmlspecialchars((string)$data[$k]) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</body>
</html>
