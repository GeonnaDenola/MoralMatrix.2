<?php
declare(strict_types=1);

require '../auth.php';
require_role('security');
include __DIR__ . '/_scanner.php';

include '../config.php';
include '../includes/security_header.php';

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cleanText(?string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    return $value;
}

function formatRelativeFromDate(DateTimeImmutable $dt): string
{
    $now = new DateTimeImmutable();
    $diff = $now->diff($dt);

    $units = [
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
    ];

    foreach ($units as $property => $label) {
        $value = $diff->$property;
        if ($value > 0) {
            $plural = $value === 1 ? '' : 's';
            $suffix = $diff->invert === 1 ? ' ago' : ' from now';
            return $value . ' ' . $label . $plural . $suffix;
        }
    }

    return 'Just now';
}

function describeDate(?string $raw): array
{
    if (!$raw) {
        return ['iso' => '', 'full' => '', 'relative' => '', 'datetime' => null];
    }

    try {
        $dt = new DateTimeImmutable($raw);
    } catch (Exception $e) {
        return ['iso' => '', 'full' => h($raw), 'relative' => '', 'datetime' => null];
    }

    return [
        'iso' => $dt->format(DateTimeInterface::ATOM),
        'full' => $dt->format('M j, Y at g:i A'),
        'relative' => formatRelativeFromDate($dt),
        'datetime' => $dt,
    ];
}

function categoryIconSvg(string $category): string
{
    $key = strtolower(trim($category));
    switch ($key) {
        case 'moderate':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 12 12 17 22 12"></polyline><polyline points="2 17 12 22 22 17"></polyline></svg>';
        case 'grave':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
        default:
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><line x1="12" y1="2" x2="12" y2="5"></line><line x1="12" y1="19" x2="12" y2="22"></line><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"></line><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"></line><line x1="2" y1="12" x2="5" y2="12"></line><line x1="19" y1="12" x2="22" y2="12"></line><line x1="4.22" y1="19.78" x2="6.34" y2="17.66"></line><line x1="17.66" y1="6.34" x2="19.78" y2="4.22"></line></svg>';
    }
}

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

$securityId = $_SESSION['actor_id'] ?? null;
if (!$securityId) {
    die('No security id in session. Please login again.');
}

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

$stmt = $conn->prepare($sql) ?: die('Prepare failed: ' . $conn->error);
$stmt->bind_param('s', $securityId);
if (!$stmt->execute()) {
    die('Execute failed: ' . $stmt->error);
}
$result = $stmt->get_result();

$debugRows = [];
if (!empty($_GET['debug'])) {
    $debug = $conn->prepare("
        SELECT COALESCE(sv.submitted_role,'(null)') AS role,
               LOWER(sv.status) AS status_norm,
               COUNT(*) AS c
        FROM student_violation sv
        WHERE sv.submitted_by = ?
        GROUP BY sv.submitted_role, LOWER(sv.status)
        ORDER BY c DESC
    ");
    if ($debug) {
        $debug->bind_param('s', $securityId);
        $debug->execute();
        $debugRows = $debug->get_result()->fetch_all(MYSQLI_ASSOC);
        $debug->close();
    }
}

$violations = [];
$categoryCounts = [];
$studentIds = [];
$latestReportedAt = null;
$latestDescriptor = ['iso' => '', 'full' => '', 'relative' => ''];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $photoFile = cleanText($row['student_photo'] ?? '');
        $photoPath = '../admin/uploads/placeholder.png';
        if ($photoFile !== '') {
            $safeFile = basename($photoFile);
            $photoPath = '../admin/uploads/' . rawurlencode($safeFile);
        }

        $studentId = cleanText($row['student_id'] ?? '');
        $nameParts = [$row['first_name'] ?? '', $row['last_name'] ?? ''];
        $studentName = cleanText(implode(' ', $nameParts));
        if ($studentName === '') {
            $studentName = 'Unnamed student';
        }

        $category = cleanText($row['offense_category'] ?? '');
        if ($category === '') {
            $category = 'Uncategorized';
        }
        $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;

        $typeRaw = cleanText($row['offense_type'] ?? '');
        $typeLabel = $typeRaw !== '' ? $typeRaw : 'Type not provided';

        $description = cleanText($row['description'] ?? '');

        $statusRaw = cleanText($row['status'] ?? 'Pending');
        $statusLabel = strtoupper($statusRaw !== '' ? $statusRaw : 'Pending');

        $dateInfo = describeDate($row['reported_at'] ?? null);
        if ($dateInfo['datetime'] instanceof DateTimeImmutable) {
            if ($latestReportedAt === null || $dateInfo['datetime'] > $latestReportedAt) {
                $latestReportedAt = $dateInfo['datetime'];
                $latestDescriptor = [
                    'iso' => $dateInfo['iso'],
                    'full' => $dateInfo['full'],
                    'relative' => $dateInfo['relative'],
                ];
            }
        }

        $violations[] = [
            'id' => (int)$row['violation_id'],
            'student_id' => $studentId,
            'student_name' => $studentName,
            'photo' => $photoPath,
            'category' => $category,
            'type' => $typeLabel,
            'description' => $description,
            'status' => $statusLabel,
            'reported_at' => [
                'iso' => $dateInfo['iso'],
                'full' => $dateInfo['full'],
                'relative' => $dateInfo['relative'],
            ],
            'has_type' => $typeRaw !== '',
        ];

        if ($studentId !== '') {
            $studentIds[$studentId] = true;
        }
    }
}

