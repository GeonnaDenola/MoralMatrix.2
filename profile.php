<?php
session_start();
require __DIR__ . '/config.php';

// ==============================
// 1. Authentication
// ==============================
if (empty($_SESSION['record_id']) || empty($_SESSION['account_type'])) {
  http_response_code(401);
  echo "Not authenticated.";
  exit;
}

$recordId = (int) $_SESSION['record_id'];
$accountType = $_SESSION['account_type'];

switch (strtolower($accountType)) {
  case 'super_admin':
    $headerFile = __DIR__ . '/includes/superadmin_header.php';
    break;
  case 'administrator':
    $headerFile = __DIR__ . '/includes/admin_header.php';
    break;
  case 'ccdu':
    $headerFile = __DIR__ . '/includes/header.php';
    break;
  case 'faculty':
    $headerFile = __DIR__ . '/includes/faculty_header.php';
    break;
  case 'security':
    $headerFile = __DIR__ . '/includes/security_header.php';
    break;
  case 'student':
    $headerFile = __DIR__ . '/includes/student_header.php';
    break;
}

if (file_exists($headerFile)) {
  include $headerFile;
} else {
  echo "<!-- Missing header for role: $accountType -->";
}

// ==============================
// 2. Database connection
// ==============================
$db = $database_settings;
$conn = new mysqli($db['servername'], $db['username'], $db['password'], $db['dbname']);
if ($conn->connect_error) {
  die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ==============================
// 3. Get id_number from accounts table
// ==============================
$stmt = $conn->prepare("SELECT id_number, email FROM accounts WHERE record_id = ? LIMIT 1");
$stmt->bind_param("i", $recordId);
$stmt->execute();
$res = $stmt->get_result();
$acc = $res->fetch_assoc();
$stmt->close();

if (!$acc || empty($acc['id_number'])) {
  echo "No id_number found for this user (record_id=$recordId).";
  $conn->close();
  exit;
}

$idNumber = $acc['id_number'];

// ==============================
// 4. Determine correct user table
// ==============================
switch ($accountType) {
  case 'student':
    $table = 'student_account';
    $idCol = 'student_id';
    $fields = "student_id, first_name, middle_name, last_name, email, mobile, photo, institute, course, level, section";
    break;
  case 'ccdu':
    $table = 'ccdu_account';
    $idCol = 'ccdu_id';
    $fields = "ccdu_id, first_name, last_name, email, mobile, photo";
    break;
  case 'faculty':
    $table = 'faculty_account';
    $idCol = 'faculty_id';
    $fields = "faculty_id, first_name, last_name, email, mobile, photo, institute";
    break;
  case 'security':
    $table = 'security_account';
    $idCol = 'security_id';
    $fields = "security_id, first_name, last_name, email, mobile, photo";
    break;
  case 'administrator':
  case 'admin':
  case 'super_admin':
    $table = 'admin_account';
    $idCol = 'admin_id';
    $fields = "admin_id, first_name, middle_name, last_name, email, mobile, photo";
    break;
  default:
    die("Unknown account type: $accountType");
}

// ==============================
// 5. Fetch user info
// ==============================
$stmt = $conn->prepare("SELECT $fields FROM $table WHERE $idCol = ? LIMIT 1");
$stmt->bind_param("s", $idNumber);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user) {
  echo "Profile not found for ID $idNumber ($accountType)";
  exit;
}

// ==============================
// 6. Handle photo path
// ==============================

// Path to upload folder (adjust if needed)
$uploadDir = __DIR__ . '/admin/uploads/';

// Determine photo URL
$photo = 'uploads/placeholder.png';
if (!empty($user['photo'])) {
  $photoPath = $uploadDir . basename($user['photo']);
  if (is_file($photoPath)) {
    // relative URL for browser (assuming profile.php is in root)
    $photo = 'admin/uploads/' . basename($user['photo']);
  }
}

$fullName = trim($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name']);
$email = $user['email'] ?? '';
$mobile = $user['mobile'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--bg:#f7f8fb;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#e5e7eb;--accent:#2563eb}
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font:15px/1.6 system-ui,Segoe UI,Roboto,Arial,sans-serif}
    .wrap{max-width:900px;margin:30px auto;padding:0 16px}
    .card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.05)}
    .header{display:flex;align-items:center;gap:20px;margin-bottom:20px}
    .avatar{width:120px;height:120px;border-radius:999px;object-fit:cover;border:1px solid var(--border)}
    h1{margin:0;font-size:24px}
    .muted{color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
    .item{border:1px dashed var(--border);border-radius:10px;padding:10px;background:#fff}
    .item b{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:5px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="header">
        <img src="<?= htmlspecialchars($photo) ?>" alt="Profile photo" class="avatar">
        <div>
          <h1><?= htmlspecialchars($fullName ?: 'Unnamed') ?></h1>
          <div class="muted"><?= ucfirst($accountType) ?> Account</div>
          <div><b>ID:</b> <?= htmlspecialchars($idNumber) ?></div>
        </div>
      </div>

      <div class="grid">
        <div class="item"><b>Email</b><span><?= htmlspecialchars($email ?: '—') ?></span></div>
        <div class="item"><b>Contact Number</b><span><?= htmlspecialchars($mobile ?: '—') ?></span></div>
      </div>

      <?php if ($accountType === 'student'): ?>
        <div class="grid" style="margin-top:16px">
          <div class="item"><b>Institute</b><span><?= htmlspecialchars($user['institute'] ?: '—') ?></span></div>
          <div class="item"><b>Course</b><span><?= htmlspecialchars($user['course'] ?: '—') ?></span></div>
          <div class="item"><b>Year Level</b><span><?= htmlspecialchars($user['level'] ?: '—') ?></span></div>
          <div class="item"><b>Section</b><span><?= htmlspecialchars($user['section'] ?: '—') ?></span></div>
        </div>
      <?php elseif ($accountType === 'ccdu'): ?>
        <div class="item" style="margin-top:16px">
          <b>Position</b><span>Staff – Center for Character Development Unit</span>
        </div>
      <?php elseif ($accountType === 'faculty'): ?>
        <div class="item" style="margin-top:16px">
          <b>Position</b><span>Faculty Member – <?= htmlspecialchars($user['institute'] ?: '—') ?></span>
        </div>
      <?php elseif ($accountType === 'security'): ?>
        <div class="item" style="margin-top:16px">
          <b>Position</b><span>Security Personnel</span>
        </div>
      <?php elseif (in_array($accountType, ['administrator','admin','super_admin'])): ?>
        <div class="item" style="margin-top:16px">
          <b>Position</b><span>Administrator</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
