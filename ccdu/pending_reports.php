<?php
session_start();
include '../includes/header.php';
include '../config.php';
include 'page_buttons.php';
include __DIR__ . '/_scanner.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* ---------------------------- Helpers ---------------------------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function time_ago($dt){
    $t = is_string($dt) ? new DateTime($dt) : $dt;
    $now = new DateTime('now'); $d = $now->diff($t);
    foreach (['y'=>'year','m'=>'month','d'=>'day','h'=>'hour','i'=>'minute'] as $k=>$w) {
        if ($d->$k) return $d->$k . " {$w}" . ($d->$k>1?'s':'') . " ago";
    } return 'just now';
}
function highlight($text, $needle){
    if ($needle === '') return h($text);
    $safe = h($text);
    $needle = preg_quote(h($needle), '/');
    return preg_replace("/($needle)/i", '<mark class="pr-hl">$1</mark>', $safe);
}

/* ----------------------- Read query params ----------------------- */
$q        = trim($_GET['q'] ?? '');
$category = strtolower(trim($_GET['category'] ?? ''));
$role     = strtolower(trim($_GET['role'] ?? ''));
$courseQ  = trim($_GET['course'] ?? '');
$sort     = $_GET['sort'] ?? 'newest';

$perPage  = (int)($_GET['per_page'] ?? 12);
$perPage  = in_array($perPage, [6,12,24,48], true) ? $perPage : 12;

$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$allowedSorts = [
  'newest'   => 'sv.reported_at DESC',
  'oldest'   => 'sv.reported_at ASC',
  'name'     => 'sa.last_name ASC, sa.first_name ASC',
  'course'   => 'sa.course ASC, sa.level ASC, sa.section ASC, sv.reported_at DESC',
  'severity' => "FIELD(LOWER(sv.offense_category),'grave','moderate','light') ASC, sv.reported_at DESC",
];
$orderBy = $allowedSorts[$sort] ?? $allowedSorts['newest'];

/* -------------------------- Build query -------------------------- */
$fields = "
 sv.violation_id, sv.student_id,
 sa.first_name AS student_first_name, sa.last_name AS student_last_name,
 sa.course, sa.level, sa.section,
 sv.offense_category, sv.offense_type, sv.description, sv.photo,
 sv.status, sv.submitted_role, sv.reported_at,
 CASE sv.submitted_role
   WHEN 'faculty' THEN CONCAT(fa.first_name,' ',fa.last_name)
   WHEN 'security' THEN CONCAT(se.first_name,' ',se.last_name)
   ELSE 'Unknown'
 END AS submitter_name
";

$from = "
 FROM student_violation sv
 JOIN student_account sa ON sv.student_id = sa.student_id
 LEFT JOIN faculty_account fa ON sv.submitted_by = fa.faculty_id AND sv.submitted_role='faculty'
 LEFT JOIN security_account se ON sv.submitted_by = se.security_id AND sv.submitted_role='security'
";

$conds  = ["sv.status='pending'"];
$types  = '';
$params = [];

// search (name, id, offense fields)
if ($q !== '') {
  $conds[] = "(sv.student_id LIKE ?
           OR sa.first_name LIKE ?
           OR sa.last_name  LIKE ?
           OR CONCAT(sa.first_name,' ',sa.last_name) LIKE ?
           OR sv.offense_type LIKE ?
           OR sv.offense_category LIKE ?
           OR sv.description LIKE ?)";
  $wild = "%{$q}%"; $types .= 'sssssss';
  array_push($params,$wild,$wild,$wild,$wild,$wild,$wild,$wild);
}
if (in_array($category,['light','moderate','grave'],true)){
  $conds[] = "LOWER(sv.offense_category)=?"; $types.='s'; $params[]=$category;
}
if (in_array($role,['faculty','security'],true)){
  $conds[] = "sv.submitted_role=?"; $types.='s'; $params[]=$role;
}
if ($courseQ!==''){ $conds[]="sa.course LIKE ?"; $types.='s'; $params[]="%{$courseQ}%"; }

$where = 'WHERE '.implode(' AND ', $conds);

