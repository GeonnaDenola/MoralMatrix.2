<?php
// /MoralMatrix/ccdu/community_service_view.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/../config.php';

/* ---------- DB ---------- */
$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* ---------- Inputs ---------- */
$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$returnIn   = $_GET['return'] ?? 'community_service.php';
$returnUrl  = (is_string($returnIn) && $returnIn !== '' && strpos($returnIn, '://') === false)
  ? $returnIn : 'community_service.php';
$isModal    = (isset($_GET['modal']) && $_GET['modal'] == '1');

if ($student_id === '') { http_response_code(400); die('Missing student_id.'); }

/* ---------- Fetch student ---------- */
$studentSql = "
  SELECT
    student_id,
    CONCAT_WS(' ', first_name, middle_name, last_name) AS student_name,
    course, level, section, institute, photo
  FROM student_account
  WHERE student_id = ?
";
$st = $conn->prepare($studentSql);
$st->bind_param("s", $student_id);
$st->execute();
$student = $st->get_result()->fetch_assoc();
$st->close();

if (!$student) { http_response_code(404); die('Student not found.'); }

$yearSection = trim(
  ($student['level'] ?? '') .
  ((!empty($student['level']) && !empty($student['section'])) ? '-' : '') .
  ($student['section'] ?? '')
);

/* ---------- Student profile image (from admin/uploads) ---------- */
$profileFile = !empty($student['photo']) ? $student['photo'] : 'placeholder.png';
$profileFs   = __DIR__ . '/../admin/uploads/' . $profileFile;
$profileUrl  = '../admin/uploads/' . $profileFile;
if (!is_file($profileFs)) {
  $profileUrl = '../admin/uploads/placeholder.png';
}

/* ---------- Hours: required vs logged vs remaining ---------- */
/* Rule: GRAVE (not "less grave") = 20h; every set of 3 others = 10h */
$hasStatusCol = false;
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'status'")) {
  $hasStatusCol = ($res->num_rows > 0);
  $res->close();
}

$sqlCats = "SELECT offense_category FROM student_violation WHERE student_id = ? ";
if ($hasStatusCol) {
  $sqlCats .= "AND LOWER(status) NOT IN ('void','voided','canceled','cancelled') ";
}
$sqlCats .= "ORDER BY reported_at ASC, violation_id ASC";

$catRows = [];
$qc = $conn->prepare($sqlCats);
$qc->bind_param("s", $student_id);
$qc->execute();
$rs = $qc->get_result();
while ($row = $rs->fetch_assoc()) {
  $catRows[] = strtolower(trim((string)$row['offense_category']));
}
$qc->close();

$graveCount = 0; $modLightCount = 0;
foreach ($catRows as $raw) {
  $isGrave = (preg_match('/\bgrave\b/i', $raw) && !preg_match('/\bless\b/i', $raw));
  if ($isGrave) $graveCount++; else $modLightCount++;
}
$requiredHours = (float)(($graveCount * 20) + (intdiv($modLightCount, 3) * 10));

/* ---------- Logged hours from entries ---------- */
$totalLogged = 0.0;
$hasCsEntriesTable = false;
if ($res = $conn->query("SHOW TABLES LIKE 'community_service_entries'")) {
  $hasCsEntriesTable = ($res->num_rows > 0);
  $res->close();
}
if ($hasCsEntriesTable) {
  $sum = $conn->prepare("SELECT COALESCE(SUM(hours),0) AS total FROM community_service_entries WHERE student_id = ?");
  $sum->bind_param("s", $student_id);
  $sum->execute();
  $sum->bind_result($sumHours);
  $sum->fetch();
  $sum->close();
  $totalLogged = (float)$sumHours;
}
$remainingHours = max(0, $requiredHours - $totalLogged);

/* ---------- Fetch entries (with validator name) ---------- */
$entries = [];
if ($hasCsEntriesTable) {
  $esql = "
    SELECT e.entry_id, e.hours, e.remarks, e.comment, e.photo_paths, e.service_date, e.created_at,
           e.violation_id, e.validator_id, v.v_username
    FROM community_service_entries e
    LEFT JOIN validator_account v ON v.validator_id = e.validator_id
    WHERE e.student_id = ?
    ORDER BY e.service_date DESC, e.created_at DESC, e.entry_id DESC
  ";
  $se = $conn->prepare($esql);
  $se->bind_param("s", $student_id);
  $se->execute();
  $res = $se->get_result();
  while ($row = $res->fetch_assoc()) $entries[] = $row;
  $se->close();
}

