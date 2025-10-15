<?php
// /MoralMatrix/ccdu/gmrc_customize.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/violation_hrs.php';

include '../includes/header.php';

// Restrict to privileged roles
$role = strtolower($_SESSION['account_type'] ?? '');
$isPrivileged = in_array($role, ['ccdu','administrator','super_admin','faculty','security','validator']);
if (!$isPrivileged) { header("Location: /login.php"); exit; }

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

// Student fetch (optional until they enter an ID)
$student = null;
$required = $logged = $remaining = null;
if ($student_id !== '') {
  $sql = "SELECT student_id, first_name, middle_name, last_name FROM student_account WHERE student_id = ?";
  $st = $conn->prepare($sql);
  $st->bind_param("s", $student_id);
  $st->execute();
  $student = $st->get_result()->fetch_assoc();
  $st->close();

  if ($student) {
    $required  = communityServiceHours($conn, $student_id);
    $logged    = communityServiceLogged($conn, $student_id);
    $remaining = communityServiceRemaining($conn, $student_id);
  }
}
$conn->close();

// Helpers
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function optionTag($value, $selectedValue) {
  $sel = ((string)$value === (string)$selectedValue) ? ' selected' : '';
  return '<option value="'.h($value).'"'.$sel.'>'.h($value).'</option>';
}

// Defaults
$todayMonth = date('F');
$todayDay   = (int)date('j');
$todayYear  = (int)date('Y');

$name = '';
if ($student) {
  $name = trim(implode(' ', array_filter([
    $student['first_name'] ?? '',
    $student['middle_name'] ?? '',
    $student['last_name'] ?? '',
  ])));
}

// AY options (last 12 AYs up to next year)
$ayOptions = [];
$base = (int)date('Y');
for ($y = $base + 1; $y >= $base - 10; $y--) {
  $ayOptions[] = ($y-1).'–'.$y; // e.g., 2024–2025
}

// Semesters & purposes
$semOptions = ['1st Semester', '2nd Semester', 'Summer'];
$purposeList = ['Scholarship','Employment','Transfer','Board Exam','OJT','Graduation Requirement','Government Requirement','Others'];

// Honorifics
$honOptions = ['Ms.','Mr.'];
$honStudent = $_GET['hon_student']   ?? 'Ms/Mr.';

// Preselects
$fromSem = $_GET['from_semester'] ?? '1st Semester';
$toSem   = $_GET['to_semester']   ?? '2nd Semester';
$fromAY  = $_GET['from_ay']       ?? ($ayOptions ? end($ayOptions) : '');
$toAY    = $_GET['to_ay']         ?? ($ayOptions ? reset($ayOptions) : '');
$reqName = $_GET['requestor_name']?? ($name !== '' ? $name : '');
$purpose = $_GET['purpose']       ?? '';

