<?php


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include '../includes/validator_header.php';

if (empty($_SESSION['validator_id'])) {
    header('Location: ../login.php?msg=Please+sign+in');
    exit;
}

$validatorId = (int)$_SESSION['validator_id'];
$vUsername   = $_SESSION['v_username'] ?? 'Validator';

require_once '../config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);
$conn->set_charset('utf8mb4');

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

$assignments = [];
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}

$assignedCount  = count($assignments);
$ongoingCount   = 0;
$completedCount = 0;
$upcomingCount  = 0;

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_dt(?string $value): string {
    if (!$value) {
        return '';
    }
    try {
        $dt = new DateTime($value);
        return $dt->format('M j, Y | g:i A');
    } catch (Exception $e) {
        return e($value);
    }
}

function assignment_status(array $row): array {
    $now = new DateTimeImmutable('now');
    try {
        if (!empty($row['ends_at'])) {
            $end = new DateTimeImmutable($row['ends_at']);
            if ($end < $now) {
                return ['Completed', 'ended'];
            }
        }
        if (!empty($row['starts_at'])) {
            $start = new DateTimeImmutable($row['starts_at']);
            if ($start > $now) {
                return ['Upcoming', 'upcoming'];
            }
        }
    } catch (Exception $e) {
        // fall through to ongoing
    }
    return ['Ongoing', 'ongoing'];
}

foreach ($assignments as $assignment) {
    [, $statusSlug] = assignment_status($assignment);
    if ($statusSlug === 'ongoing') {
        $ongoingCount++;
    } elseif ($statusSlug === 'ended') {
        $completedCount++;
    } elseif ($statusSlug === 'upcoming') {
        $upcomingCount++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Validator Dashboard</title>
  <link rel="stylesheet" href="../css/validator_dashboard.css?v=2">
</head>
<body class="validator-dashboard">
  <main class="vd-wrapper" role="main" aria-labelledby="page-title" style = "padding-top: 0;">
    <div class="vd-shell">
      <header class="vd-header">
        <div class="vd-header__text">
          <p class="vd-eyebrow">Validator Dashboard</p>
          <h1 id="page-title">Welcome back, <?= e($vUsername) ?>.</h1>
          <p class="vd-subtitle">Stay on top of your assigned students and monitor their community service progress in one place.</p>
        </div>
        <div class="vd-metrics">
          <article class="vd-metric">
            <span class="vd-metric__label">Assigned</span>
            <span class="vd-metric__value"><?= (int)$assignedCount ?></span>
          </article>
          <article class="vd-metric vd-metric--ongoing">
            <span class="vd-metric__label">Ongoing</span>
            <span class="vd-metric__value"><?= (int)$ongoingCount ?></span>
          </article>
          <article class="vd-metric vd-metric--completed">
            <span class="vd-metric__label">Completed</span>
            <span class="vd-metric__value"><?= (int)$completedCount ?></span>
          </article>
          <article class="vd-metric vd-metric--upcoming">
            <span class="vd-metric__label">Upcoming</span>
            <span class="vd-metric__value"><?= (int)$upcomingCount ?></span>
          </article>
        </div>
      </header>

      <section class="vd-section">
        <?php if ($assignedCount > 0): ?>
          <div class="vd-section__head">
            <div>
              <h2>Current Assignments</h2>
              <p>Click any card to open the student record and add updates or notes.</p>
            </div>
          </div>
          <div class="vd-grid">
            <?php foreach ($assignments as $row): ?>
              <?php [$statusLabel, $statusSlug] = assignment_status($row); ?>
              <a class="vd-card vd-card--<?= e($statusSlug) ?>" href="student_details.php?student_id=<?= urlencode((string)$row['student_id']) ?>">
                <header class="vd-card__header">
                  <div>
                    <h3><?= e($row['full_name']) ?></h3>
                    <p class="vd-card__sub">
                      <?= e((string)$row['course']) ?> | <?= e((string)$row['level']) ?>-<?= e((string)$row['section']) ?>
                    </p>
                  </div>
                  <span class="vd-pill status-<?= e($statusSlug) ?>"><?= e($statusLabel) ?></span>
                </header>

                <dl class="vd-details">
                  <div>
                    <dt>Student ID</dt>
                    <dd><?= e((string)$row['student_id']) ?></dd>
                  </div>
                  <div>
                    <dt>Starts</dt>
                    <dd><?= format_dt($row['starts_at']) ?: 'Not set' ?></dd>
                  </div>
                  <div>
                    <dt>Ends</dt>
                    <dd>
                      <?php if (!empty($row['ends_at'])): ?>
                        <?= format_dt($row['ends_at']) ?>
                      <?php else: ?>
                        <span class="vd-pill status-ongoing is-soft">Open</span>
                      <?php endif; ?>
                    </dd>
                  </div>
                </dl>

                <?php if (!empty($row['notes'])): ?>
                  <div class="vd-notes">
                    <span class="vd-notes__label">Latest note</span>
                    <p><?= e((string)$row['notes']) ?></p>
                  </div>
                <?php endif; ?>

                <footer class="vd-card__footer">
                  <span class="vd-link">View service record</span>
                </footer>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="vd-empty" role="status">
          <div class="vd-empty__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                <path d="M8 8l8 8"></path>
                <path d="M16 8l-8 8"></path>
              </svg>
            </div>
            <h2>No assignments just yet</h2>
            <p>Once community service assignments are created for you, they will appear here automatically.</p>
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