/* ---------- Helper: evidence URL -> ../validators/uploads/... ---------- */
function _starts_with($haystack, $needle) {
  return strncmp($haystack, $needle, strlen($needle)) === 0;
}
function evidence_url(string $p): string {
  $p = trim($p);
  if ($p === '') return '';
  if (preg_match('~^https?://~i', $p)) return $p;   // absolute already
  $p = ltrim($p, '/');

  // common case: stored as uploads/service/... from validator side
  if (_starts_with($p, 'uploads/')) {
    return '../validator/' . $p; // -> ../validators/uploads/...
  }
  // sometimes stored with a prefixed folder
  if (_starts_with($p, 'validator/uploads/') || _starts_with($p, 'validator/uploads/')) {
    return '../' . $p; // -> ../validator(s)/uploads/...
  }
  // fallback: one level up relative to /ccdu/
  return '../' . $p;
}

/* ---------- Build inner markup (used for both modal and full page) ---------- */
ob_start();
?>
<div class="wrap">
  <?php if (!$isModal): ?>
    <div class="back"><a href="<?= htmlspecialchars($returnUrl) ?>">&larr; Back</a></div>
  <?php else: ?>
    <!-- Minimal, modal-only CSS so sizes look correct in popup -->
    <style>
      :root{ --thumb:72px; --thumb-lg:82px; --portrait:160px; }
      .hero{
        display:grid; grid-template-columns:minmax(140px,var(--portrait)) 1fr; gap:18px;
        background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:18px;
        box-shadow:0 10px 28px rgba(17,24,39,.08);margin-top:14px;
      }
      .portrait{
        width:100%;max-width:var(--portrait);aspect-ratio:1/1;object-fit:cover;
        border-radius:14px;border:1px solid #e5e7eb;background:#fff;
      }
      .card{background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:18px;box-shadow:0 10px 28px rgba(17,24,39,.08);margin-top:18px}
      .entry{border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fafbff;margin-top:10px}
      .entry .head{display:flex;flex-wrap:wrap;gap:10px;align-items:center;font-size:.9rem;color:#6b7280}
      .entry .hours{margin-left:auto;font-weight:700;color:#8c1c13}
      .badge{display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border-radius:999px;border:1px solid #e5e7eb;background:#fff;font-size:.8rem}
      .photos{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
      .photos a{display:inline-block;line-height:0;text-decoration:none}
      .photos img{
        width:var(--thumb);height:var(--thumb);object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;background:#fff;
      }
      /* no hover effects */
      .card:hover,.entry:hover,.back a:hover,.photos img:hover{transform:none!important;box-shadow:inherit!important;opacity:1!important;filter:none!important}
    </style>
  <?php endif; ?>

  <section class="hero">
    <img class="portrait" src="<?= htmlspecialchars($profileUrl) ?>" alt="Student photo">
    <div>
      <h1 style="margin:0 0 4px;"><?= htmlspecialchars($student['student_name']) ?></h1>
      <div class="meta">
        <div class="chip"><b>Student ID</b><div><?= htmlspecialchars($student['student_id']) ?></div></div>
        <?php if ($yearSection): ?>
          <div class="chip"><b>Year &amp; Section</b><div><?= htmlspecialchars($yearSection) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($student['course'])): ?>
          <div class="chip"><b>Course</b><div><?= htmlspecialchars($student['course']) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($student['institute'])): ?>
          <div class="chip"><b>Institute</b><div><?= htmlspecialchars($student['institute']) ?></div></div>
        <?php endif; ?>
      </div>

      <div class="stats">
        <div class="stat"><b>Required (by violations)</b><div class="val"><?= number_format($requiredHours, 2) ?> <small>hrs</small></div></div>
        <div class="stat"><b>Logged (entries)</b><div class="val ok"><?= number_format($totalLogged, 2) ?> <small>hrs</small></div></div>
        <div class="stat"><b>Remaining</b><div class="val <?= $remainingHours > 0 ? 'warn' : 'ok' ?>"><?= number_format($remainingHours, 2) ?> <small>hrs</small></div></div>
      </div>
    </div>
  </section>

  <section class="card">
    <h2 style="margin:0 0 8px;">Validator Updates</h2>
    <p style="margin:0 0 12px; color:#6b7280;">Latest first. Click a photo to view full size.</p>

    <?php if (empty($entries)): ?>
      <div class="entry" style="background:#fff">No community-service entries yet for this student.</div>
    <?php else: ?>
      <?php foreach ($entries as $e): ?>
        <?php
          $photos = [];
          if (!empty($e['photo_paths'])) {
            $decoded = json_decode($e['photo_paths'], true);
            if (is_array($decoded)) $photos = $decoded;
          }
          $serviceDate = $e['service_date'] ?? $e['created_at'];
        ?>
        <article class="entry">
          <div class="head">
            <span><?= htmlspecialchars(date('M d, Y', strtotime($serviceDate))) ?></span>
            <span>Logged <?= htmlspecialchars(date('h:i A', strtotime($e['created_at']))) ?></span>
            <?php if (!empty($e['v_username'])): ?>
              <span class="badge">Validator: <?= htmlspecialchars($e['v_username']) ?></span>
            <?php endif; ?>
            <?php if (!empty($e['violation_id'])): ?>
              <span class="badge">Violation #<?= (int)$e['violation_id'] ?></span>
            <?php endif; ?>
            <span class="hours"><?= number_format((float)$e['hours'], 2) ?> hrs</span>
          </div>
          <div class="body">
            <?php if (!empty($e['remarks'])): ?>
              <div><strong>Remarks:</strong> <?= htmlspecialchars($e['remarks']) ?></div>
            <?php endif; ?>
            <?php if (!empty($e['comment'])): ?>
              <div style="color:#374151;white-space:pre-wrap;"><?= nl2br(htmlspecialchars($e['comment'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($photos)): ?>
              <div class="photos" aria-label="Evidence images">
                <?php foreach ($photos as $p):
                  $u = evidence_url((string)$p);
                  if ($u === '') continue;
                ?>
                  <a href="<?= htmlspecialchars($u) ?>" target="_blank" rel="noopener">
                    <img src="<?= htmlspecialchars($u) ?>" alt="Evidence image" loading="lazy">
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>
<?php
$inner = ob_get_clean();

/* ---------- If modal: echo inner only & exit ---------- */
if ($isModal) {
  echo $inner;
  $conn->close();
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Community Service — <?= htmlspecialchars($student['student_name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --thumb: 72px;
    --thumb-lg: 82px;
    --portrait: 160px;
  }
  body{margin:0;background:#f5f7fb;color:#0f172a;font:15px/1.55 system-ui,Segoe UI,Inter,Arial,sans-serif;}
  .wrap{max-width:1100px;margin:28px auto 80px;padding:0 18px;}
  .back a{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:#8c1c13;color:#fff;text-decoration:none;}
  .hero{
    display:grid;
    grid-template-columns: minmax(140px, var(--portrait)) 1fr;
    gap:18px;background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:18px;
    box-shadow:0 10px 28px rgba(17,24,39,.08);margin-top:14px;
  }
  .portrait{
    width:100%;
    max-width:var(--portrait);
    aspect-ratio:1/1;
    object-fit:cover;
    border-radius:14px;border:1px solid #e5e7eb;background:#fff
  }
  .meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:10px}
  .chip{border:1px solid #e5e7eb;border-radius:12px;padding:10px;background:#fafbff}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:10px}
  .stat{border:1px solid #e5e7eb;border-radius:12px;padding:14px;background:#fff}
  .stat b{display:block;font-size:.8rem;color:#6b7280;margin-bottom:6px}
  .stat .val{font-size:1.6rem;font-weight:800}
  .val.ok{color:#065f46}
  .val.warn{color:#b91c1c}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:18px;box-shadow:0 10px 28px rgba(17,24,39,.08);margin-top:18px}
  .entry{border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fafbff;margin-top:10px}
  .entry .head{display:flex;flex-wrap:wrap;gap:10px;align-items:center;font-size:.9rem;color:#6b7280}
  .entry .hours{margin-left:auto;font-weight:700;color:#8c1c13}
  .entry .body{margin-top:8px}
  .badge{display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border-radius:999px;border:1px solid #e5e7eb;background:#fff;font-size:.8rem}
  .photos{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
  .photos a{display:inline-block;line-height:0;text-decoration:none}
  .photos img{
    width:var(--thumb);
    height:var(--thumb);
    object-fit:cover;
    border-radius:10px;
    border:1px solid #e5e7eb;
    background:#fff;
  }
  @media (min-width: 900px){
    .photos img{ width:var(--thumb-lg); height:var(--thumb-lg); }
  }
  @media (max-width:900px){.hero{grid-template-columns:1fr}}
  /* keep hover effects off */
  .card:hover,.entry:hover,.back a:hover,.photos img:hover{transform:none!important;box-shadow:inherit!important;opacity:1!important;filter:none!important}
</style>
</head>
<body>
  <?= $inner ?>
</body>
</html>
<?php
$conn->close();
