<?php
// student_details.php
// Shows student profile and lets validator record community-service hours + up to 5 images.

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* Optional: get validator_id from session if you store it there */
$validator_id = isset($_SESSION['validator_id']) ? (int)$_SESSION['validator_id'] : null;

/* ---------- Inputs ---------- */
$student_id    = $_GET['student_id']    ?? null;
$violation_get = isset($_GET['violation_id']) ? (int)$_GET['violation_id'] : null; // optional deep-link from a violation
$returnUrlIn   = $_GET['return']        ?? 'validator_dashboard.php';
$returnUrl     = (is_string($returnUrlIn) && $returnUrlIn !== '' && strpos($returnUrlIn, '://') === false) ? $returnUrlIn : 'validator_dashboard.php';

if (!$student_id) { die("No student selected."); }

/* ---------- File upload settings ---------- */
$uploadDirFs   = __DIR__ . '/uploads/service';   // filesystem
$uploadDirUrl  = 'uploads/service';              // web path (relative)
$maxFiles      = 5;
$maxBytes      = 6 * 1024 * 1024;                // 6 MB per file
$allowedMimes  = ['image/jpeg','image/png','image/webp','image/gif','image/jpg'];
if (!is_dir($uploadDirFs)) { @mkdir($uploadDirFs, 0775, true); }

