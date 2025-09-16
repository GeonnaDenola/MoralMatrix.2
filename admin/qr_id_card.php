<?php
// admin/qr_id_card.php — Admin-only printer for student ID front/back

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

$studentId = $_GET['student_id'] ?? '';
$auto      = isset($_GET['auto']) ? (int)$_GET['auto'] : 0; // auto-print? 1=yes, 0=no

if($studentId === ''){
  http_response_code(400);
  echo 'Bad request: missing student_id';
  exit;
}

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if($conn->connect_error){
  http_response_code(500);
  echo 'Database connection failed.';
  exit;
}

$st = $conn->prepare('SELECT student_id, first_name, middle_name, last_name, institute, course, level, section, photo FROM student_account WHERE student_id = ? LIMIT 1');
$st->bind_param('s', $studentId);
$st->execute();
$student = $st->get_result()->fetch_assoc();
$st->close();
$conn->close();

if(!$student){
  http_response_code(404);
  echo 'Student not found.';
  exit;
}

// Paths
$photoRel = 'uploads/'.($student['photo'] ?? '');
$photoSrc = $photoRel;

// QR URL for <img> (cache-busted)
$qrSrc = 'print_qr.php?student_id='.rawurlencode($student['student_id']).'&ts='.time();

// Name line
$fullName = trim(($student['first_name'] ?? '').' '.($student['middle_name'] ?? '').' '.($student['last_name'] ?? ''));

// Subtitle (course • level-section)
$subtitle = trim($student['course'] ?? '');
if(!empty($student['level']) || !empty($student['section'])){
  $subtitle .= ($subtitle ? ' • ' : '').(string)($student['level'] ?? '');
  if(!empty($student['section'])) $subtitle .= '-'.(string)$student['section'];
}

// School/institute & logo (optional local asset)
$institute = $student['institute'] ?: 'Your School Name';
$logoSrc   = 'assets/school-logo.svg'; // put your logo here (relative to /admin/)
$ayStart   = (int)date('Y');
$ayEnd     = $ayStart + 1;

