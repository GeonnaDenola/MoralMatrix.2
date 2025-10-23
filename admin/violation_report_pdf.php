<?php
// /MoralMatrix/ccdu/violations_report_pdf.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../config.php';

// --- Locate Dompdf (works on both local + live) ---
$autoloadLocal = __DIR__ . '/../vendor/autoload.php';
$autoloadWeb   = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/vendor/autoload.php';

if (file_exists($autoloadLocal)) {
    require_once $autoloadLocal;
} elseif (file_exists($autoloadWeb)) {
    require_once $autoloadWeb;
} else {
    die('Dompdf not found. Please run: composer require dompdf/dompdf');
}

use Dompdf\Dompdf;
use Dompdf\Options;

// --- Database connection ---
$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);

if ($conn->connect_error) {
    die('DB connection failed: ' . $conn->connect_error);
}

// --- Input filters ---
$start  = isset($_GET['start'])  ? trim($_GET['start'])  : '';
$end    = isset($_GET['end'])    ? trim($_GET['end'])    : '';
$period = isset($_GET['period']) ? strtolower(trim($_GET['period'])) : 'monthly';

$startOk = $start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start);
$endOk   = $end !== ''   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end);

if (!in_array($period, ['weekly', 'monthly', 'semiannual', 'yearly'], true)) {
    $period = 'monthly';
}

// --- Ignore voided/cancelled if `status` exists ---
$hasStatus = false;
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'status'")) {
    $hasStatus = ($res->num_rows > 0);
    $res->close();
}

// --- WHERE builder ---
$where  = [];
$binds  = [];
$types  = '';

if ($startOk) {
    $where[] = "sv.reported_at >= ?";
    $binds[] = $start . ' 00:00:00';
    $types  .= 's';
}

if ($endOk) {
    $where[] = "sv.reported_at < DATE_ADD(?, INTERVAL 1 DAY)";
    $binds[] = $end;
    $types  .= 's';
}

if ($hasStatus) {
    $where[] = "LOWER(sv.status) NOT IN ('void','voided','canceled','cancelled')";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --- Violation level bucketing ---
$LEVEL_CASE = "
  CASE
    WHEN (sv.offense_category REGEXP '(?i)grave' AND sv.offense_category NOT REGEXP '(?i)less')
         THEN 'Grave'
    WHEN (sv.offense_category REGEXP '(?i)moderate|less')
         THEN 'Moderate'
    ELSE 'Light'
  END
";

// --- Period/grouping ---
switch ($period) {
    case 'weekly':
        $PERIOD_SELECT = "CONCAT(YEAR(sv.reported_at), '-W', LPAD(WEEK(sv.reported_at, 3), 2, '0'))";
        $PERIOD_ORDER  = "YEARWEEK(sv.reported_at, 3)";
        break;
    case 'semiannual':
        $PERIOD_SELECT = "CONCAT(YEAR(sv.reported_at), '-H', IF(MONTH(sv.reported_at) <= 6, 1, 2))";
        $PERIOD_ORDER  = "YEAR(sv.reported_at), IF(MONTH(sv.reported_at) <= 6, 1, 2)";
        break;
    case 'yearly':
        $PERIOD_SELECT = "CAST(YEAR(sv.reported_at) AS CHAR)";
        $PERIOD_ORDER  = "YEAR(sv.reported_at)";
        break;
    case 'monthly':
    default:
        $PERIOD_SELECT = "DATE_FORMAT(sv.reported_at, '%Y-%m')";
        $PERIOD_ORDER  = "YEAR(sv.reported_at), MONTH(sv.reported_at)";
        break;
}

// --- Helpers ---
function fetch_group(mysqli $conn, string $sql, string $types, array $binds): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$binds);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function pct(int $part, int $whole): string
{
    if ($whole <= 0) {
        return '0%';
    }

    return number_format(($part / $whole) * 100, 1) . '%';
}