/* ---------- Handle POST (before any output) ---------- */
$flashError = null;
$saved      = isset($_GET['saved']) && $_GET['saved'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_entry'])) {
    $hours        = isset($_POST['hours']) ? (float)$_POST['hours'] : 0;
    $remarks      = trim((string)($_POST['remarks'] ?? ''));
    $comment      = trim((string)($_POST['comment'] ?? ''));
    $violation_id = isset($_POST['violation_id']) && $_POST['violation_id'] !== '' ? (int)$_POST['violation_id'] : null;
    $service_date = !empty($_POST['service_date']) ? $_POST['service_date'] : date('Y-m-d'); // fallback to today

    // Basic validation
    if ($hours <= 0) {
        $flashError = "Please enter a positive number of hours.";
    }

    // If a violation is provided, ensure it belongs to the student
    if (!$flashError && $violation_id !== null) {
        $chk = $conn->prepare("SELECT 1 FROM student_violation WHERE violation_id=? AND student_id=?");
        $chk->bind_param("is", $violation_id, $student_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_row()) {
            $violation_id = null; // ignore mismatched/bad id
        }
        $chk->close();
    }

    // Files handling
    $storedPaths = [];
    if (!$flashError && isset($_FILES['evidence']) && is_array($_FILES['evidence']['name'])) {
        // Count non-empty files
        $names = $_FILES['evidence']['name'];
        $totalSelected = 0;
        foreach ($names as $n) { if (strlen((string)$n)) $totalSelected++; }
        if ($totalSelected > $maxFiles) {
            $flashError = "You can upload up to {$maxFiles} images.";
        }
    }

    if (!$flashError && isset($_FILES['evidence']) && is_array($_FILES['evidence']['name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $count = count($_FILES['evidence']['name']);
        for ($i = 0; $i < $count; $i++) {
            if (empty($_FILES['evidence']['name'][$i])) continue;
            $err  = $_FILES['evidence']['error'][$i];
            $size = (int)$_FILES['evidence']['size'][$i];
            $tmp  = $_FILES['evidence']['tmp_name'][$i];
            $name = $_FILES['evidence']['name'][$i];

            if ($err !== UPLOAD_ERR_OK) { $flashError = "Upload error on file " . htmlspecialchars($name) . " (code $err)."; break; }
            if ($size > $maxBytes)      { $flashError = "File too large: " . htmlspecialchars($name) . " (max 6MB per image)."; break; }
            $mime = $finfo->file($tmp);
            if (!in_array($mime, $allowedMimes, true)) { $flashError = "Unsupported file type: " . htmlspecialchars($name) . "."; break; }
        }

        // Move files if still OK
        if (!$flashError) {
            for ($i = 0; $i < $count; $i++) {
                if (empty($_FILES['evidence']['name'][$i])) continue;
                $tmp  = $_FILES['evidence']['tmp_name'][$i];
                $name = $_FILES['evidence']['name'][$i];

                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($ext === '') {
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                        'image/gif'  => 'gif',
                        default      => 'bin'
                    };
                }

                $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $student_id);
                $destName = $safeBase . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destFs   = $uploadDirFs . '/' . $destName;
                $destUrl  = $uploadDirUrl . '/' . $destName;

                if (!move_uploaded_file($tmp, $destFs)) { $flashError = "Failed to store file: " . htmlspecialchars($name); break; }
                $storedPaths[] = $destUrl; // relative web path
            }
        }
    }

    // Insert row if everything OK (matches your table schema)
    if (!$flashError) {
        $photosJson = $storedPaths ? json_encode($storedPaths, JSON_UNESCAPED_SLASHES) : null;

        $sql = "INSERT INTO community_service_entries
                  (student_id, violation_id, validator_id, hours, remarks, comment, photo_paths, service_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { die("Prepare failed: " . $conn->error); }
        $stmt->bind_param(
            "siidssss",
            $student_id,
            $violation_id,
            $validator_id,
            $hours,
            $remarks,
            $comment,
            $photosJson,
            $service_date
        );
        if ($stmt->execute()) {
            if (!headers_sent()) {
                header("Location: student_details.php?student_id=" . urlencode((string)$student_id) . "&return=" . urlencode($returnUrl) . "&saved=1", true, 302);
                exit;
            } else {
                echo '<script>location.replace(' . json_encode("student_details.php?student_id=".$student_id."&return=".$returnUrl."&saved=1") . ');</script>';
                exit;
            }
        } else {
            $flashError = "DB error saving entry: " . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    }
}

/* ---------- Fetch student profile ---------- */
$studentSql = "
  SELECT
    student_id,
    CONCAT_WS(' ', first_name, middle_name, last_name) AS student_name,
    course,
    level,
    section,
    institute,
    photo
  FROM student_account
  WHERE student_id = ?
";
$stmt = $conn->prepare($studentSql);
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$student) { die("Student not found."); }

$yearSection = trim(
  ($student['level'] ?? '') .
  ((!empty($student['level']) && !empty($student['section'])) ? '-' : '') .
  ($student['section'] ?? '')
);

$photoFile = !empty($student['photo']) ? $student['photo'] : 'placeholder.png';
$photoFs   = __DIR__ . '/../admin/uploads/' . $photoFile;     // adjust if path differs
$photoUrl  = '../admin/uploads/' . $photoFile;
if (!is_file($photoFs)) {
  $photoUrl = '../admin/uploads/placeholder.png';
}

/* ---------- HOURS: Required (by rule) vs Logged (entries) ---------- */
/* Rule:
   - 1 Grave = 20 hrs
   - Any 3 (Light/Moderate/Less Grave/Minor) = 10 hrs
*/

$requiredHours = 0.0;

/* Optional: if there's a status column, ignore voided/canceled violations */
$hasStatusCol = false;
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'status'")) {
  $hasStatusCol = ($res->num_rows > 0);
  $res->close();
}

/* Pull categories for this student */
$sqlCats = "SELECT offense_category FROM student_violation WHERE student_id = ? ";
if ($hasStatusCol) {
  // adjust this list if you want to also exclude 'resolved', etc.
  $sqlCats .= "AND LOWER(status) NOT IN ('void','voided','canceled','cancelled') ";
}
$sqlCats .= "ORDER BY reported_at ASC, violation_id ASC";

$catRows = [];
$q = $conn->prepare($sqlCats);
$q->bind_param("s", $student_id);
$q->execute();
$resCats = $q->get_result();
while ($row = $resCats->fetch_assoc()) {
  $catRows[] = strtolower(trim((string)$row['offense_category']));
}
$q->close();

/* Classify and count */
$graveCount    = 0;
$modLightCount = 0;

/* Treat anything that contains 'grave' BUT NOT 'less' as Grave.
   Everything else (light, moderate, less grave, minor, blank) goes to the 3-for-10 bucket. */
foreach ($catRows as $raw) {
  $isGrave = (preg_match('/\bgrave\b/i', $raw) && !preg_match('/\bless\b/i', $raw));
  if ($isGrave) {
    $graveCount++;
  } else {
    $modLightCount++;
  }
}

