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
  $ayOptions[] = ($y - 1).' - '.$y; // e.g., 2024 - 2025
}

// Semesters & purposes
$semOptions = ['1st Semester', '2nd Semester', 'Summer'];
$purposeList = ['Scholarship','Employment','Transfer','Board Exam','OJT','Graduation Requirement','Government Requirement','Others'];

// Honorifics
$honOptions = ['Ms.','Mr.'];
$honStudent = $_GET['hon_student'] ?? 'Ms/Mr.';

// Preselects
$fromSem = $_GET['from_semester'] ?? '1st Semester';
$toSem   = $_GET['to_semester'] ?? '2nd Semester';
$fromAY  = $_GET['from_ay']       ?? ($ayOptions ? end($ayOptions) : '');
$toAY    = $_GET['to_ay']         ?? ($ayOptions ? reset($ayOptions) : '');
$reqName = $_GET['requestor_name']?? ($name !== '' ? $name : '');
$purpose = $_GET['purpose']       ?? '';

$issueMonth = $_GET['issue_month'] ?? $todayMonth;
$issueDay   = isset($_GET['issue_day']) ? (int)$_GET['issue_day'] : $todayDay;
$issueYear  = isset($_GET['issue_year'])? (int)$_GET['issue_year'] : $todayYear;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Customize Good Moral Certificate</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    color-scheme: light;
    --gmrc-bg: #f2f4f8;
    --gmrc-ink: #111827;
    --gmrc-muted: #6b7280;
    --gmrc-card: #ffffff;
    --gmrc-border: #d7dce5;
    --gmrc-primary: #971200;
    --gmrc-primary-hover: #7b0e00;
    --gmrc-dark: #1c1d21;
    --gmrc-dark-hover: #101116;
    --gmrc-radius: 22px;
    --gmrc-shadow: 0 20px 40px #140f172a;
  }
  *{box-sizing:border-box;}
  body{
    margin:0;
    min-height:100vh;
    background:var(--gmrc-bg);
    color:var(--gmrc-ink);
    font:15px/1.55 "Inter","Segoe UI",system-ui,-apple-system,BlinkMacSystemFont,"Helvetica Neue",Arial,sans-serif;
    -webkit-font-smoothing:antialiased;
  }
  a{color:inherit;}
  .gmrc-page{
    position:relative;
    display:flex;
    flex-direction:column;
    gap:40px;
    min-height:100vh;
    margin-left:var(--gmrc-sidebar,264px);
    padding:calc(var(--gmrc-header,64px) + 48px) clamp(28px,6vw,64px) 80px;
    transition:margin-left .25s ease;
  }
  .gmrc-hero{
    background:#f7ebe8;
    border-radius:var(--gmrc-radius);
    padding:36px clamp(28px,5vw,52px);
    box-shadow:var(--gmrc-shadow);
    display:grid;
    gap:20px;
    border:1px solid #f0d0c7;
  }
  .gmrc-hero__eyebrow{
    margin:0;
    font-size:0.78rem;
    letter-spacing:0.32em;
    text-transform:uppercase;
    color:#364152;
    font-weight:600;
  }
  .gmrc-hero__title{
    margin:6px 0 12px;
    font-size:clamp(1.6rem,3vw,2.1rem);
    font-weight:800;
    color:#0f172a;
  }
  .gmrc-hero__text{
    margin:0;
    max-width:62ch;
    color:#2a3444;
    font-size:1rem;
  }
  .gmrc-hero__meta{
    display:flex;
    flex-wrap:wrap;
    gap:16px;
  }
  .gmrc-pill{
    min-width:180px;
    padding:18px 22px;
    border-radius:18px;
    background:#ffffff;
    border:1px solid #f1d7d0;
    display:flex;
    flex-direction:column;
    gap:6px;
    color:#0f172a;
  }
  .gmrc-pill__label{
    font-size:0.72rem;
    text-transform:uppercase;
    letter-spacing:0.2em;
    color:#4a5567;
  }
  .gmrc-pill__value{
    font-weight:700;
    font-size:1.05rem;
  }
  .gmrc-shell{
    display:grid;
    gap:32px;
  }
  .gmrc-card{
    background:var(--gmrc-card);
    border-radius:var(--gmrc-radius);
    border:1px solid #e2e6ef;
    box-shadow:var(--gmrc-shadow);
    padding:32px clamp(26px,5vw,40px);
    display:flex;
    flex-direction:column;
    gap:24px;
  }
  .gmrc-card__head{
    display:flex;
    justify-content:space-between;
    gap:18px;
    align-items:flex-start;
  }
  .gmrc-card__title{
    margin:0;
    font-size:1.35rem;
    font-weight:700;
    color:#0f172a;
    letter-spacing:-0.01em;
  }
  .gmrc-card__text{
    margin:0;
    color:var(--gmrc-muted);
    max-width:60ch;
  }
  .gmrc-step{
    margin:0 0 8px;
    font-size:0.74rem;
    text-transform:uppercase;
    letter-spacing:0.18em;
    color:#49566d;
    font-weight:700;
  }
  .gmrc-chip{
    display:inline-flex;
    align-items:center;
    padding:6px 14px;
    border-radius:999px;
    background:#e2e8f0;
    color:#475569;
    font-size:0.78rem;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:0.08em;
  }
  .gmrc-chip--ready{
    background:#d6f2e1;
    color:#047857;
  }
  .gmrc-inline-form{
    display:flex;
    flex-wrap:wrap;
    gap:16px;
    align-items:flex-end;
  }
  .gmrc-field{
    display:flex;
    flex-direction:column;
    gap:8px;
    flex:1 1 240px;
  }
  .gmrc-field__label{
    font-weight:600;
    color:#2f3a4f;
    letter-spacing:0.02em;
  }
  .gmrc-input,
  .gmrc-select{
    width:100%;
    padding:12px 14px;
    border-radius:12px;
    border:1px solid var(--gmrc-border);
    background:#fff;
    font:inherit;
    color:var(--gmrc-ink);
    transition:border-color .18s ease, box-shadow .18s ease;
  }
  .gmrc-input:focus,
  .gmrc-select:focus{
    border-color:var(--gmrc-primary);
    box-shadow:0 0 0 3px #33971200;
    outline:none;
  }
  .gmrc-btn{
    appearance:none;
    border:1px solid var(--gmrc-border);
    border-radius:14px;
    background:#fff;
    color:var(--gmrc-ink);
    font-weight:600;
    padding:12px 22px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    transition:transform .15s ease, box-shadow .15s ease, background .15s ease;
  }
  .gmrc-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 12px 25px #1f0f172a;
  }
  .gmrc-btn:focus-visible{
    outline:3px solid #73971200;
    outline-offset:2px;
  }
  .gmrc-btn:disabled{
    cursor:not-allowed;
    opacity:0.45;
    box-shadow:none;
    transform:none;
  }
  .gmrc-btn--primary{
    background:var(--gmrc-primary);
    border-color:var(--gmrc-primary);
    color:#fff;
    box-shadow:0 14px 30px #40971200;
  }
  .gmrc-btn--primary:hover{
    background:var(--gmrc-primary-hover);
    border-color:var(--gmrc-primary-hover);
  }
  .gmrc-btn--dark{
    background:var(--gmrc-dark);
    border-color:var(--gmrc-dark);
    color:#fff;
    text-decoration:none;
  }
  .gmrc-btn--dark:hover{
    background:var(--gmrc-dark-hover);
    border-color:var(--gmrc-dark-hover);
  }
  .gmrc-stats{
    display:grid;
    gap:18px;
    grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
  }
  .gmrc-stat{
    border-radius:16px;
    padding:18px;
    border:1px solid #d0d7e3;
    background:#f8f9fb;
    display:flex;
    flex-direction:column;
    gap:8px;
  }
  .gmrc-stat__label{
    font-size:0.76rem;
    text-transform:uppercase;
    letter-spacing:0.12em;
    color:#455166;
  }
  .gmrc-stat__value{
    font-size:1.45rem;
    font-weight:700;
    letter-spacing:-0.01em;
  }
  .gmrc-stat--alert{
    border-color:#f0a2a2;
    background:#ffe9e9;
    color:#b91c1c;
  }
  .gmrc-stat--ok{
    border-color:#a8e1cc;
    background:#e6f8f1;
    color:#047857;
  }
  .gmrc-alert{
    border-radius:16px;
    border:1px solid var(--gmrc-border);
    background:#f6f7fb;
    padding:16px 18px;
    display:flex;
    flex-wrap:wrap;
    gap:8px 12px;
    align-items:flex-start;
    color:var(--gmrc-ink);
  }
  .gmrc-alert strong{font-weight:700;}
  .gmrc-alert--error{
    border-color:#f3a1a1;
    background:#ffe6e6;
    color:#b91c1c;
  }
  .gmrc-alert--warning{
    border-color:#f3d098;
    background:#fdf1d9;
    color:#92400e;
  }
  .gmrc-alert--success{
    border-color:#8edab2;
    background:#e8f9f1;
    color:#047857;
  }
  .gmrc-note{
    margin:0;
    padding:12px 16px;
    border-radius:14px;
    background:#f0f2f6;
    color:#475569;
    font-size:0.9rem;
  }
  .gmrc-form-section{
    border-radius:18px;
    border:1px solid #e6e9f0;
    background:#f3f6fb;
    padding:22px 24px;
    display:flex;
    flex-direction:column;
    gap:18px;
    margin-bottom:20px;
  }
  .gmrc-section-title{
    margin:0;
    font-size:1.05rem;
    font-weight:700;
    color:#0f172a;
  }
  .gmrc-form-grid{
    display:grid;
    gap:18px;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  }
  .gmrc-checkbox{
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-size:0.9rem;
    color:var(--gmrc-muted);
    margin-top:4px;
  }
  .gmrc-checkbox input{width:16px;height:16px;}
  .gmrc-toolbar{
    display:flex;
    flex-wrap:wrap;
    gap:14px;
    justify-content:flex-end;
    margin-top:8px;
  }
  .gmrc-toolbar__spacer{flex:1 1 auto;}
  .gmrc-card--preview{
    position:relative;
  }
  .gmrc-card--preview.is-loading .gmrc-preview__frame{opacity:0.35;}
  .gmrc-card--preview.is-ready .gmrc-empty{display:none;}
  .gmrc-empty{
    border:1px dashed #c9d4e8;
    background:#f2f6ff;
    border-radius:16px;
    padding:18px 20px;
    color:#1f3c73;
    font-weight:600;
  }
  .gmrc-btn--print{
    background:#c51f2c;
    border-color:#b01b26;
    color:#fff;
    box-shadow:0 14px 32px rgba(197,31,44,.25);
  }
  .gmrc-btn--print:hover{
    background:#a91923;
    border-color:#951720;
  }
  .gmrc-btn--print:focus-visible{
    outline:3px solid rgba(197,31,44,.35);
    outline-offset:3px;
  }
  .gmrc-preview__frame{
    display:block;
    width:min(100%, 940px);
    aspect-ratio:210 / 297;
    min-height:1120px;
    margin:20px auto 0;
    border:1px solid #d0d7e3;
    border-radius:20px;
    background:#f8fafc;
    box-shadow:inset 0 0 0 1px #eef2f7, 0 32px 50px rgba(15, 23, 42, 0.12);
    transition:opacity .25s ease;
  }
  .gmrc-card--preview:not(.is-ready) .gmrc-preview__frame{opacity:0;}
  @media (max-width:980px){
    .gmrc-page{
      margin-left:var(--gmrc-sidebar,92px);
      padding:calc(var(--gmrc-header,64px) + 40px) 24px 64px;
    }
  }
  @media (max-width:720px){
    .gmrc-page{
      margin-left:0;
      padding:calc(var(--gmrc-header,56px) + 32px) 18px 52px;
    }
    .gmrc-card{padding:28px 22px;}
    .gmrc-hero{padding:28px 22px;}
  }
  @media (prefers-reduced-motion: reduce){
    *,*::before,*::after{
      transition-duration:0.01ms !important;
      animation-duration:0.01ms !important;
    }
  }
