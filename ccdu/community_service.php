<?php
// /MoralMatrix/ccdu/community_service.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/../config.php';

$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* ------- Access control ------- */
$role = strtolower($_SESSION['account_type'] ?? '');
$isPrivileged = in_array($role, ['ccdu','administrator','super_admin','faculty','security','validator']);
if (!$isPrivileged) { header("Location: /login.php"); exit; }

/* ------- Filters ------- */
$studentFilter = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$validatorId   = isset($_GET['validator_id']) ? (int)$_GET['validator_id'] : 0;
$period        = isset($_GET['period']) ? strtolower(trim($_GET['period'])) : 'month'; // week|month|6mo|year|all

/* ------- Pagination (simple & safe) ------- */
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, min(48, (int)$_GET['per_page'])) : 6;

/* Small helper for safe querystrings (preserve active filters) */
function cs_qs(array $extra = []): string {
  $params = $_GET;
  foreach ($extra as $k => $v) {
    if ($v === null) unset($params[$k]);
    else $params[$k] = $v;
  }
  return '?' . http_build_query($params);
}

$today = new DateTime('today');
switch ($period) {
  case 'week':  $startDate = (clone $today)->modify('-6 days'); break;
  case '6mo':   $startDate = (clone $today)->modify('-6 months'); break;
  case 'year':  $startDate = (clone $today)->modify('-1 year'); break;
  case 'all':   $startDate = null; break;
  case 'month':
  default:      $startDate = (clone $today)->modify('-30 days'); break;
}

/* ------- Existence checks ------- */
$hasEntries = false;
if ($res = $conn->query("SHOW TABLES LIKE 'community_service_entries'")) {
  $hasEntries = ($res->num_rows > 0);
  $res->close();
}

/* ------- Validator list for dropdown ------- */
$validators = [];
if ($vs = $conn->query("SELECT validator_id, v_username FROM validator_account ORDER BY v_username ASC")) {
  while ($row = $vs->fetch_assoc()) $validators[] = $row;
  $vs->close();
}

