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
$sum = $conn->prepare("SELECT COALESCE(SUM(hours),0) AS total FROM community_service_entries WHERE student_id = ?");
$sum->bind_param("s", $student_id);
$sum->execute();
$sum->bind_result($sumHours);
$sum->fetch();
$sum->close();
$totalLogged = (float)$sumHours;

/* Remaining (never negative) */
$remainingHours = max(0, $requiredHours - $totalLogged);


/* ---------- Fetch previous entries ---------- */
$entries = [];
$esql = "SELECT entry_id, hours, remarks, comment, photo_paths, service_date, created_at, violation_id
         FROM community_service_entries
         WHERE student_id = ?
         ORDER BY service_date DESC, created_at DESC, entry_id DESC";
$st = $conn->prepare($esql);
$st->bind_param("s", $student_id);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $entries[] = $row; }
$st->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Details — Community Service</title>
  <style>
    html, body { margin:0; padding:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#111827; }
    * { box-sizing: border-box; }
    a { text-decoration: none; color:#2563eb; }
    .page { max-width: 1100px; margin: 0 auto; padding: 16px; }
    .grid { display:grid; grid-template-columns: 320px 1fr; gap: 20px; align-items: start; }
    .card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
    .muted { color:#6b7280; }
    .profile-img { width:100%; height:auto; border-radius:12px; object-fit:cover; border:1px solid #e5e7eb; }
    .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:.8rem; background:#f3f4f6; }

    .alert { padding:10px 12px; border-radius:8px; margin:12px 0; }
    .alert-ok { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
    .alert-err{ background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }

    label { display:block; font-weight:600; margin:.5rem 0 .25rem; }
    input[type="number"], input[type="text"], input[type="date"], textarea {
      width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px;
    }
    textarea { min-height: 90px; resize: vertical; }
    .help { font-size:.85rem; color:#6b7280; }
    .row { display:flex; gap:10px; align-items:center; }
    .row > * { flex:1; }
    .btn { display:inline-block; padding:10px 14px; border:1px solid #e5e7eb; border-radius:10px; background:#111827; color:#fff; cursor:pointer; }
    .btn.secondary { background:#fff; color:#111827; }
    .thumbs { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
    .thumbs img { width:90px; height:90px; object-fit:cover; border-radius:8px; border:1px solid #e5e7eb; }

    .stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap:10px; margin:14px 0; }
    .stat { border:1px solid #e5e7eb; border-radius:12px; padding:12px; background:#fff; }
    .stat .label { font-size:.85rem; color:#6b7280; }
    .stat .value { font-weight:700; font-size:1.4rem; margin-top:4px; }
    .value.ok { color:#065f46; }
    .value.warn { color:#991b1b; }

    .entries { margin-top: 18px; display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
    .entry { border:1px solid #e5e7eb; border-radius:10px; padding:12px; background:#fff; }
    .entry .meta { font-size:.85rem; color:#6b7280; margin-bottom:6px; }
  </style>
</head>
<body>
  <?php include '../includes/header.php'; ?>

  <main class="page">
    <p><a href="<?= htmlspecialchars($returnUrl) ?>">← Back to Dashboard</a></p>

    <?php if ($saved): ?>
      <div class="alert alert-ok">✅ Entry saved.</div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-err">⚠️ <?= $flashError ?></div>
    <?php endif; ?>

    <div class="grid">
      <!-- Left: Student profile -->
      <section class="card">
        <img class="profile-img" src="<?= htmlspecialchars($photoUrl) ?>" alt="Profile photo">
        <h2 style="margin:10px 0 0;"><?= htmlspecialchars($student['student_name']) ?></h2>
        <p class="muted" style="margin:4px 0 0;"><span class="badge"><?= htmlspecialchars($student['student_id']) ?></span></p>
        <p style="margin:10px 0 0;"><b>Year & Section:</b> <?= htmlspecialchars($yearSection ?: '—') ?></p>
        <p class="muted"><b>Course:</b> <?= htmlspecialchars($student['course'] ?? '—') ?></p>

        <!-- Hours: Required / Logged / Remaining -->
        <div class="stats">
          <div class="stat">
            <div class="label">Required (by violations)</div>
            <div class="value"><?= (int)$requiredHours ?> hrs</div>
          </div>
          <div class="stat">
            <div class="label">Logged (entries)</div>
            <div class="value <?= $totalLogged > 0 ? 'ok' : '' ?>"><?= number_format($totalLogged, 2) ?> hrs</div>
          </div>
          <div class="stat">
            <div class="label">Remaining</div>
            <div class="value <?= $remainingHours > 0 ? 'warn' : 'ok' ?>"><?= number_format($remainingHours, 2) ?> hrs</div>
          </div>
        </div>
      </section>

      <!-- Right: Form -->
      <section class="card">
        <h3 style="margin-top:0;">Log Community Service</h3>
        <form method="post" action="" enctype="multipart/form-data" id="entryForm">
          <input type="hidden" name="save_entry" value="1">
          <input type="hidden" name="violation_id" value="<?= $violation_get !== null ? (int)$violation_get : '' ?>">

          <div class="row">
            <div>
              <label for="hours">Hours<span class="muted"> (e.g., 2 or 2.5)</span></label>
              <input type="number" id="hours" name="hours" step="0.5" min="0.5" required>
            </div>
            <div>
              <label for="service_date">Service Date</label>
              <input type="date" id="service_date" name="service_date" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <label for="remarks">Remarks (short)</label>
          <input type="text" id="remarks" name="remarks" maxlength="255" placeholder="e.g., Park clean-up, Day 1">

          <label for="comment">Comment (details)</label>
          <textarea id="comment" name="comment" placeholder="Add any relevant details..."></textarea>

          <label for="evidence">Photo Evidence (up to 5 images)</label>
          <input type="file" id="evidence" name="evidence[]" accept="image/*" multiple>
          <div class="help">Max 5 files; up to 6MB each. Accepted: JPG, PNG, WEBP, GIF.</div>
          <div class="thumbs" id="preview"></div>

          <div style="margin-top:12px;">
            <button type="submit" class="btn">Save Entry</button>
            <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn secondary">Cancel</a>
          </div>
        </form>

        <?php if (!empty($entries)): ?>
          <h4 style="margin:18px 0 6px;">Previous Entries</h4>
          <div class="entries">
            <?php foreach ($entries as $e):
              $photos = [];
              if (!empty($e['photo_paths'])) {
                $decoded = json_decode($e['photo_paths'], true);
                if (is_array($decoded)) $photos = $decoded;
              }
            ?>
              <div class="entry">
                <div class="meta">
                  <?= htmlspecialchars(date('M d, Y', strtotime($e['service_date'] ?? $e['created_at']))) ?>
                  · logged <?= htmlspecialchars(date('h:i A', strtotime($e['created_at']))) ?>
                  <?php if (!empty($e['violation_id'])): ?>
                    · <span class="badge">Violation #<?= (int)$e['violation_id'] ?></span>
                  <?php endif; ?>
                </div>
                <div><b>Hours:</b> <?= htmlspecialchars(number_format((float)$e['hours'], 2)) ?></div>
                <?php if (!empty($e['remarks'])): ?>
                  <div><b>Remarks:</b> <?= htmlspecialchars($e['remarks']) ?></div>
                <?php endif; ?>
                <?php if (!empty($e['comment'])): ?>
                  <div class="muted"><?= nl2br(htmlspecialchars($e['comment'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($photos)): ?>
                  <div class="thumbs" style="margin-top:8px;">
                    <?php foreach ($photos as $p): ?>
                      <a href="<?= htmlspecialchars($p) ?>" target="_blank" title="Open image">
                        <img src="<?= htmlspecialchars($p) ?>" alt="Evidence">
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <script>
    // Limit to 5 files and preview thumbnails
    const input = document.getElementById('evidence');
    const preview = document.getElementById('preview');
    input?.addEventListener('change', () => {
      const files = Array.from(input.files || []);
      if (files.length > 5) {
        alert('You can upload up to 5 images only.');
        input.value = '';
        preview.innerHTML = '';
        return;
      }
      preview.innerHTML = '';
      files.forEach(file => {
        const url = URL.createObjectURL(file);
        const img = document.createElement('img');
        img.src = url;
        img.alt = 'Preview';
        img.onload = () => URL.revokeObjectURL(url);
        preview.appendChild(img);
      });
    });
  </script>
</body>
</html>
<?php
$conn->close();
