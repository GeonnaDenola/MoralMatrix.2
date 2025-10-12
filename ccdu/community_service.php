<?php
// /MoralMatrix/ccdu/community_service.php
// CCDU-facing read-only overview: one card per student with community service entries.
// Clicking a card opens a MODAL (popup) with student detail (fetched from community_service_view.php?student_id=...&modal=1)

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/../config.php';

$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* ------- Access control (read-only for CCDU + other privileged roles) ------- */
$role = strtolower($_SESSION['account_type'] ?? '');
$isPrivileged = in_array($role, ['ccdu','administrator','super_admin','faculty','security','validator']);
if (!$isPrivileged) { header("Location: /login.php"); exit; }

/* ------- Filters ------- */
$studentFilter = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$validatorId   = isset($_GET['validator_id']) ? (int)$_GET['validator_id'] : 0;
$period        = isset($_GET['period']) ? strtolower(trim($_GET['period'])) : 'month'; // week|month|6mo|year|all

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
  if (preg_match('~^https?://~i', $p)) return $p;     // absolute
  if ($p[0] === '/') return $p;                       // root-absolute
  $p = ltrim($p, '/');
  if (strpos($p, 'uploads/') === 0) return '../validator/' . $p;          // "uploads/service/..."
  if (strpos($p, 'validator/uploads/') === 0) return '../' . $p;          // already prefixed
  return '../validator/uploads/' . $p;                                     // fallback
}

function student_profile_url(array $student): string {
  $file = !empty($student['photo']) ? $student['photo'] : 'placeholder.png';
  $fs   = __DIR__ . '/../admin/uploads/' . $file;
  return is_file($fs) ? ('../admin/uploads/' . $file) : '../admin/uploads/placeholder.png';
}

/** Required hours from violations (3×light/mod/less-grave/minor = 10h; each grave = 20h). */
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

/** Total logged hours (all-time) for a student. */
function total_logged_hours(mysqli $conn, string $student_id): float {
  $sum = $conn->prepare("SELECT COALESCE(SUM(hours),0) FROM community_service_entries WHERE student_id = ?");
  $sum->bind_param("s", $student_id);
  $sum->execute(); $sum->bind_result($h); $sum->fetch(); $sum->close();
  return (float)$h;
}

/** Latest entry (within filters). */
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

/** Count entries (within filters). */
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

/* ------- Students that match filters (based on entries) ------- */
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

