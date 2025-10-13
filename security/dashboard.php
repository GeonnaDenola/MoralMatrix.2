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

/* ========= HELPERS (existing) ========= */
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
    usort($options, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));
    return $options;
}
function safeText(?string $text): string { return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8'); }
function normaliseKey(string $value): string {
    $value = trim($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'na';
}
function toSearchIndex(string $value): string {
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}
/** @return array{iso:string,full:string,relative:string} */
function describeDate(?string $raw): array
{
    if (!$raw) return ['iso' => '', 'full' => '—', 'relative' => ''];
    try { $dt = new DateTimeImmutable($raw); }
    catch (Exception $e) { return ['iso' => '', 'full' => safeText($raw), 'relative' => '']; }
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
    foreach (['y'=>'year','m'=>'month','d'=>'day','h'=>'hour','i'=>'minute'] as $k=>$label) {
        $v = $diff->$k;
        if ($v > 0) {
            $plural = $v === 1 ? '' : 's';
            $suffix = $diff->invert === 1 ? ' ago' : ' from now';
            return $v . ' ' . $label . $plural . $suffix;
        }
    }
    return 'Just now';
}
/* ========= /HELPERS ========= */

$latestDescriptor = $latestReportedAt
    ? ['full' => $latestReportedAt->format('M j, Y • g:i A'), 'relative' => formatRelativeFromDate($latestReportedAt)]
    : ['full' => 'Awaiting first approval', 'relative' => ''];

/* =================== PAGINATION (ADD-ONLY) =================== */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 12)));
$offset  = ($page - 1) * $perPage;

$lastPage = max(1, (int)ceil(($totalReports ?: 0) / $perPage));
if ($page > $lastPage) { $page = $lastPage; $offset = ($page - 1) * $perPage; }

$pageViolations = array_slice($violations, $offset, $perPage);
$pageCount      = count($pageViolations);

/** keep other query params when switching pages */
function security_build_page_link(int $targetPage): string {
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    $q = $_GET; unset($q['page']);
    $q['page'] = $targetPage;
    return htmlspecialchars($base . '?' . http_build_query($q), ENT_QUOTES, 'UTF-8');
}

$pagerHtml = '';
if ($totalReports > 0) {
    $prevDisabled = $page <= 1;
    $nextDisabled = $page >= $lastPage;
    $statusText = 'Page '.$page.' of '.$lastPage.' • '.$totalReports.' total';
    $pagerHtml = '
    <nav class="pagerbar" aria-label="Pagination">
      <div class="pagerbar__status">'.safeText($statusText).'</div>
      <div class="pagerbar__controls">
        <a class="pagerbtn'.($prevDisabled?' is-disabled':'').'" href="'.($prevDisabled ? '#' : security_build_page_link($page-1)).'" aria-disabled="'.($prevDisabled?'true':'false').'" rel="prev">← Prev</a>
        <a class="pagerbtn'.($nextDisabled?' is-disabled':'').'" href="'.($nextDisabled ? '#' : security_build_page_link($page+1)).'" aria-disabled="'.($nextDisabled?'true':'false').'" rel="next">Next →</a>
      </div>
    </nav>';
}
/* ================= /PAGINATION (ADD-ONLY) =================== */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Security · Approved Violations</title>
  <link rel="stylesheet" href="../css/security_dashboard.css">
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
            Showing <strong id="resultsCount"><?= $pageCount; ?></strong> of <strong id="totalCount"><?= $pageCount; ?></strong> approved reports
          </div>
        </div>
        <section class="cards-grid" id="violationsGrid" aria-label="Approved reports">
          <?php foreach ($pageViolations as $violation):
              $firstName = trim((string)($violation['first_name'] ?? ''));
              $lastName  = trim((string)($violation['last_name'] ?? ''));
              $studentName = trim($firstName . ' ' . $lastName);
              if ($studentName === '') { $studentName = 'Unnamed student'; }
              $studentId = (string)($violation['student_id'] ?? '—');
              $categoryLabel = trim((string)($violation['offense_category'] ?? '')) ?: 'Uncategorized';
              $typeLabel     = trim((string)($violation['offense_type'] ?? '')) ?: 'Unspecified';
              $statusLabelRaw = trim((string)($violation['status'] ?? ''));
              $statusLabel = $statusLabelRaw !== '' ? ucwords(str_replace('_',' ', strtolower($statusLabelRaw))) : 'Approved';
              $photoFile = trim((string)($violation['student_photo'] ?? ''));
              $photoSrc  = $photoFile !== '' ? '../admin/uploads/' . $photoFile : 'placeholder.png';
              $description = trim((string)($violation['description'] ?? ''));
              $dateInfo = describeDate($violation['reported_at'] ?? null);
              $searchIndex = toSearchIndex($studentName.' '.$studentId.' '.$categoryLabel.' '.$typeLabel.' '.$description);
              $categoryKey = normaliseKey($categoryLabel);
              $typeKey     = normaliseKey($typeLabel);
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
                <span class="status-pill"><?= safeText($statusLabel); ?></span>
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

        <!-- PAGER BOTTOM -->
        <?= $pagerHtml ?>

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
            <a class="button button--primary" href="/moralmatrix/security/report_student.php">Report new case</a>
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
    function sync(){ if (!summary) return; summary.setAttribute('aria-expanded', dropdown.hasAttribute('open') ? 'true' : 'false'); }
    dropdown.addEventListener('toggle', sync);
    document.addEventListener('click', function(evt){ if (!dropdown.contains(evt.target)) { dropdown.removeAttribute('open'); sync(); }});
    document.addEventListener('keydown', function(evt){ if (evt.key === 'Escape') { dropdown.removeAttribute('open'); sync(); }});
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
      const totalRecords = cards.length;

      function parseTime(value){ const parsed = Date.parse(value); return Number.isNaN(parsed) ? 0 : parsed; }

      function sortCards(list){
        const mode = sortSelect ? sortSelect.value : 'newest';
        const sorted = list.slice();
        switch(mode){
          case 'oldest':  sorted.sort((a,b)=>parseTime(a.dataset.reportedAt||'')-parseTime(b.dataset.reportedAt||'')); break;
          case 'student': sorted.sort((a,b)=>(a.dataset.student||'').localeCompare(b.dataset.student||'')); break;
          case 'category':sorted.sort((a,b)=>(a.dataset.categoryLabel||'').localeCompare(b.dataset.categoryLabel||'')); break;
          case 'newest':
          default:        sorted.sort((a,b)=>parseTime(b.dataset.reportedAt||'')-parseTime(a.dataset.reportedAt||'')); break;
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
          if (shouldShow) visible.push(card);
        });

        visible = sortCards(visible);
        const hiddenCards = cards.filter(card => card.hidden);
        [...visible, ...hiddenCards].forEach(card => grid?.appendChild(card));

        const visibleCount = visible.length;
        if (resultsCount) resultsCount.textContent = visibleCount.toString();
        if (noResults) noResults.hidden = visibleCount !== 0;

        const hasFilters = !!query || !!category || !!type || (sortSelect && sortSelect.value !== 'newest');
        if (resetButton) resetButton.hidden = !hasFilters;
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

      if (totalCount) totalCount.textContent = totalRecords.toString();
      applyFilters();
    })();
  </script>
  <?php endif; ?>
</body>
</html>
