<?php
include '../includes/header.php';
include '../config.php';
include 'page_buttons.php'; // (not used anymore, but left in place)
include __DIR__ . '/_scanner.php';

/* ---------------- Database ---------------- */
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

/* ---------------- Filters ---------------- */
$status = $_GET['status'] ?? 'all';   // all | active | inactive
$q      = trim($_GET['q'] ?? '');
$sort   = $_GET['sort'] ?? 'recent';

$allowedStatus = ['all','active','inactive'];
if (!in_array($status, $allowedStatus, true)) $status = 'all';

$sortMap = [
  'recent'        => 'created_at DESC',
  'oldest'        => 'created_at ASC',
  'name_asc'      => 'v_username ASC',
  'name_desc'     => 'v_username DESC',
  'expires_soon'  => 'CASE WHEN (expires_at IS NULL OR expires_at IN ("0000-00-00","0000-00-00 00:00:00")) THEN 1 ELSE 0 END, expires_at ASC',
  'expires_last'  => 'CASE WHEN (expires_at IS NULL OR expires_at IN ("0000-00-00","0000-00-00 00:00:00")) THEN 1 ELSE 0 END, expires_at DESC'
];
if (!array_key_exists($sort, $sortMap)) $sort = 'recent';

/* ---------------- Pagination ---------------- */
$perPage = (int)($_GET['per_page'] ?? 12);
if ($perPage < 1)   $perPage = 12;
if ($perPage > 60)  $perPage = 60; // safety cap

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

/* ---------------- WHERE builder ---------------- */
$where   = [];
$params  = [];
$types   = '';

if ($status === 'active')   { $where[] = 'active = ?';   $types .= 'i'; $params[] = 1; }
if ($status === 'inactive') { $where[] = 'active = ?';   $types .= 'i'; $params[] = 0; }

if ($q !== '') {
  $where[] = '(v_username LIKE ? OR designation LIKE ?)';
  $types  .= 'ss';
  $kw = '%'.$q.'%';
  $params[] = $kw;
  $params[] = $kw;
}

$whereSql = $where ? (' WHERE '.implode(' AND ', $where)) : '';

/* ---------------- Count first ---------------- */
$sqlCount = "SELECT COUNT(*) AS cnt FROM validator_account" . $whereSql;
$stmtCount = $conn->prepare($sqlCount);
if ($stmtCount === false) { die("Prepare error: " . $conn->error); }
if ($types) { $stmtCount->bind_param($types, ...$params); }
if (!$stmtCount->execute()) { die("Query error: " . $stmtCount->error); }
$resCount = $stmtCount->get_result()->fetch_assoc();
$total    = (int)($resCount['cnt'] ?? 0);
$stmtCount->close();

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

/* ---------------- Fetch current page ---------------- */
/* Safe to inline LIMIT/OFFSET after casting to int */
$orderSql = $sortMap[$sort];
$sqlData  = "SELECT validator_id, v_username, created_at, expires_at, active, designation
             FROM validator_account{$whereSql}
             ORDER BY {$orderSql}
             LIMIT {$perPage} OFFSET {$offset}";

$stmt = $conn->prepare($sqlData);
if ($stmt === false) { die("Prepare error: " . $conn->error); }
if ($types) { $stmt->bind_param($types, ...$params); }
if (!$stmt->execute()) { die("Query error: " . $stmt->error); }
$result = $stmt->get_result();