// count total
$countSql = "SELECT COUNT(*) AS total $from $where";
$countStmt = $conn->prepare($countSql);
if ($types) {
  $bind = []; $bind[]=&$types; foreach($params as $k=>$_){ $bind[]=&$params[$k]; }
  call_user_func_array([$countStmt,'bind_param'],$bind);
}
$countStmt->execute(); $totalRows=(int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = max(1,(int)ceil($totalRows/$perPage));

// results
$sql = "SELECT $fields $from $where ORDER BY $orderBy LIMIT ? OFFSET ?";
$types2 = $types.'ii'; $params2 = $params; $params2[]=$perPage; $params2[]=$offset;
$stmt = $conn->prepare($sql);
$bind2=[]; $bind2[]=&$types2; foreach($params2 as $k=>$_){ $bind2[]=&$params2[$k]; }
call_user_func_array([$stmt,'bind_param'],$bind2);
$stmt->execute(); $result = $stmt->get_result();

/* -------------------- Suggestions for <datalist> ------------------ */
$suggest = [];
$sg = $conn->query("
  SELECT DISTINCT sa.student_id, sa.first_name, sa.last_name
  $from
  WHERE sv.status='pending'
  ORDER BY sa.last_name ASC, sa.first_name ASC
  LIMIT 200
");
if ($sg) { while($r=$sg->fetch_assoc()){ $suggest[]=$r; } }

/* ----------------------- Utility for URLs ------------------------ */
function build_query(array $overrides=[]): string {
  $merged = array_merge($_GET,$overrides);
  foreach($merged as $k=>$v){ if($v===null) unset($merged[$k]); }
  return http_build_query($merged);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Pending Reports</title>
  <link rel="stylesheet" href="../css/pending_reports.css?v=rev2"/>
</head>
<body>
<main class="pr-page" style="padding-top: calc(var(--header-h) + var(--top-gap));">
  <!-- Title + tools -->
  <header class="pr-head">
    <div class="pr-headL">
      <h1 class="pr-title">Pending Reports</h1>
      <span class="pr-count" aria-label="Total pending"><?= (int)$totalRows ?></span>
    </div>

    <!-- Toolbar: search + filters (auto-submit; no Apply button) -->
    <form class="pr-toolbar" id="filtersForm" method="get" role="search" aria-label="Filter pending reports">
      <!-- SEARCH -->
      <label class="pr-search" aria-label="Search by name, ID, or offense">
        <svg class="pr-search__icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M21 21l-4.2-4.2M17 10a7 7 0 11-14 0 7 7 0 0114 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <input list="nameSuggestions" type="search" name="q" id="q"
               value="<?= h($q) ?>" placeholder="Search name, ID, offense…" autocomplete="off"/>
        <button type="button" class="pr-search__clear" id="clearSearch" aria-label="Clear search">&times;</button>
        <datalist id="nameSuggestions">
          <?php foreach($suggest as $s): ?>
            <?php $full = trim(($s['first_name']??'').' '.($s['last_name']??'')); ?>
            <option value="<?= h($full) ?>"><?= h($full) ?> — <?= h($s['student_id']) ?></option>
            <option value="<?= h($s['student_id']) ?>"><?= h($s['student_id']) ?> — <?= h($full) ?></option>
          <?php endforeach; ?>
        </datalist>
      </label>

      <!-- Filters (auto submit on change) -->
      <select name="category" class="pr-select" data-autosubmit>
        <option value="">All categories</option>
        <option value="grave"    <?= $category==='grave'?'selected':'' ?>>Grave</option>
        <option value="moderate" <?= $category==='moderate'?'selected':'' ?>>Moderate</option>
        <option value="light"    <?= $category==='light'?'selected':'' ?>>Light</option>
      </select>

      <select name="role" class="pr-select" data-autosubmit>
        <option value="">All roles</option>
        <option value="faculty"  <?= $role==='faculty'?'selected':'' ?>>Faculty</option>
        <option value="security" <?= $role==='security'?'selected':'' ?>>Security</option>
      </select>

      <input type="text" name="course" value="<?= h($courseQ) ?>" class="pr-input" placeholder="Course (e.g., BSIT)" data-enter-submit />

      <select name="sort" class="pr-select" data-autosubmit>
        <option value="newest"   <?= $sort==='newest'?'selected':'' ?>>Newest</option>
        <option value="oldest"   <?= $sort==='oldest'?'selected':'' ?>>Oldest</option>
        <option value="name"     <?= $sort==='name'?'selected':''   ?>>Name</option>
        <option value="course"   <?= $sort==='course'?'selected':'' ?>>Course</option>
        <option value="severity" <?= $sort==='severity'?'selected':'' ?>>Severity</option>
      </select>

      <select name="per_page" class="pr-select" data-autosubmit>
        <option value="6"  <?= $perPage===6  ?'selected':'' ?>>6</option>
        <option value="12" <?= $perPage===12 ?'selected':'' ?>>12</option>
        <option value="24" <?= $perPage===24 ?'selected':'' ?>>24</option>
        <option value="48" <?= $perPage===48 ?'selected':'' ?>>48</option>
      </select>

      <!-- keep page in sync -->
      <input type="hidden" name="page" value="1"/>
      <a class="btn btn--ghost" href="?">Reset</a>
    </form>
  </header>

  <!-- “Results for …” badge -->
  <?php if ($q !== ''): ?>
    <div class="pr-result-tag" role="status" aria-live="polite">
      Showing results for <strong>“<?= h($q) ?>”</strong>
    </div>
  <?php endif; ?>

  <!-- Toast -->
  <?php if (isset($_GET['msg'])): ?>
    <div class="pr-toast <?= $_GET['msg']==='approved' ? 'pr-toast--ok':'pr-toast--bad' ?>" role="status" aria-live="polite">
      <?php if ($_GET['msg']==='approved'): ?>
        <strong>Approved.</strong> Report updated.
      <?php elseif ($_GET['msg']==='rejected'): ?>
        <strong>Rejected.</strong> Report updated.
      <?php endif; ?>
      <button class="pr-toast__close" type="button" aria-label="Dismiss">&times;</button>
    </div>
  <?php endif; ?>

  <!-- Results -->
  <?php if ($result && $result->num_rows > 0): ?>
    <div class="pr-grid">
      <?php while ($row = $result->fetch_assoc()): ?>
        <?php
          $violationId   = (int)$row['violation_id'];
          $photo         = trim($row['photo'] ?? '');
          $firstName     = $row['student_first_name'] ?? '';
          $lastName      = $row['student_last_name'] ?? '';
          $fullName      = trim($firstName.' '.$lastName);
          $studentId     = $row['student_id'] ?? '';
          $course        = $row['course'] ?? '';
          $level         = $row['level'] ?? '';
          $section       = $row['section'] ?? '';
          $categoryRaw   = strtolower($row['offense_category'] ?? '');
          $categoryLbl   = $categoryRaw !== '' ? ucfirst($categoryRaw) : '';
          $type          = $row['offense_type'] ?? '';
          $description   = $row['description'] ?? '';
          $submitter     = $row['submitter_name'] ?? '';
          $submittedRole = $row['submitted_role'] ?? '';
          $courseLine    = trim(h($course).' '.h($level).'-'.h($section));
          $reportedAt    = h(date('M d, Y · h:i A', strtotime($row['reported_at'] ?? '')));
          $ago           = time_ago($row['reported_at'] ?? '');
        ?>
        <article class="pr-card">
          <div class="pr-media">
            <?php if ($photo !== ''): ?>
              <img src="uploads/<?= h($photo) ?>" alt="Evidence photo for <?= h($fullName) ?>" loading="lazy"/>
            <?php else: ?>
              <div class="pr-media__placeholder" aria-label="No evidence photo provided">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3zM3 15l5-5 4 4 3-3 4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span>No evidence photo</span>
              </div>
            <?php endif; ?>
          </div>

          <div class="pr-body">
            <h2 class="pr-name"><?= highlight($fullName, $q) ?></h2>

            <div class="pr-meta">
              <div class="pr-meta-row">
                <span class="pr-label">Student ID</span>
                <span class="pr-value"><?= highlight($studentId, $q) ?></span>
              </div>
              <div class="pr-meta-row">
                <span class="pr-label">Course</span>
                <span class="pr-value"><?= $courseLine ?></span>
              </div>

              <div class="pr-chips">
                <?php if ($categoryLbl): ?>
                  <span class="pr-chip pr-chip--category" data-val="<?= h($categoryLbl) ?>"><?= h($categoryLbl) ?></span>
                <?php endif; ?>
                <?php if ($type): ?>
                  <span class="pr-chip pr-chip--type"><?= h($type) ?></span>
                <?php endif; ?>
              </div>

              <?php if ($description): ?>
                <p class="pr-desc"><?= highlight($description, $q) ?></p>
              <?php endif; ?>

              <p class="pr-submitted">
                <span class="pr-label">Submitted by</span>
                <span class="pr-value"><?= h($submitter) ?> (<?= h(ucfirst($submittedRole)) ?>)</span>
              </p>

              <p class="pr-time" title="<?= $reportedAt ?>">
                <svg class="pr-time__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7v5l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Reported <?= h($ago) ?>
              </p>
            </div>

            <div class="pr-actions">
              <form action="approve_violation.php" method="post" class="pr-form">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" value="<?= $violationId ?>"/>
                <input type="hidden" name="action" value="approve"/>
                <button type="submit" class="btn btn-approve" aria-label="Approve report for <?= h($fullName) ?>">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 13l4 4L19 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  Approve
                </button>
              </form>

              <form action="approve_violation.php" method="post" class="pr-form">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" value="<?= $violationId ?>"/>
                <input type="hidden" name="action" value="reject"/>
                <button type="submit" class="btn btn-reject" aria-label="Reject report for <?= h($fullName) ?>">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  Reject
                </button>
              </form>
            </div>
          </div>
        </article>
      <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <nav class="pr-pagination" aria-label="Pagination">
      <div class="pr-pagination__summary">
        Page <?= (int)$page ?> of <?= (int)$totalPages ?> • <?= (int)$totalRows ?> total
      </div>
      <div class="pr-pagination__controls">
        <?php $prev=max(1,$page-1); $next=min($totalPages,$page+1); ?>
        <a class="pr-pagebtn <?= $page<=1?'is-disabled':'' ?>" href="?<?= build_query(['page'=>$prev]) ?>" aria-disabled="<?= $page<=1?'true':'false' ?>">&larr; Prev</a>
        <a class="pr-pagebtn <?= $page>=$totalPages?'is-disabled':'' ?>" href="?<?= build_query(['page'=>$next]) ?>" aria-disabled="<?= $page>=$totalPages?'true':'false' ?>">Next &rarr;</a>
      </div>
    </nav>

  <?php else: ?>
    <section class="pr-empty">
      <img src="empty-state.svg" alt="" aria-hidden="true"/>
      <h2>No pending violations<?= $q!==''?' for “'.h($q).'”':'' ?></h2>
      <p>Try different keywords or clear filters.</p>
      <div class="pr-empty__actions">
        <a class="btn btn--primary" href="?">Clear all</a>
      </div>
    </section>
  <?php endif; ?>
</main>

<script>
(function(){
  const form = document.getElementById('filtersForm');
  const q = document.getElementById('q');
  const clearBtn = document.getElementById('clearSearch');

  // Auto-submit selects
  document.querySelectorAll('[data-autosubmit]').forEach(el=>{
    el.addEventListener('change', ()=>{ form.querySelector('input[name="page"]').value='1'; form.submit(); });
  });

  // Enter on free text inputs
  document.querySelectorAll('[data-enter-submit]').forEach(el=>{
    el.addEventListener('keydown', e => { if (e.key === 'Enter') { form.querySelector('input[name="page"]').value='1'; form.submit(); }});
  });

  // Debounced auto-submit on search typing
  let t;
  if (q) {
    const toggleX = ()=> clearBtn.style.visibility = q.value ? 'visible':'hidden';
    toggleX();
    q.addEventListener('input', ()=>{
      toggleX();
      clearTimeout(t);
      t = setTimeout(()=>{
        form.querySelector('input[name="page"]').value='1';
        form.submit();
      }, 400); // smooth, fast updates
    });
  }
  // Clear search
  clearBtn && clearBtn.addEventListener('click', ()=>{ q.value=''; form.submit(); });

  // Toast auto hide
  const toast = document.querySelector('.pr-toast');
  if (toast){
    const close = toast.querySelector('.pr-toast__close');
    setTimeout(()=> toast.classList.add('is-hide'), 3600);
    close && close.addEventListener('click', ()=> toast.classList.add('is-hide'));
  }
})();
</script>
</body>
</html>
