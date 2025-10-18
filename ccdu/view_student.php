<?php
include '../includes/header.php';
require '../config.php';
require __DIR__.'/_scanner.php';
require 'violation_hrs.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!empty($_SESSION['sms_alert']) && is_array($_SESSION['sms_alert'])) {
    $smsFlash = $_SESSION['sms_alert'];
    unset($_SESSION['sms_alert']);
    echo '<script>window.__sms_alert_from_server = ' . json_encode($smsFlash, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) . ';</script>';
}

/* ---------------- DB ---------------- */
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* ---------------- Resolve student ---------------- */
$origStudent = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$kParam      = isset($_GET['k']) ? trim($_GET['k']) : '';

$ID_PATTERN    = '/^\d{4}-\d{4}$/';
$KEY64_PATTERN = '/^[a-f0-9]{64}$/i';
$HEX_FLEX      = '/^[a-f0-9]{10,64}$/i';

$student_id = $origStudent;

/* By qr key (k=) */
if ($student_id === '' && $kParam !== '' && preg_match($KEY64_PATTERN, $kParam)) {
    $stmtK = $conn->prepare('SELECT student_id FROM student_qr_keys WHERE qr_key = ? LIMIT 1');
    $stmtK->bind_param('s', $kParam);
    $stmtK->execute();
    $rowK = $stmtK->get_result()->fetch_assoc();
    $stmtK->close();
    if (!empty($rowK['student_id'])) { $student_id = $rowK['student_id']; }
}

/* Legacy hex in student_id */
if ($student_id !== '' && !preg_match($ID_PATTERN, $student_id) && preg_match($HEX_FLEX, $student_id)) {
    $legacyKey = $student_id;
    $stmtL = $conn->prepare('SELECT student_id FROM student_qr_keys WHERE qr_key = ? LIMIT 1');
    $stmtL->bind_param('s', $legacyKey);
    $stmtL->execute();
    $rowL = $stmtL->get_result()->fetch_assoc();
    $stmtL->close();
    if (!empty($rowL['student_id'])) { $student_id = $rowL['student_id']; }
}

/* Guard */
if ($student_id === '') {
    http_response_code(400);
    die("No student selected.");
}

/* Canonicalize URL when needed */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$canonicalPath = $base . '/view_student.php';
$canonicalQS   = 'student_id=' . rawurlencode($student_id);
$currentPath   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentQS     = (string)($_SERVER['QUERY_STRING'] ?? '');

$triggeredByKey = ($kParam !== '') || ($origStudent !== '' && !preg_match($ID_PATTERN, $origStudent)) || ($origStudent !== '' && $origStudent !== $student_id);
$alreadyCanonical = ($currentPath === $canonicalPath) && ($currentQS === $canonicalQS);

if ($triggeredByKey && !$alreadyCanonical) {
    header('Location: '.$scheme.'://'.$host.$canonicalPath.'?'.$canonicalQS, true, 302);
    $conn->close();
    exit;
}

/* ---------------- Student ---------------- */
$sql = "SELECT * FROM student_account WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

/* Robust student photo */
$photoFile = !empty($student['photo']) ? $student['photo'] : 'placeholder.png';
$photoPath = __DIR__ . '/../admin/uploads/' . $photoFile;
if (!is_file($photoPath)) { $photoFile = 'placeholder.png'; }
$photo = '../admin/uploads/' . $photoFile;

/* Root-relative dir for links in this folder */
$selfDir = rtrim(str_replace('\\','/', dirname($_SERVER['PHP_SELF'])), '/');

/* ---------------- Violations (ALL) ---------------- */
$violationsAll = [];
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
while ($row = $resv->fetch_assoc()) { $violationsAll[] = $row; }
$stmtv->close();

