<?php
// /MoralMatrix/ccdu/gmrc_certificate.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/violation_hrs.php';

// Restrict to privileged roles
$role = strtolower($_SESSION['account_type'] ?? '');
$isPrivileged = in_array($role, ['ccdu','administrator','super_admin','faculty','security','validator']);
if (!$isPrivileged) { header("Location: /login.php"); exit; }

/* ====== LOCAL FILE ASSETS (filesystem paths; change names/locations if needed) ====== */
$ASSET_FILES = [
  'seal_left'  => __DIR__ . '/../assets/cert/mcc-seal.png',           // MCC round seal (header left)
  'watermark'  => __DIR__ . '/../assets/cert/mabalacat-city-seal.png',// City seal (center watermark)
  'footer_logo'=> __DIR__ . '/../assets/cert/mcc-footer-logo.png',    // MCC ACCN mark (bottom-right)
];

/* Convert a local file to a data: URI; returns "" if file missing */
function data_uri($fsPath) {
  if (!$fsPath || !is_file($fsPath)) return '';
  $mime = function_exists('mime_content_type') ? mime_content_type($fsPath) : 'image/png';
  $bin  = @file_get_contents($fsPath);
  if ($bin === false) return '';
  return 'data:' . $mime . ';base64,' . base64_encode($bin);
}

/* Header/Footer text (adjust if needed) */
$HDR_TEXT_1 = 'MABALACAT CITY COLLEGE';
$HDR_TEXT_2 = 'CENTER FOR CHARACTER DEVELOPMENT UNIT';

$FOOTER_LEFT  = 'Rizal Street Barangay Dolores, Mabalacat City, Pampanga, Philippines';
$FOOTER_RIGHT = 'info@mcc.edu.ph | MCC.edu.ph';
$FOOTER_BAR_START = '#c04b5a';
$FOOTER_BAR_END   = '#d27480';
/* ================================================================================ */

// DB
$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) { http_response_code(500); die("Connection failed."); }

// Input
$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
if ($student_id === '') { http_response_code(400); die('Missing student_id.'); }

// Student
$sql = "SELECT student_id, first_name, middle_name, last_name FROM student_account WHERE student_id = ?";
$st = $conn->prepare($sql);
$st->bind_param("s", $student_id);
$st->execute();
$student = $st->get_result()->fetch_assoc();
$st->close();
if (!$student) { http_response_code(404); die('Student not found.'); }

// Community service gate
$required  = communityServiceHours($conn, $student_id);
$logged    = communityServiceLogged($conn, $student_id);
$remaining = communityServiceRemaining($conn, $student_id);
$conn->close();

