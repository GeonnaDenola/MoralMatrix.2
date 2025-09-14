<?php
// admin/account_view.php
// Standalone account viewer with "Print ID" for students

require_once __DIR__ . '/../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$id   = isset($_GET['id'])   ? (int)$_GET['id'] : 0;        // record_id in the specific table
$type = isset($_GET['type']) ? $_GET['type']    : '';       // student|faculty|security|ccdu

if(!$id || !$type){
  http_response_code(400);
  die("Invalid request.");
}

switch($type){
  case 'student':
    $sql = "SELECT * FROM student_account WHERE record_id = ?";
    $photoFieldIsFilename = true; // we saved filenames in /uploads
    break;
  case 'faculty':
    $sql = "SELECT * FROM faculty_account WHERE record_id = ?";
    $photoFieldIsFilename = true;
    break;
  case 'security':
    $sql = "SELECT * FROM security_account WHERE record_id = ?";
    $photoFieldIsFilename = true;
    break;
  case 'ccdu':
    $sql = "SELECT * FROM ccdu_account WHERE record_id = ?";
    $photoFieldIsFilename = true;
    break;
  default:
    http_response_code(400);
    die("Unknown account type.");
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if($res->num_rows === 0){
  $stmt->close();
  $conn->close();
  http_response_code(404);
  die("Record not found.");
}
$data = $res->fetch_assoc();
$stmt->close();
$conn->close();

// figure photo path (files live in /admin/uploads)
$photoPath = 'placeholder.png'; // put this file in /admin/ or adjust path

if (!empty($data['photo'])) {
    // if DB stores just the filename
    $rel = 'uploads/' . $data['photo'];          // -> /admin/uploads/<file>
    $abs = __DIR__ . '/' . $rel;
    if (is_file($abs)) {
        $photoPath = $rel;
    }
}


// fields we should not display
$hide = ['password','photo']; // never show password / raw filename
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(ucfirst($type)) ?> Account</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#f7f9fc;margin:0;padding:24px;}
    .card{max-width:680px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.06);padding:24px;}
    .avatar{width:120px;height:120px;border-radius:50%;object-fit:cover;display:block;margin:0 auto 14px;}
    .title{margin:0 0 6px;font-size:20px;font-weight:700;text-align:center;}
    .subtitle{text-align:center;margin:0 0 18px;color:#6b7280;}
    .grid{display:grid;grid-template-columns:1fr 2fr;gap:10px 16px;margin-top:8px;}
    .label{color:#6b7280}
    .value{color:#111827}
    .actions{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap;justify-content:center}
    .btn{display:inline-block;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;text-decoration:none;color:#111}
    .btn:hover{background:#f3f4f6}
    .back{color:#2563eb;border-color:#bfdbfe}
  </style>
</head>
<body>
  <div class="card">
    <img src="<?= htmlspecialchars($photoPath) ?>" class="avatar" alt="Profile">
    <h1 class="title"><?= htmlspecialchars(ucfirst($type)) ?> Account</h1>
    <p class="subtitle">Record #<?= (int)$data['record_id'] ?></p>

    <div class="grid">
      <?php foreach($data as $k => $v): if(in_array($k, $hide, true)) continue; ?>
        <div class="label"><?= htmlspecialchars(ucfirst(str_replace('_',' ', $k))) ?>:</div>
        <div class="value"><?= htmlspecialchars((string)$v) ?></div>
      <?php endforeach; ?>
    </div>

    <div class="actions">
      <a class="btn back" href="javascript:history.back()">← Back</a>

      <?php if($type === 'student' && !empty($data['student_id'])): ?>
        <!-- Opens your printable ID (front/back with QR) in a new tab -->
        <a class="btn" target="_blank" rel="noopener"
           href="qr_id_card.php?student_id=<?= urlencode($data['student_id']) ?>">
          Print Student ID
        </a>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
