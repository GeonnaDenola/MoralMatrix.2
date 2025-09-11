<?php
include '../config.php';
include '../includes/header.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_GET['student_id'])) {
    die("No student selected.");
}

$student_id = $_GET['student_id'];
$sql = "SELECT * FROM student_account WHERE student_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
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
while ($row = $resv->fetch_assoc()) {
    $violations[] = $row;
}
$stmtv->close();

$conn->close();

/* Build a root-absolute directory path for this folder (e.g., /MoralMatrix/ccdu) */
$selfDir = rtrim(str_replace('\\','/', dirname($_SERVER['PHP_SELF'])), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Profile</title>

  <style>
  /* Make modal/backdrop truly hidden when class="hidden" is present */
  .modal-backdrop.hidden, .modal.hidden { display: none !important; }

  /* Modal layers */
  .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.50);z-index:9998}
  .modal{position:fixed;inset:0;display:grid;place-items:center;z-index:9999}
  .modal-content{max-width:780px;width:min(92vw,780px);max-height:85vh;overflow:auto;background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.25);padding:18px 20px}
  .modal-close{position:fixed;right:18px;top:18px;border:1px solid #e5e7eb;background:#fff;border-radius:8px;padding:4px 8px;cursor:pointer;z-index:10000}
  body.modal-open{overflow:hidden}
</style>

</head>
<body>

<div class="left-container">
  <div id="pageButtons">
    <?php include 'page_buttons.php' ?>
  </div>
</div>

<div class="right-container">
  <?php if($student): ?>
      <div class="profile">
          <img src="<?= !empty($student['photo']) ? '../admin/uploads/'.$student['photo'] : 'placeholder.png' ?>" alt="Profile">
          <p><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
          <h2><?= htmlspecialchars($student['first_name'] . " " . $student['middle_name'] . " " . $student['last_name']) ?></h2>
          <p><strong>Course:</strong> <?= htmlspecialchars($student['course']) ?></p>
          <p><strong>Year Level:</strong> <?= htmlspecialchars($student['level']) ?></p>
          <p><strong>Section:</strong> <?= htmlspecialchars($student['section']) ?></p>
          <p><strong>Institute:</strong> <?= htmlspecialchars($student['institute']) ?></p>
          <p><strong>Guardian:</strong> <?= htmlspecialchars($student['guardian']) ?> (<?= htmlspecialchars($student['guardian_mobile']) ?>)</p>
          <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
          <p><strong>Mobile:</strong> <?= htmlspecialchars($student['mobile']) ?></p>
      </div>
  <?php else: ?>
      <p>Student not found.</p>
  <?php endif; ?>

  <div class="">
    <div class="add-violation-btn">
      <a href="<?= $selfDir ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">
        <button>Add Violation</button>
      </a>
    </div>

    <div class="violationHistory-container" id="violationHistory">
      <?php if (empty($violations)): ?>
        <p>No Violations Recorded.</p>
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
            <a class="profile-card" data-violation-link href="<?= $href ?>">
              <img src="<?= $selfDir ?>/violation_photo.php?id=<?= urlencode($v['violation_id']) ?>" alt="Evidence" onerror="this.style.display='none'">
              <div class="info">
                <p><strong>Category: </strong>
                  <span class="badge badge-<?= $cat ?>"><?= ucfirst($cat) ?></span>
                </p>
                <p><strong>Type:</strong> <?= $type ?></p>
                 <?php if (!empty($chips)): ?>
                  <p><strong>Details:</strong> <?= implode(', ', $chips) ?></p>
                <?php endif; ?>
                <p><strong>Reported:</strong> <?= $date ?></p>
                <?php if ($desc): ?>
                  <p><strong>Description:</strong> <?= $desc ?></p>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Backdrop -->
<div id="violationBackdrop" class="modal-backdrop hidden"></div>

<!-- Modal -->
<div id="violationModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="violationModalTitle">
  <button type="button" class="modal-close" id="violationClose" aria-label="Close">✕</button>
  <div id="violationContent" class="modal-content">
    <!-- violation_view.php?modal=1 will be injected here -->
  </div>
</div>

<script>
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
