<?php
session_start();
require __DIR__ . '/config.php';

/* ================================
   1) AUTH
================================ */
if (empty($_SESSION['record_id']) || empty($_SESSION['account_type'])) {
  http_response_code(401);
  echo "Not authenticated.";
  exit;
}
$recordId    = (int) $_SESSION['record_id'];
$accountType = strtolower((string) $_SESSION['account_type']);

/* Include site header (topbar + sidebar) */
$headerFile = null;
switch ($accountType) {
  case 'super_admin':   $headerFile = __DIR__ . '/includes/superadmin_header.php'; break;
  case 'administrator': $headerFile = __DIR__ . '/includes/admin_header.php'; break;
  case 'ccdu':          $headerFile = __DIR__ . '/includes/header.php'; break;
  case 'faculty':       $headerFile = __DIR__ . '/includes/faculty_header.php'; break;
  case 'security':      $headerFile = __DIR__ . '/includes/security_header.php'; break;
  case 'student':       $headerFile = __DIR__ . '/includes/student_header.php'; break;
}
if ($headerFile && file_exists($headerFile)) {
  include $headerFile;
}

/* ================================
   2) DB
================================ */
$db   = $database_settings;
$conn = new mysqli($db['servername'], $db['username'], $db['password'], $db['dbname']);
if ($conn->connect_error) { die("Database connection failed: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

/* ================================
   3) id_number from accounts
================================ */
$stmt = $conn->prepare("SELECT id_number, email FROM accounts WHERE record_id=? LIMIT 1");
$stmt->bind_param("i", $recordId);
$stmt->execute();
$acc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$acc || empty($acc['id_number'])) {
  echo "No id_number found for this user.";
  $conn->close();
  exit;
}
$idNumber = (string) $acc['id_number'];

/* ================================
   4) Role → table/columns
================================ */
switch ($accountType) {
  case 'student':
    $table='student_account'; $idCol='student_id';
    $fields="student_id, first_name, middle_name, last_name, email, mobile, photo, institute, course, level, section";
    break;
  case 'ccdu':
    $table='ccdu_account'; $idCol='ccdu_id';
    $fields="ccdu_id, first_name, last_name, email, mobile, photo";
    break;
  case 'faculty':
    $table='faculty_account'; $idCol='faculty_id';
    $fields="faculty_id, first_name, last_name, email, mobile, photo, institute";
    break;
  case 'security':
    $table='security_account'; $idCol='security_id';
    $fields="security_id, first_name, last_name, email, mobile, photo";
    break;
  case 'administrator':
  case 'admin':
  case 'super_admin':
    $table='admin_account'; $idCol='admin_id';
    $fields="admin_id, first_name, middle_name, last_name, email, mobile, photo";
    break;
  default:
    die("Unknown account type: {$accountType}");
}

/* ================================
   5) Fetch user row
================================ */
$stmt = $conn->prepare("SELECT $fields FROM $table WHERE $idCol=? LIMIT 1");
$stmt->bind_param("s", $idNumber);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user) { echo "Profile not found."; exit; }

