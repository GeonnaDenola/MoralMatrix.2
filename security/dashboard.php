<?php
declare(strict_types=1);

require '../auth.php';
require_role('security');
include __DIR__ . '/_scanner.php';
include '../config.php';
include '../includes/security_header.php';
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
  AND LOWER(sv.status) = 'approved'
ORDER BY sv.reported_at DESC, sv.violation_id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param('s', $securityId);
if (!$stmt->execute()) {
    die('Execute failed: ' . $stmt->error);
}
$result = $stmt->get_result();

$violations = [];
$categoryCounts = [];
$typeCounts = [];
$studentIds = [];
$latestReportedAt = null;
$recentWindow = new DateTimeImmutable('-7 days');
$recentCount = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $violations[] = $row;

        $studentId = (string)($row['student_id'] ?? '');
        if ($studentId !== '') {
            $studentIds[$studentId] = true;
        }

        $categoryLabel = trim((string)($row['offense_category'] ?? ''));
        if ($categoryLabel === '') {
            $categoryLabel = 'Uncategorized';
        }
        $categoryCounts[$categoryLabel] = ($categoryCounts[$categoryLabel] ?? 0) + 1;

        $typeLabel = trim((string)($row['offense_type'] ?? ''));
        if ($typeLabel === '') {
            $typeLabel = 'Unspecified';
        }
        $typeCounts[$typeLabel] = ($typeCounts[$typeLabel] ?? 0) + 1;

        $reportedRaw = $row['reported_at'] ?? null;
        if ($reportedRaw) {
            try {
                $reportedAt = new DateTimeImmutable($reportedRaw);
                if ($latestReportedAt === null || $reportedAt > $latestReportedAt) {
                    $latestReportedAt = $reportedAt;
                }
                if ($reportedAt >= $recentWindow) {
                    $recentCount++;
                }
            } catch (Exception $e) {
                // ignore parse issues
            }
        }
    }
}

$totalReports   = count($violations);
$uniqueStudents = count($studentIds);

$categoryCountsDesc = $categoryCounts;
arsort($categoryCountsDesc);
$topCategoryName = $categoryCountsDesc ? array_key_first($categoryCountsDesc) : '';
$topCategoryShare = ($totalReports > 0 && $topCategoryName !== '')
    ? round(($categoryCountsDesc[$topCategoryName] / $totalReports) * 100)
    : 0;
$topCategories = array_slice($categoryCountsDesc, 0, 3, true);

$typeCountsDesc = $typeCounts;
arsort($typeCountsDesc);
$uniqueTypes = count($typeCountsDesc);

$categoryOptions = buildFilterOptions($categoryCounts);
$typeOptions = buildFilterOptions($typeCounts);

$stmt->close();
$conn->close();

/**
 * @param array<string,int> $counts
 * @return array<int,array{label:string,value:string,count:int}>
 */
function buildFilterOptions(array $counts): array
{
    $options = [];
    foreach ($counts as $label => $count) {
        $options[] = [
            'label' => $label,
            'value' => normaliseKey($label),
            'count' => $count,
        ];
    }

    usort(
        $options,
        static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label'])
    );

    return $options;
}