$stmt->close();
$conn->close();

$pendingCount = count($violations);
$uniqueStudents = count($studentIds);

$topCategory = '';
$topCategoryCount = 0;
if ($categoryCounts) {
    arsort($categoryCounts);
    $topCategory = (string)array_key_first($categoryCounts);
    $topCategoryCount = (int)$categoryCounts[$topCategory];
}

$topCategorySummary = '';
if ($topCategory !== '') {
    $topCategorySummary = $topCategory . ' / ' . ($topCategoryCount === 1 ? '1 case' : $topCategoryCount . ' cases');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Security - Pending Violations</title>
    <link rel="stylesheet" href="../css/security_pending.css">
</head>
<body>
<main class="page">
    <div class="pending-shell">
        <section class="page-header">
            <div class="page-header__intro">
                <span class="page-header__eyebrow">Pending queue</span>
                <h1>Resolve outstanding reports</h1>
                <p>Review the incidents you have submitted, follow up on critical situations, and keep the campus timeline moving.</p>

                <div class="page-header__meta">
                    <span class="meta-pill">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <polyline points="12 7 12 12 15 15"></polyline>
                        </svg>
                        <?= h((string)$pendingCount); ?> awaiting review
                    </span>
                    <?php if (!empty($latestDescriptor['iso'])): ?>
                        <span class="meta-pill">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 5h18"></path>
                                <path d="M8 3v4"></path>
                                <path d="M16 3v4"></path>
                                <rect x="3" y="7" width="18" height="14" rx="2"></rect>
                                <path d="M8 11h8"></path>
                                <path d="M8 15h6"></path>
                            </svg>
                            Latest: <time datetime="<?= h($latestDescriptor['iso']); ?>" title="<?= h($latestDescriptor['full']); ?>">
                                <?= h($latestDescriptor['relative'] ?: $latestDescriptor['full']); ?>
                            </time>
                        </span>
                    <?php endif; ?>
                    <?php if ($topCategorySummary !== ''): ?>
                        <span class="meta-pill">
                            <?= categoryIconSvg($topCategory); ?>
                            <?= h($topCategorySummary); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="page-header__actions">
                <a class="button button--ghost" href="dashboard.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 13h8V3H3z"></path>
                        <path d="M13 21h8V9h-8z"></path>
                        <path d="M3 21h8v-6H3z"></path>
                        <path d="M13 3v6h8V3z"></path>
                    </svg>
                    View approved cases
                </a>
                <a class="button button--primary" href="/moralmatrix/security/report_student.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>
                    Report new incident
                </a>
            </div>
        </section>

        <?php if ($debugRows): ?>
            <div class="debug">
                <strong>Debug (per-role/status for your submissions)</strong>
                <div>actor_id: <code><?= h((string)$securityId); ?></code></div>
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Submitted role</th>
                            <th scope="col">Status (normalized)</th>
                            <th scope="col" style="text-align:right;">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($debugRows as $debugRow): ?>
                        <tr>
                            <td><?= h($debugRow['role']); ?></td>
                            <td><?= h($debugRow['status_norm']); ?></td>
                            <td style="text-align:right;"><?= (int)$debugRow['c']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top: 10px; color: var(--ink-500); font-size: 0.85rem;">
                    Tip: If you see statuses such as <em>pending approval</em> or roles other than <em>security</em>, that explains empty results in the queue.
                </div>
            </div>
        <?php endif; ?>

        <?php if ($pendingCount > 0): ?>
            <section class="queue-summary" aria-label="Queue highlights">
                <div class="summary-card">
                    <span class="summary-label">Pending cases</span>
                    <span class="summary-value"><?= h((string)$pendingCount); ?></span>
                    <span class="summary-hint">Everything submitted under your name that still needs attention.</span>
                </div>
                <div class="summary-card">
                    <span class="summary-label">Students in queue</span>
                    <span class="summary-value"><?= h((string)$uniqueStudents); ?></span>
                    <span class="summary-hint">Unique students linked to the reports above.</span>
                </div>
                <?php if ($topCategorySummary !== ''): ?>
                    <div class="summary-card">
                        <span class="summary-label">Most common category</span>
                        <span class="summary-value"><?= h($topCategory); ?></span>
                        <span class="summary-hint"><?= h(ucfirst($topCategorySummary)); ?></span>
                    </div>
                <?php endif; ?>
            </section>

            <section>
                <h2 class="visually-hidden">Pending violations</h2>
                <div class="violation-grid">
                    <?php foreach ($violations as $violation): ?>
                        <article class="violation-card">
                            <div class="violation-card__header">
                                <div class="avatar">
                                    <img src="<?= h($violation['photo']); ?>" alt="Photo of <?= h($violation['student_name']); ?>" loading="lazy" onerror="this.src='../admin/uploads/placeholder.png';this.onerror=null;">
                                </div>
                                <div class="student">
                                    <span class="student-name"><?= h($violation['student_name']); ?></span>
                                    <?php if ($violation['student_id'] !== ''): ?>
                                        <span class="student-id"><?= h($violation['student_id']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="status-pill"><?= h($violation['status']); ?></span>
                            </div>

                            <div class="violation-card__body">
                                <div class="meta-row">
                                    <span class="badge">
                                        <?= categoryIconSvg($violation['category']); ?>
                                        <?= h($violation['category']); ?>
                                    </span>
                                    <span class="badge">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M20 6L9 17l-5-5"></path>
                                        </svg>
                                        <?= h($violation['type']); ?>
                                    </span>
                                </div>
                                <?php if ($violation['description'] !== ''): ?>
                                    <p><?= h($violation['description']); ?></p>
                                <?php else: ?>
                                    <p>No additional description was provided.</p>
                                <?php endif; ?>
                            </div>

                            <div class="violation-card__footer">
                                <time datetime="<?= h($violation['reported_at']['iso']); ?>" title="<?= h($violation['reported_at']['full']); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9"></circle>
                                        <polyline points="12 7 12 12 15 15"></polyline>
                                    </svg>
                                    <?= h($violation['reported_at']['relative'] ?: $violation['reported_at']['full']); ?>
                                </time>
                                <a class="review-link" href="view_violation_pending.php?id=<?= $violation['id']; ?>">
                                    Review details
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M5 12h14"></path>
                                        <path d="M12 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else: ?>
            <section class="empty-state" aria-label="No pending reports">
                <h2>Everything is clear</h2>
                <p>There are no pending violations submitted under your account. When you report a new case, it will appear here until it is approved.</p>
                <div class="empty-tips">
                    <span>Confirm you selected the right filters</span>
                    <span>Use the debug toggle if a case is missing</span>
                    <span>Reach out to the review team for follow up</span>
                </div>
                <a class="button button--primary" href="/moralmatrix/security/report_student.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>
                    Report new incident
                </a>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
