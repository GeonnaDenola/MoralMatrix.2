<?php
include '../includes/header.php';
require '../config.php';
require __DIR__.'/_scanner.php';
require 'violation_hrs.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!empty($_SESSION['sms_alert']) && is_array($_SESSION['sms_alert'])) {
    // sanitize values to avoid XSS — json_encode will escape correctly
    $smsFlash = $_SESSION['sms_alert'];
    unset($_SESSION['sms_alert']);
    // export it to JS so the page's showSmsAlert listener can pick it up
    echo '<script>window.__sms_alert_from_server = ' . json_encode($smsFlash, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) . ';</script>';
}

$hours = 0;

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* Accept either student_id (####-####) or k=qr_key, and be tolerant of legacy hex in student_id */
$origStudent = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$kParam      = isset($_GET['k']) ? trim($_GET['k']) : '';

$ID_PATTERN    = '/^\d{4}-\d{4}$/';
$KEY64_PATTERN = '/^[a-f0-9]{64}$/i';
$HEX_FLEX      = '/^[a-f0-9]{10,64}$/i'; // legacy shorter hex from old cards

$student_id = $origStudent;

/* 1) If k= is present and valid, resolve it */
if ($student_id === '' && $kParam !== '' && preg_match($KEY64_PATTERN, $kParam)) {
    $stmtK = $conn->prepare('SELECT student_id FROM student_qr_keys WHERE qr_key = ? LIMIT 1');
    $stmtK->bind_param('s', $kParam);
    $stmtK->execute();
    $rowK = $stmtK->get_result()->fetch_assoc();
    $stmtK->close();
    if (!empty($rowK['student_id'])) {
        $student_id = $rowK['student_id'];
    }
}

/* 2) If student_id looks hex-y (old behavior), try resolving as qr_key too */
if ($student_id !== '' && !preg_match($ID_PATTERN, $student_id) && preg_match($HEX_FLEX, $student_id)) {
    $legacyKey = $student_id;
    $stmtL = $conn->prepare('SELECT student_id FROM student_qr_keys WHERE qr_key = ? LIMIT 1');
    $stmtL->bind_param('s', $legacyKey);
    $stmtL->execute();
    $rowL = $stmtL->get_result()->fetch_assoc();
    $stmtL->close();
    if (!empty($rowL['student_id'])) {
        $student_id = $rowL['student_id'];
    }
}

/* 3) Guard */
if ($student_id === '') {
    http_response_code(400);
    die("No student selected.");
}

$hours = communityServiceHours($conn, $student_id);

/* 4) Canonicalize URL only if current URL is NOT already canonical */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // e.g. /MoralMatrix/ccdu

$canonicalPath = $base . '/view_student.php';
$canonicalQS   = 'student_id=' . rawurlencode($student_id);
$currentPath   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentQS     = (string)($_SERVER['QUERY_STRING'] ?? '');

/* Decide if we need to redirect: we used k=... or hex, OR the qs isn’t exactly student_id=... */
$triggeredByKey = ($kParam !== '') || ($origStudent !== '' && !preg_match($ID_PATTERN, $origStudent)) || ($origStudent !== '' && $origStudent !== $student_id);

/* Are we already at the canonical path+query? */
$alreadyCanonical = ($currentPath === $canonicalPath) && ($currentQS === $canonicalQS);

if ($triggeredByKey && !$alreadyCanonical) {
    header('Location: '.$scheme.'://'.$host.$canonicalPath.'?'.$canonicalQS, true, 302);
    $conn->close();
    exit;
}

/* === FETCH STUDENT === */
$sql = "SELECT * FROM student_account WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

/* ==== FETCH VIOLATIONS ==== */
/* ⬇⬇⬇ CHANGED: include `photo` so we can render card images */
$violations = [];
$sqlv = "SELECT violation_id,
                offense_category,
                offense_type,
                offense_details,
                description,
                reported_at,
                photo
         FROM student_violation
         WHERE student_id = ?
         ORDER BY reported_at DESC, violation_id DESC";
$stmtv = $conn->prepare($sqlv);
$stmtv->bind_param("s", $student_id);
$stmtv->execute();
$resv = $stmtv->get_result();
while ($row = $resv->fetch_assoc()) { $violations[] = $row; }
$stmtv->close();

/* ==== COMPUTE REQUIRED HOURS + FIND ELIGIBLE 3-FOR-10 GROUPS ==== */

/* 1) Recompute required hours here (3 light/moderate/less-grave => 10h; each grave => 20h) */
$modLightCount = 0;
$graveCount    = 0;

foreach ($violations as $vv) {
    $raw = strtolower((string)($vv['offense_category'] ?? ''));
    $isGrave = (preg_match('/\bgrave\b/', $raw) && !preg_match('/\bless\b/', $raw));
    if ($isGrave) { $graveCount++; } else { $modLightCount++; }
}
$hours = intdiv($modLightCount, 3) * 10 + ($graveCount * 20);

/* 2) Build a set of already-assigned violation IDs so we don’t group them again */
$assignedIds = [];
$hasAssignCol = $hasViolCol = false;
if ($res = $conn->query("SHOW COLUMNS FROM validator_student_assignment LIKE 'assignment_id'")) { $hasAssignCol = ($res->num_rows > 0); $res->close(); }
if ($res = $conn->query("SHOW COLUMNS FROM validator_student_assignment LIKE 'violation_id'")) { $hasViolCol   = ($res->num_rows > 0); $res->close(); }

if ($hasAssignCol && $hasViolCol) {
    $sqlA = "SELECT COALESCE(violation_id, assignment_id) AS vid
             FROM validator_student_assignment
             WHERE student_id = ?";
    $stA = $conn->prepare($sqlA);
    $stA->bind_param("s", $student_id);
    $stA->execute();
    $rsA = $stA->get_result();
    while ($row = $rsA->fetch_assoc()) { if (!empty($row['vid'])) $assignedIds[(int)$row['vid']] = true; }
    $stA->close();
} elseif ($hasViolCol) {
    $sqlA = "SELECT violation_id AS vid FROM validator_student_assignment WHERE student_id = ?";
    $stA = $conn->prepare($sqlA);
    $stA->bind_param("s", $student_id);
    $stA->execute();
    $rsA = $stA->get_result();
    while ($row = $rsA->fetch_assoc()) { if (!empty($row['vid'])) $assignedIds[(int)$row['vid']] = true; }
    $stA->close();
} elseif ($hasAssignCol) {
    $sqlA = "SELECT assignment_id AS vid FROM validator_student_assignment WHERE student_id = ?";
    $stA = $conn->prepare($sqlA);
    $stA->bind_param("s", $student_id);
    $stA->execute();
    $rsA = $stA->get_result();
    while ($row = $rsA->fetch_assoc()) { if (!empty($row['vid'])) $assignedIds[(int)$row['vid']] = true; }
    $stA->close();
}

/* 3) Collect *unassigned* light/moderate/less-grave violations (skip grave) in chronological order */
$eligible = $violations;
usort($eligible, function($a,$b){
    $ta = strtotime($a['reported_at'] ?? '1970-01-01');
    $tb = strtotime($b['reported_at'] ?? '1970-01-01');
    if ($ta === $tb) return ($a['violation_id'] <=> $b['violation_id']);
    return $ta <=> $tb; // oldest first
});

$lightModPool = [];
foreach ($eligible as $vv) {
    $vid = (int)$vv['violation_id'];
    if (isset($assignedIds[$vid])) continue; // already in a CS assignment
    $raw = strtolower((string)($vv['offense_category'] ?? ''));
    $isGrave = (preg_match('/\bgrave\b/', $raw) && !preg_match('/\bless\b/', $raw));
    if ($isGrave) continue; // only pool non-grave
    $lightModPool[] = $vv;
}

/* 4) Make as many full groups of 3 as possible */
$csGroups = [];
for ($i = 0; $i + 2 < count($lightModPool); $i += 3) {
    $csGroups[] = array_slice($lightModPool, $i, 3);
}
/* $csGroups is an array of groups; each group has 3 violations: [ [v1,v2,v3], [v4,v5,v6], ... ] */

/* --- Robust photo path (fallback if file is missing) --- */
$photoFile = !empty($student['photo']) ? $student['photo'] : 'placeholder.png';
$photoPath = __DIR__ . '/../admin/uploads/' . $photoFile;

if (!is_file($photoPath)) {
    $photoFile = 'placeholder.png'; // fallback if file missing
}

$photo = '../admin/uploads/' . $photoFile;

/* Build a root-absolute directory path for this folder (e.g., /MoralMatrix/ccdu) */
$selfDir = rtrim(str_replace('\\','/', dirname($_SERVER['PHP_SELF'])), '/');

/* Now it’s safe to include files that output HTML */

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Profile</title>
  <link rel="stylesheet" href="../css/view_student.css" />
  <!-- Theme color matches red header tone -->
  <meta name="theme-color" content="#b91c1c">
</head>
<body>

  <!-- Your global header/nav stays as-is above this file -->

  <main class="page content-safe">
    <div class="page-shell">
      <div class="page-title-row">
        <h2 class="page-title">Student Profile</h2>
      </div>

      <?php if ($student): ?>
        <section class="profile-layout">
          <article class="card profile-card">
            <div class="profile-card__media">
              <img src="<?= htmlspecialchars($photo) ?>"
                   alt="Profile Image"
                   class="profile-img"
                   loading="lazy">
            </div>

            <div class="profile-card__body">
              <p class="eyebrow">Student ID: <?= htmlspecialchars($student['student_id']) ?></p>

              <h3 class="profile-card__name">
                <?= htmlspecialchars(trim($student['first_name'].' '.$student['middle_name'].' '.$student['last_name'])) ?>
              </h3>

              <div class="facts-grid profile-card__meta">
                <p>
                  <strong>Course</strong>
                  <span><?= htmlspecialchars(($student['course'] ?? '') ?: 'N/A') ?></span>
                </p>
                <p>
                  <strong>Year Level</strong>
                  <span><?= htmlspecialchars(($student['level'] ?? '') ?: 'N/A') ?></span>
                </p>
                <p>
                  <strong>Section</strong>
                  <span><?= htmlspecialchars(($student['section'] ?? '') ?: 'N/A') ?></span>
                </p>
                <p>
                  <strong>Institute</strong>
                  <span><?= htmlspecialchars(($student['institute'] ?? '') ?: 'N/A') ?></span>
                </p>
              </div>
            </div>
          </article>

          <aside class="profile-sidebar">
            <article class="card metric-card">
              <p class="eyebrow">Community Service</p>
              <p class="metric-card__value">
                <?= htmlspecialchars((string)$hours) ?>
                <span><?= $hours === 1 ? 'hour logged' : 'hours logged' ?></span>
              </p>
              <p class="metric-card__hint">Keep this student aligned with their outstanding service requirements.</p>
              <a class="btn btn-primary btn-block"
                 href="<?= $selfDir ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">
                Add Violation
              </a>
            </article>

            <article class="card contact-card">
              <h4>Contact Details</h4>
              <div class="contact-list">
                <div class="contact-list__item">
                  <span class="label">Guardian</span>
                  <span class="value"><?= htmlspecialchars(($student['guardian'] ?? '') ?: 'N/A') ?></span>
                </div>
                <div class="contact-list__item">
                  <span class="label">Guardian Mobile</span>
                  <span class="value"><?= htmlspecialchars(($student['guardian_mobile'] ?? '') ?: 'N/A') ?></span>
                </div>
                <div class="contact-list__item">
                  <span class="label">Email</span>
                  <?php if (!empty($student['email'])): ?>
                    <a class="value link" href="mailto:<?= htmlspecialchars($student['email']) ?>">
                      <?= htmlspecialchars($student['email']) ?>
                    </a>
                  <?php else: ?>
                    <span class="value">N/A</span>
                  <?php endif; ?>
                </div>
                <div class="contact-list__item">
                  <span class="label">Mobile</span>
                  <?php if (!empty($student['mobile'])): ?>
                    <a class="value link" href="tel:<?= htmlspecialchars($student['mobile']) ?>">
                      <?= htmlspecialchars($student['mobile']) ?>
                    </a>
                  <?php else: ?>
                    <span class="value">N/A</span>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          </aside>
        </section>
      <?php else: ?>
        <section class="card empty-state">
          <p>Student not found.</p>
        </section>
      <?php endif; ?>

      <?php if (!empty($csGroups)): ?>
        <section class="card cs-sets">
          <div class="section-head section-head--stacked">
            <div>
              <h3>Eligible 3-for-10 Community Service Sets</h3>
            </div>
            <p class="muted">These are unassigned light/moderate/less-grave violations, grouped in 3s (10 hours per set).</p>
          </div>

          <div class="sets-grid">
            <?php foreach ($csGroups as $group):
              $ids = array_map(fn($x) => (int)$x['violation_id'], $group);
              $csv = implode(',', $ids);
              $setUrl = $selfDir . "/set_community_service.php?student_id=" . urlencode($student_id)
                      . "&group=" . urlencode($csv)
                      . "&return=" . urlencode($scheme.'://'.$host.$currentPath.'?'.$canonicalQS);
            ?>
              <article class="set-card">
                <header class="set-card__header">
                  <span class="set-card__title">Group</span>
                  <span class="badge badge-neutral">10 hours</span>
                </header>

                <ul class="set-card__list">
                  <?php foreach ($group as $g): ?>
                    <?php
                      $category   = htmlspecialchars(ucwords(strtolower((string)$g['offense_category'])));
                      $offense    = htmlspecialchars($g['offense_type'] ?: 'No type recorded');
                      $reportedAt = htmlspecialchars(date('M d, Y', strtotime($g['reported_at'])));
                    ?>
                    <li>
                      <span class="badge badge-<?= strtolower((string)$g['offense_category']) ?>"><?= $category ?></span>
                      <span class="set-card__offense"><?= $offense ?></span>
                      <span class="set-card__date"><?= $reportedAt ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>

                <a class="btn btn-outline btn-block" href="<?= $setUrl ?>">
                  Assign this 10-hour set
                </a>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <!-- Violations -->
      <section aria-labelledby="violationsTitle" class="card">
        <div class="section-head">
          <h3 id="violationsTitle">Violation History</h3>
        </div>

        <?php if (empty($violations)): ?>
          <p class="muted">No Violations Recorded.</p>
        <?php else: ?>
          <div class="cards-grid">
            <?php foreach ($violations as $v):
              $cat  = htmlspecialchars($v['offense_category']);
              $type = htmlspecialchars($v['offense_type']);
              $desc = htmlspecialchars($v['description'] ?? '');
              $date = date('M d, Y h:i A', strtotime($v['reported_at']));

              /* CHANGED: build a reliable image path for this violation */
              $photoRel = $selfDir . '/uploads/placeholder.png';
              if (!empty($v['photo'])) {
                $tryAbs = __DIR__ . '/uploads/' . $v['photo'];
                if (is_file($tryAbs)) {
                  $photoRel = $selfDir . '/uploads/' . rawurlencode($v['photo']);
                }
              }

              $chips = [];
              if (!empty($v['offense_details'])) {
                $decoded = json_decode($v['offense_details'], true);
                if (is_array($decoded)) {
                  foreach ($decoded as $d) { $chips[] = htmlspecialchars($d); }
                }
              }
              $href = $selfDir . "/violation_view.php?id=" . urlencode($v['violation_id']) . "&student_id=" . urlencode($student_id);
            ?>
              <a class="violation-card" data-violation-link href="<?= $href ?>">
                <div class="violation-card__media">
                  <!-- CHANGED: show actual image (or placeholder) directly -->
                  <img
                    src="<?= htmlspecialchars($photoRel) ?>"
                    alt="Evidence for violation #<?= (int)$v['violation_id'] ?>"
                    loading="lazy">
                </div>

                <div class="violation-card__body">
                  <p class="chip-row">
                    <span class="badge badge-<?= strtolower($cat) ?>"><?= ucfirst($cat) ?></span>
                  </p>

                  <p class="title"><strong>Type:</strong> <?= $type ?></p>

                  <?php if (!empty($chips)): ?>
                    <p><strong>Details:</strong> <?= implode(', ', $chips) ?></p>
                  <?php endif; ?>

                  <p class="muted">
                    <strong>Reported:</strong>
                    <span class="nowrap"><?= htmlspecialchars($date) ?></span>
                  </p>

                  <?php if ($desc): ?>
                    <p class="description"><strong>Description:</strong> <?= $desc ?></p>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <!-- Backdrop for the violation modal -->
  <div id="violationBackdrop" class="modal-backdrop hidden" aria-hidden="true"></div>

  <!-- Modal -->
  <div id="violationModal"
       class="modal hidden"
       role="dialog"
       aria-modal="true"
       aria-labelledby="violationModalTitle">
    <button type="button" class="modal-close" id="violationClose" aria-label="Close">✕</button>
    <div id="violationContent" class="modal-content" style ="margin-top: 20px; margin-left:200px;">
      <!-- violation_view.php?modal=1 will be injected here -->
    </div>
  </div>

  <!-- JS: violation modal loader -->
  <script>
    // Ensure modal is hidden at start
    window.addEventListener('DOMContentLoaded', () => {
      document.getElementById('violationBackdrop')?.classList.add('hidden');
      document.getElementById('violationModal')?.classList.add('hidden');
      document.body.classList.remove('modal-open');
    });

    (function () {
      const backdrop = document.getElementById('violationBackdrop');
      const modal    = document.getElementById('violationModal');
      const content  = document.getElementById('violationContent');
      const btnClose = document.getElementById('violationClose');

      function openModalWith(url) {
        fetch(url, { credentials: 'same-origin' })
          .then(r => { if (!r.ok) throw new Error('Failed to load violation'); return r.text(); })
          .then(html => {
            content.innerHTML = html;
            backdrop.classList.remove('hidden');
            modal.classList.remove('hidden');
            document.body.classList.add('modal-open');
            if (!history.state || history.state.modalOpen !== true) {
              history.pushState({ modalOpen: true }, '');
            }
          })
          .catch(err => alert('Unable to load violation: ' + err.message));
      }

      function closeModal() {
        backdrop.classList.add('hidden');
        modal.classList.add('hidden');
        document.body.classList.remove('modal-open');
        if (history.state && history.state.modalOpen === true) history.back();
      }

      // Intercept clicks ONLY on links that opted-in via data-violation-link
      document.addEventListener('click', function (e) {
        const link = e.target.closest('a[data-violation-link]');
        if (!link) return;

        // allow new-tab/middle-click
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

        e.preventDefault();
        const url = link.href + (link.href.includes('?') ? '&' : '?') + 'modal=1';
        openModalWith(url);
      });

      btnClose.addEventListener('click', closeModal);
      backdrop.addEventListener('click', closeModal);
      document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

      // Handle back/forward
      window.addEventListener('popstate', function () {
        if (!backdrop.classList.contains('hidden') || !modal.classList.contains('hidden')) {
          backdrop.classList.add('hidden');
          modal.classList.add('hidden');
          document.body.classList.remove('modal-open');
        }
      });
    })();

  (function(){
  // If server set a flash, show it now
  if (window.__sms_alert_from_server && window.showSmsAlert) {
    const payload = window.__sms_alert_from_server;
    const status = Number(payload.status || 500);
    const message = payload.message || (status === 200 ? 'SMS sent' : 'SMS failed');
    window.showSmsAlert(status === 200 ? 'success' : 'error', (status === 200 ? '✅ ' : '⚠️ ') + message);
    // optional: also clear it to be safe
    try { delete window.__sms_alert_from_server; } catch(e) {}
  }
})();
  </script>
</body>
</html>