if ($remaining > 0.00001) {
  ?>
  <!doctype html>
  <html lang="en">
  <head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>GMRC Certificate — Not Available</title>
  <style>
    body{font-family:system-ui,Segoe UI,Inter,Arial,sans-serif;background:#fafafa;color:#111;margin:0;padding:24px}
    .box{max-width:720px;margin:40px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px}
    .warn{color:#b91c1c}
    .btn{display:inline-block;margin-top:10px;padding:10px 14px;border:1px solid #e5e7eb;border-radius:10px;text-decoration:none;color:#111;background:#fff}
  </style></head>
  <body>
    <div class="box">
      <h2 class="warn" style="margin:0 0 8px;">Certificate Not Available</h2>
      <p><strong><?= htmlspecialchars(($student['first_name']??'').' '.($student['last_name']??'')) ?></strong> (ID <?= htmlspecialchars($student['student_id']) ?>)
      still has community service remaining.</p>
      <p><b>Required:</b> <?= number_format($required,2) ?> h • <b>Logged:</b> <?= number_format($logged,2) ?> h •
         <b>Remaining:</b> <?= number_format($remaining,2) ?> h</p>
      <a class="btn" href="community_service.php">Back to Community Service</a>
    </div>
  </body>
  </html>
  <?php
  exit;
}

// Helpers
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function ordinal($n){
  $j=$n%10; $k=$n%100;
  if($j==1 && $k!=11) return $n.'st';
  if($j==2 && $k!=12) return $n.'nd';
  if($j==3 && $k!=13) return $n.'rd';
  return $n.'th';
}
function normalizeHon($s){
  $s = trim((string)$s);
  $allowed = ['Ms.','Mr.','Mx.','Ms/Mr.','None',''];
  if (!in_array($s, $allowed, true)) $s = 'Ms/Mr.';
  return ($s === 'None' || $s === '') ? '' : $s;
}

// Vars
$name = trim(implode(' ', array_filter([
  $student['first_name'] ?? '', $student['middle_name'] ?? '', $student['last_name'] ?? '',
])));

$from_semester = trim($_GET['from_semester'] ?? '1st Semester');
$from_ay       = trim($_GET['from_ay'] ?? '____');
$to_semester   = trim($_GET['to_semester'] ?? '2nd Semester');
$to_ay         = trim($_GET['to_ay'] ?? '____');
$requestor     = trim($_GET['requestor_name'] ?? 'NAME');
$purpose       = trim($_GET['purpose'] ?? 'PURPOSE');

$issue_day   = isset($_GET['issue_day']) ? (int)$_GET['issue_day'] : (int)date('j');
$issue_month = trim($_GET['issue_month'] ?? date('F'));
$issue_year  = isset($_GET['issue_year']) ? (int)$_GET['issue_year'] : (int)date('Y');

// Honorifics (with smart fallback for the second instance)
$honStudent = normalizeHon($_GET['hon_student'] ?? 'Ms/Mr.');
$honRequest = normalizeHon($_GET['hon_requestor'] ?? ''); // start empty

// If no requestor honorific was supplied OR requestor is the same person,
// copy the student's honorific so the second “Ms/Mr.” matches.
if ($honRequest === '' && strcasecmp($requestor, $name) === 0) {
    $honRequest = $honStudent;
}

$honStudentPrefix = $honStudent ? h($honStudent) . ' ' : '';
$honRequestPrefix = $honRequest ? h($honRequest) . ' ' : '';


/* Build data URIs once */
$IMG_SEAL   = data_uri($ASSET_FILES['seal_left']);
$IMG_WATER  = data_uri($ASSET_FILES['watermark']);
$IMG_FOOTER = data_uri($ASSET_FILES['footer_logo']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Certificate of Good Moral Character — <?= h($student_id) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
  @page { size: A4 portrait; margin: 20mm 18mm 22mm 18mm; }
  html, body { background:#f3f4f6; }
  body {
    margin:0;
    color:#111;
    font-family: Calibri, "Helvetica Neue", Arial, sans-serif;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }

  .printbar { display:flex; gap:10px; justify-content:flex-end; margin:14px auto 0; max-width:900px; }
  .btn{
    appearance:none;
    border:1px solid #a3131f;
    background:#c51f2c;
    color:#fff;
    border-radius:12px;
    padding:10px 20px;
    font-weight:700;
    letter-spacing:.02em;
    cursor:pointer;
    transition:background .18s ease, transform .18s ease, box-shadow .18s ease;
    box-shadow:0 12px 24px rgba(197,31,44,.25);
  }
  .btn:hover{
    background:#ab1b26;
    transform:translateY(-1px);
    box-shadow:0 16px 28px rgba(197,31,44,.35);
  }
  .btn:focus-visible{
    outline:3px solid rgba(197,31,44,.35);
    outline-offset:3px;
  }

  .page {
    width:210mm;
    min-height:297mm;
    margin:12px auto 32px;
    background:#fff;
    box-shadow:0 12px 40px rgba(0,0,0,.08);
    position:relative;
    overflow:hidden;
  }

  /* HEADER */
  .header {
    display:flex; align-items:center; gap:12mm; padding:14mm 16mm 8mm; position:relative;
    border-bottom:1px solid #d1d5db;
  }
  .hdr-seal { width:32mm; display:flex; align-items:center; justify-content:center; }
  .hdr-seal img { max-width:32mm; height:auto; object-fit:contain; }
  .hdr-text { flex:1; text-align:left; }
  .hdr-line-1 { font-weight:700; font-size:20pt; letter-spacing:.02em; color:#9b1c2a; }
  .hdr-line-2 { margin-top:2mm; font-weight:600; font-size:12pt; letter-spacing:.04em; color:#697279; text-transform:uppercase; }

  /* WATERMARK (center, faint) */
  .watermark {
    position:absolute; left:50%; top:50%; transform:translate(-50%,-8%); opacity:.08; pointer-events:none;
    width:68%; max-width:520px; filter:grayscale(100%);
  }

  /* TITLE & BODY */
  .content { padding: 0 18mm 12mm; position:relative; }
  .title { text-align:center; margin:12mm 0 8mm; font-weight:800; letter-spacing:.06em; font-size:13.5pt; text-transform:uppercase; }
  .body { font-size:12pt; line-height:1.65; position:relative; z-index:1; }
  .body p { margin: 0 0 6mm; text-align: justify; text-justify: inter-word; }

  .u { text-decoration: underline; text-decoration-thickness: .06em; text-underline-offset: 2px; }
  .b { font-weight:700; }

  /* SIGNATORIES */
  .sigwrap { margin-top:18mm; }
  .siglabel { font-size:11pt; margin-bottom:10mm; }
  .signame { font-size:11.5pt; text-decoration:underline; font-weight:700; }
  .sigsub  { font-size:10.5pt; font-style:italic; margin-top:1mm; color:#222; }

  /* FOOTER */
  .footer { position:relative; margin-top:16mm; padding-top:10mm; }
  .footer-bar {
    position:relative; height:12mm; border-radius:2px;
    background: linear-gradient(90deg, <?= $FOOTER_BAR_START ?>, <?= $FOOTER_BAR_END ?>);
    color:#fff; display:flex; align-items:center; justify-content:space-between;
    padding:0 10mm; font-size:10pt; font-weight:600;
  }
  .footer-logo { position:absolute; right:6mm; bottom:14mm; width:62mm; opacity:.95; }
  .footer-logo img { width:100%; height:auto; object-fit:contain; }

  @media print {
    html, body { background: none; }
    .page { box-shadow:none; margin:0; }
    .printbar { display:none; }
  }
</style>
</head>
<body>

<div class="printbar">
  <button class="btn" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="page">
  <!-- WATERMARK -->
  <?php if ($IMG_WATER !== ''): ?>
    <img class="watermark" src="<?= $IMG_WATER ?>" alt="" aria-hidden="true">
  <?php endif; ?>

  <!-- HEADER -->
  <div class="header">
    <div class="hdr-seal">
      <?php if ($IMG_SEAL !== ''): ?>
        <img src="<?= $IMG_SEAL ?>" alt="MCC Seal">
      <?php endif; ?>
    </div>
    <div class="hdr-text">
      <div class="hdr-line-1"><?= h($HDR_TEXT_1) ?></div>
      <div class="hdr-line-2"><?= h($HDR_TEXT_2) ?></div>
    </div>
  </div>

  <div class="content">
    <!-- TITLE -->
    <div class="title">CERTIFICATE OF GOOD MORAL CHARACTER</div>

    <!-- BODY -->
    <div class="body">
      <p>
        This is to certify that <?= $honStudentPrefix ?><span class="b u"><?= h($name) ?></span> is a bona fide student in this Institution from
        <span class="b u"><?= h($from_semester) ?></span> of <span class="b u">Academic Year <?= h($from_ay) ?></span> up to
        <span class="b u"><?= h($to_semester) ?></span> of <span class="b u">Academic Year <?= h($to_ay) ?></span>.
      </p>

      <p>
        This is to further certify that the said student is known to have a <span class="b u">good moral character</span>
        and has not been involved in any untoward incident, nor violated any school regulation prescribed by the
        <span class="b">Mabalacat City College Student Manual</span>.
      </p>

      <p>
        This certification is issued upon the request of <?= $honRequestPrefix ?><span class="b u"><?= h($requestor) ?></span> as a requirement for
        <span class="b u"><?= h($purpose) ?></span>.
      </p>

      <p>
        Issued this <span class="b u"><?= h(ordinal($issue_day)) ?></span> day of
        <span class="b u"><?= h($issue_month) ?></span> <span class="b u"><?= h($issue_year) ?></span> at
        <span class="b">Dolores, Mabalacat City, Pampanga.</span>
      </p>

      <div class="sigwrap">
        <div class="siglabel">Noted by:</div>
        <div class="sig">
          <div class="signame">MR. RYZEN C. ESTARDO, LPT</div>
          <div class="sigsub">Coordinator, Center for Character Development Unit</div>
        </div>

        <div class="siglabel" style="margin-top:14mm">Approved by:</div>
        <div class="sig">
          <div class="signame">MS. FLORIENT G. NON, RN, MSN.</div>
          <div class="sigsub">Director for Student Affairs and Support Services</div>
        </div>
      </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
      <div class="footer-bar">
        <div><?= h($FOOTER_LEFT) ?></div>
        <div><?= h($FOOTER_RIGHT) ?></div>
      </div>
      <?php if ($IMG_FOOTER !== ''): ?>
        <div class="footer-logo"><img src="<?= $IMG_FOOTER ?>" alt="MCC Logo"></div>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