function prettyPeriodName(string $p): string
{
    return [
        'weekly'     => 'Weekly',
        'monthly'    => 'Monthly',
        'semiannual' => 'Every 6 Months',
        'yearly'     => 'Yearly',
    ][$p] ?? 'Monthly';
}

function prettyRangeLabel(bool $startOk, string $start, bool $endOk, string $end): string
{
    if (!$startOk && !$endOk) {
        return 'All recorded dates';
    }

    $from = $startOk ? $start : 'Earliest';
    $to   = $endOk   ? $end   : 'Latest';

    if ($from === $to) {
        return $from;
    }

    return $from . ' to ' . $to;
}

function pluralLabel(int $count, string $singular, ?string $plural = null): string
{
    $plural = $plural ?? ($singular . 's');
    return ($count === 1) ? $singular : $plural;
}

// --- Aggregate queries ---
$totSql = "
    SELECT COUNT(*) AS total
    FROM student_violation sv
    JOIN student_account sa ON sa.student_id = sv.student_id
    $whereSql
";
$totalRows = fetch_group($conn, $totSql, $types, $binds);
$totalViolations = (int)($totalRows[0]['total'] ?? 0);

$uniqueSql = "
    SELECT COUNT(DISTINCT sv.student_id) AS total_unique
    FROM student_violation sv
    JOIN student_account sa ON sa.student_id = sv.student_id
    $whereSql
";
$uniqueRows = fetch_group($conn, $uniqueSql, $types, $binds);
$uniqueStudents = (int)($uniqueRows[0]['total_unique'] ?? 0);

$sqlByInst = "
    SELECT COALESCE(NULLIF(TRIM(sa.institute),''), '-') AS label,
           COUNT(*) AS violations,
           COUNT(DISTINCT sv.student_id) AS students
    FROM student_violation sv
    JOIN student_account sa ON sa.student_id = sv.student_id
    $whereSql
    GROUP BY label
    ORDER BY violations DESC, label ASC
";
$byInstitute = fetch_group($conn, $sqlByInst, $types, $binds);

$sqlByYear = "
    SELECT COALESCE(NULLIF(TRIM(sa.level),''), 'N/A') AS label,
           COUNT(*) AS violations,
           COUNT(DISTINCT sv.student_id) AS students
    FROM student_violation sv
    JOIN student_account sa ON sa.student_id = sv.student_id
    $whereSql
    GROUP BY label
    ORDER BY
      CASE WHEN label REGEXP '^[0-9]+$' THEN CAST(label AS UNSIGNED) ELSE 999999 END ASC,
      label ASC
";
$byYear = fetch_group($conn, $sqlByYear, $types, $binds);

$sqlByCourse = "
    SELECT COALESCE(NULLIF(TRIM(sa.course),''), 'N/A') AS label,
           COUNT(*) AS violations,
           COUNT(DISTINCT sv.student_id) AS students
    FROM student_violation sv
    JOIN student_account sa ON sa.student_id = sv.student_id
    $whereSql
    GROUP BY label
    ORDER BY violations DESC, label ASC
";
$byCourse = fetch_group($conn, $sqlByCourse, $types, $binds);

$sqlByLevel = "
    SELECT $LEVEL_CASE AS label,
           COUNT(*) AS violations,
           COUNT(DISTINCT sv.student_id) AS students
    FROM student_violation sv
    JOIN student_account sa ON sa.student_id = sv.student_id
    $whereSql
    GROUP BY label
    ORDER BY FIELD(label, 'Grave','Moderate','Light'), label
";
$byLevel = fetch_group($conn, $sqlByLevel, $types, $binds);

$sqlByPeriod = "
    SELECT
        $PERIOD_SELECT AS period_label,
        COUNT(*) AS violations,
        COUNT(DISTINCT sv.student_id) AS students
    FROM student_violation sv
    JOIN student_account sa ON sa.student_id = sv.student_id
    $whereSql
    GROUP BY period_label
    ORDER BY $PERIOD_ORDER
