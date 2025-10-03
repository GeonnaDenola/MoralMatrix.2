<?php
include '../includes/header.php';
require '../config.php';
require __DIR__.'/_scanner.php';
require 'violation_hrs.php';

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
$violations = [];
$sqlv = "SELECT violation_id, offense_category, offense_type, offense_details, description, reported_at
         FROM student_violation
         WHERE student_id = ?
         ORDER BY reported_at DESC, violation_id DESC";
$stmtv = $conn->prepare($sqlv);
$stmtv->bind_param("s", $student_id);
$stmtv->execute();
$resv = $stmtv->get_result();
while ($row = $resv->fetch_assoc()) { $violations[] = $row; }
$stmtv->close();

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

  <main class="page container content-safe">
    <!-- Optional on-page title (NOT a header element) -->
    <h2 class="page-title">Student Profile</h2>

    <?php if ($student): ?>
      <!-- Student profile -->
      <section class="card student-card">
        <div class="student-card__media">
          <img src="<?= htmlspecialchars($photo) ?>"
               alt="Profile Image"
               class="profile-img"
               loading="lazy">
        </div>

        <div class="student-card__body">
          <p class="muted"><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>

          <h3 class="student-name">
            <?= htmlspecialchars(trim($student['first_name'].' '.$student['middle_name'].' '.$student['last_name'])) ?>
          </h3>

          <div class="facts-grid">
            <p><strong>Course:</strong> <?= htmlspecialchars($student['course']) ?></p>
            <p><strong>Year Level:</strong> <?= htmlspecialchars($student['level']) ?></p>
            <p><strong>Section:</strong> <?= htmlspecialchars($student['section']) ?></p>

            <p><strong>Institute:</strong> <?= htmlspecialchars($student['institute']) ?></p>
            <p><strong>Guardian:</strong> <?= htmlspecialchars($student['guardian']) ?></p>
            <p><strong>Guardian Mobile:</strong> <?= htmlspecialchars($student['guardian_mobile']) ?></p>

            <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
            <p><strong>Mobile:</strong> <?= htmlspecialchars($student['mobile']) ?></p>
          </div>
        </div>
      </section>

      <!-- Community service stat -->
      <section class="stat card">
        <div class="stat__value">
          <?= htmlspecialchars((string)$hours) ?>
        </div>
        <div class="stat__label">
         <?= $hours === 1 ? 'hour' : 'hours' ?> Community Service 
        </div>
      </section>
    <?php else: ?>
      <section class="card">
        <p>Student not found.</p>
      </section>
    <?php endif; ?>

    <!-- Actions -->
    <section class="actions">
      <a class="btn btn-primary"
         href="<?= $selfDir ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">
        Add Violation
      </a>
    </section>

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
                <img src="<?= $selfDir ?>/violation_photo.php?id=<?= urlencode($v['violation_id']) ?>"
                     alt="Evidence"
                     loading="lazy"
                     onerror="this.style.display='none'">
              </div>

              <div class="violation-card__body">
                <p class="chip-row">
                  <span class="badge badge-<?= strtolower($cat) ?>"><?= ucfirst($cat) ?></span>
                </p>

                <p class="title"><strong>Type:</strong> <?= $type ?></p>

                <?php if (!empty($chips)): ?>
                  <p><strong>Details:</strong> <?= implode(', ', $chips) ?></p>
                <?php endif; ?>

                <p class="muted"><strong>Reported:</strong> <?= $date ?></p>

                <?php if ($desc): ?>
                  <p class="description"><strong>Description:</strong> <?= $desc ?></p>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
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
    <div id="violationContent" class="modal-content">
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
  </script>
</body>
</html>
