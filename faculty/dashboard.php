<?php
// faculty/dashboard.php
require '../auth.php';
require_role('faculty');

include '../config.php';
include '../includes/faculty_header.php';

// DB connect
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// current faculty id (id_number stored in session by your login)
$faculty_id = $_SESSION['actor_id'] ?? null;
if (!$faculty_id) {
    die("No faculty id in session. Please login again.");
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
  AND sv.status = 'approved'
ORDER BY sv.reported_at DESC, sv.violation_id DESC
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("s", $faculty_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$result = $stmt->get_result();
$totalApproved = ($result && $result->num_rows) ? (int) $result->num_rows : 0;

/**
 * Build a normalised search string for client-side filtering.
 */
function faculty_to_search_index(string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Faculty - Approved Violations</title>
  <link rel="stylesheet" href="../css/faculty_dashboard.css">
</head>
<body>

<main class="page" aria-labelledby="pageTitle">
  <div class="dashboard-shell">
    <section class="page-heading">
      <div class="page-heading__intro">
        <span class="page-heading__eyebrow">Faculty Command Center</span>
        <h1 id="pageTitle">Approved violation overview</h1>
        <p>Review the cases you reported that have cleared approval and revisit their details any time.</p>
      </div>
      <div class="page-heading__actions">
        <a class="button button--ghost" href="pending_reports.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"></circle>
            <line x1="12" y1="7" x2="12" y2="13"></line>
            <circle cx="12" cy="17" r="1" fill="currentColor" stroke="none"></circle>
          </svg>
          Review pending
        </a>
        <a class="button button--primary" href="report_student.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 5v14M5 12h14"/>
          </svg>
          Report new case
        </a>
      </div>
    </section>

    <?php if ($totalApproved > 0): ?>
      <div class="results-meta" aria-live="polite">
        <div>
          Showing <strong id="resultsCount"><?= $totalApproved; ?></strong> of <strong id="totalCount"><?= $totalApproved; ?></strong> approved reports
        </div>
      </div>

      <section class="filter-panel" aria-label="Filtering tools">
        <label class="search-input" for="searchInput">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input type="search" id="searchInput" name="search" placeholder="Search by name, student ID, or keywords">
        </label>

        <div class="filters-actions">
          <button type="button" class="button button--subtle" id="clearSearch" hidden>Clear search</button>
        </div>
      </section>

      <section class="cards-grid" id="violationsGrid" aria-label="Approved reports">
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
            $studentPhotoFile = $row['student_photo'] ?? '';
            $studentPhotoSrc = $studentPhotoFile
                ? '../admin/uploads/' . $studentPhotoFile
                : 'placeholder.png';

            $firstName = trim((string)($row['first_name'] ?? ''));
            $lastName  = trim((string)($row['last_name'] ?? ''));
            $studentName = trim($firstName . ' ' . $lastName);
            if ($studentName === '') {
                $studentName = 'Unnamed student';
            }

            $studentId = (string)($row['student_id'] ?? '-');

            $categoryLabel = trim((string)($row['offense_category'] ?? ''));
            if ($categoryLabel === '') {
                $categoryLabel = 'Uncategorized';
            }

            $typeLabel = trim((string)($row['offense_type'] ?? ''));
            if ($typeLabel === '') {
                $typeLabel = 'Unspecified';
            }

            $statusLabelRaw = trim((string)($row['status'] ?? ''));
            $statusLabel = $statusLabelRaw !== ''
                ? ucwords(strtolower($statusLabelRaw))
                : 'Approved';

            $description = trim((string)($row['description'] ?? ''));

            $reportedRaw = $row['reported_at'] ?? '';
            $reportedIso = '';
            $reportedFull = 'Date unavailable';
            if ($reportedRaw) {
                $timestamp = strtotime($reportedRaw);
                if ($timestamp !== false) {
                    $reportedIso = date(DATE_ATOM, $timestamp);
                    $reportedFull = date('M j, Y \\a\\t g:i A', $timestamp);
                }
            }

            $violationId = urlencode((string)($row['violation_id'] ?? ''));
            $searchIndex = faculty_to_search_index(
                $studentName . ' ' . $studentId . ' ' . $categoryLabel . ' ' . $typeLabel . ' ' . $description
            );
          ?>
          <article class="violation-card" data-search="<?= htmlspecialchars($searchIndex, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="card-header">
              <div class="student">
                <div class="avatar">
                  <img
                    src="<?= htmlspecialchars($studentPhotoSrc, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Photo of <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?>"
                    width="72"
                    height="72"
                    loading="lazy"
                    decoding="async"
                    onerror="this.onerror=null;this.src='placeholder.png';"
                  >
                </div>
                <div>
                  <h3><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></h3>
                  <div class="student-id"><?= htmlspecialchars($studentId, ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="meta">
                    <span><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span>&middot;</span>
                    <span><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                </div>
              </div>
              <span class="status-pill">
                <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </div>

            <div class="card-body">
              <?php if ($description !== ''): ?>
                <p><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')); ?></p>
              <?php else: ?>
                <p><em>No additional notes provided.</em></p>
              <?php endif; ?>
            </div>

            <div class="card-footer">
              <div>
                <time datetime="<?= htmlspecialchars($reportedIso, ENT_QUOTES, 'UTF-8'); ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <polyline points="12 7 12 12 16 14"/>
                  </svg>
                  <?= htmlspecialchars($reportedFull, ENT_QUOTES, 'UTF-8'); ?>
                </time>
              </div>
              <a href="view_violation_approved.php?id=<?= $violationId; ?>">
                View details
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <line x1="5" y1="12" x2="19" y2="12"/>
                  <polyline points="12 5 19 12 12 19"/>
                </svg>
              </a>
            </div>
          </article>
        <?php endwhile; ?>
      </section>

      <div class="no-results" id="noResults" hidden>
        No matches found for your current search. Try a different name, student ID, or keyword.
      </div>
    <?php else: ?>
      <section class="empty-state">
        <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="9" stroke-dasharray="2 4"/>
          <path d="M9 10.5l3 3 3-3"/>
        </svg>
        <h2>No approved reports yet</h2>
        <p>Once the violations you report are approved, they'll show up here so you can keep track of outcomes.</p>
        <div class="tips">
          <span>Check the pending queue to follow up</span>
          <span>Submit a new violation report</span>
        </div>
        <div class="page-heading__actions">
          <a class="button button--ghost" href="pending_reports.php">Go to pending queue</a>
          <a class="button button--primary" href="report_student.php">Report new case</a>
        </div>
      </section>
    <?php endif; ?>
  </div>
</main>

<?php if ($totalApproved > 0): ?>
<script>
(function(){
  const searchInput = document.getElementById('searchInput');
  const clearButton = document.getElementById('clearSearch');
  const cards = Array.from(document.querySelectorAll('.violation-card'));
  const resultsCount = document.getElementById('resultsCount');
  const totalCount = document.getElementById('totalCount');
  const noResults = document.getElementById('noResults');

  const totalRecords = cards.length;
  if (totalCount) {
    totalCount.textContent = totalRecords.toString();
  }

  function applySearch(){
    const query = (searchInput?.value || '').trim().toLowerCase();
    let visible = 0;

    cards.forEach(card => {
      const matches = !query || (card.dataset.search || '').includes(query);
      card.hidden = !matches;
      card.classList.toggle('is-hidden', !matches);
      if (matches) {
        visible++;
      }
    });

    if (resultsCount) {
      resultsCount.textContent = visible.toString();
    }
    if (noResults) {
      noResults.hidden = visible !== 0;
    }
    if (clearButton) {
      clearButton.hidden = query === '';
    }
  }

  clearButton?.addEventListener('click', function(){
    if (searchInput) {
      searchInput.value = '';
      searchInput.focus();
    }
    applySearch();
  });

  searchInput?.addEventListener('input', applySearch);
  applySearch();
})();
</script>
<?php endif; ?>

<?php
$stmt->close();
$conn->close();
?>
</body>
</html>
