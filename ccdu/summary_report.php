<?php
// /MoralMatrix/ccdu/violations_report.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/../config.php';

include '../includes/header.php';

$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) { die("DB connection failed: " . $conn->connect_error); }

/* ----- Inputs (optional date filter + period granularity) ----- */
$start  = isset($_GET['start'])  ? trim($_GET['start'])  : '';
$end    = isset($_GET['end'])    ? trim($_GET['end'])    : '';
$period = isset($_GET['period']) ? strtolower(trim($_GET['period'])) : 'monthly';

$startOk = $start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start);
$endOk   = $end   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end);
if (!in_array($period, ['weekly','monthly','semiannual','yearly'], true)) {
  $period = 'monthly';
}

/* ----- Ignore voided/cancelled if `status` exists ----- */
$hasStatus = false;
if ($res = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'status'")) {
  $hasStatus = ($res->num_rows > 0);
  $res->close();
}

/* ----- WHERE builder ----- */
$where  = [];
$binds  = [];
$types  = '';

if ($startOk) { $where[] = "sv.reported_at >= ?"; $binds[] = $start . " 00:00:00"; $types .= 's'; }
if ($endOk)   { $where[] = "sv.reported_at < DATE_ADD(?, INTERVAL 1 DAY)"; $binds[] = $end; $types .= 's'; }
if ($hasStatus) {
  $where[] = "LOWER(sv.status) NOT IN ('void','voided','canceled','cancelled')";
}
$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

/* ----- Violation level bucketing (Light / Moderate / Grave) ----- */
$LEVEL_CASE = "
  CASE
    WHEN (sv.offense_category REGEXP '(?i)grave' AND sv.offense_category NOT REGEXP '(?i)less')
         THEN 'Grave'
    WHEN (sv.offense_category REGEXP '(?i)moderate|less')
         THEN 'Moderate'
    ELSE 'Light'
  END
";

/* ----- Period/grouping CASE + ORDER for Time Series ----- */
switch ($period) {
  case 'weekly':
    // ISO-like week label: 2025-W03
    $PERIOD_SELECT = "CONCAT(YEAR(sv.reported_at), '-W', LPAD(WEEK(sv.reported_at, 3), 2, '0'))";
    $PERIOD_ORDER  = "YEARWEEK(sv.reported_at, 3)";
    break;
  case 'semiannual':
    // Half-year label: 2025-H1 / 2025-H2
    $PERIOD_SELECT = "CONCAT(YEAR(sv.reported_at), '-H', IF(MONTH(sv.reported_at) <= 6, 1, 2))";
    $PERIOD_ORDER  = "YEAR(sv.reported_at), IF(MONTH(sv.reported_at) <= 6, 1, 2)";
    break;
  case 'yearly':
    $PERIOD_SELECT = "CAST(YEAR(sv.reported_at) AS CHAR)";
    $PERIOD_ORDER  = "YEAR(sv.reported_at)";
    break;
  case 'monthly':
  default:
    // Month label: 2025-03
    $PERIOD_SELECT = "DATE_FORMAT(sv.reported_at, '%Y-%m')";
    $PERIOD_ORDER  = "YEAR(sv.reported_at), MONTH(sv.reported_at)";
    break;
}

/* ----- Helper to run a grouped query safely ----- */
function fetch_group($conn, $sql, $types, $binds) {
  $stmt = $conn->prepare($sql);
  if (!$stmt) { die("Prepare failed: " . $conn->error); }
  if ($types !== '') { $stmt->bind_param($types, ...$binds); }
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) { $rows[] = $r; }
  $stmt->close();
  return $rows;
}

/* ----- Total violations (for percentages) ----- */
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

/* ----- 1) By Institute ----- */
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

/* ----- 2) By Year Level ----- */
$sqlByYear = "
  SELECT COALESCE(NULLIF(TRIM(sa.level),''), '—') AS label,
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

/* ----- 3) By Course ----- */
$sqlByCourse = "
  SELECT COALESCE(NULLIF(TRIM(sa.course),''), '—') AS label,
         COUNT(*) AS violations,
         COUNT(DISTINCT sv.student_id) AS students
  FROM student_violation sv
  JOIN student_account sa ON sa.student_id = sv.student_id
  $whereSql
  GROUP BY label
  ORDER BY violations DESC, label ASC
";
$byCourse = fetch_group($conn, $sqlByCourse, $types, $binds);

/* ----- 4) By Violation Level ----- */
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

/* ----- 5) By Time Period (Weekly / Monthly / Semiannual / Yearly) ----- */
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