/* ------- Helpers ------- */
function evidence_url_ccdu(string $p): string {
  $p = trim($p);
  if ($p === '') return '';
  if (preg_match('~^https?://~i', $p)) return $p;
  if ($p[0] === '/') return $p;
  $p = ltrim($p, '/');
  if (strpos($p, 'uploads/') === 0) return '../validator/' . $p;
  if (strpos($p, 'validator/uploads/') === 0) return '../' . $p;
  return '../validator/uploads/' . $p;
}
function student_profile_url(array $student): string {
  $file = !empty($student['photo']) ? $student['photo'] : 'placeholder.png';
  $fs   = __DIR__ . '/../admin/uploads/' . $file;
  return is_file($fs) ? ('../admin/uploads/' . $file) : '../admin/uploads/placeholder.png';
}
function compute_required_hours(mysqli $conn, string $student_id): float {
  $hasStatus = false;
  if ($r = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'status'")) { $hasStatus = ($r->num_rows > 0); $r->close(); }
  $sql = "SELECT offense_category FROM student_violation WHERE student_id = ? ";
  if ($hasStatus) $sql .= "AND LOWER(status) NOT IN ('void','voided','canceled','cancelled') ";
  $sql .= "ORDER BY reported_at ASC, violation_id ASC";
  $grave = 0; $bucket = 0;
  $st = $conn->prepare($sql);
  $st->bind_param("s", $student_id);
  $st->execute();
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) {
    $raw = strtolower(trim((string)$row['offense_category']));
    $isGrave = (preg_match('/\bgrave\b/i', $raw) && !preg_match('/\bless\b/i', $raw));
    if ($isGrave) $grave++; else $bucket++;
  }
  $st->close();
  return ($grave * 20) + (intdiv($bucket, 3) * 10);
}
function total_logged_hours(mysqli $conn, string $student_id): float {
  $sum = $conn->prepare("SELECT COALESCE(SUM(hours),0) FROM community_service_entries WHERE student_id = ?");
  $sum->bind_param("s", $student_id);
  $sum->execute(); $sum->bind_result($h); $sum->fetch(); $sum->close();
  return (float)$h;
}
function latest_entry(mysqli $conn, string $student_id, ?string $minServiceDate, int $validatorId): ?array {
  $sql = "
    SELECT e.entry_id, e.service_date, e.created_at, e.hours, e.remarks, e.comment, e.photo_paths,
           e.violation_id, e.validator_id, va.v_username
    FROM community_service_entries e
    LEFT JOIN validator_account va ON va.validator_id = e.validator_id
    WHERE e.student_id = ?
  ";
  $types = 's'; $params = [$student_id];
  if ($validatorId > 0) { $sql .= " AND e.validator_id = ?"; $types .= 'i'; $params[] = $validatorId; }
  if ($minServiceDate)  { $sql .= " AND e.service_date >= ?"; $types .= 's'; $params[] = $minServiceDate; }
  $sql .= " ORDER BY e.service_date DESC, e.created_at DESC, e.entry_id DESC LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param($types, ...$params);
  $st->execute();
  $res = $st->get_result();
  $row = $res->fetch_assoc();
  $st->close();
  return $row ?: null;
}
function count_entries(mysqli $conn, string $student_id, ?string $minServiceDate, int $validatorId): int {
  $sql = "SELECT COUNT(*) FROM community_service_entries WHERE student_id = ?";
  $types = 's'; $params = [$student_id];
  if ($validatorId > 0) { $sql .= " AND validator_id = ?"; $types .= 'i'; $params[] = $validatorId; }
  if ($minServiceDate)  { $sql .= " AND service_date >= ?"; $types .= 's'; $params[] = $minServiceDate; }
  $st = $conn->prepare($sql);
  $st->bind_param($types, ...$params);
  $st->execute(); $st->bind_result($c); $st->fetch(); $st->close();
  return (int)$c;
}
function ongoing_student_ids(mysqli $conn, bool $hasEntries): array {
  $hasStatus = false;
  if ($r = $conn->query("SHOW COLUMNS FROM student_violation LIKE 'status'")) { $hasStatus = ($r->num_rows > 0); $r->close(); }
  $sqlReq = "
    SELECT student_id,
      SUM(CASE WHEN (LOWER(offense_category) LIKE '%grave%' AND LOWER(offense_category) NOT LIKE '%less%')
               THEN 1 ELSE 0 END) AS grave_cnt,
      SUM(CASE WHEN NOT (LOWER(offense_category) LIKE '%grave%' AND LOWER(offense_category) NOT LIKE '%less%')
               THEN 1 ELSE 0 END) AS non_grave_cnt
    FROM student_violation
  ";
  if ($hasStatus) $sqlReq .= " WHERE LOWER(status) NOT IN ('void','voided','canceled','cancelled') ";
  $sqlReq .= " GROUP BY student_id";
  $requiredByStudent = [];
  if ($rs = $conn->query($sqlReq)) {
    while ($row = $rs->fetch_assoc()) {
      $sid = (string)$row['student_id'];
      $grave = (int)($row['grave_cnt'] ?? 0);
      $minor = (int)($row['non_grave_cnt'] ?? 0);
      $requiredByStudent[$sid] = ($grave * 20) + (intdiv($minor, 3) * 10);
    }
    $rs->close();
  }
  if (empty($requiredByStudent)) return [];
  $loggedByStudent = [];
  if ($hasEntries) {
    if ($rs = $conn->query("SELECT student_id, COALESCE(SUM(hours),0) AS total FROM community_service_entries GROUP BY student_id")) {
      while ($row = $rs->fetch_assoc()) {
        $loggedByStudent[(string)$row['student_id']] = (float)$row['total'];
      }
      $rs->close();
    }
  }
  $ongoing = [];
  foreach ($requiredByStudent as $sid => $req) {
    $logged = (float)($loggedByStudent[$sid] ?? 0.0);
    if ($req > 0 && $req - $logged > 0.00001) $ongoing[$sid] = true;
  }
  return array_keys($ongoing);
}