function safeText(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function normaliseKey(string $value): string
{
    $value = trim($value);
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'na';
}

function toSearchIndex(string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

/**
 * @return array{iso:string,full:string,relative:string}
 */
function describeDate(?string $raw): array
{
    if (!$raw) {
        return ['iso' => '', 'full' => '—', 'relative' => ''];
    }

    try {
        $dt = new DateTimeImmutable($raw);
    } catch (Exception $e) {
        return ['iso' => '', 'full' => safeText($raw), 'relative' => ''];
    }

    return [
        'iso' => $dt->format(DateTimeInterface::ATOM),
        'full' => $dt->format('M j, Y • g:i A'),
        'relative' => formatRelativeFromDate($dt),
    ];
}

function formatRelativeFromDate(DateTimeImmutable $dt): string
{
    $now  = new DateTimeImmutable();
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

$latestDescriptor = $latestReportedAt
    ? [
        'full' => $latestReportedAt->format('M j, Y • g:i A'),
        'relative' => formatRelativeFromDate($latestReportedAt),
    ]
    : ['full' => 'Awaiting first approval', 'relative' => ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Security · Approved Violations</title>

  <style>
    :root{
      --page-max-width: 1180px;
      --shell-gap: clamp(24px, 4vw, 36px);
      --card-radius: 20px;
      --card-shadow: 0 18px 44px rgba(15, 23, 42, 0.09);
      --border-muted: rgba(148, 163, 184, 0.18);
      --ink-700: #0f172a;
      --ink-500: #475569;
      --ink-400: #64748b;
      --ink-300: #94a3b8;
      --accent: #b91c1c;
      --accent-soft: rgba(185, 28, 28, 0.1);
      --accent-strong: rgba(185, 28, 28, 0.18);
    }

    body{
      background: #f4f6fb;
      color: var(--ink-700);
    }
    .page{
      margin-top: 2px;
    }

    .dashboard-shell{
      max-width: var(--page-max-width);
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: var(--shell-gap);
    }

    .page-heading{
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: clamp(18px, 4vw, 28px);
      background: #fff;
      border-radius: var(--card-radius);
      border: 1px solid var(--border-muted);
      box-shadow: var(--card-shadow);
      padding: clamp(24px, 4vw, 36px);
    }

    .page-heading__intro{
      max-width: 560px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .page-heading__eyebrow{
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: .3em;
      text-transform: uppercase;
      color: var(--ink-300);
    }

    .page-heading__intro h1{
      margin: 0;
      font-size: clamp(1.85rem, 2.8vw, 2.45rem);
      font-weight: 800;
      letter-spacing: -0.01em;
      color: var(--ink-700);
    }

    .page-heading__intro p{
      margin: 0;
      font-size: 1rem;
      color: var(--ink-500);
      line-height: 1.6;
    }

    .page-heading__actions{
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
    }

    .button{
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      border-radius: 999px;
      font-weight: 600;
      padding: 11px 20px;
      border: 1px solid transparent;
      cursor: pointer;
      transition: transform .2s ease, box-shadow .2s ease, background .2s ease, border-color .2s ease;
      font-size: 0.96rem;
    }

    .button svg{
      width: 18px;
      height: 18px;
    }

    .button--primary{
      background: linear-gradient(120deg, #b91c1c, #dc2626);
      color: #fff;
      box-shadow: 0 14px 28px rgba(185, 28, 28, 0.28);
    }
    .button--primary:hover{
      transform: translateY(-1px);
      box-shadow: 0 18px 34px rgba(185, 28, 28, 0.32);
    }

    .button--ghost{
      background: rgba(185, 28, 28, 0.08);
      color: #8b1c1c;
      border-color: rgba(185, 28, 28, 0.2);
    }
    .button--ghost:hover{
      background: rgba(185, 28, 28, 0.12);
      border-color: rgba(185, 28, 28, 0.32);
    }

    .button--subtle{
      background: #fff;
      border: 1px solid rgba(148, 163, 184, 0.32);
      color: var(--ink-500);
      padding: 9px 16px;
      font-size: 0.88rem;
    }
    .button--subtle:hover{
      border-color: rgba(148, 163, 184, 0.5);
      color: var(--ink-700);
    }

    .stats-grid{
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: clamp(16px, 3vw, 22px);
    }

    .stat-card{
      background: #fff;
      border-radius: var(--card-radius);
      border: 1px solid var(--border-muted);
      box-shadow: var(--card-shadow);
      padding: clamp(20px, 3vw, 26px);
      display: flex;
      flex-direction: column;
      gap: 12px;
      position: relative;
      overflow: hidden;
    }

    .stat-card::after{
      content: '';
      position: absolute;
      inset: 0;
      pointer-events: none;
      background: radial-gradient(circle at 18% 20%, rgba(239, 68, 68, 0.2), transparent 65%);
      opacity: 0;
      transition: opacity .25s ease;
    }

    .stat-card:hover::after{
      opacity: 1;
    }

    .stat-card--accent{
      background: linear-gradient(135deg, #b91c1c, #dc2626);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.18);
      box-shadow: 0 20px 42px rgba(220, 38, 38, 0.35);
    }
    .stat-card--accent::after{
      background: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.25), transparent 60%);
    }
    .stat-card--accent .stat-label,
    .stat-card--accent .stat-caption{
      color: rgba(255,255,255,0.82);
    }
    .stat-card--accent .stat-value{
      color: #fff;
    }

    .stat-label{
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: .32em;
      font-weight: 700;
      color: var(--ink-300);
    }

    .stat-value{
      font-size: clamp(2.1rem, 3.4vw, 2.6rem);
      font-weight: 800;
      line-height: 1;
      color: var(--ink-700);
    }

    .stat-caption{
      color: var(--ink-500);
      font-size: 0.95rem;
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      align-items: baseline;
    }

    .stat-badge{
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border-radius: 999px;
      padding: 4px 10px;
      font-size: 0.75rem;
      font-weight: 600;
      background: rgba(34, 197, 94, 0.15);
      color: #166534;
      border: 1px solid rgba(34, 197, 94, 0.25);
    }

    .filter-panel{
      background: #fff;
      border-radius: var(--card-radius);
      border: 1px solid var(--border-muted);
      box-shadow: var(--card-shadow);
      padding: clamp(18px, 3vw, 26px);
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: clamp(16px, 3vw, 24px);
    }

    .search-input{
      flex: 1 1 240px;
      display: flex;
      align-items: center;
      gap: 10px;
      background: #f8fafc;
      border-radius: 16px;
      border: 1px solid rgba(148, 163, 184, 0.3);
      padding: 12px 16px;
      transition: border-color .2s ease, box-shadow .2s ease;
    }

    .search-input svg{
      width: 18px;
      height: 18px;
      color: var(--ink-400);
      flex-shrink: 0;
    }

    .search-input input{
      background: transparent;
      border: none;
      outline: none;
      width: 100%;
      font-size: 0.95rem;
      color: var(--ink-700);
    }
    .search-input input::placeholder{
      color: var(--ink-300);
    }
    .search-input:focus-within{
      border-color: rgba(185, 28, 28, 0.5);
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.18);
    }

    .filter-group{
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 180px;
    }

    .filter-label{
      font-size: 0.72rem;
      letter-spacing: .22em;
      text-transform: uppercase;
      font-weight: 700;
      color: var(--ink-300);
    }

    .filter-group select{
      appearance: none;
      border-radius: 14px;
      border: 1px solid rgba(148, 163, 184, 0.3);
      background: #fff;
      padding: 10px 14px;
      font-size: 0.92rem;
      font-weight: 600;
      color: var(--ink-500);
      transition: border-color .2s ease, box-shadow .2s ease;
    }
    .filter-group select:focus-visible{
      border-color: rgba(185, 28, 28, 0.5);
      outline: none;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.18);
    }

    .filters-actions{
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .results-meta{
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      font-size: 0.95rem;
      color: var(--ink-500);
    }

    .results-meta strong{
      color: var(--ink-700);
    }

    .insights{
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .insights h2{
      margin: 0;
      font-size: 1.05rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--ink-400);
    }

    .insight-chips{
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .insight-chip{
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: #fff;
      border-radius: 999px;
      border: 1px solid rgba(148, 163, 184, 0.32);
      box-shadow: 0 12px 24px rgba(148, 163, 184, 0.18);
      font-weight: 600;
      color: var(--ink-500);
    }
    .insight-chip .chip-count{
      background: rgba(185, 28, 28, 0.12);
      color: #991b1b;
      border-radius: 999px;
      padding: 4px 9px;
      font-size: 0.8rem;
    }

    .cards-grid{
      display: grid;
      gap: clamp(18px, 3vw, 24px);
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    }

    .violation-card{
      background: #fff;
      border-radius: var(--card-radius);
      border: 1px solid var(--border-muted);
      box-shadow: var(--card-shadow);
      padding: clamp(20px, 3vw, 28px);
      display: flex;
      flex-direction: column;
      gap: 18px;
      transition: transform .24s ease, box-shadow .24s ease, border-color .24s ease;
    }
    .violation-card:hover{
      transform: translateY(-6px);
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
      border-color: rgba(239, 68, 68, 0.35);
    }

    .violation-card.is-hidden{
      display: none;
    }

    .card-header{
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 18px;
    }

    .student{
      display: flex;
      gap: 16px;
    }

    .avatar{
      width: 64px;
      height: 64px;
      border-radius: 18px;
      overflow: hidden;
      border: 1px solid rgba(148, 163, 184, 0.2);
      background: #f8fafc;
      flex-shrink: 0;
    }

    .avatar img{
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .student h3{
      margin: 0;
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--ink-700);
      line-height: 1.35;
    }

    .student .student-id{
      font-weight: 600;
      color: var(--ink-300);
      font-size: 0.9rem;
    }

    .student .meta{
      margin: 6px 0 0;
      color: var(--ink-400);
      font-size: 0.92rem;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .status-pill{
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: .18em;
      text-transform: uppercase;
      background: rgba(34, 197, 94, 0.18);
      color: #166534;
      border: 1px solid rgba(34, 197, 94, 0.32);
      white-space: nowrap;
    }

    .card-body{
      color: var(--ink-500);
      font-size: 0.96rem;
      line-height: 1.6;
    }
    .card-body p{
      margin: 0;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .card-body p + p{
      margin-top: 10px;
    }

    .card-footer{
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      color: var(--ink-400);
      font-size: 0.9rem;
    }

    .card-footer time{
      font-weight: 600;
      color: var(--ink-500);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .card-footer .relative{
      color: var(--ink-300);
      font-size: 0.82rem;
    }

    .card-footer a{
      font-weight: 600;
      color: #b91c1c;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .card-footer a svg{
      width: 16px;
      height: 16px;
    }

    .no-results{
      border-radius: var(--card-radius);
      border: 1px dashed rgba(148, 163, 184, 0.4);
      padding: clamp(20px, 3vw, 26px);
      text-align: center;
      color: var(--ink-400);
      background: #fff;
    }

    .empty-state{
      background: #fff;
      border-radius: var(--card-radius);
      border: 1px solid var(--border-muted);
      box-shadow: var(--card-shadow);
      padding: clamp(32px, 5vw, 48px);
      text-align: center;
      display: flex;
      flex-direction: column;
      gap: 18px;
      align-items: center;
    }

    .empty-state h2{
      margin: 0;
      font-size: clamp(1.4rem, 2.4vw, 1.8rem);
      font-weight: 700;
      color: var(--ink-700);
    }

    .empty-state p{
      margin: 0;
      max-width: 460px;
      color: var(--ink-500);
      line-height: 1.6;
    }

    .empty-state .tips{
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: center;
    }

    .empty-state .tips span{
      background: rgba(148, 163, 184, 0.16);
      border-radius: 999px;
      padding: 6px 12px;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--ink-500);
    }

    @media (max-width: 980px){
      .page{
        margin-left: var(--sidebar-w);
        padding: clamp(20px, 4vw, 36px);
      }
    }

    @media (max-width: 720px){
      .page{
        margin-left: 0;
        padding: clamp(18px, 6vw, 28px);
        margin-top: calc(var(--header-h) + 12px);
      }
      .page-heading{
        padding: 24px;
      }
      .page-heading__actions{
        width: 100%;
        justify-content: flex-start;
      }
      .filter-panel{
        flex-direction: column;
        align-items: stretch;
      }
      .filters-actions{
        width: 100%;
        justify-content: flex-start;
      }
      .results-meta{
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media (max-width: 540px){
      .stats-grid{
        grid-template-columns: 1fr;
      }
      .cards-grid{
        grid-template-columns: 1fr;
      }
      .student{
        flex-direction: column;
        align-items: flex-start;
      }
      .avatar{
        width: 56px;
        height: 56px;
      }
      .status-pill{
        letter-spacing: .12em;
      }
    }

    </style>
</head>
<body>



  <main class="page">
    <div class="dashboard-shell">
      <section class="page-heading">
        <div class="page-heading__intro">
          <span class="page-heading__eyebrow">Security Command Center</span>
          <h1>Approved violation overview</h1>
          <p>Monitor the cases that cleared review, track recurring offenses, and jump straight into the details you need when it matters.</p>
        </div>
        <div class="page-heading__actions">
          <a class="button button--ghost" href="pending_reports.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="9"></circle>
              <line x1="12" y1="7" x2="12" y2="13"></line>
              <circle cx="12" cy="17" r="1" fill="currentColor" stroke="none"></circle>
            </svg>
            Review pending
          </a>
          <a class="button button--primary" href="/moralmatrix/security/report_student.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 5v14M5 12h14"/>
            </svg>
            Report new case
          </a>
        </div>
      </section>

      <section class="stats-grid">
        <article class="stat-card stat-card--accent">
          <span class="stat-label">Approved reports</span>
          <span class="stat-value"><?= number_format($totalReports); ?></span>
          <span class="stat-caption">
            <span class="stat-badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14M5 12h14"/>
              </svg>
              Last 7 days: <?= number_format($recentCount); ?>
            </span>
            Reviewed submissions promoted to the approved queue.
          </span>
        </article>

        <article class="stat-card">
          <span class="stat-label">Students involved</span>
          <span class="stat-value"><?= number_format($uniqueStudents); ?></span>
          <span class="stat-caption">
            <?= $uniqueTypes === 1 ? 'Single offense type recorded' : $uniqueTypes . ' distinct offense types tracked'; ?>
          </span>
        </article>

        <article class="stat-card">
          <span class="stat-label">Latest update</span>
          <span class="stat-value"><?= safeText($latestDescriptor['full']); ?></span>
          <?php if ($latestDescriptor['relative'] !== ''): ?>
            <span class="stat-caption">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 6v6l3 3"/>
                <circle cx="12" cy="12" r="9"/>
              </svg>
              <?= safeText($latestDescriptor['relative']); ?>
            </span>
          <?php else: ?>
            <span class="stat-caption">No approvals logged yet.</span>
          <?php endif; ?>
        </article>

        <article class="stat-card">
          <span class="stat-label">Top category</span>
          <?php if ($topCategoryName !== ''): ?>
            <span class="stat-value"><?= safeText($topCategoryName); ?></span>
            <span class="stat-caption">
              <span class="stat-badge"><?= $topCategoryShare; ?>%</span>
              of approved reports this cycle
            </span>
          <?php else: ?>
            <span class="stat-value">—</span>
            <span class="stat-caption">Insights will appear once approvals start coming in.</span>
          <?php endif; ?>
        </article>
      </section>

      <?php if ($totalReports > 0): ?>
        <section class="filter-panel" aria-label="Filtering tools">
          <label class="search-input" for="searchInput">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="7"/>
              <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="search" id="searchInput" name="search" placeholder="Search by name, student ID, or keywords…">
          </label>

          <div class="filter-group">
            <span class="filter-label">Category</span>
            <select id="categoryFilter" name="category">
              <option value="">All categories</option>
              <?php foreach ($categoryOptions as $option): ?>
                <option value="<?= safeText($option['value']); ?>">
                  <?= safeText($option['label']); ?> (<?= $option['count']; ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-group">
            <span class="filter-label">Offense type</span>
            <select id="typeFilter" name="type">
              <option value="">All types</option>
              <?php foreach ($typeOptions as $option): ?>
                <option value="<?= safeText($option['value']); ?>">
                  <?= safeText($option['label']); ?> (<?= $option['count']; ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-group">
            <span class="filter-label">Sort</span>
            <select id="sortSelect" name="sort">
              <option value="newest" selected>Newest first</option>
              <option value="oldest">Oldest first</option>
              <option value="student">Student name</option>
              <option value="category">Category</option>
            </select>
          </div>

          <div class="filters-actions">
            <button type="button" class="button button--subtle" id="resetFilters" hidden>Clear filters</button>
          </div>
        </section>

        <?php if (!empty($topCategories)): ?>
          <section class="insights" aria-label="Category insights">
            <h2>Most frequent categories</h2>
            <div class="insight-chips">
              <?php foreach ($topCategories as $label => $count): ?>
                <span class="insight-chip">
                  <?= safeText($label); ?>
                  <span class="chip-count"><?= number_format($count); ?></span>
                </span>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <div class="results-meta" aria-live="polite">
          <div>
            Showing <strong id="resultsCount"><?= $totalReports; ?></strong> of <strong id="totalCount"><?= $totalReports; ?></strong> approved reports
          </div>
        </div>

        <section class="cards-grid" id="violationsGrid" aria-label="Approved reports">
          <?php foreach ($violations as $violation):
              $firstName = trim((string)($violation['first_name'] ?? ''));
              $lastName  = trim((string)($violation['last_name'] ?? ''));
              $studentName = trim($firstName . ' ' . $lastName);
              if ($studentName === '') {
                  $studentName = 'Unnamed student';
              }
              $studentId = (string)($violation['student_id'] ?? '—');
              $categoryLabel = trim((string)($violation['offense_category'] ?? ''));
              if ($categoryLabel === '') {
                  $categoryLabel = 'Uncategorized';
              }
              $typeLabel = trim((string)($violation['offense_type'] ?? ''));
              if ($typeLabel === '') {
                  $typeLabel = 'Unspecified';
              }
              $statusLabelRaw = trim((string)($violation['status'] ?? ''));
              $statusLabel = $statusLabelRaw !== ''
                  ? ucwords(str_replace('_', ' ', strtolower($statusLabelRaw)))
                  : 'Approved';

              $photoFile = trim((string)($violation['student_photo'] ?? ''));
              $photoSrc = $photoFile !== '' ? '../admin/uploads/' . $photoFile : 'placeholder.png';

              $description = trim((string)($violation['description'] ?? ''));
              $dateInfo = describeDate($violation['reported_at'] ?? null);

              $searchIndex = toSearchIndex($studentName . ' ' . $studentId . ' ' . $categoryLabel . ' ' . $typeLabel . ' ' . $description);
              $categoryKey = normaliseKey($categoryLabel);
              $typeKey = normaliseKey($typeLabel);
              $studentSort = toSearchIndex($studentName);
          ?>
            <article
              class="violation-card"
              data-category="<?= safeText($categoryKey); ?>"
              data-category-label="<?= safeText($categoryLabel); ?>"
              data-type="<?= safeText($typeKey); ?>"
              data-type-label="<?= safeText($typeLabel); ?>"
              data-student="<?= safeText($studentSort); ?>"
              data-search="<?= safeText($searchIndex); ?>"
              data-reported-at="<?= safeText($dateInfo['iso']); ?>"
            >
              <div class="card-header">
                <div class="student">
                  <div class="avatar">
                    <img src="<?= safeText($photoSrc); ?>" alt="Student photo for <?= safeText($studentName); ?>" onerror="this.src='placeholder.png'">
                  </div>
                  <div>
                    <h3><?= safeText($studentName); ?></h3>
                    <div class="student-id"><?= safeText($studentId); ?></div>
                    <div class="meta">
                      <span><?= safeText($categoryLabel); ?></span>
                      <span>•</span>
                      <span><?= safeText($typeLabel); ?></span>
                    </div>
                  </div>
                </div>
                <span class="status-pill">
                  <?= safeText($statusLabel); ?>
                </span>
              </div>

              <div class="card-body">
                <?php if ($description !== ''): ?>
                  <p><?= nl2br(safeText($description)); ?></p>
                <?php else: ?>
                  <p><em>No additional notes provided.</em></p>
                <?php endif; ?>
              </div>

              <div class="card-footer">
                <div>
                  <time datetime="<?= safeText($dateInfo['iso']); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="9"/>
                      <polyline points="12 7 12 12 16 14"/>
                    </svg>
                    <?= safeText($dateInfo['full']); ?>
                  </time>
                  <?php if ($dateInfo['relative'] !== ''): ?>
                    <span class="relative">· <?= safeText($dateInfo['relative']); ?></span>
                  <?php endif; ?>
                </div>
                <a href="view_violation_approved.php?id=<?= urlencode((string)($violation['violation_id'] ?? '')); ?>">
                  View details
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                  </svg>
                </a>
              </div>
            </article>
          <?php endforeach; ?>
        </section>

        <div class="no-results" id="noResults" hidden>
          No matches found for your current filters. Try adjusting the search or clear all filters.
        </div>
      <?php else: ?>
        <section class="empty-state">
          <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9" stroke-dasharray="2 4"/>
            <path d="M9 10.5l3 3 3-3"/>
          </svg>
          <h2>No approved reports yet</h2>
          <p>Once cases have been reviewed and approved they will appear here with quick filters and insights. You can still register a new concern or review pending submissions.</p>
          <div class="tips">
            <span>Use the scanner to capture new reports</span>
            <span>Follow up on pending reviews</span>
          </div>
          <div class="page-heading__actions">
            <a class="button button--ghost" href="pending_reports.php">Go to pending queue</a>
            <a class="button button--primary" href="../faculty/report_student.php">Report new case</a>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </main>
  <script>
  (function(){
    const dropdown = document.getElementById('logoutDropdown');
    if (!dropdown) return;
    const summary = dropdown.querySelector('summary');

    function sync(){
      if (!summary) { return; }
      summary.setAttribute('aria-expanded', dropdown.hasAttribute('open') ? 'true' : 'false');
    }

    dropdown.addEventListener('toggle', sync);
    document.addEventListener('click', function(evt){
      if (!dropdown.contains(evt.target)) {
        dropdown.removeAttribute('open');
        sync();
      }
    });
    document.addEventListener('keydown', function(evt){
      if (evt.key === 'Escape') {
        dropdown.removeAttribute('open');
        sync();
      }
    });
  })();
  </script>

  <?php if ($totalReports > 0): ?>
  <script>
    (function(){
      const searchInput = document.getElementById('searchInput');
      const categoryFilter = document.getElementById('categoryFilter');
      const typeFilter = document.getElementById('typeFilter');
      const sortSelect = document.getElementById('sortSelect');
      const resetButton = document.getElementById('resetFilters');
      const grid = document.getElementById('violationsGrid');
      const resultsCount = document.getElementById('resultsCount');
      const totalCount = document.getElementById('totalCount');
      const noResults = document.getElementById('noResults');
      const cards = Array.from(document.querySelectorAll('.violation-card'));
      const totalRecords = Number(totalCount ? totalCount.textContent : cards.length);

      function parseTime(value){
        const parsed = Date.parse(value);
        return Number.isNaN(parsed) ? 0 : parsed;
      }

      function sortCards(list){
        const mode = sortSelect ? sortSelect.value : 'newest';
        const sorted = list.slice();

        switch(mode){
          case 'oldest':
            sorted.sort((a, b) => parseTime(a.dataset.reportedAt || '') - parseTime(b.dataset.reportedAt || ''));
            break;
          case 'student':
            sorted.sort((a, b) => (a.dataset.student || '').localeCompare(b.dataset.student || ''));
            break;
          case 'category':
            sorted.sort((a, b) => (a.dataset.categoryLabel || '').localeCompare(b.dataset.categoryLabel || ''));
            break;
          case 'newest':
          default:
            sorted.sort((a, b) => parseTime(b.dataset.reportedAt || '') - parseTime(a.dataset.reportedAt || ''));
            break;
        }
        return sorted;
      }

      function applyFilters(){
        const query = (searchInput?.value || '').trim().toLowerCase();
        const category = categoryFilter?.value || '';
        const type = typeFilter?.value || '';

        let visible = [];

        cards.forEach(card => {
          const matchesSearch = !query || (card.dataset.search || '').includes(query);
          const matchesCategory = !category || card.dataset.category === category;
          const matchesType = !type || card.dataset.type === type;

          const shouldShow = matchesSearch && matchesCategory && matchesType;
          card.hidden = !shouldShow;
          card.classList.toggle('is-hidden', !shouldShow);
          if (shouldShow) {
            visible.push(card);
          }
        });

        visible = sortCards(visible);
        const hiddenCards = cards.filter(card => card.hidden);
        [...visible, ...hiddenCards].forEach(card => grid?.appendChild(card));

        const visibleCount = visible.length;
        if (resultsCount) {
          resultsCount.textContent = visibleCount.toString();
        }

        if (noResults) {
          noResults.hidden = visibleCount !== 0;
        }

        const hasFilters = !!query || !!category || !!type || (sortSelect && sortSelect.value !== 'newest');
        if (resetButton) {
          resetButton.hidden = !hasFilters;
        }
      }

      if (resetButton) {
        resetButton.addEventListener('click', function(){
          if (searchInput) searchInput.value = '';
          if (categoryFilter) categoryFilter.selectedIndex = 0;
          if (typeFilter) typeFilter.selectedIndex = 0;
          if (sortSelect) sortSelect.value = 'newest';
          applyFilters();
          if (searchInput) searchInput.focus();
        });
      }

      searchInput?.addEventListener('input', applyFilters);
      categoryFilter?.addEventListener('change', applyFilters);
      typeFilter?.addEventListener('change', applyFilters);
      sortSelect?.addEventListener('change', applyFilters);

      if (totalCount) {
        totalCount.textContent = totalRecords.toString();
      }
      applyFilters();
    })();
  </script>
  <?php endif; ?>
</body>
</html>