$issueMonth = $_GET['issue_month'] ?? $todayMonth;
$issueDay   = isset($_GET['issue_day']) ? (int)$_GET['issue_day'] : $todayDay;
$issueYear  = isset($_GET['issue_year'])? (int)$_GET['issue_year'] : $todayYear;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Customize Good Moral Certificate (Inline Preview)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{ --border:#e5e7eb; --muted:#6b7280; --text:#111827; }
  html,body{margin:0;padding:0;background:#f8fafc;color:var(--text);font:14px/1.6 system-ui,Segoe UI,Inter,Arial,sans-serif}
  .wrap{max-width:1200px;margin:28px auto;padding:0 16px}
  .card{background:#fff;border:1px solid var(--border);border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.06);padding:20px}
  h1{font-size:1.4rem;margin:0 0 12px}
  .row{display:flex;gap:14px;flex-wrap:wrap}
  .col{flex:1;min-width:260px}
  label{display:block;font-weight:600;margin:10px 0 6px}
  input[type=text], select, input[type=number]{
    width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:#fff
  }
  .muted{color:var(--muted)}
  .small{font-size:.92rem}
  .toolbar{display:flex;gap:10px;justify-content:flex-end;margin-top:16px}
  .btn{appearance:none;border:1px solid var(--border);background:#fff;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer}
  .btn.primary{background:#111;color:#fff;border-color:#111}
  .warn{color:#b91c1c}
  .ok{color:#065f46}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media (max-width:960px){ .grid{grid-template-columns:1fr} }
  iframe#previewFrame{width:100%;min-height:1100px;border:1px solid var(--border);border-radius:12px;background:#fff}
</style>
</head>
<body>
<div class="wrap">

  <div class="card" style="margin-bottom:14px">
    <h1>Customize Good Moral Certificate</h1>
    <p class="muted small">Enter a Student ID, fill details (including honorifics), then click <b>Preview in Page</b>. The certificate renders below.</p>
  </div>

  <!-- Student selector -->
  <div class="card" style="margin-bottom:14px">
    <form method="get" class="row" action="">
      <div class="col">
        <label for="student_id">Student ID</label>
        <input type="text" id="student_id" name="student_id" placeholder="e.g., 2023-0001" value="<?= h($student_id) ?>" required>
      </div>
      <div class="col" style="align-self:flex-end">
        <button class="btn">Load Student</button>
      </div>
    </form>

    <?php if ($student_id !== '' && !$student): ?>
      <p class="warn" style="margin-top:8px">Student not found.</p>
    <?php endif; ?>

    <?php if ($student): ?>
      <p style="margin-top:8px"><strong><?= h($name) ?></strong> (<?= h($student['student_id']) ?>)</p>
      <?php if ($remaining !== null): ?>
        <?php if ($remaining > 0.00001): ?>
          <p class="warn"><b>Community Service:</b> Required <?= number_format($required,2) ?> h • Logged <?= number_format($logged,2) ?> h • Remaining <?= number_format($remaining,2) ?> h — certificate will be blocked until completed (you can still preview the notice below).</p>
        <?php else: ?>
          <p class="ok"><b>Community Service:</b> Cleared (Required <?= number_format($required,2) ?> h • Logged <?= number_format($logged,2) ?> h • Remaining <?= number_format($remaining,2) ?> h)</p>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Customization + inline preview -->
  <div class="card" style="margin-bottom:14px">
    <form id="certForm" method="get" action="gmrc_certificate.php" target="previewFrame">
      <input type="hidden" name="student_id" value="<?= h($student_id) ?>">

      <div class="grid">
        <div>
          <label>Student Honorific</label>
          <select name="hon_student">
            <?php foreach ($honOptions as $opt) echo optionTag($opt, $honStudent); ?>
          </select>
        </div>

        <div>
          <label>From Semester</label>
          <select name="from_semester">
            <?php foreach ($semOptions as $opt) echo optionTag($opt, $fromSem); ?>
          </select>
        </div>
        <div>
          <label>From Academic Year</label>
          <select name="from_ay">
            <?php foreach ($ayOptions as $opt) echo optionTag($opt, $fromAY); ?>
          </select>
        </div>

        <div>
          <label>To Semester</label>
          <select name="to_semester">
            <?php foreach ($semOptions as $opt) echo optionTag($opt, $toSem); ?>
          </select>
        </div>
        <div>
          <label>To Academic Year</label>
          <select name="to_ay">
            <?php foreach ($ayOptions as $opt) echo optionTag($opt, $toAY); ?>
          </select>
        </div>

        <div>
          <label>Requestor Name</label>
          <input type="text" name="requestor_name" id="requestor_name" value="<?= h($reqName) ?>" placeholder="e.g., <?= h($name ?: 'Juan Dela Cruz') ?>">
          <div class="small muted">
            <label><input type="checkbox" id="use_student_name" <?= $name && $reqName === $name ? 'checked' : '' ?>> Use student’s name</label>
          </div>
        </div>

        <div>
          <label>Purpose</label>
          <input list="purpose_list" type="text" name="purpose" value="<?= h($purpose) ?>" placeholder="e.g., Scholarship">
          <datalist id="purpose_list">
            <?php foreach ($purposeList as $p) echo '<option value="'.h($p).'">'; ?>
          </datalist>
        </div>

        <div>
          <label>Issue Month</label>
          <select name="issue_month">
            <?php foreach ([
              'January','February','March','April','May','June','July','August','September','October','November','December'
            ] as $m) echo optionTag($m, $issueMonth); ?>
          </select>
        </div>

        <div>
          <label>Issue Day</label>
          <input type="number" name="issue_day" min="1" max="31" value="<?= (int)$issueDay ?>">
        </div>

        <div>
          <label>Issue Year</label>
          <input type="number" name="issue_year" min="<?= $todayYear-5 ?>" max="<?= $todayYear+5 ?>" value="<?= (int)$issueYear ?>">
        </div>
      </div>

      <div class="toolbar">
        <a class="btn" href="community_service.php">Back</a>
        <button id="btnPreview" class="btn primary" <?= $student ? '' : 'disabled title="Load a student first"' ?>>Preview in Page</button>
        <button id="btnPrint" type="button" class="btn" disabled>Print Certificate</button>
      </div>
    </form>
  </div>

  <div class="card">
    <iframe id="previewFrame" name="previewFrame" title="Certificate Preview"></iframe>
  </div>
</div>

<script>
  (function(){
    const chk = document.getElementById('use_student_name');
    const req = document.getElementById('requestor_name');
    const form = document.getElementById('certForm');
    const btnPreview = document.getElementById('btnPreview');
    const btnPrint = document.getElementById('btnPrint');
    const frame = document.getElementById('previewFrame');
    const studentLoaded = <?= $student ? 'true' : 'false' ?>;

    const studentName = <?= json_encode($name, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
    if (chk && req) {
      chk.addEventListener('change', function(){
        if (this.checked && studentName) req.value = studentName;
      });
    }

    if (!studentLoaded) { btnPreview.disabled = true; }

    btnPreview?.addEventListener('click', function(e){
      e.preventDefault();
      if (!studentLoaded) return;
      form.submit();
    });

    frame.addEventListener('load', function(){
      try {
        const doc = frame.contentDocument || frame.contentWindow.document;
        const h = Math.max(1100, doc.body.scrollHeight + 40);
        frame.style.minHeight = h + 'px';
        btnPrint.disabled = false;
      } catch(e){
        btnPrint.disabled = false;
      }
    });

    btnPrint?.addEventListener('click', function(){
      if (frame && frame.contentWindow) frame.contentWindow.print();
    });
  })();
</script>
</body>
</html>
