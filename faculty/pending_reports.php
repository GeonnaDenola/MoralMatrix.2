<?php
// faculty/pending.php — show ONLY my pending violations (faculty)
declare(strict_types=1);

require '../auth.php';
require_role('faculty');

include '../config.php';
include '../includes/faculty_header.php';

// --- DB connect ---
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

// --- current faculty id (same as your dashboard) ---
$faculty_id = $_SESSION['actor_id'] ?? null;
if (!$faculty_id) { die("No faculty id in session. Please login again."); }

// --- ONLY my pending violations ---
// NOTE: match dashboard style: filter by submitted_by only; make status case-insensitive
$sql = "
SELECT sv.violation_id,
       sv.student_id,
       s.photo AS student_photo,
       s.first_name,
       s.last_name,
       sv.offense_category,
       sv.offense_type,
       sv.description,
       sv.reported_at,
       sv.status
FROM student_violation sv
JOIN student_account s ON sv.student_id = s.student_id
WHERE sv.submitted_by = ?
  AND LOWER(sv.status) = 'pending'
ORDER BY sv.reported_at DESC, sv.violation_id DESC
";
$stmt = $conn->prepare($sql) ?: die('Prepare failed: '.$conn->error);
$stmt->bind_param('s', $faculty_id);          // keep 's' to match your dashboard
$stmt->execute() || die('Execute failed: '.$stmt->error);
$result = $stmt->get_result();

// --- Optional: debug snapshot by role/status for THIS user (visit ?debug=1 to see) ---
$debugRows = [];
if (!empty($_GET['debug'])) {
  $d = $conn->prepare("
    SELECT COALESCE(sv.submitted_role,'(null)') AS role,
           LOWER(sv.status) AS status_norm,
           COUNT(*) AS c
    FROM student_violation sv
    WHERE sv.submitted_by = ?
    GROUP BY sv.submitted_role, LOWER(sv.status)
    ORDER BY c DESC
  ");
  $d->bind_param('s', $faculty_id);
  $d->execute();
  $debugRows = $d->get_result()->fetch_all(MYSQLI_ASSOC);
  $d->close();
}
?>


<?php
// ... your auth / db bootstrap above ...

/**
 * Expecting:
 * - $result: mysqli_result of pending violations
 * - $debugRows: optional array for debug view (role/status counts)
 * - $faculty_id: current faculty user id
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Faculty — My Pending Violations</title>

<link rel="stylesheet" href="../css/faculty_reports.css">
</head>
<body>

<main class="pv-page">
  <?php if (!empty($debugRows)): ?>
    <section class="pv-debug" aria-label="Debug data">
      <h4>Debug (per-role/status for your submissions)</h4>
      <div class="pv-debug__meta">actor_id:
        <code><?= htmlspecialchars((string)$faculty_id) ?></code>
      </div>

      <div class="pv-table-wrap">
        <table class="pv-table">
          <thead>
            <tr><th>submitted_role</th><th>status (normalized)</th><th class="t-right">count</th></tr>
          </thead>
          <tbody>
          <?php foreach ($debugRows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['role']) ?></td>
              <td><?= htmlspecialchars($r['status_norm']) ?></td>
              <td class="t-right"><?= (int)$r['c'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <p class="pv-debug__tip">
        Tip: If you see statuses like <em>pending approval</em> or roles not equal to <em>faculty</em>,
        that explains empty results.
      </p>
    </section>
  <?php endif; ?>

  <section class="pv-section" aria-labelledby="pv-title">
    <header class="pv-head">
      <h3 id="pv-title">My Pending Violations</h3>
      <p class="pv-sub">Only items you submitted that are still unresolved.</p>
    </header>

    <?php if ($result && $result->num_rows > 0): ?>
      <ul class="pv-list" role="list">
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
            $first = $row['first_name'] ?? '';
            $last  = $row['last_name'] ?? '';
            $studentName = trim($first . ' ' . $last);
            $studentId   = htmlspecialchars($row['student_id'] ?? '');
            $studentPhotoFile = $row['student_photo'] ?? '';
            $studentPhotoSrc = $studentPhotoFile
              ? '../admin/uploads/' . htmlspecialchars($studentPhotoFile)
              : 'placeholder.png';
            $violationId = (int)($row['violation_id'] ?? 0);
            $category = htmlspecialchars($row['offense_category'] ?? '');
            $type     = htmlspecialchars($row['offense_type'] ?? '');
            $desc     = $row['description'] ?? '';
            $status   = trim((string)($row['status'] ?? ''));
            // slugify status for pill color class
            $statusClass = 'status--' . preg_replace('/[^a-z0-9]+/','-', strtolower($status));
            $reportedAt  = htmlspecialchars($row['reported_at'] ?? '');
            $reportedISO = $row['reported_at'] ? date('c', strtotime($row['reported_at'])) : '';
          ?>
          <li class="pv-item">
            <a class="pv-card-link" href="view_violation_approved.php?id=<?= $violationId ?>">
              <article class="pv-card">
                <div class="pv-card__media">
                  <img
                    src="<?= $studentPhotoSrc ?>"
                    alt="Photo of <?= htmlspecialchars($studentName ?: 'student') ?>"
                    onerror="this.src='placeholder.png'">
                </div>

                <div class="pv-card__body">
                  <div class="pv-row pv-row--between">
                    <h4 class="pv-title">
                      <?= htmlspecialchars($studentName) ?>
                      <?php if ($studentId !== ''): ?>
                        <span class="pv-id">(<?= $studentId ?>)</span>
                      <?php endif; ?>
                    </h4>
                    <?php if ($status !== ''): ?>
                      <span class="pv-pill <?= $statusClass ?>">
                        <?= htmlspecialchars($status) ?>
                      </span>
                    <?php endif; ?>
                  </div>

                  <p class="pv-meta">
                    <span class="pv-chip"><?= $category ?></span>
                    <span class="pv-dot" aria-hidden="true">•</span>
                    <span class="pv-chip"><?= $type ?></span>
                  </p>

                  <?php if (!empty($desc)): ?>
                    <p class="pv-desc"><?= nl2br(htmlspecialchars($desc)) ?></p>
                  <?php endif; ?>

                  <?php if ($reportedAt !== ''): ?>
                    <p class="pv-footer">
                      <strong>Reported:</strong>
                      <time datetime="<?= $reportedISO ?>"><?= $reportedAt ?></time>
                    </p>
                  <?php endif; ?>
                </div>
              </article>
            </a>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php else: ?>
      <div class="pv-empty" role="status" aria-live="polite">
        <h4>No pending violations found</h4>
        <p class="pv-subtle">
          If you expect items here, try <a href="?debug=1">debug view</a> to see actual statuses/roles stored.
        </p>
      </div>
    <?php endif; ?>
  </section>
</main>

<?php
// Close resources
if (isset($stmt) && $stmt instanceof mysqli_stmt) { $stmt->close(); }
if (isset($conn) && $conn instanceof mysqli)     { $conn->close(); }
?>
</body>
</html>