";
$byPeriod = fetch_group($conn, $sqlByPeriod, $types, $binds);

$conn->close();

$rangeLabel   = prettyRangeLabel($startOk, $start, $endOk, $end);
$periodLabel  = prettyPeriodName($period);
$generatedAt  = date('F d, Y g:i A');

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 18mm 18mm 20mm 18mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
            font-size: 12px;
            color: #1d232f;
            background: #ffffff;
        }
        .report-wrapper {
            background: #ffffff;
            padding: 24px 28px 32px;
            border-radius: 16px;
            border: 1px solid #e4e7ef;
            box-shadow: 0 8px 20px rgba(17, 24, 39, 0.08);
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            border-bottom: 3px solid #8c1c13;
            padding-bottom: 18px;
            margin-bottom: 22px;
        }
        .report-title {
            max-width: 70%;
        }
        .report-title h1 {
            margin: 0 0 6px;
            font-size: 24px;
            letter-spacing: -0.3px;
            color: #091021;
        }
        .report-title p {
            margin: 0;
            color: #5b6576;
            line-height: 1.45;
        }
        .meta-block {
            background: #ffffff;
            border: 1px solid rgba(140, 28, 19, 0.18);
            border-radius: 12px;
            padding: 12px 16px;
            min-width: 180px;
        }
        .meta-block span {
            display: block;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #8c1c13;
            margin-bottom: 4px;
        }
        .meta-block strong {
            font-size: 12px;
            color: #1d232f;
            font-weight: 600;
        }
        .info-band {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            background: #ffffff;
            border: 1px solid #eceff4;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 24px;
        }
        .info-band div {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .info-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: #746f68;
        }
        .info-value {
            font-size: 12px;
            font-weight: 600;
            color: #2f3747;
        }
        .metric-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            margin-bottom: 12px;
        }
        .metric-card {
            flex: 1 1 220px;
            background: #ffffff;
            border: 1px solid rgba(140, 28, 19, 0.14);
            border-radius: 14px;
            padding: 18px 20px;
            position: relative;
            overflow: hidden;
            margin-bottom: 18px;
            margin-right: 12px;
        }
        .metric-card:last-child {
            margin-right: 0;
        }
        .metric-card::after {
            content: "";
            position: absolute;
            inset: -40% -20% 50% 50%;
            background: radial-gradient(circle at top, rgba(140,28,19,0.12), transparent 70%);
            opacity: 0.35;
        }
        .metric-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: #742014;
            margin-bottom: 6px;
            position: relative;
        }
        .metric-value {
            font-size: 28px;
            font-weight: 700;
            color: #201417;
            margin: 0;
            line-height: 1.1;
            position: relative;
        }
        .metric-subtext {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: #835750;
            margin-top: 6px;
            position: relative;
        }
        .section {
            margin-bottom: 22px;
            page-break-inside: avoid;
        }
        .section h2 {
            margin: 0 0 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: #73231c;
            page-break-after: avoid;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            background: #ffffff;
            page-break-inside: auto;
        }
        thead th {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            font-weight: 700;
            color: #4e596b;
            border-bottom: 2px solid #d9dde6;
            padding: 8px 10px;
            text-align: left;
            background: #ffffff;
        }
        tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #edf0f5;
            color: #263046;
            font-size: 11px;
            background: #ffffff;
            page-break-inside: avoid;
        }
        .empty-row td {
            text-align: center;
            color: #9aa3b4;
            font-style: italic;
        }
        .footer-note {
            margin-top: 12px;
            font-size: 10px;
            color: #6d778a;
            text-align: right;
        }
        .two-column {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }
        .two-column > div {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="report-wrapper">
        <div class="report-header">
            <div class="report-title">
                <h1>Student Violations Analytics</h1>
                <p>
                    Comprehensive summary of violation trends across institutes, courses,
                    year levels, and reporting periods for the Moral Matrix monitoring system.
                </p>
            </div>
            <div class="meta-block">
                <span>Generated</span>
                <strong><?= htmlspecialchars($generatedAt) ?></strong>
            </div>
        </div>

        <div class="info-band">
            <div>
                <span class="info-label">Current View</span>
                <span class="info-value"><?= htmlspecialchars($periodLabel) ?></span>
            </div>
            <div>
                <span class="info-label">Date Range</span>
                <span class="info-value"><?= htmlspecialchars($rangeLabel) ?></span>
            </div>
            <div>
                <span class="info-label">Start Date</span>
                <span class="info-value"><?= $startOk ? htmlspecialchars($start) : 'N/A' ?></span>
            </div>
            <div>
                <span class="info-label">End Date</span>
                <span class="info-value"><?= $endOk ? htmlspecialchars($end) : 'N/A' ?></span>
            </div>
        </div>

        <div class="metric-row">
            <div class="metric-card">
                <div class="metric-label">Total Violations</div>
                <div class="metric-value"><?= number_format($totalViolations) ?></div>
                <div class="metric-subtext">
                    <?= htmlspecialchars(pluralLabel($totalViolations, 'Case Recorded', 'Cases Recorded')) ?>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Unique Students</div>
                <div class="metric-value"><?= number_format($uniqueStudents) ?></div>
                <div class="metric-subtext">
                    <?= htmlspecialchars(pluralLabel($uniqueStudents, 'Student Involved', 'Students Involved')) ?>
                </div>
            </div>
        </div>

        <div class="two-column section">
            <div>
                <h2>By Institute</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Institute</th>
                            <th>Violations</th>
                            <th>%</th>
                            <th>Students</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($byInstitute): ?>
                        <?php foreach ($byInstitute as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td><?= number_format((int) $row['violations']) ?></td>
                                <td><?= htmlspecialchars(pct((int) $row['violations'], $totalViolations)) ?></td>
                                <td><?= number_format((int) $row['students']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="4">No institute data available for the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <h2>By Year Level</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Year Level</th>
                            <th>Violations</th>
                            <th>%</th>
                            <th>Students</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($byYear): ?>
                        <?php foreach ($byYear as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td><?= number_format((int) $row['violations']) ?></td>
                                <td><?= htmlspecialchars(pct((int) $row['violations'], $totalViolations)) ?></td>
                                <td><?= number_format((int) $row['students']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="4">No year level data available for the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="two-column section">
            <div>
                <h2>By Course</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Violations</th>
                            <th>%</th>
                            <th>Students</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($byCourse): ?>
                        <?php foreach ($byCourse as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td><?= number_format((int) $row['violations']) ?></td>
                                <td><?= htmlspecialchars(pct((int) $row['violations'], $totalViolations)) ?></td>
                                <td><?= number_format((int) $row['students']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="4">No course data available for the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <h2>By Violation Level</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Violations</th>
                            <th>%</th>
                            <th>Students</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($byLevel): ?>
                        <?php foreach ($byLevel as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td><?= number_format((int) $row['violations']) ?></td>
                                <td><?= htmlspecialchars(pct((int) $row['violations'], $totalViolations)) ?></td>
                                <td><?= number_format((int) $row['students']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="4">No violation level data available for the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <h2>By Reporting Period (<?= htmlspecialchars($periodLabel) ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Violations</th>
                        <th>%</th>
                        <th>Students</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($byPeriod): ?>
                    <?php foreach ($byPeriod as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['period_label']) ?></td>
                            <td><?= number_format((int) $row['violations']) ?></td>
                            <td><?= htmlspecialchars(pct((int) $row['violations'], $totalViolations)) ?></td>
                            <td><?= number_format((int) $row['students']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="empty-row">
                        <td colspan="4">No period data available for the selected filters.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="footer-note">
            Generated by Moral Matrix &middot; Center for Character Development Unit &middot;
            <?= htmlspecialchars($generatedAt) ?>
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Violation_Summary_Report_' . date('Y-m-d_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
