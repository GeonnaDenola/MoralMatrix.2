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

/* ----- 1) By Institute ----- */
$sqlByInst = "
  SELECT COALESCE(NULLIF(TRIM(sa.institute),''), '—') AS label,
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
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Student Violations — Analytics</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--b:#e5e7eb;--tx:#111827;--mut:#6b7280;--card:#fff;--bg:#f8fafc;--pri:#8C1C13}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.55 system-ui,Segoe UI,Roboto,Arial}
  .wrap{max-width:1200px;margin:24px auto;padding:0 16px}
  h1{margin:0 0 8px}
  .sub{color:var(--mut);margin:0 0 18px}
  .filters{display:flex;gap:12px;align-items:end;margin:14px 0 18px;flex-wrap:wrap}
  .card{background:var(--card);border:1px solid var(--b);border-radius:14px;padding:14px;margin:12px 0;box-shadow:0 6px 20px rgba(0,0,0,.04)}
  label{font-weight:600;display:block;margin:0 0 6px}
  input[type=date], select{padding:8px 10px;border:1px solid var(--b);border-radius:10px;background:#fff}
  .btn{appearance:none;background:var(--pri);color:#fff;border:none;border-radius:999px;padding:10px 16px;font-weight:700;cursor:pointer}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid var(--b);text-align:left;vertical-align:top}
  th{font-size:.88rem;color:var(--mut);text-transform:uppercase;letter-spacing:.06em}
  .kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:10px 0}
  .k{background:#fff;border:1px solid var(--b);border-radius:12px;padding:12px}
  .k .lbl{font-size:.8rem;color:var(--mut)}
  .k .val{font-weight:800;font-size:1.6rem}
  .mut{color:var(--mut)}
</style>
</head>
<body>
  <div class="wrap">
    <h1>Student Violations — Analytics</h1>
    <p class="sub">Grouped by Institute • Year Level • Course • Violation Level • Time Period</p>

    <form class="filters card" method="get" action="">
      <div>
        <label for="start">Start date</label>
        <input type="date" id="start" name="start" value="<?= htmlspecialchars($startOk ? $start : '') ?>">
      </div>
      <div>
        <label for="end">End date</label>
        <input type="date" id="end" name="end" value="<?= htmlspecialchars($endOk ? $end : '') ?>">
      </div>
      <div>
        <label for="period">Period</label>
        <select id="period" name="period">
          <option value="weekly"     <?= $period==='weekly'?'selected':'' ?>>Weekly</option>
          <option value="monthly"    <?= $period==='monthly'?'selected':'' ?>>Monthly</option>
          <option value="semiannual" <?= $period==='semiannual'?'selected':'' ?>>Every 6 Months</option>
          <option value="yearly"     <?= $period==='yearly'?'selected':'' ?>>Yearly</option>
        </select>
      </div>
      <div>
        <label>&nbsp;</label>
        <button class="btn" type="submit">Apply</button>
      </div>
      <div class="mut">
        Showing:
        <?= $startOk ? htmlspecialchars($start) : '…' ?> to <?= $endOk ? htmlspecialchars($end) : '…' ?> •
        Period: <strong><?= htmlspecialchars(prettyPeriodName($period)) ?></strong>
      </div>
    </form>

    <div class="kpi">
      <div class="k"><div class="lbl">Total Violations</div><div class="val"><?= number_format($totalViolations) ?></div></div>
      <div class="k">
        <div class="lbl">Unique Students</div>
        <div class="val">
          <?php
            $uSql = "SELECT COUNT(DISTINCT sv.student_id) AS u FROM student_violation sv JOIN student_account sa ON sa.student_id = sv.student_id $whereSql";
            $u = fetch_group($conn, $uSql, $types, $binds);
            echo number_format((int)($u[0]['u'] ?? 0));
          ?>
        </div>
      </div>
    </div>

    <div class="grid">
      <!-- By Institute -->
      <section class="card">
        <h3 style="margin:0 0 8px">By Institute</h3>
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
            <tr><td colspan="4" class="mut">No data.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>

      <!-- By Year Level -->
      <section class="card">
        <h3 style="margin:0 0 8px">By Year Level</h3>
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
            <tr><td colspan="4" class="mut">No data.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>

      <!-- By Course -->
      <section class="card">
        <h3 style="margin:0 0 8px">By Course</h3>
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
            <tr><td colspan="4" class="mut">No data.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>

      <!-- By Violation Level -->
      <section class="card">
        <h3 style="margin:0 0 8px">By Violation Level</h3>
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
            <tr><td colspan="4" class="mut">No data.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>
    </div>

    <!-- NEW: By Time Period -->
    <section class="card">
      <h3 style="margin:0 0 8px">By Time Period (<?= htmlspecialchars(prettyPeriodName($period)) ?>)</h3>
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
          <tr><td colspan="4" class="mut">No data.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </section>

  </div>
</body>
</html>
<?php $conn->close(); ?>