</style>
</head>
<body>
<main class="gmrc-page" id="gmrcPage" data-student-loaded="<?= $student ? 'true' : 'false' ?>">
  <section class="gmrc-hero">
    <div>
      <p class="gmrc-hero__eyebrow">Certificate Builder</p>
      <h1 class="gmrc-hero__title">Customize the Good Moral Certificate</h1>
      <p class="gmrc-hero__text">Load a student record to autofill community service status, tailor the details, and preview the certificate before printing.</p>
    </div>
    <div class="gmrc-hero__meta">
      <?php if ($student): ?>
        <div class="gmrc-pill">
          <span class="gmrc-pill__label">Student</span>
          <span class="gmrc-pill__value"><?= h($name) ?></span>
        </div>
        <div class="gmrc-pill">
          <span class="gmrc-pill__label">Student ID</span>
          <span class="gmrc-pill__value"><?= h($student['student_id']) ?></span>
        </div>
      <?php elseif ($student_id !== ''): ?>
        <div class="gmrc-pill">
          <span class="gmrc-pill__label">Student</span>
          <span class="gmrc-pill__value">No match yet</span>
        </div>
      <?php else: ?>
        <div class="gmrc-pill">
          <span class="gmrc-pill__label">Getting started</span>
          <span class="gmrc-pill__value">Lookup a student to begin</span>
        </div>
      <?php endif; ?>
      <div class="gmrc-pill">
        <span class="gmrc-pill__label">Preview mode</span>
        <span class="gmrc-pill__value"><?= $student ? 'Enabled' : 'Waiting for student' ?></span>
      </div>
    </div>
  </section>

  <div class="gmrc-shell">
    <section class="gmrc-card gmrc-card--lookup">
      <div class="gmrc-card__head">
        <div>
          <p class="gmrc-step">Step 1</p>
          <h2 class="gmrc-card__title">Load student record</h2>
          <p class="gmrc-card__text">Search by student ID to bring in their latest service status and populate the customization options.</p>
        </div>
        <span class="gmrc-chip <?= $student ? 'gmrc-chip--ready' : '' ?>"><?= $student ? 'Student loaded' : 'Required' ?></span>
      </div>

      <form method="get" action="" class="gmrc-inline-form" autocomplete="off">
        <label class="gmrc-field" for="student_id">
          <span class="gmrc-field__label">Student ID</span>
          <input class="gmrc-input" type="text" id="student_id" name="student_id" value="<?= h($student_id) ?>" placeholder="Enter the ID number" pattern="[0-9\-]*" inputmode="numeric" aria-describedby="studentHelp">
        </label>
        <button class="gmrc-btn gmrc-btn--primary" type="submit">Load Student</button>
      </form>

      <p class="gmrc-note" id="studentHelp">Tip: You can jump here from the dashboard or scan an ID to fill this field automatically.</p>

      <?php if ($student_id !== '' && !$student): ?>
        <div class="gmrc-alert gmrc-alert--error" role="alert">
          <strong>No student record found.</strong>
          <span>Double-check the ID or search from the dashboard to verify the information.</span>
        </div>
      <?php endif; ?>

      <?php if ($student): ?>
        <div class="gmrc-stats" role="status" aria-live="polite">
          <div class="gmrc-stat">
            <span class="gmrc-stat__label">Required hours</span>
            <span class="gmrc-stat__value"><?= number_format($required ?? 0, 2) ?></span>
          </div>
          <div class="gmrc-stat">
            <span class="gmrc-stat__label">Logged hours</span>
            <span class="gmrc-stat__value"><?= number_format($logged ?? 0, 2) ?></span>
          </div>
          <div class="gmrc-stat <?= ($remaining !== null && $remaining > 0.00001) ? 'gmrc-stat--alert' : 'gmrc-stat--ok' ?>">
            <span class="gmrc-stat__label">Remaining hours</span>
            <span class="gmrc-stat__value"><?= number_format($remaining ?? 0, 2) ?></span>
          </div>
        </div>

        <?php if ($remaining !== null): ?>
          <?php if ($remaining > 0.00001): ?>
            <div class="gmrc-alert gmrc-alert--warning" role="alert">
              <strong>Community service pending.</strong>
              <span>The certificate preview will include a notice until the remaining <?= number_format($remaining, 2) ?> hours are completed.</span>
            </div>
          <?php else: ?>
            <div class="gmrc-alert gmrc-alert--success" role="status">
              <strong>Community service cleared.</strong>
              <span>All required hours are satisfied. The certificate will reflect the cleared status.</span>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="gmrc-card gmrc-card--form">
      <div class="gmrc-card__head">
        <div>
          <p class="gmrc-step">Step 2</p>
          <h2 class="gmrc-card__title">Customize certificate details</h2>
          <p class="gmrc-card__text">Adjust honorifics, coverage dates, and issuance details. Preview updates instantly without leaving this page.</p>
        </div>
      </div>

      <form id="certForm" method="get" action="gmrc_certificate.php" target="previewFrame">
        <input type="hidden" name="student_id" value="<?= h($student_id) ?>">

        <section class="gmrc-form-section">
          <h3 class="gmrc-section-title">Recipient details</h3>
          <div class="gmrc-form-grid">
            <label class="gmrc-field">
              <span class="gmrc-field__label">Student honorific</span>
              <select class="gmrc-select" name="hon_student">
                <?php foreach ($honOptions as $opt) echo optionTag($opt, $honStudent); ?>
              </select>
            </label>

            <label class="gmrc-field">
              <span class="gmrc-field__label">Requestor name</span>
              <input class="gmrc-input" type="text" name="requestor_name" id="requestor_name" value="<?= h($reqName) ?>" placeholder="e.g., <?= h($name ?: 'Juan Dela Cruz') ?>">
              <label class="gmrc-checkbox">
                <input type="checkbox" id="use_student_name" <?= $name && $reqName === $name ? 'checked' : '' ?>>
                <span>Use student's name</span>
              </label>
            </label>
          </div>
        </section>

        <section class="gmrc-form-section">
          <h3 class="gmrc-section-title">Service coverage</h3>
          <div class="gmrc-form-grid">
            <label class="gmrc-field">
              <span class="gmrc-field__label">From semester</span>
              <select class="gmrc-select" name="from_semester">
                <?php foreach ($semOptions as $opt) echo optionTag($opt, $fromSem); ?>
              </select>
            </label>
            <label class="gmrc-field">
              <span class="gmrc-field__label">From academic year</span>
              <select class="gmrc-select" name="from_ay">
                <?php foreach ($ayOptions as $opt) echo optionTag($opt, $fromAY); ?>
              </select>
            </label>
            <label class="gmrc-field">
              <span class="gmrc-field__label">To semester</span>
              <select class="gmrc-select" name="to_semester">
                <?php foreach ($semOptions as $opt) echo optionTag($opt, $toSem); ?>
              </select>
            </label>
            <label class="gmrc-field">
              <span class="gmrc-field__label">To academic year</span>
              <select class="gmrc-select" name="to_ay">
                <?php foreach ($ayOptions as $opt) echo optionTag($opt, $toAY); ?>
              </select>
            </label>
          </div>
        </section>

        <section class="gmrc-form-section">
          <h3 class="gmrc-section-title">Issuance details</h3>
          <div class="gmrc-form-grid">
            <label class="gmrc-field">
              <span class="gmrc-field__label">Purpose</span>
              <input class="gmrc-input" list="purpose_list" type="text" name="purpose" value="<?= h($purpose) ?>" placeholder="e.g., Scholarship">
              <datalist id="purpose_list">
                <?php foreach ($purposeList as $p) echo '<option value="'.h($p).'">'; ?>
              </datalist>
            </label>
            <label class="gmrc-field">
              <span class="gmrc-field__label">Issue month</span>
              <select class="gmrc-select" name="issue_month">
                <?php foreach ([
                  'January','February','March','April','May','June','July','August','September','October','November','December'
                ] as $m) echo optionTag($m, $issueMonth); ?>
              </select>
            </label>
            <label class="gmrc-field">
              <span class="gmrc-field__label">Issue day</span>
              <input class="gmrc-input" type="number" name="issue_day" min="1" max="31" value="<?= (int)$issueDay ?>">
            </label>
            <label class="gmrc-field">
              <span class="gmrc-field__label">Issue year</span>
              <input class="gmrc-input" type="number" name="issue_year" min="<?= $todayYear-5 ?>" max="<?= $todayYear+5 ?>" value="<?= (int)$issueYear ?>">
            </label>
          </div>
        </section>

        <div class="gmrc-toolbar">
          <a class="gmrc-btn gmrc-btn--dark" href="community_service.php">Back to service records</a>
          <div class="gmrc-toolbar__spacer"></div>
          <button id="btnPreview" type="submit" class="gmrc-btn gmrc-btn--primary" <?= $student ? '' : 'disabled' ?>>Preview in Page</button>
          <button id="btnPrint" type="button" class="gmrc-btn gmrc-btn--print" disabled>Print / Save as PDF</button>
        </div>
      </form>
    </section>

    <section class="gmrc-card gmrc-card--preview" id="gmrcPreviewCard">
      <div class="gmrc-card__head">
        <div>
          <p class="gmrc-step">Step 3</p>
          <h2 class="gmrc-card__title">Instant preview</h2>
          <p class="gmrc-card__text">Your customized certificate appears below. Use the print control to produce the final copy.</p>
        </div>
      </div>

      <div class="gmrc-empty" id="gmrcPreviewHint">
        <?php if ($student): ?>
          Adjust the details above and click "Preview in Page" to refresh the certificate preview here.
        <?php else: ?>
          Load a student record first to enable the certificate preview panel.
        <?php endif; ?>
      </div>

      <iframe id="previewFrame" name="previewFrame" title="Certificate preview" class="gmrc-preview__frame" aria-live="polite" aria-busy="false"></iframe>
    </section>
  </div>