/* ------- Build cards ------- */
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
    $inCount  = count_entries($conn, $sid, $startDate ? $startDate->format('Y-m-d') : null, $validatorId);

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

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Community Service — Students</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--bg:#f5f7fb;--text:#0f172a;--muted:#6b7280;--surface:#fff;--border:#e5e7eb;--primary:#8c1c13}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font:15px/1.55 system-ui,Segoe UI,Inter,Arial,sans-serif}
  .wrap{max-width:1140px;margin:26px auto 80px;padding:0 18px}
  .pagehead{display:flex;align-items:end;justify-content:space-between;margin:8px 0 14px}
  h1{margin:0;font-size:1.6rem}
  .note{color:var(--muted);font-size:.92rem}
  .filter{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px;display:flex;flex-wrap:wrap;gap:12px;align-items:end;box-shadow:0 10px 26px -20px rgba(17,24,39,.25)}
  .field{display:flex;flex-direction:column;gap:6px;min-width:180px}
  label{font-size:.82rem;font-weight:700;color:#374151}
  input,select{border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:#fff}
  .btn{appearance:none;border:none;border-radius:10px;padding:10px 16px;font-weight:700;cursor:pointer}
  .btn.primary{background:var(--primary);color:#fff}
  .summary{display:flex;gap:10px;align-items:center;margin:10px 0;color:var(--muted);font-size:.9rem}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px;margin-top:12px}
  .card{display:block;text-decoration:none;color:inherit;background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:14px;box-shadow:0 12px 28px -22px rgba(17,24,39,.25);transition:transform .12s}
  .card:hover{transform:translateY(-2px)}
  .row{display:flex;gap:10px;align-items:center}
  .avatar{width:56px;height:56px;border-radius:12px;object-fit:cover;border:1px solid var(--border);background:#fff}
  .thumb{width:56px;height:56px;border-radius:12px;object-fit:cover;border:1px solid var(--border);background:#fff}
  .title{font-weight:800}
  .meta{display:flex;flex-wrap:wrap;gap:8px;color:var(--muted);font-size:.9rem;margin-top:2px}
  .chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
  .chip{border:1px solid var(--border);border-radius:999px;padding:4px 10px;font-size:.8rem;background:#fff}
  .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px}
  .stat{border:1px solid var(--border);border-radius:12px;padding:10px;background:#fff;text-align:center}
  .stat b{display:block;font-size:.75rem;color:#6b7280;margin-bottom:4px}
  .stat .val{font-weight:800}
  .val.ok{color:#065f46}
  .val.warn{color:#b91c1c}
  .empty{padding:18px;border:1px dashed var(--border);border-radius:14px;text-align:center;color:var(--muted);background:#fff;margin-top:14px}

  /* --- Modal styles --- */
  .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;z-index:999}
  .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;padding:24px;z-index:1000}
  .modal.open,.modal-backdrop.open{display:flex}
  .modal-card{max-width:960px;width:100%;background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 30px 70px rgba(0,0,0,.35);max-height:90vh;overflow:auto}
  .modal-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border);position:sticky;top:0;background:#fff;z-index:1}
  .modal-title{margin:0;font-size:1rem;font-weight:800}
  .modal-close{appearance:none;border:1px solid var(--border);background:#fff;border-radius:999px;width:34px;height:34px;font-weight:700;cursor:pointer}
  .modal-body{padding:14px}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="wrap">
  <div class="pagehead">
    <div>
      <h1>Community Service — Students</h1>
      <div class="note">Read-only overview. Click a student to view validator updates in a popup.</div>
    </div>
  </div>

  <form class="filter" method="get" action="">
    <div class="field">
      <label for="student_id">Student ID</label>
      <input type="text" id="student_id" name="student_id" value="<?= htmlspecialchars($studentFilter) ?>" placeholder="e.g., 2024-1234">
    </div>
    <div class="field">
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
    <div class="field">
      <label for="period">Period</label>
      <select id="period" name="period">
        <option value="week"  <?= $period==='week'?'selected':'' ?>>Last 7 days</option>
        <option value="month" <?= $period==='month'?'selected':'' ?>>Last 30 days</option>
        <option value="6mo"   <?= $period==='6mo'?'selected':'' ?>>Last 6 months</option>
        <option value="year"  <?= $period==='year'?'selected':'' ?>>Last year</option>
        <option value="all"   <?= $period==='all'?'selected':'' ?>>All time</option>
      </select>
    </div>
    <div class="field" style="min-width:auto;">
      <button class="btn primary" type="submit">Apply</button>
    </div>
  </form>

  <?php if (!$hasEntries): ?>
    <div class="empty">No <code>community_service_entries</code> table found.</div>
  <?php elseif (empty($cards)): ?>
    <div class="empty">No students match your filters.</div>
  <?php else: ?>
    <div class="summary">
      Showing <strong><?= count($cards) ?></strong> student<?= count($cards)===1?'':'s' ?><?= $studentFilter?' for ID '.htmlspecialchars($studentFilter):'' ?><?= $validatorId>0?' • filtered by validator':'' ?><?= $period!=='all'?' • period: '.$period:'' ?>
    </div>

    <div class="grid">
      <?php foreach ($cards as $c): 
        $latest = $c['latest'];
        $lastDate = $latest ? ($latest['service_date'] ?? $latest['created_at']) : null;
        $detailsUrl = 'community_service_view.php?student_id=' . urlencode($c['student_id']);
      ?>
        <a class="card js-student-card" href="<?= $detailsUrl ?>" data-modal-url="<?= $detailsUrl ?>">
          <div class="row">
            <img class="avatar" src="<?= htmlspecialchars($c['profile']) ?>" alt="Student">
            <div style="flex:1">
              <div class="title"><?= htmlspecialchars($c['name'] ?: $c['student_id']) ?></div>
              <div class="meta">
                <span>ID <?= htmlspecialchars($c['student_id']) ?></span>
                <?php if ($c['course']): ?><span>• <?= htmlspecialchars($c['course']) ?></span><?php endif; ?>
                <?php if ($c['yearsection']): ?><span>• <?= htmlspecialchars($c['yearsection']) ?></span><?php endif; ?>
              </div>
            </div>
            <?php if ($c['thumb']): ?>
              <img class="thumb" src="<?= htmlspecialchars($c['thumb']) ?>" alt="Latest evidence">
            <?php endif; ?>
          </div>

          <div class="chips">
            <?php if ($c['institute']): ?><span class="chip"><?= htmlspecialchars($c['institute']) ?></span><?php endif; ?>
            <span class="chip"><?= (int)$c['inCount'] ?> entr<?= $c['inCount']==1?'y':'ies' ?><?= $lastDate ? ' • last '.htmlspecialchars(date('M d, Y', strtotime($lastDate))) : '' ?></span>
            <?php if ($latest && !empty($latest['v_username'])): ?>
              <span class="chip">Validator: <?= htmlspecialchars($latest['v_username']) ?></span>
            <?php endif; ?>
          </div>

          <div class="stats">
            <div class="stat"><b>Required</b><div class="val"><?= number_format($c['required'], 2) ?> h</div></div>
            <div class="stat"><b>Logged</b><div class="val ok"><?= number_format($c['logged'], 2) ?> h</div></div>
            <div class="stat"><b>Remaining</b><div class="val <?= $c['remaining']>0?'warn':'ok' ?>"><?= number_format($c['remaining'], 2) ?> h</div></div>
          </div>

          <?php if ($latest && !empty($latest['remarks'])): ?>
            <div class="meta" style="margin-top:8px">Latest remarks: <em><?= htmlspecialchars($latest['remarks']) ?></em></div>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal/backdrop -->
<div id="cs-backdrop" class="modal-backdrop" aria-hidden="true"></div>
<div id="cs-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="cs-title">
  <div class="modal-card">
    <div class="modal-head">
      <h2 id="cs-title" class="modal-title">Community Service Details</h2>
      <button class="modal-close" type="button" aria-label="Close">&times;</button>
    </div>
    <div id="cs-body" class="modal-body">
      <!-- Fetched HTML from community_service_view.php?student_id=...&modal=1 goes here -->
    </div>
  </div>
</div>

<script>
(function(){
  const modal    = document.getElementById('cs-modal');
  const backdrop = document.getElementById('cs-backdrop');
  const body     = document.getElementById('cs-body');
  const btnClose = modal.querySelector('.modal-close');

  function openModalWith(url){
    // add modal=1 for compact rendering
    const modalUrl = url + (url.includes('?') ? '&' : '?') + 'modal=1';
    body.innerHTML = 'Loading…';
    fetch(modalUrl, { credentials: 'same-origin' })
      .then(r => { if(!r.ok) throw new Error('Failed to load details'); return r.text(); })
      .then(html => {
        body.innerHTML = html;
        modal.classList.add('open');
        backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
        // push a state so back button closes modal
        if (!history.state || history.state.csModalOpen !== true) {
          history.pushState({ csModalOpen: true }, '');
        }
      })
      .catch(err => {
        body.innerHTML = '<div style="color:#b91c1c">Unable to load details: ' + (err && err.message ? err.message : 'Error') + '</div>';
        modal.classList.add('open');
        backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
      });
  }

  function closeModal(){
    modal.classList.remove('open');
    backdrop.classList.remove('open');
    document.body.style.overflow = '';
    // if history was pushed, pop it
    if (history.state && history.state.csModalOpen === true) {
      history.back();
    }
  }

  document.addEventListener('click', function(e){
    const card = e.target.closest('.js-student-card');
    if (!card) return;

    // allow new-tab, middle click, or with modifiers
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

    e.preventDefault();
    const url = card.getAttribute('data-modal-url') || card.getAttribute('href');
    if (url) openModalWith(url);
  });

  btnClose.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

  // Close modal on browser back (after pushState)
  window.addEventListener('popstate', function(){
    if (modal.classList.contains('open')) {
      modal.classList.remove('open');
      backdrop.classList.remove('open');
      document.body.style.overflow = '';
    }
  });
})();
</script>

</body>
</html>
<?php $conn->close(); ?>