/* Helper for % */
function pct($part, $whole) {
  if ($whole <= 0) return '0%';
  return number_format(($part / $whole) * 100, 1) . '%';
}

/* Pretty period label subtitle */
function prettyPeriodName($p) {
  return [
    'weekly'     => 'Weekly',
    'monthly'    => 'Monthly',
    'semiannual' => 'Every 6 Months',
    'yearly'     => 'Yearly'
  ][$p] ?? 'Monthly';
}

function prettyRangeLabel($startOk, $start, $endOk, $end) {
  if (!$startOk && !$endOk) {
    return 'All recorded dates';
  }

  $from = $startOk ? $start : 'Earliest';
  $to   = $endOk ? $end : 'Latest';

  if ($from === $to) {
    return $from;
  }

  return $from . ' to ' . $to;
}

function pluralLabel($count, $singular, $plural = null) {
  $plural = $plural ?? ($singular . 's');
  return ($count === 1) ? $singular : $plural;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Student Violations - Analytics</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body.summary-report-page{
    --bg:#f4f6fb;
    --text:#141b2a;
    --muted:#6b7280;
    --surface:#ffffff;
    --surface-muted:#f8f9fd;
    --border:#e5e7f3;
    --primary:#8c1c13;
    --shadow:0 24px 40px -30px rgba(17,24,39,.35);
    margin:0;
    background:linear-gradient(180deg, rgba(140,28,19,.05) 0%, rgba(244,246,251,1) 260px);
    color:var(--text);
    font:15px/1.55 "Inter","Segoe UI",system-ui,-apple-system,BlinkMacSystemFont,"Helvetica Neue",Arial,sans-serif;
    overflow-x:hidden;
    padding:0;
  }
  body.summary-report-page *{box-sizing:border-box;}
  .summary-report{
    width:min(1200px,100%);
    margin:40px auto 80px;
    padding:0 clamp(20px,4vw,48px);
    display:flex;
    flex-direction:column;
    gap:28px;
  }
  @media (min-width:1100px){
    body.summary-report-page{
      --sidebar-offset:250px;
      padding-left:calc(var(--sidebar-offset,240px) + clamp(24px,4vw,72px));
      padding-right:clamp(32px,4vw,80px);
    }
    .summary-report{
      margin:48px auto 96px;
      width:min(1200px,100%);
    }
  }
  @media (max-width:600px){
    .summary-report{
      margin:32px auto 64px;
      padding:0 18px;
    }
  }
  .card{background:var(--surface);border-radius:22px;padding:28px;border:1px solid var(--border);box-shadow:var(--shadow);}
  .card.accent{background:linear-gradient(135deg,rgba(140,28,19,.12),rgba(255,255,255,.92));border:1px solid rgba(140,28,19,.25);}
  .page-header{display:flex;flex-wrap:wrap;gap:24px;align-items:flex-end;justify-content:space-between;}
  .page-header h1{margin:6px 0 0;font-size:2.05rem;font-weight:700;letter-spacing:-.01em;}
  .page-header .lead{margin:12px 0 0;color:var(--muted);max-width:48ch;}
  .eyebrow{font-size:.75rem;text-transform:uppercase;letter-spacing:.3em;font-weight:700;color:rgba(140,28,19,.8);margin:0;}
  .header-meta{display:flex;flex-wrap:wrap;gap:12px;}
  .chip{padding:12px 18px;border-radius:18px;background:rgba(255,255,255,.9);border:1px solid rgba(140,28,19,.18);min-width:190px;display:flex;flex-direction:column;gap:4px;}
  .chip-muted{background:var(--surface);border-color:var(--border);}
  .chip-label{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.18em;color:rgba(17,24,39,.6);}
  .chip-value{font-size:.95rem;font-weight:600;color:var(--text);}
  .chip-value strong{color:var(--primary);}
  .filters{display:flex;flex-direction:column;gap:24px;}
  .filters-grid{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));}
  .control label{font-weight:600;font-size:.9rem;margin-bottom:8px;color:var(--text);}
  .control input[type=date], .control select{width:100%;padding:12px 14px;border-radius:14px;border:1px solid var(--border);background:var(--surface-muted);font-size:.95rem;color:var(--text);transition:border-color .18s, box-shadow .18s, background .18s;}
  .control input[type=date]:focus, .control select:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(140,28,19,.15);outline:none;background:#fff;}
  .filters-actions{display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;}
  .btn{appearance:none;background:var(--primary);color:#fff;border:none;border-radius:14px;padding:12px 28px;font-weight:700;cursor:pointer;font-size:.98rem;letter-spacing:.04em;transition:transform .18s, box-shadow .18s, background .18s;}
  .btn:hover{transform:translateY(-1px);box-shadow:0 18px 32px -20px rgba(140,28,19,.6);}
  .btn:focus-visible{outline:3px solid rgba(140,28,19,.4);outline-offset:2px;}
  .status-text{color:var(--muted);font-size:.9rem;}
  .kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:20px;}
  .metric-card{
    position:relative;
    background:var(--surface);
    border-radius:22px;
    border:1px solid var(--border);
    padding:26px 28px 28px;
    box-shadow:var(--shadow);
    display:flex;
    flex-direction:column;
    gap:18px;
    overflow:hidden;
  }
  .metric-card::after{
    content:"";
    position:absolute;
    inset:-60% 40% auto -40%;
    height:160%;
    background:radial-gradient(ellipse at top, rgba(140,28,19,.22) 0%, rgba(140,28,19,0) 70%);
    opacity:.65;
    pointer-events:none;
  }
  .metric-card .label{
    font-size:.72rem;
    font-weight:700;
    letter-spacing:.22em;
    text-transform:uppercase;
    color:rgba(17,24,39,.55);
    position:relative;
    z-index:1;
  }
  .metric-card .value{
    position:relative;
    z-index:1;
    font-size:2.6rem;
    font-weight:700;
    color:var(--primary);
    line-height:1.1;
    display:flex;
    flex-direction:column;
    gap:6px;
  }
  .metric-card .value small{
    font-size:.78rem;
    font-weight:600;
    letter-spacing:.18em;
    text-transform:uppercase;
    color:rgba(17,24,39,.55);
  }
  .grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    gap:20px;
  }
  @media (min-width:1200px){
    .grid{
      grid-template-columns:repeat(2,minmax(0,1fr));
    }
  }
  .data-card{
    display:flex;
    flex-direction:column;
    height:100%;
  }
  .data-card h3{margin:0;font-size:1.05rem;font-weight:600;color:var(--text);}
  .data-card table{margin-top:16px;flex:1;}
  table{width:100%;border-collapse:collapse;}
  th,td{padding:14px 12px;border-bottom:1px solid rgba(229,231,243,.8);text-align:center;}
  th{text-transform:uppercase;font-size:.72rem;letter-spacing:.18em;color:rgba(17,24,39,.55);}
  td{font-size:.95rem;}
  td:first-child{font-weight:600;color:var(--text);}
  tbody tr:hover{background:rgba(140,28,19,.05);}
  tbody tr:last-child td{border-bottom:none;}
  .empty{text-align:center;color:var(--muted);font-style:italic;padding:12px 0;}
  .full-width{grid-column:1 / -1;}
  @media (max-width:900px){
    .summary-report{margin-top:24px;}
    .card{padding:22px;}
    .page-header h1{font-size:1.85rem;}
    .metric-card{padding:22px 24px;}
    .metric-card .value{font-size:2.1rem;}
    .metric-card .value small{font-size:.72rem;}
  }