</main>

<script>
  (function(){
    const page = document.getElementById('gmrcPage');
    function syncOffsets(){
      if (!page) return;
      let headerH = 64;
      const header = document.querySelector('.site-header');
      if (header){
        const rect = header.getBoundingClientRect();
        if (rect.height) headerH = Math.round(rect.height);
      }
      let sidebarW = 264;
      const sidebar = document.querySelector('.sidebar');
      if (sidebar){
        const rect = sidebar.getBoundingClientRect();
        if (rect.width > 40) sidebarW = Math.round(rect.width);
      }
      page.style.setProperty('--gmrc-header', headerH + 'px');
      page.style.setProperty('--gmrc-sidebar', sidebarW + 'px');
    }
    syncOffsets();
    window.addEventListener('resize', syncOffsets);

    const form = document.getElementById('certForm');
    const btnPreview = document.getElementById('btnPreview');
    const btnPrint = document.getElementById('btnPrint');
    const previewFrame = document.getElementById('previewFrame');
    const previewCard = document.getElementById('gmrcPreviewCard');
    const previewHint = document.getElementById('gmrcPreviewHint');
    const requestInput = document.getElementById('requestor_name');
    const chkUseStudent = document.getElementById('use_student_name');
    const studentLoaded = page && page.dataset ? page.dataset.studentLoaded === 'true' : false;
    const studentName = <?= json_encode($name, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;

    if (!studentLoaded && btnPreview){
      btnPreview.disabled = true;
    }

    if (chkUseStudent && requestInput){
      chkUseStudent.addEventListener('change', function(){
        if (this.checked && studentName){
          requestInput.value = studentName;
        }
      });
    }

    btnPreview?.addEventListener('click', function(e){
      if (!form) return;
      if (btnPreview.disabled){
        e.preventDefault();
        return;
      }
      e.preventDefault();
      if (previewCard) previewCard.classList.add('is-loading');
      if (previewFrame) previewFrame.setAttribute('aria-busy','true');
      form.submit();
    });

    previewFrame?.addEventListener('load', function(){
      try {
        const doc = previewFrame.contentDocument || previewFrame.contentWindow.document;
        const innerHeight = doc && doc.body ? doc.body.scrollHeight : 0;
        const targetHeight = Math.max(1100, innerHeight + 40);
        previewFrame.style.minHeight = targetHeight + 'px';
      } catch (err) {
        /* no-op fallback */
      }
      if (previewCard) {
        previewCard.classList.add('is-ready');
        previewCard.classList.remove('is-loading');
      }
      if (previewHint) {
        previewHint.style.display = 'none';
      }
      if (btnPrint) {
        btnPrint.disabled = false;
      }
      if (previewFrame) {
        previewFrame.setAttribute('aria-busy','false');
      }
    });

    btnPrint?.addEventListener('click', function(){
      if (previewFrame && previewFrame.contentWindow){
        previewFrame.contentWindow.print();
      }
    });
  })();
</script>
</body>
</html>