/* ================================
   6) Helpers & photo
================================ */
if (!function_exists('h')) {
  function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
$uploadDir = __DIR__ . '/admin/uploads/';
$photo     = 'admin/uploads/placeholder.png';
if (!empty($user['photo'])) {
  $candidate = $uploadDir . basename($user['photo']);
  if (is_file($candidate)) $photo = 'admin/uploads/' . basename($user['photo']);
}
$fullName = trim(($user['first_name'] ?? '').' '.($user['middle_name'] ?? '').' '.($user['last_name'] ?? ''));
$email    = $user['email']  ?? '';
$mobile   = $user['mobile'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* ===== Light, brand-fit UI (no dark mode) ===== */
    :root{
      /* Match your layout chrome */
      --header-h: 72px;     /* fixed top bar height */
      --sidebar-w: 260px;   /* left sidebar width */

      /* Palette aligned to your site */
      --brand: #b91c1c;      /* red topbar hue */
      --bg: #f5f7fb;
      --surface:#ffffff;
      --ink:#0f172a;
      --muted:#64748b;
      --line:#e5e7eb;
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background:var(--bg);
      color:var(--ink);
      font:15px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      overflow-x:hidden; /* prevent horizontal scroll */
    }

    /* Page space that respects the fixed header + sidebar */
    .page{
      min-height:100%;
      padding: 28px clamp(16px, 2vw, 24px);
      padding-top: calc(var(--header-h) + 28px); /* never hide under topbar */
      margin-left: var(--sidebar-w);             /* never overlap sidebar */
    }
    @media (max-width:1200px){ :root{ --sidebar-w: 88px; } }
    @media (max-width:768px){  :root{ --sidebar-w: 0px; } }

    /* Center the content */
    .container{
      width:min(1100px, 100%);
      margin:0 auto;
    }

    /* Card */
    .card{
      background:var(--surface);
      border:1px solid var(--line);
      border-radius:18px;
      box-shadow:0 10px 30px rgba(15,23,42,.06);
      overflow:hidden;
    }

    .card-head{
      display:grid;
      grid-template-columns: 140px 1fr;
      gap:18px;
      padding:20px 20px 16px 20px;
      border-bottom:1px dashed var(--line);
      background:linear-gradient(180deg, #fff, #fff 60%, #fafafa);
    }
    @media (max-width:640px){
      .card-head{ grid-template-columns: 1fr; }
    }

    .avatar{
      width:128px;height:128px;border-radius:50%;
      object-fit:cover;border:1px solid var(--line);background:#fafafa;display:block;
    }

    .name{
      margin:0 0 4px 0;
      font-size: clamp(20px, 2.2vw, 28px);
      font-weight: 700;
      letter-spacing:.2px;
    }

    .role-badges{
      display:flex;flex-wrap:wrap;gap:10px 12px;align-items:center;margin-top:6px;
    }
    .badge{
      display:inline-flex;align-items:center;gap:8px;
      background:#fee2e2;color:#7f1d1d;
      padding:6px 12px;border-radius:999px;border:1px solid #fecaca;
      font-size:12px;font-weight:600;
    }
    .chip{
      display:inline-flex;align-items:center;gap:8px;
      background:#fff;border:1px solid var(--line);color:#111827;
      padding:6px 10px;border-radius:999px;font-size:13px;
    }

    .grid{
      display:grid;
      grid-template-columns: repeat(2, minmax(260px, 1fr));
      gap:14px;
      padding:16px 20px 22px 20px;
      background:#fff;
    }
    @media (max-width:860px){ .grid{ grid-template-columns: 1fr; } }

    .item{
      border:1px solid var(--line);
      border-radius:12px;
      padding:12px 14px;
      background:#fff;
      transition: box-shadow .15s ease, transform .15s ease;
    }
    .item:hover{ box-shadow:0 6px 18px rgba(15,23,42,.06); transform:translateY(-1px); }
    .item b{
      display:block;font-size:12px;color:var(--muted);
      text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;
    }

    /* No breadcrumbs, no page-level action bar (Back/Edit) per request */
  </style>
</head>
<body>
  <main class="page">
    <div class="container">

      <!-- PROFILE CARD ONLY (no breadcrumbs, no Back/Edit buttons) -->
      <section class="card" aria-label="Profile">
        <div class="card-head">
          <div>
            <img class="avatar" src="<?= h($photo) ?>" alt="Profile photo of <?= h($fullName ?: 'User') ?>">
          </div>
          <div>
            <h2 class="name"><?= h($fullName ?: 'Unnamed User') ?></h2>
            <div class="role-badges">
              <span class="badge"><?= strtoupper($accountType) ?> ACCOUNT</span>
              <span class="chip"><strong>ID:</strong>&nbsp;<?= h($idNumber) ?></span>
            </div>
          </div>
        </div>

        <div class="grid" aria-label="Contact Information">
          <div class="item"><b>Email</b><div><?= h($email ?: '—') ?></div></div>
          <div class="item"><b>Contact Number</b><div><?= h($mobile ?: '—') ?></div></div>
        </div>

        <?php if ($accountType === 'student'): ?>
          <div class="grid" aria-label="Student Details">
            <div class="item"><b>Institute</b><div><?= h($user['institute'] ?? '—') ?></div></div>
            <div class="item"><b>Course</b><div><?= h($user['course'] ?? '—') ?></div></div>
            <div class="item"><b>Year Level</b><div><?= h($user['level'] ?? '—') ?></div></div>
            <div class="item"><b>Section</b><div><?= h($user['section'] ?? '—') ?></div></div>
          </div>
        <?php elseif ($accountType === 'ccdu'): ?>
          <div class="grid" aria-label="CCDU Details">
            <div class="item" style="grid-column:1/-1"><b>Position</b><div>Staff – Center for Character Development Unit</div></div>
          </div>
        <?php elseif ($accountType === 'faculty'): ?>
          <div class="grid" aria-label="Faculty Details">
            <div class="item" style="grid-column:1/-1"><b>Position</b><div>Faculty Member – <?= h($user['institute'] ?? '—') ?></div></div>
          </div>
        <?php elseif ($accountType === 'security'): ?>
          <div class="grid" aria-label="Security Details">
            <div class="item" style="grid-column:1/-1"><b>Position</b><div>Security Personnel</div></div>
          </div>
        <?php elseif (in_array($accountType, ['administrator','admin','super_admin'], true)): ?>
          <div class="grid" aria-label="Admin Details">
            <div class="item" style="grid-column:1/-1"><b>Position</b><div>Administrator</div></div>
          </div>
        <?php endif; ?>
      </section>

    </div>
  </main>
</body>
</html>