/* ---------------- Compute hours + build CS groups ---------------- */
$modLightCount = 0;
$graveCount    = 0;
foreach ($violationsAll as $vv) {
    $raw = strtolower((string)($vv['offense_category'] ?? ''));
    $isGrave = (preg_match('/\bgrave\b/', $raw) && !preg_match('/\bless\b/', $raw));
    if ($isGrave) { $graveCount++; } else { $modLightCount++; }
}
$hours = intdiv($modLightCount, 3) * 10 + ($graveCount * 20);

$requiredHours  = communityServiceHours($conn, $student_id);
$loggedHours    = communityServiceLogged($conn, $student_id);
$remainingHours = communityServiceRemaining($conn, $student_id); // or $requiredHours - $loggedHours with max(0, …)


/* Map of already-assigned violation ids */
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

/* Eligible pool (non-grave, unassigned) oldest-first for grouping */
$eligible = $violationsAll;
usort($eligible, function($a,$b){
    $ta = strtotime($a['reported_at'] ?? '1970-01-01');
    $tb = strtotime($b['reported_at'] ?? '1970-01-01');
    if ($ta === $tb) return ($a['violation_id'] <=> $b['violation_id']);
    return $ta <=> $tb;
});
$lightModPool = [];
foreach ($eligible as $vv) {
    $vid = (int)$vv['violation_id'];
    if (isset($assignedIds[$vid])) continue;
    $raw = strtolower((string)($vv['offense_category'] ?? ''));
    $isGrave = (preg_match('/\bgrave\b/', $raw) && !preg_match('/\bless\b/', $raw));
    if ($isGrave) continue;
    $lightModPool[] = $vv;
}
$csGroups = [];
for ($i = 0; $i + 2 < count($lightModPool); $i += 3) {
    $csGroups[] = array_slice($lightModPool, $i, 3);
}

/* ---------------- Pagination for Violations ---------------- */
$viCount   = count($violationsAll);
$perPage   = (int)($_GET['vpp'] ?? 9);   // per page
if ($perPage < 1)  $perPage = 9;
if ($perPage > 60) $perPage = 60;

$vPage     = (int)($_GET['vpage'] ?? 1);
if ($vPage < 1) $vPage = 1;

$totalVPages = max(1, (int)ceil($viCount / $perPage));
if ($vPage > $totalVPages) $vPage = $totalVPages;

$offset          = ($vPage - 1) * $perPage;
$violationsPage  = array_slice($violationsAll, $offset, $perPage);