/* Required per rule */
$requiredHours = ($graveCount * 20) + (intdiv($modLightCount, 3) * 10);

/* Logged hours (portable fetch) */
$totalLogged = 0.0;
$hasCsEntriesTable = false;
if ($res = $conn->query("SHOW TABLES LIKE 'community_service_entries'")) {
  $hasCsEntriesTable = ($res->num_rows > 0);
  $res->close();
}
if ($hasCsEntriesTable) {
  $sum = $conn->prepare("SELECT COALESCE(SUM(hours),0) AS total FROM community_service_entries WHERE student_id = ?");
  if ($sum) {
    $sum->bind_param("s", $student_id);
    $sum->execute();
    $sum->bind_result($sumHours);
    $sum->fetch();
    $sum->close();
    $totalLogged = (float)$sumHours;
  }
}

/* Remaining (never negative) */
$remainingHours = max(0, $requiredHours - $totalLogged);

/* ---------- Fetch previous entries ---------- */
$entries = [];
if ($hasCsEntriesTable) {
  $esql = "SELECT entry_id, hours, remarks, comment, photo_paths, service_date, created_at, violation_id
           FROM community_service_entries
           WHERE student_id = ?
           ORDER BY service_date DESC, created_at DESC, entry_id DESC";
  $st = $conn->prepare($esql);
  if ($st) {
    $st->bind_param("s", $student_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $entries[] = $row; }
    $st->close();
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Details - Community Service</title>
<style>
  body.validator-student-page{
    --bg:#f4f6fb;
    --text:#171f2c;
    --muted:#6c7385;
    --surface:#ffffff;
    --surface-muted:#f8f9fd;
    --border:#e3e7f2;
    --primary:#8c1c13;
    --shadow:0 28px 50px -36px rgba(17,24,39,.45);
    margin:0;
    background:linear-gradient(180deg, rgba(140,28,19,.06) 0%, rgba(244,246,251,1) 220px);
    color:var(--text);
    font:15px/1.55 "Inter","Segoe UI",system-ui,-apple-system,"Helvetica Neue",Arial,sans-serif;
    padding:0;
  }
  body.validator-student-page *{box-sizing:border-box;}
  .validator-content{
    width:min(1100px,100%);
    margin:28px auto 96px;
    padding:0 clamp(20px,3vw,40px);
    display:flex;
    flex-direction:column;
    gap:24px;
  }
  @media (min-width:1100px){
    body.validator-student-page{
      --sidebar-offset:250px;
      padding-left:calc(var(--sidebar-offset,240px) + clamp(24px,4vw,64px));
      padding-right:clamp(28px,4vw,72px);
    }
    .validator-content{margin-top:32px;}
  }
  @media (max-width:640px){
    .validator-content{margin:24px auto 72px;padding:0 18px;}
  }
  .back-link-wrapper{display:flex;justify-content:flex-start;}
  .back-link{
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-weight:600;
    color:#fff;
    background:var(--primary);
    text-decoration:none;
    font-size:.9rem;
    padding:9px 16px;
    border-radius:999px;
    box-shadow:0 12px 24px -20px rgba(140,28,19,.65);
    transition:transform .18s, box-shadow .18s, opacity .18s;
  }
  .back-link:hover{transform:translateY(-1px);opacity:.94;}
  .alert{
    margin:4px 0 0;
    padding:14px 18px;
    border-radius:14px;
    border:1px solid transparent;
    font-size:.92rem;
    display:flex;
    gap:8px;
    align-items:flex-start;
    box-shadow:0 10px 22px -14px rgba(17,24,39,.18);
  }
  .alert strong{font-weight:700;}
  .alert-ok{background:#ecfdf5;border-color:#a7f3d0;color:#047857;}
  .alert-err{background:#fef2f2;border-color:#fecaca;color:#b91c1c;}
  .card{
    background:var(--surface);
    border-radius:24px;
    border:1px solid var(--border);
    padding:30px;
    box-shadow:var(--shadow);
  }
  .card.slim{padding:24px;}
  .hero-card{
    display:grid;
    gap:28px;
    grid-template-columns:minmax(0,280px) minmax(0,1fr);
    align-items:center;
    background:linear-gradient(135deg, rgba(140,28,19,.12), rgba(255,255,255,.95));
    border:1px solid rgba(140,28,19,.22);
  }
  .hero-media{display:flex;justify-content:center;}
  .portrait{
    width:100%;
    max-width:260px;
    aspect-ratio:1/1;
    object-fit:cover;
    border-radius:22px;
    border:2px solid rgba(255,255,255,.7);
    box-shadow:0 18px 40px -26px rgba(17,24,39,.6);
    background:#fff;
  }
  .hero-body{display:flex;flex-direction:column;gap:12px;}
  .hero-overline{
    text-transform:uppercase;
    letter-spacing:.28em;
    font-size:.7rem;
    color:rgba(23,31,44,.55);
    font-weight:700;
  }
  .hero-title{margin:0;font-size:2.2rem;line-height:1.1;letter-spacing:-.01em;}
  .hero-tags{display:flex;flex-wrap:wrap;gap:12px;margin-top:6px;}
  .chip{
    display:flex;
    flex-direction:column;
    gap:2px;
    padding:12px 16px;
    border-radius:16px;
    background:rgba(255,255,255,.72);
    border:1px solid rgba(140,28,19,.18);
    min-width:150px;
  }
  .chip-label{
    font-size:.68rem;
    letter-spacing:.2em;
    text-transform:uppercase;
    color:rgba(23,31,44,.55);
    font-weight:700;
  }
  .chip-value{font-size:.98rem;font-weight:600;color:var(--text);}
  .hero-stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
    gap:16px;
    margin-top:10px;
  }
  .hero-stat{
    background:rgba(255,255,255,.82);
    border-radius:18px;
    border:1px solid rgba(227,231,242,.8);
    padding:16px 18px;
    display:flex;
    flex-direction:column;
    gap:6px;
  }
  .hero-stat-label{
    font-size:.75rem;
    letter-spacing:.16em;
    text-transform:uppercase;
    color:rgba(23,31,44,.6);
    font-weight:600;
  }
  .hero-stat-value{
    font-size:1.9rem;
    font-weight:700;
    color:var(--primary);
    line-height:1.1;
    display:flex;
    align-items:baseline;
    gap:6px;
  }
  .hero-stat-value small{
    font-size:.78rem;
    text-transform:uppercase;
    letter-spacing:.14em;
    color:rgba(23,31,44,.55);
    font-weight:600;
  }
  .hero-stat-value.ok{color:#047857;}
  .hero-stat-value.warn{color:#b91c1c;}
  .content-grid{
    display:grid;
    gap:24px;
    grid-template-columns:minmax(0,1.1fr) minmax(0,0.9fr);
    align-items:start;
  }
  @media (max-width:1080px){
    .hero-card{grid-template-columns:1fr;}
    .hero-media{justify-content:flex-start;}
    .portrait{max-width:200px;}
    .content-grid{grid-template-columns:1fr;}
  }
  form{display:flex;flex-direction:column;gap:18px;}
  .form-row{display:flex;flex-wrap:wrap;gap:16px;}
  .field{flex:1;min-width:180px;display:flex;flex-direction:column;gap:6px;}
  label{font-weight:600;font-size:.92rem;color:var(--text);}
  input[type="number"],
  input[type="text"],
  input[type="date"],
  textarea{
    width:100%;
    border-radius:14px;
    border:1px solid var(--border);
    background:var(--surface-muted);
    padding:12px 14px;
    font-size:.96rem;
    color:var(--text);
    transition:border-color .18s, box-shadow .18s, background .18s;
  }
  textarea{min-height:120px;resize:vertical;}
  input:focus,
  textarea:focus{
    border-color:var(--primary);
    box-shadow:0 0 0 4px rgba(140,28,19,.16);
    outline:none;
    background:#fff;
  }
  .help{font-size:.8rem;color:var(--muted);}
  .preview-grid{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:4px;
  }
  .preview-grid img,
  .entry-photos img{
    width:88px;
    height:88px;
    object-fit:cover;
    border-radius:12px;
    border:1px solid rgba(227,231,242,.9);
    box-shadow:0 8px 16px -12px rgba(17,24,39,.35);
  }
  .button-group{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:4px;}
  .btn{
    appearance:none;
    border:none;
    border-radius:14px;
    padding:12px 26px;
    font-weight:700;
    font-size:.95rem;
    letter-spacing:.04em;
    cursor:pointer;
    transition:transform .18s, box-shadow .18s, opacity .18s;
  }
  .btn.primary{
    background:var(--primary);
    color:#fff;
    box-shadow:0 20px 32px -24px rgba(140,28,19,.6);
  }
  .btn.primary:hover{transform:translateY(-1px);opacity:.92;}
  .btn.secondary{background:var(--surface);color:var(--text);border:1px solid var(--border);text-decoration:none;display:inline-flex;align-items:center;}
  .history-card{display:flex;flex-direction:column;gap:18px;}
  .history-card h2{margin:0;font-size:1.35rem;}
  .history-subtext{font-size:.9rem;color:var(--muted);margin:0;}
  .empty-state{
    padding:28px;
    border-radius:18px;
    background:var(--surface-muted);
    border:1px dashed rgba(140,28,19,.25);
    text-align:center;
    color:var(--muted);
    font-size:.95rem;
  }
  .entries{display:flex;flex-direction:column;gap:14px;}
  .entry{
    border:1px solid rgba(227,231,242,.9);
    border-radius:18px;
    padding:18px 20px;
    background:var(--surface-muted);
    box-shadow:0 18px 32px -30px rgba(17,24,39,.45);
    display:flex;
    flex-direction:column;
    gap:10px;
  }
  .entry-header{display:flex;flex-wrap:wrap;gap:12px;align-items:center;font-size:.9rem;color:var(--muted);}
  .entry-hours{margin-left:auto;font-weight:700;color:var(--primary);font-size:1.05rem;}
  .entry-body{display:flex;flex-direction:column;gap:8px;}
  .entry-body p{margin:0;font-size:.92rem;color:var(--text);}
  .entry-body p.muted{color:var(--muted);}
  .entry-photos{display:flex;flex-wrap:wrap;gap:10px;}
  .badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:0 10px;
    height:26px;
    border-radius:999px;
    font-size:.78rem;
    background:rgba(140,28,19,.12);
    color:var(--primary);
    border:1px solid rgba(140,28,19,.25);
    font-weight:600;
  }
  @media (max-width:540px){
    .card{padding:24px;}
    .hero-title{font-size:1.8rem;}
    .hero-stats{grid-template-columns:repeat(auto-fit,minmax(140px,1fr));}
    .entry{padding:16px;}
    .entry-hours{margin-left:0;}
  }
</style>
</head>
<body class="validator-student-page">
<?php include '../includes/header.php'; ?>

<main class="validator-content">
  <div class="back-link-wrapper">
    <a class="back-link" href="<?= htmlspecialchars($returnUrl) ?>">&larr; Back to Dashboard</a>
  </div>

  <?php if ($saved): ?>
    <div class="alert alert-ok"><strong>Entry logged!</strong> The community service record was saved successfully.</div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="alert alert-err"><strong>Heads up:</strong> <?= htmlspecialchars($flashError) ?></div>
  <?php endif; ?>

  <section class="card hero-card">
    <div class="hero-media">
      <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Student photo" class="portrait">
    </div>
    <div class="hero-body">
      <p class="hero-overline">Student ID <?= htmlspecialchars($student['student_id']) ?></p>
      <h1 class="hero-title"><?= htmlspecialchars($student['student_name']) ?></h1>

      <div class="hero-tags">
        <?php if ($yearSection): ?>
          <span class="chip">
            <span class="chip-label">Year &amp; Section</span>
            <span class="chip-value"><?= htmlspecialchars($yearSection) ?></span>
          </span>
        <?php endif; ?>
        <?php if (!empty($student['course'])): ?>
          <span class="chip">
            <span class="chip-label">Course</span>
            <span class="chip-value"><?= htmlspecialchars($student['course']) ?></span>
          </span>
        <?php endif; ?>
        <?php if (!empty($student['institute'])): ?>
          <span class="chip">
            <span class="chip-label">Institute</span>
            <span class="chip-value"><?= htmlspecialchars($student['institute']) ?></span>
          </span>
        <?php endif; ?>
      </div>

      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-label">Required Hours</span>
          <span class="hero-stat-value">
            <?= htmlspecialchars(number_format($requiredHours, 2)) ?>
            <small>hrs</small>
          </span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-label">Logged Hours</span>
          <span class="hero-stat-value ok">
            <?= htmlspecialchars(number_format($totalLogged, 2)) ?>
            <small>hrs</small>
          </span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-label">Remaining</span>
          <span class="hero-stat-value <?= $remainingHours > 0 ? 'warn' : 'ok' ?>">
            <?= htmlspecialchars(number_format($remainingHours, 2)) ?>
            <small>hrs</small>
          </span>
        </div>
      </div>
    </div>
  </section>

  <div class="content-grid">
    <section class="card slim form-card">
      <h2 style="margin:0 0 4px;">Log Community Service</h2>
      <p class="history-subtext" style="margin-bottom:12px;">Capture service hours, remarks, and supporting photos for this student.</p>
      <form method="post" action="" enctype="multipart/form-data" id="entryForm">
        <input type="hidden" name="save_entry" value="1">
        <input type="hidden" name="violation_id" value="<?= $violation_get !== null ? (int)$violation_get : '' ?>">

        <div class="form-row">
          <div class="field">
            <label for="hours">Hours</label>
            <input type="number" id="hours" name="hours" step="0.5" min="0.5" placeholder="e.g., 2 or 2.5" required>
          </div>
          <div class="field">
            <label for="service_date">Service Date</label>
            <input type="date" id="service_date" name="service_date" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="field">
          <label for="remarks">Remarks (short)</label>
          <input type="text" id="remarks" name="remarks" maxlength="255" placeholder="e.g., Park clean-up, Day 1">
        </div>

        <div class="field">
          <label for="comment">Comment (details)</label>
          <textarea id="comment" name="comment" placeholder="Highlight the tasks completed, supervisors, or any notable observations..."></textarea>
        </div>

        <div class="field">
          <label for="evidence">Photo Evidence</label>
          <input type="file" id="evidence" name="evidence[]" accept="image/*" multiple>
          <div class="help">Attach up to 5 images - JPG, PNG, WEBP, or GIF, max 6 MB each.</div>
          <div class="preview-grid" id="preview"></div>
        </div>

        <div class="button-group">
          <button type="submit" class="btn primary">Save Entry</button>
          <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn secondary">Cancel</a>
        </div>
      </form>
    </section>

    <section class="card slim history-card">
      <header>
        <h2>Service History</h2>
        <p class="history-subtext">Latest entries appear first. Click an image to open the full photo.</p>
      </header>

      <?php if (empty($entries)): ?>
        <div class="empty-state">No community-service entries logged for this student yet. Use the form to add the first record.</div>
      <?php else: ?>
        <div class="entries">
          <?php foreach ($entries as $e):
            $photos = [];
            if (!empty($e['photo_paths'])) {
              $decoded = json_decode($e['photo_paths'], true);
              if (is_array($decoded)) $photos = $decoded;
            }
            $serviceDate = $e['service_date'] ?? $e['created_at'];
          ?>
          <article class="entry">
            <div class="entry-header">
              <span><?= htmlspecialchars(date('M d, Y', strtotime($serviceDate))) ?></span>
              <span>Logged at <?= htmlspecialchars(date('h:i A', strtotime($e['created_at']))) ?></span>
              <?php if (!empty($e['violation_id'])): ?>
                <span class="badge">Violation #<?= (int)$e['violation_id'] ?></span>
              <?php endif; ?>
              <span class="entry-hours"><?= htmlspecialchars(number_format((float)$e['hours'], 2)) ?> hrs</span>
            </div>
            <div class="entry-body">
              <?php if (!empty($e['remarks'])): ?>
                <p><strong>Remarks:</strong> <?= htmlspecialchars($e['remarks']) ?></p>
              <?php endif; ?>
              <?php if (!empty($e['comment'])): ?>
                <p class="muted"><?= nl2br(htmlspecialchars($e['comment'])) ?></p>
              <?php endif; ?>
            </div>
            <?php if (!empty($photos)): ?>
              <div class="entry-photos">
                <?php foreach ($photos as $p): ?>
                  <a href="<?= htmlspecialchars($p) ?>" target="_blank" title="Open image">
                    <img src="<?= htmlspecialchars($p) ?>" alt="Evidence">
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>
