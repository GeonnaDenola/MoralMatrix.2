<?php
// /MoralMatrix/ccdu/incident_letter.php
// Printable (browser print-to-PDF) incident report letter for GRAVE offenses.

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/../config.php';

// Restrict to CCDU only
$role = strtolower($_SESSION['account_type'] ?? '');
$isCCDU = ($role === 'ccdu');
if (!$isCCDU) {
  http_response_code(403);
  die('Restricted to CCDU users only.');
}

// DB
$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Input: violation id (accept id or violation_id)
$violation_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_GET['violation_id'] ?? 0);
if ($violation_id <= 0) { http_response_code(400); die('Missing violation id.'); }

// Check if student_violation has a status col to exclude void/canceled
$hasStatus = false;
if ($chk = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'status'")) { $hasStatus = ($chk->num_rows > 0); $chk->close(); }

// Fetch violation + student
$sql = "
  SELECT v.violation_id, v.student_id, v.offense_category, v.offense_type, v.offense_details,
         v.description, v.reported_at, v.photo" . ($hasStatus ? ", v.status" : "") . ",
         s.first_name, s.middle_name, s.last_name, s.course, s.level, s.section, s.institute
  FROM student_violation v
  LEFT JOIN student_account s ON s.student_id = v.student_id
  WHERE v.violation_id = ?
";
$st = $conn->prepare($sql);
$st->bind_param("i", $violation_id);
$st->execute();
$vi = $st->get_result()->fetch_assoc();
$st->close();

if (!$vi) { http_response_code(404); die('Violation not found.'); }

// Exclude void/canceled if status exists
if ($hasStatus) {
  $stLower = strtolower((string)($vi['status'] ?? ''));
  if (in_array($stLower, ['void','voided','canceled','cancelled'], true)) {
    http_response_code(403);
    die('This violation is void/canceled.');
  }
}

// Must be GRAVE (and NOT "less grave")
$rawCat   = strtolower(trim((string)($vi['offense_category'] ?? '')));
$isGrave  = (preg_match('/grave/', $rawCat) && !preg_match('/less/', $rawCat));
if (!$isGrave) {
  http_response_code(403);
  die('Incident letter is only for GRAVE offenses.');
}

// Student & letter data
$student_id = $vi['student_id'];
$name = trim(implode(' ', array_filter([$vi['first_name'] ?? '', $vi['middle_name'] ?? '', $vi['last_name'] ?? ''])));
$course   = $vi['course'] ?? '';
$level    = $vi['level'] ?? '';
$section  = $vi['section'] ?? '';
$institute= $vi['institute'] ?? '';
$offType  = $vi['offense_type'] ?: 'N/A';
$desc     = trim((string)($vi['description'] ?? ''));
$repAtRaw = $vi['reported_at'] ?: date('Y-m-d H:i:s');
$repAt    = date('F d, Y \\a\\t h:i A', strtotime($repAtRaw));

// Optional helpers from query params (so you can prefill if you like)
$hearingDate  = isset($_GET['hearing']) ? trim($_GET['hearing']) : '';   // e.g. "March 10, 2025 – 9:00 AM"
$hearingPlace = isset($_GET['venue'])   ? trim($_GET['venue'])   : '';   // e.g. "CCDU Office"
$reportNo     = sprintf('IR-%s-%06d', date('Ymd', strtotime($repAtRaw)), (int)$violation_id);

// Organization header (edit to your school info)
$schoolName  = 'Your School Name';
$schoolAddr  = 'School Address, City';
$schoolPhone = '';

// Back link
$ref = $_SERVER['HTTP_REFERER'] ?? '';
$backUrl = $ref && strpos($ref, '/login.php') === false ? $ref : 'community_service.php';

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Incident Report (Grave Offense) — <?= htmlspecialchars($reportNo) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{ --border:#e5e7eb; --text:#111827; --muted:#6b7280; }
  html,body{margin:0;padding:0;background:#f8fafc;color:var(--text);font:14px/1.6 system-ui,Segoe UI,Inter,Arial,sans-serif}
  .printbar{display:flex;justify-content:flex-end;gap:10px;margin:12px auto 0;max-width:900px}
  .btn{appearance:none;border:1px solid var(--border);background:#fff;border-radius:10px;padding:8px 12px;font-weight:700;text-decoration:none;color:#111;cursor:pointer}
  .sheet{max-width:900px;margin:16px auto 40px;background:#fff;border:1px solid var(--border);border-radius:12px;padding:34px 42px;box-shadow:0 20px 60px rgba(0,0,0,.10)}
  .head{text-align:center}
  .head h1{margin:0;font-size:1.35rem}
  .head .sub{color:var(--muted);font-size:.92rem;margin-top:4px}
  .hr{height:1px;background:var(--border);margin:16px 0 18px}
  .ref{display:flex;justify-content:space-between;gap:10px;font-weight:700}
  .title{ text-align:center; margin:10px 0 14px; font-size:1.2rem; letter-spacing:.02em; text-transform:uppercase; }
  .block{margin:10px 0}
  .label{color:var(--muted)}
  .row{display:flex;gap:14px;flex-wrap:wrap}
  .row p{margin:2px 0}
  .box{border:1px solid var(--border);border-radius:10px;padding:10px 12px;margin:10px 0}
  .siggrid{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:42px}
  .sig{text-align:center}
  .line{border-top:1px solid #111;margin-top:52px;padding-top:6px;font-weight:600}
  @media print {
    body{background:#fff}
    .printbar{display:none}
    .sheet{box-shadow:none;border:none;margin:0;border-radius:0;padding:0}
  }
</style>
</head>
<body>

<div class="printbar">
  <button class="btn" onclick="window.print()">Print / Save as PDF</button>
  <a class="btn" href="<?= htmlspecialchars($backUrl) ?>">Back</a>
</div>

<div class="sheet">
  <div class="head">
    <h1><?= htmlspecialchars($schoolName) ?></h1>
    <div class="sub"><?= htmlspecialchars($schoolAddr) ?><?= $schoolPhone ? ' • '.htmlspecialchars($schoolPhone) : '' ?></div>
  </div>

  <div class="hr"></div>

  <div class="ref">
    <div>Reference No.: <?= htmlspecialchars($reportNo) ?></div>
    <div>Date: <?= htmlspecialchars(date('F d, Y')) ?></div>
  </div>

  <div class="title"><strong>Incident Report — Grave Offense</strong></div>

  <div class="block">
    <div class="row">
      <p><span class="label">Student:</span> <strong><?= htmlspecialchars($name) ?></strong></p>
      <p><span class="label">ID:</span> <strong><?= htmlspecialchars($student_id) ?></strong></p>
    </div>
    <div class="row">
      <p><span class="label">Course:</span> <strong><?= htmlspecialchars($course ?: 'N/A') ?></strong></p>
      <p><span class="label">Level/Section:</span> <strong><?= htmlspecialchars(($level ?: 'N/A') . (($section)?' - '.$section:'')) ?></strong></p>
      <p><span class="label">Institute:</span> <strong><?= htmlspecialchars($institute ?: 'N/A') ?></strong></p>
    </div>
  </div>

  <div class="block">
    <p><strong>Offense Classification:</strong> Grave offense</p>
    <p><strong>Type:</strong> <?= htmlspecialchars($offType) ?></p>
    <p><strong>Reported At:</strong> <?= htmlspecialchars($repAt) ?></p>
  </div>

  <div class="block">
    <p><strong>Incident Summary / Narrative</strong></p>
    <div class="box" style="white-space:pre-wrap;min-height:120px;">
      <?= $desc ? htmlspecialchars($desc) : "[Insert factual narrative of the incident here.]" ?>
    </div>
  </div>

  <div class="block">
    <p><strong>Basis / Provision Breached</strong> <span class="label">(Student Handbook / Code of Conduct)</span></p>
    <div class="box" style="min-height:70px;">[Cite the policy/section/clause violated]</div>
  </div>

  <div class="block">
    <p><strong>Actions Taken / Immediate Measures</strong></p>
    <div class="box" style="min-height:90px;">[E.g., parent notified, temporary measures, safety actions, incident logged, etc.]</div>
  </div>

  <div class="block">
    <p><strong>Conference / Hearing Details</strong></p>
    <div class="box">
      <p><b>Schedule:</b> <?= htmlspecialchars($hearingDate ?: '[Insert date/time]') ?></p>
      <p><b>Venue:</b> <?= htmlspecialchars($hearingPlace ?: '[Insert venue]') ?></p>
      <p><b>Participants:</b> [Student, Parent/Guardian, CCDU, Program Head, others as needed]</p>
    </div>
  </div>

  <div class="block">
    <p><strong>Recommendations</strong></p>
    <div class="box" style="min-height:90px;">[Recommended sanctions/interventions, counseling, monitoring, restitution, etc.]</div>
  </div>

  <div class="siggrid">
    <div class="sig">
      <div class="line">Reporting Officer</div>
      <div class="label">Signature over printed name</div>
    </div>
    <div class="sig">
      <div class="line">CCDU Head / Guidance Counselor</div>
      <div class="label">Signature over printed name</div>
    </div>
  </div>

  <div class="siggrid" style="margin-top:26px">
    <div class="sig">
      <div class="line">Student</div>
      <div class="label">Signature over printed name</div>
    </div>
    <div class="sig">
      <div class="line">Parent / Guardian</div>
      <div class="label">Signature over printed name</div>
    </div>
  </div>

  <p style="margin-top:28px;color:#6b7280;font-size:.9rem">Document generated by the CCDU System.</p>
</div>

</body>
</html>
