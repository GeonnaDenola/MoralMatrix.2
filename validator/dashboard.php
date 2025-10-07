<?php
declare(strict_types=1);

// Start session ASAP, before ANY output.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include '../includes/validator_header.php';

// Enforce auth: require a validator_id
if (empty($_SESSION['validator_id'])) {
    header('Location: ../login.php?msg=Please+sign+in');
    exit;
}

// Read session values safely
$validatorId = (int)$_SESSION['validator_id'];
$vUsername   = $_SESSION['v_username'] ?? 'Validator';

require_once '../config.php';

// DB connection (throw on error; set charset)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);
$conn->set_charset('utf8mb4');

// Query (handle null/empty middle_name cleanly)
$sql = "SELECT 
            s.student_id,
            TRIM(CONCAT_WS(' ', s.first_name, NULLIF(s.middle_name,''), s.last_name)) AS full_name,
            s.course,
            s.level,
            s.section,
            a.starts_at,
            a.ends_at,
            a.notes
        FROM validator_student_assignment a
        INNER JOIN student_account s ON a.student_id = s.student_id
        WHERE a.validator_id = ?
        ORDER BY a.starts_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $validatorId);
$stmt->execute();
$result = $stmt->get_result();
$assignedCount = $result->num_rows;

// Helper to format datetime nicely without breaking if format unknown
function format_dt(?string $value): string {
    if (!$value) return '';
    try {
        $dt = new DateTime($value);
        return $dt->format('M j, Y · g:i A');
    } catch (Exception $e) {
        // Fallback to raw if parsing fails
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Validator Dashboard</title>
  <link rel="stylesheet" href="../css/validator_dashboard.css">
</head>
<body class="validator-dashboard">
  <main class="vd-page" role="main" aria-labelledby="page-title">
    <div class="vd-container">
      <section class="vd-hero">
        <h1 id="page-title">Welcome, <?= htmlspecialchars($vUsername, ENT_QUOTES, 'UTF-8') ?>!</h1>
        <p class="vd-subtitle">Here are your current student assignments.</p>

        <div class="vd-stats">
          <span class="vd-chip">
            Assigned: <strong><?= (int)$assignedCount ?></strong>
          </span>
        </div>
      </section>

      <section class="vd-content">
        <?php if ($assignedCount > 0): ?>
          <div class="card-grid">
            <?php while ($row = $result->fetch_assoc()): ?>
              <a class="card-link" href="student_details.php?student_id=<?= urlencode((string)$row['student_id']) ?>" aria-label="Open details for <?= htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="card">
                  <h3 class="card-title"><?= htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8') ?></h3>

                  <div class="card-meta">
                    <p><span class="label">ID</span><span class="value"><?= htmlspecialchars((string)$row['student_id'], ENT_QUOTES, 'UTF-8') ?></span></p>
                    <p><span class="label">Course</span><span class="value"><?= htmlspecialchars((string)$row['course'], ENT_QUOTES, 'UTF-8') ?></span></p>
                    <p><span class="label">Level &amp; Section</span><span class="value"><?= htmlspecialchars((string)$row['level'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string)$row['section'], ENT_QUOTES, 'UTF-8') ?></span></p>
                    <p><span class="label">Starts</span><span class="value"><?= format_dt($row['starts_at']) ?></span></p>
                    <p>
                      <span class="label">Ends</span>
                      <span class="value">
                        <?php if (!empty($row['ends_at'])): ?>
                          <span class="pill ended"><?= format_dt($row['ends_at']) ?></span>
                        <?php else: ?>
                          <span class="pill ongoing">Ongoing</span>
                        <?php endif; ?>
                      </span>
                    </p>
                  </div>

                  <?php if (!empty($row['notes'])): ?>
                    <p class="card-notes">
                      <span class="notes-label">Notes</span>
                      <span class="notes-text"><?= htmlspecialchars((string)$row['notes'], ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                  <?php endif; ?>
                </div>
              </a>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <div class="empty-state" role="status">
            <div class="empty-emoji" aria-hidden="true">📄</div>
            <h3>No students are currently assigned to you.</h3>
            <p>When assignments are created for you, they’ll appear here automatically.</p>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
<?php
$stmt->close();
$conn->close();