function viol_page_url($p, $pp = null){
    $qs = $_GET;
    $qs['student_id'] = $GLOBALS['student_id'];
    $qs['vpage'] = max(1, (int)$p);
    $qs['vpp']   = $pp ?? (int)($_GET['vpp'] ?? 9);
    return '?' . http_build_query($qs) . '#violationsTitle';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Profile</title>
  <link rel="stylesheet" href="../css/view_student.css?v=2.3" />
  <meta name="theme-color" content="#b91c1c">
</head>
<body>
  <main class="page content-safe">
    <div class="page-shell">
      <div class="page-title-row">
        <h2 class="page-title">Student Profile</h2>
      </div>

      <?php if ($student): ?>
        <section class="profile-layout">
          <article class="card profile-card">
            <div class="profile-card__media">
              <img src="<?= htmlspecialchars($photo) ?>" alt="Profile Image" class="profile-img" loading="lazy">
            </div>
            <div class="profile-card__body">
              <p class="eyebrow">Student ID: <?= htmlspecialchars($student['student_id']) ?></p>
              <h3 class="profile-card__name">
                <?= htmlspecialchars(trim(($student['first_name'] ?? '').' '.($student['middle_name'] ?? '').' '.($student['last_name'] ?? ''))) ?>
              </h3>
              <div class="facts-grid profile-card__meta">
                <p><strong>Course</strong><span><?= htmlspecialchars(($student['course'] ?? '') ?: 'N/A') ?></span></p>
                <p><strong>Year Level</strong><span><?= htmlspecialchars(($student['level'] ?? '') ?: 'N/A') ?></span></p>
                <p><strong>Section</strong><span><?= htmlspecialchars(($student['section'] ?? '') ?: 'N/A') ?></span></p>
                <p><strong>Institute</strong><span><?= htmlspecialchars(($student['institute'] ?? '') ?: 'N/A') ?></span></p>
              </div>
            </div>
          </article>

          <aside class="profile-sidebar">
            <article class="card metric-card">
              <p class="eyebrow">Community Service</p>

              <p class="metric-card__value">
                  <?= number_format((float)$remainingHours, 2) ?>
                  <span><?= ((float)$remainingHours === 1.0) ? 'hour remaining' : 'hours remaining' ?></span>
              </p>
              <p class="metric-card__hint">Keep this student aligned with their outstanding service requirements. 
                Required: <?= number_format((float)$requiredHours, 0) ?> hrs •
                Logged: <?= number_format((float)$loggedHours, 2) ?> hrs
              </p>
               <?php if ($remainingHours <= 0.00001): ?>
    <a class="btn btn-primary btn-block" href="<?= $selfDir ?>/gmrc_customize.php?student_id=<?= urlencode($student_id) ?>" style ="margin-bottom: 10px;">
      Download GMRC Certificate
    </a>
  <?php endif; ?>
              <a class="btn btn-primary btn-block" href="<?= $selfDir ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">Add Violation</a>
            </article>

            <article class="card contact-card">
              <h4>Contact Details</h4>
              <div class="contact-list">
                <div class="contact-list__item"><span class="label">Guardian</span><span class="value"><?= htmlspecialchars(($student['guardian'] ?? '') ?: 'N/A') ?></span></div>
                <div class="contact-list__item"><span class="label">Guardian Mobile</span><span class="value"><?= htmlspecialchars(($student['guardian_mobile'] ?? '') ?: 'N/A') ?></span></div>
                <div class="contact-list__item">
                  <span class="label">Email</span>
                  <?php if (!empty($student['email'])): ?>
                    <a class="value link" href="mailto:<?= htmlspecialchars($student['email']) ?>"><?= htmlspecialchars($student['email']) ?></a>
                  <?php else: ?><span class="value">N/A</span><?php endif; ?>
                </div>
                <div class="contact-list__item">
                  <span class="label">Mobile</span>
                  <?php if (!empty($student['mobile'])): ?>
                    <a class="value link" href="tel:<?= htmlspecialchars($student['mobile']) ?>"><?= htmlspecialchars($student['mobile']) ?></a>
                  <?php else: ?><span class="value">N/A</span><?php endif; ?>
                </div>
              </div>
            </article>
          </aside>
        </section>
      <?php else: ?>
        <section class="card empty-state"><p>Student not found.</p></section>
      <?php endif; ?>

      <?php if (!empty($csGroups)): ?>
        <section class="card cs-sets">
          <div class="section-head section-head--stacked">
            <div><h3>Eligible 3-for-10 Community Service Sets</h3></div>
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
                <a class="btn btn-outline btn-block" href="<?= $setUrl ?>">Assign this 10-hour set</a>
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

        <?php if ($viCount === 0): ?>
          <p class="muted">No Violations Recorded.</p>
        <?php else: ?>
          <div class="cards-grid">
            <?php foreach ($violationsPage as $v):
              $cat  = htmlspecialchars($v['offense_category']);
              $type = htmlspecialchars($v['offense_type']);
              $desc = htmlspecialchars($v['description'] ?? '');
              $date = date('M d, Y h:i A', strtotime($v['reported_at']));

              $photoRel = $selfDir . '/uploads/placeholder.png';
              if (!empty($v['photo'])) {
                $tryAbs = __DIR__ . '/uploads/' . $v['photo'];
                if (is_file($tryAbs)) { $photoRel = $selfDir . '/uploads/' . rawurlencode($v['photo']); }
              }

              $chips = [];
              if (!empty($v['offense_details'])) {
                $decoded = json_decode($v['offense_details'], true);
                if (is_array($decoded)) { foreach ($decoded as $d) { $chips[] = htmlspecialchars($d); } }
              }
              $href = $selfDir . "/violation_view.php?id=" . urlencode($v['violation_id']) . "&student_id=" . urlencode($student_id);
            ?>
              <a class="violation-card" data-violation-link href="<?= $href ?>">
                <div class="violation-card__media">
                  <img src="<?= htmlspecialchars($photoRel) ?>" alt="Evidence for violation #<?= (int)$v['violation_id'] ?>" loading="lazy">
                </div>
                <div class="violation-card__body">
                  <p class="chip-row"><span class="badge badge-<?= strtolower($cat) ?>"><?= ucfirst($cat) ?></span></p>
                  <p class="title"><strong>Type:</strong> <?= $type ?></p>
                  <?php if (!empty($chips)): ?><p><strong>Details:</strong> <?= implode(', ', $chips) ?></p><?php endif; ?>
                  <p class="muted"><strong>Reported:</strong> <span class="nowrap"><?= htmlspecialchars($date) ?></span></p>
                  <?php if ($desc): ?><p class="description"><strong>Description:</strong> <?= $desc ?></p><?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>

          <!-- Pager: info LEFT, Prev/Next RIGHT -->
          <div class="pager-area">
            <div class="pager" role="navigation" aria-label="Violations pagination">
              <div class="pager__info">
                Page <?= $viCount ? $vPage : 1 ?> of <?= $totalVPages ?> • <?= number_format($viCount) ?> total
              </div>
              <div class="pager__actions">
                <?php if ($vPage > 1): ?>
                  <a class="btn btn-outline pager__btn" href="<?= htmlspecialchars(viol_page_url($vPage - 1)) ?>" rel="prev">← Prev</a>
                <?php else: ?>
                  <span class="btn btn-outline pager__btn is-disabled" aria-disabled="true">← Prev</span>
                <?php endif; ?>

                <?php if ($vPage < $totalVPages): ?>
                  <a class="btn btn-outline pager__btn" href="<?= htmlspecialchars(viol_page_url($vPage + 1)) ?>" rel="next">Next →</a>
                <?php else: ?>
                  <span class="btn btn-outline pager__btn is-disabled" aria-disabled="true">Next →</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <!-- Backdrop -->
  <div id="violationBackdrop" class="modal-backdrop hidden" aria-hidden="true"></div>

  <!-- Modal -->
  <div id="violationModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="violationModalTitle">
    <button type="button" class="modal-close" id="violationClose" aria-label="Close">✕</button>
    <div id="violationContent" class="modal-content" style="margin-top:50px; margin-left:200px;"></div>
  </div>

  <script>
    // Modal loader
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

      document.addEventListener('click', function (e) {
        const link = e.target.closest('a[data-violation-link]');
        if (!link) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        e.preventDefault();
        const url = link.href + (link.href.includes('?') ? '&' : '?') + 'modal=1';
        openModalWith(url);
      });

      btnClose.addEventListener('click', closeModal);
      backdrop.addEventListener('click', closeModal);
      document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

      window.addEventListener('popstate', function () {
        if (!backdrop.classList.contains('hidden') || !modal.classList.contains('hidden')) {
          backdrop.classList.add('hidden'); modal.classList.add('hidden'); document.body.classList.remove('modal-open');
        }
      });
    })();

    (function(){
      if (window.__sms_alert_from_server && window.showSmsAlert) {
        const payload = window.__sms_alert_from_server;
        const status = Number(payload.status || 500);
        const message = payload.message || (status === 200 ? 'SMS sent' : 'SMS failed');
        window.showSmsAlert(status === 200 ? 'success' : 'error', (status === 200 ? '✅ ' : '⚠️ ') + message);
        try { delete window.__sms_alert_from_server; } catch(e) {}
      }
    })();
  </script>
</body>
</html>