</style>
</head>
<body class="summary-report-page">
  <main class="summary-report">
    <section class="card accent page-header">
      <div>
        <p class="eyebrow">Summary Report</p>
        <h1>Student Violations Analytics</h1>
        <p class="lead">Monitor violation trends across institutes, year levels, courses, violation levels, and time periods.</p>
      </div>
      <div class="header-meta">
        <div class="chip">
          <span class="chip-label">Current View</span>
          <span class="chip-value"><?= htmlspecialchars(prettyPeriodName($period)) ?></span>
        </div>
        <div class="chip chip-muted">
          <span class="chip-label">Date Range</span>
          <span class="chip-value"><?= htmlspecialchars(prettyRangeLabel($startOk, $start, $endOk, $end)) ?></span>
        </div>
      </div>
    </section>

    <form class="card filters" method="get" action="">
      <div class="filters-grid">
        <div class="control">
          <label for="start">Start date</label>
          <input type="date" id="start" name="start" value="<?= htmlspecialchars($startOk ? $start : '') ?>">
        </div>
        <div class="control">
          <label for="end">End date</label>
          <input type="date" id="end" name="end" value="<?= htmlspecialchars($endOk ? $end : '') ?>">
        </div>
        <div class="control">
          <label for="period">Period</label>
          <select id="period" name="period">
            <option value="weekly"     <?= $period==='weekly'?'selected':'' ?>>Weekly</option>
            <option value="monthly"    <?= $period==='monthly'?'selected':'' ?>>Monthly</option>
            <option value="semiannual" <?= $period==='semiannual'?'selected':'' ?>>Every 6 Months</option>
            <option value="yearly"     <?= $period==='yearly'?'selected':'' ?>>Yearly</option>
          </select>
        </div>
      </div>
      <div class="filters-actions">
        <button class="btn" type="submit">Apply Filters</button>
        <p class="status-text">
          Showing <?= htmlspecialchars(prettyRangeLabel($startOk, $start, $endOk, $end)) ?> &middot; Period:
          <strong><?= htmlspecialchars(prettyPeriodName($period)) ?></strong>
        </p>
      </div>
    </form>

    <section class="kpi">
      <article class="metric-card">
        <span class="label">Total Violations</span>
        <span class="value">
          <?= number_format($totalViolations) ?>
          <small><?= htmlspecialchars(pluralLabel($totalViolations, 'case recorded', 'cases recorded')) ?></small>
        </span>
      </article>
      <article class="metric-card">
        <span class="label">Unique Students</span>
        <span class="value">
          <?= number_format($uniqueStudents) ?>
          <small><?= htmlspecialchars(pluralLabel($uniqueStudents, 'student involved', 'students involved')) ?></small>
        </span>
      </article>
    </section>

    <div class="grid">
      <section class="card data-card">
        <h3>By Institute</h3>
        <table>
          <thead><tr><th>Institute</th><th>Violations</th><th>%</th><th>Students</th></tr></thead>
          <tbody>
          <?php foreach ($byInstitute as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['label']) ?></td>
              <td><?= number_format($r['violations']) ?></td>
              <td><?= htmlspecialchars(pct((int)$r['violations'], $totalViolations)) ?></td>
              <td><?= number_format($r['students']) ?></td>
            </tr>
          <?php endforeach; if (!$byInstitute): ?>
            <tr><td colspan="4" class="empty">No data available</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>

      <section class="card data-card">
        <h3>By Year Level</h3>
        <table>
          <thead><tr><th>Year Level</th><th>Violations</th><th>%</th><th>Students</th></tr></thead>
          <tbody>
          <?php foreach ($byYear as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['label']) ?></td>
              <td><?= number_format($r['violations']) ?></td>
              <td><?= htmlspecialchars(pct((int)$r['violations'], $totalViolations)) ?></td>
              <td><?= number_format($r['students']) ?></td>
            </tr>
          <?php endforeach; if (!$byYear): ?>
            <tr><td colspan="4" class="empty">No data available</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>

      <section class="card data-card">
        <h3>By Course</h3>
        <table>
          <thead><tr><th>Course</th><th>Violations</th><th>%</th><th>Students</th></tr></thead>
          <tbody>
          <?php foreach ($byCourse as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['label']) ?></td>
              <td><?= number_format($r['violations']) ?></td>
              <td><?= htmlspecialchars(pct((int)$r['violations'], $totalViolations)) ?></td>
              <td><?= number_format($r['students']) ?></td>
            </tr>
          <?php endforeach; if (!$byCourse): ?>
            <tr><td colspan="4" class="empty">No data available</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>

      <section class="card data-card">
        <h3>By Violation Level</h3>
        <table>
          <thead><tr><th>Level</th><th>Violations</th><th>%</th><th>Students</th></tr></thead>
          <tbody>
          <?php foreach ($byLevel as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['label']) ?></td>
              <td><?= number_format($r['violations']) ?></td>
              <td><?= htmlspecialchars(pct((int)$r['violations'], $totalViolations)) ?></td>
              <td><?= number_format($r['students']) ?></td>
            </tr>
          <?php endforeach; if (!$byLevel): ?>
            <tr><td colspan="4" class="empty">No data available</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>
    </div>

    <section class="card data-card full-width">
      <h3>By Time Period (<?= htmlspecialchars(prettyPeriodName($period)) ?>)</h3>
      <table>
        <thead><tr><th>Period</th><th>Violations</th><th>%</th><th>Students</th></tr></thead>
        <tbody>
        <?php foreach ($byPeriod as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['period_label']) ?></td>
            <td><?= number_format($r['violations']) ?></td>
            <td><?= htmlspecialchars(pct((int)$r['violations'], $totalViolations)) ?></td>
            <td><?= number_format($r['students']) ?></td>
          </tr>
        <?php endforeach; if (!$byPeriod): ?>
          <tr><td colspan="4" class="empty">No data available</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </section>

  </main>
</body>
</html>
<?php $conn->close(); ?>