// simple acronym for watermark / fallback crest
$acronym = '';
if(preg_match_all('/\b([A-Za-z])/u', $institute, $m)){
  $acronym = strtoupper(implode('', $m[1]));
  if(strlen($acronym) > 4) $acronym = substr($acronym,0,4);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>ID • <?= htmlspecialchars($student['student_id']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* ---------- Theme tokens ---------- */
:root{
  --brand:#0055cc;       /* school primary color */
  --brand-2:#20c997;     /* secondary accent */
  --ink:#0b132b;
  --muted:#51607a;
  --line:#e6e9ef;
  --paper:#ffffff;
  --r:4mm;
  --preview-width: 700px; /* screen preview width (px) */
  --qr-mm: 32;            /* physical QR size in millimeters */
}

html, body { box-sizing:border-box; }
*, *::before, *::after { box-sizing:inherit; }

/* CR80: 85.6mm × 54mm */
@page{ size:85.6mm 54mm; margin:0 }

body{
  margin:0;
  background:#f5f6f8;
  color:var(--ink);
  font-family:system-ui, Segoe UI, Roboto, Arial, sans-serif;
}

/* --------- Screen preview: pixel size, print stays mm --------- */
.sheet{
  width: var(--preview-width);
  margin: 28px auto;
  display:flex; flex-direction:column; gap:28px;
}
.side{
  width:100%;
  height: calc(var(--preview-width) * 54 / 85.6); /* keep the card ratio on screen */
  background:var(--paper);
  border:1px solid var(--line);
  border-radius: var(--r);
  box-shadow:0 6px 18px rgba(0,0,0,.10);
  overflow:hidden;
  position:relative;
  -webkit-print-color-adjust:exact; print-color-adjust:exact;
}

/* ---------- FRONT ---------- */
.front{
  display:flex; flex-direction:column; height:100%;
  overflow:hidden; /* ensures header/security strip respect rounded corners */
}

/* Fixed/clean blue header bar */
.topbar{
  display:flex; align-items:center; gap:10px;
  padding:8px 12px;

  /* red gradient + solid fallback */
  background-color:#ef4444; /* red-500 */
  background-image: linear-gradient(
    90deg,
    #ef4444 0%,
    #dc2626 100%
  );
  color:#fff;

  /* match card rounding on top */
  border-top-left-radius: var(--r);
  border-top-right-radius: var(--r);

  /* subtle inner highlight/separator */
  box-shadow:
    inset 0 -1px 0 rgba(255,255,255,.35),
    0 1px 0 rgba(0,0,0,.03);
}

.crest{
  width:28px; height:28px; border-radius:6px; background:rgba(255,255,255,.2); display:grid; place-items:center; overflow:hidden;
  flex:0 0 auto;
}
.crest img{ width:100%; height:100%; object-fit:contain; }
.crest-fallback{
  font-weight:800; font-size:13px; letter-spacing:.06em; color:#fff;
}
.schoolname{
  display:flex; flex-direction:column; line-height:1.1; min-width:0;
}
.schoolname .title{ font-weight:800; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.schoolname .sub{ font-size:11px; opacity:.9 }
.badge{
  margin-left:auto; font-size:11px; font-weight:700; padding:6px 8px; border-radius:999px;
  background:rgba(255,255,255,.18);
  border:1px solid rgba(255,255,255,.35);
  backdrop-filter:saturate(120%);
}

.body{
  display:grid; grid-template-columns: 0.42fr 1fr; gap:12px; padding:12px 14px 0 14px; flex:1 1 auto;
  background:
    linear-gradient(135deg, rgba(0,0,0,.02) 0%, rgba(0,0,0,0) 60%),
    repeating-linear-gradient(135deg, rgba(0,0,0,.02) 0 6px, rgba(0,0,0,0) 6px 12px);
}

.photo{
  align-self:start; width:100%; aspect-ratio: 22 / 28; border-radius:6px; overflow:hidden; background:#e5e7eb;
  border:2px solid color-mix(in oklab, var(--brand) 25%, white);
  box-shadow:inset 0 0 0 6px #fff;
}
.photo img{ width:100%; height:100%; object-fit:cover; image-rendering:-webkit-optimize-contrast; }

.info{ min-width:0; display:flex; flex-direction:column; gap:6px; }
.name{ font-size:18px; font-weight:900; line-height:1.15; }
.idline{ font-size:13px; }
.idline strong{
  color: color-mix(in oklab, var(--brand) 88%, white);
}
.subline{ font-size:12px; color:#273247; }

/* security strip (holo-ish) on far right */
.front::after{
  content:"STUDENT • <?= htmlspecialchars($acronym ?: 'ID') ?> • <?= htmlspecialchars($acronym ?: 'ID') ?> • ";
  position:absolute; top:1px; right:0; width:14px; height:100%;
  writing-mode:vertical-rl; text-orientation:upright; letter-spacing:.12em;
  font-size:9px; font-weight:700; color:rgba(0,0,0,.18);
  background:linear-gradient(180deg, rgba(255,255,255,.9), rgba(255,255,255,0));
  text-align:center; padding-top:8px;
}

.belt{
  display:flex; align-items:center; gap:10px; padding:8px 12px; margin-top:auto;
  background:linear-gradient(90deg, color-mix(in oklab, var(--brand) 10%, white), color-mix(in oklab, var(--brand-2) 12%, white));
  border-top:1px solid var(--line);
}
.role{
  font-weight:800; font-size:12px; letter-spacing:.12em; text-transform:uppercase;
  color: color-mix(in oklab, var(--brand) 80%, black);
}
.ay{ margin-left:auto; font-size:11px; color:#2b3a55; }

/* ---------- BACK ---------- */
.back{ display:flex; flex-direction:column; height:100%; }
.qrzone{
  position:relative; display:flex; align-items:center; justify-content:center; flex:1 1 auto;
  padding:0;
  background:
    radial-gradient( circle at 50% 20%, rgba(0,0,0,.02), rgba(0,0,0,0) 56%),
    repeating-linear-gradient(0deg, rgba(0,0,0,.03) 0 10px, rgba(0,0,0,0) 10px 20px);
}

/* QR stack sits above hints, with generous vertical space */
.qrwrap{
  position:relative; z-index:2;
  display:flex; flex-direction:column; align-items:center;
  gap: 2mm;
  padding: 9mm 0 10mm;
}
.qrframe{
  background:#fff;
  border-radius: 2mm;
  border: 0.35mm dashed color-mix(in oklab, var(--brand) 30%, white);
  padding: 2mm;
  box-shadow: 0 0 0 0.6mm #fff inset;
  overflow: hidden;
}
#qrImg{
  display:block;
  image-rendering: -webkit-optimize-contrast;
  image-rendering: crisp-edges;
}
.serial{ font-size:12px; color:var(--muted); }

.qrhint{
  position:absolute; top:8px; left:0; right:0; text-align:center; font-size:11px; color:var(--muted); z-index:1;
}

.footer{
  display:grid; grid-template-columns:1fr 1fr; gap:10px; padding:10px 12px 12px; border-top:1px solid var(--line); background:#fff;
}
.sig{
  border-top:1px solid #cfd6e3; padding-top:6px; text-align:center; font-size:11px; color:#2b3a55; min-height:28px;
}
.fine{
  grid-column:1 / -1; text-align:center; font-size:11px; color:#57637a;
}
.ice{
  grid-column:1 / -1; text-align:center; font-size:11px; color:#57637a;
}

/* back footer microtext */
.backnote{
  text-align:center; font-size:10px; color:#6b7280; padding-bottom:6px;
}

/* ---------- Print overrides (exact mm) ---------- */
@media print{
  body{ background:#fff }
  .sheet{ width:auto; margin:0; gap:0 }
  .side{
    width:85.6mm; height:54mm; box-shadow:none; border:none; border-radius:0;
  }
  .front{ page-break-after:always }

  /* tighten spacing + text sizes on paper */
  .body{ padding: 4mm 5mm 0 5mm; gap: 4mm; }
  .name{ font-size: 12.5pt; line-height: 1.15; }
  .idline{ font-size: 10.5pt; }
  .subline{ font-size: 10pt; }

  /* QR true physical size + safe padding */
  #qrImg{
    width:  calc(var(--qr-mm) * 1mm);
    height: calc(var(--qr-mm) * 1mm);
    image-rendering: auto;
  }
  .qrframe{ padding: 2mm; }
  .qrwrap{  padding: 7mm 0 8mm; }

  /* faint trim hairline (optional) */
  .side{ outline: 0.2mm solid #d7dbe3; outline-offset: 0; }

  .actions{ display:none!important }
}

/* --- Screen preview: compute QR pixel size from preview width --- */
@media screen {
  /* 1mm on screen = var(--preview-width)/85.6 px */
  #qrImg{
    width:  calc(var(--preview-width) * var(--qr-mm) / 85.6);
    height: calc(var(--preview-width) * var(--qr-mm) / 85.6);
  }
  .qrframe{ padding: calc(var(--preview-width) * 2 / 85.6); }
  .qrwrap{  padding: calc(var(--preview-width) * 9 / 85.6) 0 calc(var(--preview-width) * 10 / 85.6); }
}

/* Buttons (screen only) */
.actions{ margin:10px auto; width:var(--preview-width); text-align:center }
.btn{
  display:inline-block; padding:10px 14px; border:1px solid var(--line); border-radius:10px; background:#fff; color:var(--ink);
  text-decoration:none; margin:0 6px; box-shadow:0 1px 2px rgba(0,0,0,.06);
}
.btn:hover{ background:#f3f4f6 }
</style>
</head>
<body>

<div class="sheet">
  <!-- FRONT -->
  <section class="side front" aria-label="ID Front">
    <div class="topbar">
      <div class="crest">
        <img id="logo" src="<?= htmlspecialchars($logoSrc) ?>" alt="School logo" />
        <div id="crestFallback" class="crest-fallback" style="display:none"><?= htmlspecialchars($acronym ?: 'SCHL') ?></div>
      </div>
      <div class="schoolname">
        <div class="title"><?= htmlspecialchars($institute) ?></div>
        <div class="sub">Registrar’s Office</div>
      </div>
      <div class="badge">Student ID</div>
    </div>

    <div class="body">
      <div class="photo">
        <img id="studentPhoto" src="<?= htmlspecialchars($photoSrc) ?>" alt="Photo">
      </div>
      <div class="info">
        <div class="name"><?= htmlspecialchars($fullName) ?></div>
        <div class="idline"><strong>ID:</strong> <?= htmlspecialchars($student['student_id']) ?></div>
        <?php if($subtitle): ?>
          <div class="subline"><?= htmlspecialchars($subtitle) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="belt">
      <div class="role">Student</div>
      <div class="ay">Valid: AY <?= $ayStart ?>–<?= $ayEnd ?></div>
    </div>
  </section>

  <!-- BACK -->
  <section class="side back" aria-label="ID Back">
    <div class="qrzone">
      <div class="qrhint">Scan to open student profile</div>
      <div class="qrwrap">
        <div class="qrframe">
          <img id="qrImg" src="<?= htmlspecialchars($qrSrc) ?>" alt="QR code for <?= htmlspecialchars($student['student_id']) ?>">
        </div>
        <div class="serial">ID <?= htmlspecialchars($student['student_id']) ?></div>
      </div>
    </div>

    <div class="footer">
      <div class="sig">Student Signature</div>
      <div class="sig">Registrar</div>
      <div class="fine">Property of <?= htmlspecialchars($institute) ?>. If found, return to Registrar’s Office.</div>
      <div class="ice">ICE (In Case of Emergency): ______________________</div>
    </div>

    <div class="backnote">Flip on <strong>short edge</strong> when printing duplex</div>
  </section>

  <div class="actions">
    <a class="btn" href="#" onclick="window.print();return false;">Print</a>
    <a class="btn" href="print_qr.php?student_id=<?= urlencode($student['student_id']) ?>&download=1">Download QR</a>
  </div>
</div>

<script>
  // logo fallback to acronym tile (if asset missing)
  (function(){
    const logo = document.getElementById('logo');
    const fb = document.getElementById('crestFallback');
    logo.addEventListener('error', () => { logo.style.display='none'; fb.style.display='grid'; });
  })();

  // Safe photo fallback
  (function(){
    const img = document.getElementById('studentPhoto');
    img.addEventListener('error', () => {
      img.style.display = 'none';
      const box = img.closest('.photo');
      if(box) box.style.background = '#d1d5db';
    });
  })();

  <?php if($auto): ?>window.addEventListener('load',()=>window.print());<?php endif; ?>
</script>
</body>
</html>