/* ------- Students for cards ------- */
$studentIds = [];
if ($hasEntries) {
  $sql = "SELECT DISTINCT e.student_id FROM community_service_entries e WHERE 1=1";
  $types = ''; $params = [];
  if ($studentFilter !== '') { $sql .= " AND e.student_id = ?"; $types .= 's'; $params[] = $studentFilter; }
  if ($validatorId > 0)      { $sql .= " AND e.validator_id = ?"; $types .= 'i'; $params[] = $validatorId; }
  if ($startDate)            { $sql .= " AND e.service_date >= ?"; $types .= 's'; $params[] = $startDate->format('Y-m-d'); }
  $sql .= " ORDER BY e.student_id ASC";
  $st = $conn->prepare($sql);
  if ($types !== '') $st->bind_param($types, ...$params);
  $st->execute();
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) $studentIds[] = $row['student_id'];
  $st->close();
}
$ongoingIds = ongoing_student_ids($conn, $hasEntries);
$studentIds = array_values(array_unique(array_merge($studentIds, $ongoingIds)));
if ($studentFilter !== '' && !in_array($studentFilter, $studentIds, true)) $studentIds[] = $studentFilter;
sort($studentIds, SORT_STRING);

/* Build full cards (we'll paginate after building to preserve your current logic) */
$cards = [];
if (!empty($studentIds)) {
  $stStudent = $conn->prepare("
    SELECT student_id, first_name, middle_name, last_name, course, level, section, institute, photo
    FROM student_account WHERE student_id = ?
  ");
  foreach ($studentIds as $sid) {
    $stStudent->bind_param("s", $sid);
    $stStudent->execute();
    $student = $stStudent->get_result()->fetch_assoc() ?: ['student_id'=>$sid,'first_name'=>'','middle_name'=>'','last_name'=>'','course'=>'','level'=>'','section'=>'','institute'=>'','photo'=>''];

    $name = trim(implode(' ', array_filter([$student['first_name'] ?? '', $student['middle_name'] ?? '', $student['last_name'] ?? ''])));
    $ys   = trim(($student['level'] ?? '') . ((!empty($student['level']) && !empty($student['section'])) ? '-' : '') . ($student['section'] ?? ''));
    $profileUrl = student_profile_url($student);

    $required = compute_required_hours($conn, $sid);
    $logged   = total_logged_hours($conn, $sid);
    $remaining= max(0, $required - $logged);

    $latest   = latest_entry($conn, $sid, $startDate ? $startDate->format('Y-m-d') : null, $validatorId);
    $inCount  = $hasEntries ? count_entries($conn, $sid, $startDate ? $startDate->format('Y-m-d') : null, $validatorId) : 0;

    $thumbUrl = '';
    if ($latest && !empty($latest['photo_paths'])) {
      $dec = json_decode($latest['photo_paths'], true);
      if (is_array($dec) && !empty($dec)) $thumbUrl = evidence_url_ccdu((string)$dec[0]);
    }

    $cards[] = [
      'student_id'   => $sid,
      'name'         => $name,
      'course'       => $student['course'] ?? '',
      'yearsection'  => $ys,
      'institute'    => $student['institute'] ?? '',
      'profile'      => $profileUrl,
      'required'     => $required,
      'logged'       => $logged,
      'remaining'    => $remaining,
      'inCount'      => $inCount,
      'latest'       => $latest,
      'thumb'        => $thumbUrl,
    ];
  }
  $stStudent->close();
}

/* ------- Pagination slicing ------- */
$totalResults = count($cards);
$totalPages  = max(1, (int)ceil($totalResults / $perPage));
$page        = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;
$cardsPage   = array_slice($cards, $offset, $perPage);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Community Service Students</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* ------------- SCOPE EVERYTHING TO THIS PAGE (.mmcs) ------------- */
.mmcs{ --header-h:64px; --sidebar-w:256px; --pad: clamp(16px,2.2vw,24px); --max:1180px;
       --bg:#f6f8fc; --surface:#fff; --text:#0f172a; --muted:#64748b; --border:#e5e7eb;
       --primary:#8c1c13; --ok:#0f766e; --warn:#b91c1c; --ring: rgba(140,28,19,.25); }

.mmcs .mmcs-shell{
  min-height:100dvh;
  padding: calc(var(--header-h) + 16px) var(--pad) 110px calc(var(--sidebar-w) + var(--pad));
  background: var(--bg);
  color: var(--text);
}
.mmcs .mmcs-container{ max-width:var(--max); margin:0 auto; }

/* Head */
.mmcs .pagehead{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px;margin:2px 0 14px}
.mmcs h1{margin:0;font-size:1.6rem;letter-spacing:.2px}

/* Filter — force single row on wide screens */
.mmcs .filter{
  position:sticky; top: calc(var(--header-h) + 12px); z-index:30;
  background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:12px;
  display:flex; gap:12px; align-items:end; flex-wrap:nowrap;
  box-shadow:0 10px 26px -20px rgba(17,24,39,.25);
}
.mmcs .field{display:flex;flex-direction:column;gap:6px;min-width:200px}
.mmcs .field.grow{min-width:260px;flex:1}
.mmcs label{font-size:.82rem;font-weight:700;color:#374151}
.mmcs input,.mmcs select{
  height:40px;border:1px solid var(--border);border-radius:10px;padding:8px 12px;background:#fff;outline:none;
}
.mmcs input:focus,.mmcs select:focus{box-shadow:0 0 0 4px var(--ring); border-color: var(--primary)}
.mmcs .btn{appearance:none;border:1px solid var(--border);border-radius:10px;padding:9px 16px;font-weight:700;cursor:pointer;background:#fff}
.mmcs .btn.primary{border-color:transparent;background:var(--primary);color:#fff}
.mmcs .btn[disabled]{opacity:.5;cursor:not-allowed}

/* Search input with icon */
.mmcs .input-icon{position:relative}
.mmcs .input-icon svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:18px;height:18px;pointer-events:none;opacity:.6}
.mmcs .input-icon input{padding-left:36px}

/* Summary */
.mmcs .summary{display:flex;gap:10px;align-items:center;margin:12px 0 6px;color:var(--muted);font-size:.92rem}

/* Grid + Card */
.mmcs .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:16px;margin-top:10px}
.mmcs .card{display:block;text-decoration:none;color:inherit;background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:14px;box-shadow:0 12px 28px -22px rgba(17,24,39,.25);transition:transform .12s, box-shadow .12s}
.mmcs .card:hover{transform:translateY(-2px);box-shadow:0 18px 34px -20px rgba(17,24,39,.28)}

/* Card top */
.mmcs .card-top{display:grid;grid-template-columns:64px 1fr 72px;align-items:center;gap:12px}
.mmcs .avatar{width:56px;height:56px;border-radius:12px;object-fit:cover;border:1px solid var(--border);background:#fff;justify-self:start}
.mmcs .title{font-weight:800;line-height:1.2}
.mmcs .meta{display:flex;flex-wrap:wrap;gap:8px;color:var(--muted);font-size:.9rem;margin-top:3px}

.mmcs .aside{display:flex;flex-direction:column;align-items:flex-end;gap:8px;width:72px}
.mmcs .thumb{width:56px;height:56px;border-radius:12px;object-fit:cover;border:1px solid var(--border);background:#fff}
.mmcs .badge{font-size:.74rem;font-weight:800;border-radius:999px;padding:4px 10px;border:1px solid var(--border);background:#fff;color:#0b4a46}
.mmcs .badge.warn{color:var(--warn)} .mmcs .badge.ok{color:var(--ok)}

/* Progress + stats */
.mmcs .progress{height:8px;border-radius:999px;background:#f3f4f6;border:1px solid var(--border);overflow:hidden;margin:10px 0 0}
.mmcs .progress > span{display:block;height:100%;background:linear-gradient(90deg,#fecaca,#ef4444);width:0}
.mmcs .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px}
.mmcs .stat{border:1px solid var(--border);border-radius:12px;padding:10px;background:#fff;text-align:center}
.mmcs .stat b{display:block;font-size:.75rem;color:#6b7280;margin-bottom:4px}
.mmcs .stat .val{font-weight:800} .mmcs .val.ok{color:var(--ok)} .mmcs .val.warn{color:var(--warn)}

/* Chips */
.mmcs .chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.mmcs .chip{border:1px solid var(--border);border-radius:999px;padding:4px 10px;font-size:.78rem;background:#fff}

/* Empty */
.mmcs .empty{padding:18px;border:1px dashed var(--border);border-radius:14px;text-align:center;color:#64748b;background:#fff;margin-top:14px}

/* Pager — updated to match screenshot layout */
.mmcs .pager{
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-top:16px;
  color:var(--muted);
}
.mmcs .pager-info{font-size:.95rem}
.mmcs .pager-actions{display:flex;gap:12px}
.mmcs .pager .btn{padding:8px 14px;border-radius:12px}

/* Remove underline from pager links and make them look like buttons */
.mmcs .pager .btn,
.mmcs .pager .btn:visited,
.mmcs .pager .btn:active {
  text-decoration: none !important;
  box-shadow: none;
  color: inherit;
}

/* Optional: Add a little hover effect for clarity */
.mmcs .pager .btn:hover:not([aria-disabled="true"]) {
  background: #f3f4f6;
  color: #8c1c13;
  border-color: #8c1c13;
}

/* Optional: Make disabled buttons look muted */
.mmcs .pager .btn[aria-disabled="true"] {
  opacity: 0.5;
  pointer-events: none;
  background: #f6f8fc;
  color: #b0b0b0;
  border-color: #e5e7eb;
}

/* Modal (scoped) */
.mmcs .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;z-index:3990}
.mmcs .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;padding:24px;z-index:4000}
.mmcs .modal.open,.mmcs .modal-backdrop.open{display:flex}
.mmcs .modal-card{max-width:960px;width:100%;background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 30px 70px rgba(0,0,0,.35);max-height:90vh;overflow:auto}
.mmcs .modal-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border);position:sticky;top:0;background:#fff;z-index:1}
.mmcs .modal-title{margin:0;font-size:1rem;font-weight:800}
.mmcs .modal-close{appearance:none;border:1px solid var(--border);background:#fff;border-radius:999px;width:34px;height:34px;font-weight:700;cursor:pointer}
.mmcs .modal-body{padding:14px}

@media (max-width:1100px){
  .mmcs .filter{flex-wrap:wrap}
}
@media (max-width:1024px){
  .mmcs{ --sidebar-w:72px; }
  .mmcs .grid{grid-template-columns:repeat(auto-fill,minmax(320px,1fr))}
}
@media (max-width:520px){
  .mmcs .card-top{grid-template-columns:56px 1fr 56px}
  .mmcs .aside{width:56px}
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div id="mmcs" class="mmcs">
  <main id="mmcs-shell" class="mmcs-shell">
    <div class="mmcs-container">

      <div class="pagehead">
        <h1>Community Service Students</h1>
      </div>

      <form class="filter" method="get" action="">
        <div class="field grow">
          <label for="student_id">Student ID</label>
          <div class="input-icon">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
              <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"></circle>
              <path d="M20 20L17 17" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
            </svg>
            <input
              type="text"
              id="student_id"
              name="student_id"
              value="<?= htmlspecialchars($studentFilter) ?>"
              placeholder="e.g., 2024-1234"
              inputmode="numeric"
              title="Search by Student ID">
          </div>
        </div>

        <div class="field" style="min-width:220px">
          <label for="validator_id">Validator</label>
          <select id="validator_id" name="validator_id">
            <option value="0">All validators</option>
            <?php foreach ($validators as $v): ?>
              <option value="<?= (int)$v['validator_id'] ?>" <?= $validatorId===(int)$v['validator_id']?'selected':'' ?>>
                <?= htmlspecialchars($v['v_username'] ?: ('ID '.$v['validator_id'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="min-width:220px">
          <label for="period">Period</label>
          <select id="period" name="period">
            <option value="week"  <?= $period==='week'?'selected':'' ?>>Last 7 days</option>
            <option value="month" <?= $period==='month'?'selected':'' ?>>Last 30 days</option>
            <option value="6mo"   <?= $period==='6mo'?'selected':'' ?>>Last 6 months</option>
            <option value="year"  <?= $period==='year'?'selected':'' ?>>Last year</option>
            <option value="all"   <?= $period==='all'?'selected':'' ?>>All time</option>
          </select>
        </div>

        <!-- persist per_page; reset to page 1 on apply -->
        <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">

        <div class="field" style="min-width:auto;display:flex;gap:8px;">
          <button class="btn primary" type="submit">Apply</button>
          <a class="btn" href="community_service.php" title="Reset filters">Reset</a>
        </div>
      </form>

      <?php if (!$hasEntries && empty($cards)): ?>
        <div class="empty">No <code>community_service_entries</code> table found and no students with ongoing community service.</div>
      <?php elseif (empty($cardsPage)): ?>
        <div class="empty">No students match your filters.</div>
      <?php else: ?>
        <div class="summary">
          Showing <strong><?= count($cardsPage) ?></strong> student<?= count($cardsPage)===1?'':'s' ?>
          <?= $studentFilter ? ' for ID '.htmlspecialchars($studentFilter) : '' ?>
          <?= $validatorId>0 ? ' • filtered by validator' : '' ?>
          <?= $period!=='all' ? ' • period: '.$period : '' ?>
        </div>

        <div class="grid">
          <?php foreach ($cardsPage as $c):
            $latest = $c['latest'];
            $lastDate = $latest ? ($latest['service_date'] ?? $latest['created_at']) : null;
            $detailsUrl = 'community_service_view.php?student_id=' . urlencode($c['student_id']);
            $req = max(0.0, (float)$c['required']);
            $log = max(0.0, (float)$c['logged']);
            $rem = max(0.0, (float)$c['remaining']);
            $pct = ($req > 0.000001) ? min(100, max(0, round(($log / $req) * 100))) : ($log > 0 ? 100 : 0);
            $done = ($rem <= 0.00001);
          ?>
            <a class="card js-student-card" href="<?= $detailsUrl ?>" data-modal-url="<?= $detailsUrl ?>" aria-label="Open details for <?= htmlspecialchars($c['name'] ?: $c['student_id']) ?>">
              <div class="card-top">
                <img class="avatar" src="<?= htmlspecialchars($c['profile']) ?>" alt="Student">
                <div>
                  <div class="title"><?= htmlspecialchars($c['name'] ?: $c['student_id']) ?></div>
                  <div class="meta">
                    <span>ID <?= htmlspecialchars($c['student_id']) ?></span>
                    <?php if ($c['course']): ?><span>• <?= htmlspecialchars($c['course']) ?></span><?php endif; ?>
                    <?php if ($c['yearsection']): ?><span>• <?= htmlspecialchars($c['yearsection']) ?></span><?php endif; ?>
                  </div>
                  <div class="chips">
                    <?php if ($c['institute']): ?><span class="chip"><?= htmlspecialchars($c['institute']) ?></span><?php endif; ?>
                    <span class="chip"><?= (int)$c['inCount'] ?> entr<?= $c['inCount']==1?'y':'ies' ?><?= $lastDate ? ' • last '.htmlspecialchars(date('M d, Y', strtotime($lastDate))) : '' ?></span>
                    <?php if ($latest && !empty($latest['v_username'])): ?>
                      <span class="chip">Validator: <?= htmlspecialchars($latest['v_username']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="aside">
                  <?php if ($c['thumb']): ?>
                    <img class="thumb" src="<?= htmlspecialchars($c['thumb']) ?>" alt="Latest evidence">
                  <?php endif; ?>
                  <span class="badge <?= $done ? 'ok' : 'warn' ?>"><?= $done ? 'Completed' : 'Ongoing' ?></span>
                </div>
              </div>

              <div class="progress" aria-hidden="true" title="Logged <?= number_format($log,2) ?> of <?= number_format($req,2) ?> hours">
                <span style="width: <?= $pct ?>%"></span>
              </div>

              <div class="stats" aria-label="Service hours">
                <div class="stat"><b>Required</b><div class="val"><?= number_format($req, 2) ?> h</div></div>
                <div class="stat"><b>Logged</b><div class="val ok"><?= number_format($log, 2) ?> h</div></div>
                <div class="stat"><b>Remaining</b><div class="val <?= $rem>0?'warn':'ok' ?>"><?= number_format($rem, 2) ?> h</div></div>
              </div>

              <?php if ($latest && !empty($latest['remarks'])): ?>
                <div class="meta" style="margin-top:8px">Latest remarks: <em><?= htmlspecialchars($latest['remarks']) ?></em></div>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Pager (updated markup/layout only) -->
        <div class="pager" role="navigation" aria-label="Pagination">
          <?php $prevDisabled = $page <= 1; $nextDisabled = $page >= $totalPages; ?>
          <div class="pager-info">
            Page <b><?= (int)$page ?></b> of <b><?= (int)$totalPages ?></b> • <?= (int)$totalResults ?> total
          </div>
          <div class="pager-actions">
            <a class="btn" href="<?= $prevDisabled ? '#' : cs_qs(['page'=>max(1,$page-1)]) ?>" <?= $prevDisabled?'aria-disabled="true" tabindex="-1"':'' ?>>&larr; Prev</a>
            <a class="btn" href="<?= $nextDisabled ? '#' : cs_qs(['page'=>min($totalPages,$page+1)]) ?>" <?= $nextDisabled?'aria-disabled="true" tabindex="-1"':'' ?>>Next &rarr;</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Modal/backdrop -->
  <div id="cs-backdrop" class="modal-backdrop" aria-hidden="true"></div>
  <div id="cs-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="cs-title"  style = "margin-left: 14rem;">
    <div class="modal-card">
      <div class="modal-head">
        <h2 id="cs-title" class="modal-title">Community Service Details</h2>
        <button class="modal-close" type="button" aria-label="Close">&times;</button>
      </div>
      <div id="cs-body" class="modal-body" aria-live="polite"></div>
    </div>
  </div>
</div>

<script>
(function(){
  const wrap = document.getElementById('mmcs');
  const shell = document.getElementById('mmcs-shell');

  // Offsets are APPLIED ONLY to this page wrapper
  function applyOffsets(){
    let headerH = 64, sideW = 256;

    const header = document.querySelector('header, .topbar, .site-header, .app-header, #topbar');
    if (header) {
      const r = header.getBoundingClientRect();
      if (r.height > 40 && r.height < 200) headerH = Math.round(r.height);
    }

    // pick the visible left sidebar (fixed or sticky near left)
    const cands = document.querySelectorAll('.sidebar, #sidebar, nav[aria-label="Sidebar"], .sidenav, .left-sidebar');
    for (const el of cands) {
      const rect = el.getBoundingClientRect();
      const st = getComputedStyle(el);
      if (rect.width > 60 && rect.left <= 0 && (st.position === 'fixed' || st.position === 'sticky')) {
        sideW = Math.round(rect.width);
        break;
      }
    }

    wrap.style.setProperty('--header-h', headerH + 'px');
    wrap.style.setProperty('--sidebar-w', sideW + 'px');
  }
  applyOffsets();
  window.addEventListener('resize', applyOffsets);

  /* -------- Modal (scoped) -------- */
  const modal    = document.getElementById('cs-modal');
  const backdrop = document.getElementById('cs-backdrop');
  const body     = document.getElementById('cs-body');
  const btnClose = modal.querySelector('.modal-close');

  function openModalWith(url){
    const modalUrl = url + (url.includes('?') ? '&' : '?') + 'modal=1';
    body.setAttribute('aria-busy', 'true');
    body.textContent = 'Loading…';
    fetch(modalUrl, { credentials: 'same-origin' })
      .then(r => { if(!r.ok) throw new Error('Failed to load details'); return r.text(); })
      .then(html => {
        body.innerHTML = html; body.removeAttribute('aria-busy');
        modal.classList.add('open'); backdrop.classList.add('open'); document.body.style.overflow = 'hidden';
        if (!history.state || history.state.csModalOpen !== true) history.pushState({ csModalOpen: true }, '');
        btnClose.focus();
      })
      .catch(err => {
        body.innerHTML = '<div style="color:#b91c1c">Unable to load details: ' + (err && err.message ? err.message : 'Error') + '</div>';
        body.removeAttribute('aria-busy');
        modal.classList.add('open'); backdrop.classList.add('open'); document.body.style.overflow = 'hidden';
      });
  }
  function closeModal(){
    modal.classList.remove('open'); backdrop.classList.remove('open'); document.body.style.overflow = '';
    if (history.state && history.state.csModalOpen === true) history.back();
  }

  document.addEventListener('click', function(e){
    const card = e.target.closest('.js-student-card');
    if (!card) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    e.preventDefault();
    const url = card.getAttribute('data-modal-url') || card.getAttribute('href');
    if (url) openModalWith(url);
  });
  btnClose.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });
  window.addEventListener('popstate', function (){
    if (modal.classList.contains('open')) { modal.classList.remove('open'); backdrop.classList.remove('open'); document.body.style.overflow = ''; }
  });
})();
</script>

</body>
</html>
<?php $conn->close(); ?>
