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

// Photo path (file physically in /admin/uploads/)
$photoRel = 'uploads/'.($student['photo'] ?? '');
$photoAbs = __DIR__.'/uploads/'.($student['photo'] ?? '');
$photoSrc = $photoRel;


// QR stream endpoint (regenerates if file is missing, cache-busted with ts)
// QR URL for <img> (cache-busted)
$qrSrc = 'print_qr.php?student_id='.rawurlencode($student['student_id']).'&ts='.time();


// Name line
$fullName = trim(($student['first_name'] ?? '').' '.($student['middle_name'] ?? '').' '.($student['last_name'] ?? ''));

// Subtitle (optional)
$subtitle = ($student['course'] ?? '');
if(!empty($student['level']) || !empty($student['section'])){
  $subtitle .= ($subtitle ? ' • ' : '').(string)($student['level'] ?? '');
  if(!empty($student['section'])) $subtitle .= '-'.(string)$student['section'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>ID • <?= htmlspecialchars($student['student_id']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  /* Physical size: CR80 card 85.6mm × 54mm */
  @page{size: 85.6mm 54mm; margin:0}
  html,body{height:100%; margin:0; font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif; color:#111}
  body{background:#f5f6f8}

  .sheet{
    width:85.6mm; height:54mm; margin:8mm auto;
    display:flex; flex-direction:column; gap:8mm;
  }
  .side{
    width:85.6mm; height:54mm; background:#fff; border:1px solid #e5e7eb; border-radius:6mm;
    box-shadow:0 4px 12px rgba(0,0,0,.1);
    position:relative; overflow:hidden;
    display:flex; align-items:center; justify-content:center;
  }
  .front{
    padding:5mm 7mm; display:flex; gap:5mm; align-items:center; justify-content:flex-start;
  }
  .photo{
    width:22mm; height:28mm; border-radius:2mm; overflow:hidden; background:#e5e7eb; flex:0 0 auto;
    display:flex; align-items:center; justify-content:center;
  }
  .photo img{width:100%; height:100%; object-fit:cover}
  .info{min-width:0}
  .school{font-size:10px; letter-spacing:.05em; text-transform:uppercase; color:#6b7280}
  .name{font-size:14px; font-weight:800; line-height:1.2; margin-top:1mm}
  .idline{font-size:12px; margin-top:1mm}
  .sub{font-size:10px; color:#374151; margin-top:1mm}

  .back{ padding:0 }
  .qrbox{ display:flex; align-items:center; justify-content:center; width:100%; height:100% }
  .qrbox img{ width:36mm; height:36mm }
  .back-footer{
    position:absolute; bottom:3mm; left:0; right:0; text-align:center; font-size:9px; color:#6b7280;
  }

  .actions{margin:10px auto; width:85.6mm; text-align:center}
  .btn{
    display:inline-block; padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; text-decoration:none; color:#111; margin:0 4px;
  }
  .btn:hover{background:#f3f4f6}

  @media print{
    body{background:#fff}
    .sheet{margin:0}
    .actions{display:none!important}
    .side{box-shadow:none; border:none; border-radius:0}
    .front, .back{border-radius:0}
    /* Make each .side on its own page for duplex printing */
    .front{ page-break-after:always }
  }
</style>
</head>
<body>

<div class="sheet">
  <!-- FRONT SIDE -->
<section class="side front" aria-label="ID Front">
  <div class="photo">
    <img id="studentPhoto"
         src="<?= htmlspecialchars($photoSrc) ?>"
         alt="Photo">
  </div>
  <div class="info">
    <div class="school"><?= htmlspecialchars($student['institute'] ?: 'Institute') ?></div>
    <div class="name"><?= htmlspecialchars($fullName) ?></div>
    <div class="idline"><strong>ID:</strong> <?= htmlspecialchars($student['student_id']) ?></div>
    <?php if($subtitle): ?>
      <div class="sub"><?= htmlspecialchars($subtitle) ?></div>
    <?php endif; ?>
  </div>
</section>

  <!-- BACK SIDE -->
<section class="side back" aria-label="ID Back">
  <div class="qrbox">
    <img id="qrImg"
         src="<?= htmlspecialchars($qrSrc) ?>"
         alt="QR code for <?= htmlspecialchars($student['student_id']) ?>">
  </div>
  <div class="back-footer">Scan to open student profile • Flip on <strong>short edge</strong> when printing duplex</div>
</section>

<div class="actions">
  <a class="btn" href="#" onclick="window.print();return false;">Print</a>
  <a class="btn" href="print_qr.php?student_id=<?= urlencode($student['student_id']) ?>&download=1">Download QR</a>
</div>


<script>// Safe photo fallback (no inline onerror leaking into UI)
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