/* Helper: build URL for a page while preserving filters */
function page_url($p, $perPage){
  $qs = $_GET;
  $qs['page'] = max(1, (int)$p);
  $qs['per_page'] = $perPage;
  return '?' . http_build_query($qs);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Community Service Validators</title>
  <link rel="stylesheet" href="../css/community_validator.css?v=2.2"/>
</head>

<body class="has-fixed-chrome">
  <main class="validators-shell">
    <section class="validators-page" aria-labelledby="page-title">
      <!-- Page header -->
      <header class="page-header">
        <div class="page-header__left">
          <h1 id="page-title" class="page-title">Community Service Validators</h1>
          <p class="page-subtitle">Manage accounts that validate community service completions.</p>
        </div>
        <div class="page-actions">
          <a href="add_validator.php" class="btn btn-primary" title="Create a new validator account">
            <span class="btn__icon" aria-hidden="true">＋</span>
            <span>Create Account</span>
          </a>
        </div>
      </header>

      <!-- Filters -->
      <form method="get" class="filters" aria-label="Filters" role="search">
        <div class="filters__left">
          <label class="field">
            <span class="field-label">Status</span>
            <select name="status" class="select" aria-label="Status">
              <option value="all"      <?= $status==='all'?'selected':'' ?>>All</option>
              <option value="active"   <?= $status==='active'?'selected':'' ?>>Active</option>
              <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
          </label>

          <label class="field field--grow search-field">
            <span class="field-label">Search</span>
            <div class="search-wrap">
              <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($q) ?>"
                class="input search-input"
                placeholder="Search by name or designation…"
                aria-label="Search by name or designation"
              />
              <button class="icon-btn" type="submit" title="Search" aria-label="Search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M21 21l-4.35-4.35m1.18-4.9a7.28 7.28 0 11-14.56 0 7.28 7.28 0 0114.56 0z"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </label>

          <label class="field">
            <span class="field-label">Sort</span>
            <select name="sort" class="select" aria-label="Sort">
              <option value="recent"        <?= $sort==='recent'?'selected':'' ?>>Newest first</option>
              <option value="oldest"        <?= $sort==='oldest'?'selected':'' ?>>Oldest first</option>
              <option value="name_asc"      <?= $sort==='name_asc'?'selected':'' ?>>Name A → Z</option>
              <option value="name_desc"     <?= $sort==='name_desc'?'selected':'' ?>>Name Z → A</option>
              <option value="expires_soon"  <?= $sort==='expires_soon'?'selected':'' ?>>Expires soonest</option>
              <option value="expires_last"  <?= $sort==='expires_last'?'selected':'' ?>>Expires latest</option>
            </select>
          </label>
        </div>

        <div class="filters__right">
          <button type="submit" class="btn btn-outline">Apply</button>
          <?php $resetQS = http_build_query(['status'=>'all']); ?>
          <a href="?<?= $resetQS ?>" class="btn btn-ghost" role="button" title="Reset filters">Reset</a>
        </div>
      </form>

      <!-- Results meta -->
      <div class="results-meta" role="status" aria-live="polite">
        <span class="pill"><?= ucfirst($status) ?></span>
        <span class="count"><?= number_format($total) ?> <?= $total === 1 ? 'record' : 'records' ?></span>
        <?php if ($q !== ''): ?>
          <span class="query">for “<?= htmlspecialchars($q) ?>”</span>
        <?php endif; ?>
      </div>

      <?php if ($total === 0): ?>
        <!-- Empty state -->
        <section class="empty-state" aria-live="polite">
          <div class="empty-emoji" aria-hidden="true">🧭</div>
          <h2>No <?= htmlspecialchars($status) ?> validators found</h2>
          <p>Try clearing the search, switching status, or creating a new account.</p>
          <div class="empty-actions">
            <a class="btn btn-primary" href="add_validator.php">Create Account</a>
            <a class="btn btn-ghost" href="?<?= $resetQS ?>">Back to All</a>
          </div>
        </section>
      <?php else: ?>
        <!-- Cards -->
        <section class="card-container" aria-live="polite">
          <?php while ($row = $result->fetch_assoc()): ?>
            <?php
              $name  = $row['v_username'] ?: '—';
              $desig = $row['designation'] ?: '—';

              $created = ($row['created_at'] && $row['created_at'] !== '0000-00-00' && $row['created_at'] !== '0000-00-00 00:00:00')
                ? date('M d, Y', strtotime($row['created_at'])) : '—';

              $expires = ($row['expires_at'] && $row['expires_at'] !== '0000-00-00' && $row['expires_at'] !== '0000-00-00 00:00:00')
                ? date('M d, Y', strtotime($row['expires_at'])) : null;

              $href = 'validator_details.php?id='.(int)$row['validator_id'];
            ?>
            <article class="card" role="link" tabindex="0" data-href="<?= $href ?>"
                     aria-label="Open details for <?= htmlspecialchars($name); ?>">
              <header class="card-header">
                <h3 class="card-title"><?= htmlspecialchars($name); ?></h3>
                <span class="status-chip <?= $row['active'] ? 'is-active' : 'is-inactive'; ?>">
                  <?= $row['active'] ? 'Active' : 'Inactive'; ?>
                </span>
              </header>

              <dl class="meta">
                <div class="meta-row">
                  <dt>Created</dt>
                  <dd><?= $created; ?></dd>
                </div>

                <?php if ($expires): ?>
                  <div class="meta-row">
                    <dt>Expires</dt>
                    <dd><?= $expires; ?></dd>
                  </div>
                <?php endif; ?>

                <div class="meta-row">
                  <dt>Designation</dt>
                  <dd><?= htmlspecialchars($desig); ?></dd>
                </div>
              </dl>

              <div class="card-arrow" aria-hidden="true">↗</div>
            </article>
          <?php endwhile; ?>
        </section>
      <?php endif; ?>

      <!-- Pager -->
      <div class="pager-area">
        <div class="pager" role="navigation" aria-label="Pagination">
          <div class="pager__info">
            Page <?= $total ? $page : 1 ?> of <?= $totalPages ?> • <?= number_format($total) ?> total
          </div>
          <div class="pager__actions">
            <?php if ($page > 1): ?>
              <a class="btn btn-outline pager__btn" href="<?= htmlspecialchars(page_url($page-1, $perPage)) ?>" rel="prev" aria-label="Previous page">← Prev</a>
            <?php else: ?>
              <span class="btn btn-outline pager__btn is-disabled" aria-disabled="true">← Prev</span>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
              <a class="btn btn-outline pager__btn" href="<?= htmlspecialchars(page_url($page+1, $perPage)) ?>" rel="next" aria-label="Next page">Next →</a>
            <?php else: ?>
              <span class="btn btn-outline pager__btn is-disabled" aria-disabled="true">Next →</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
  </main>

  <script>
    // Click to navigate (mouse)
    document.addEventListener('click', e => {
      const card = e.target.closest('.card[data-href]');
      if (!card) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      window.location = card.dataset.href;
    });

    // Keyboard accessibility
    document.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        const card = document.activeElement.closest?.('.card[data-href]');
        if (card) window.location = card.dataset.href;
      }
    });

    // Auto-apply for selects
    const filtersForm = document.querySelector('.filters');
    const autoInputs = filtersForm?.querySelectorAll('select[name="status"], select[name="sort"]');
    autoInputs?.forEach(sel => sel.addEventListener('change', () => filtersForm.submit()));
  </script>
</body>
</html>
