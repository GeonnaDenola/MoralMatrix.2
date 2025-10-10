<?php
require '../auth.php';
require_role('faculty');
require '../config.php';

include '../includes/faculty_header.php';


$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$violation_id = $_GET['id'] ?? '';
if (!$violation_id) {
    die("Invalid violation ID.");
}

// include the photo column in query
$sql = "SELECT sv.violation_id, sv.student_id, sv.offense_category, sv.offense_type, 
               sv.description, sv.reported_at, sv.status, sv.photo, 
               sa.first_name, sa.last_name
        FROM student_violation sv
        JOIN student_account sa ON sv.student_id = sa.student_id
        WHERE sv.violation_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $violation_id);
$stmt->execute();
$result = $stmt->get_result();
$violation = $result->fetch_assoc();
$stmt->close();

$studentName = '';
$studentId = '';
$category = '';
$type = '';
$description = '';
$status = '';
$statusClass = '';
$reportedRaw = '';
$reportedDisplay = '';
$reportedRelative = '';
$photoSrc = '';

if ($violation) {
    $studentFirst = trim((string)($violation['first_name'] ?? ''));
    $studentLast  = trim((string)($violation['last_name'] ?? ''));
    $studentName  = trim($studentFirst . ' ' . $studentLast);
    if ($studentName === '') {
        $studentName = 'Unnamed student';
    }
    $studentId = trim((string)($violation['student_id'] ?? ''));
    $category = trim((string)($violation['offense_category'] ?? 'Uncategorized'));
    $type = trim((string)($violation['offense_type'] ?? 'Unspecified'));
    $description = trim((string)($violation['description'] ?? ''));
    $status = trim((string)($violation['status'] ?? ''));
    $statusClass = strtolower(str_replace(' ', '-', $status));
    $reportedRaw = (string)($violation['reported_at'] ?? '');

    if ($reportedRaw !== '') {
        try {
            $reportedDate = new DateTime($reportedRaw);
            $reportedDisplay = $reportedDate->format('M d, Y g:i A');
            $reportedRelative = $reportedDate->format('l, F j');
        } catch (Exception $e) {
            $reportedDisplay = $reportedRaw;
        }
    }

    $photo = trim((string)($violation['photo'] ?? ''));
    if ($photo !== '') {
        $photoSrc = '../uploads/' . $photo;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Violation Details</title>
    <link rel="stylesheet" href="../css/faculty_view_violation.css">
</head>
<body class="violation-page">
<?php if ($violation): ?>
    <main class="violation-shell" role="main" aria-labelledby="violation-title">
        <section class="hero-card" role="region">
            <div class="hero-top">
                <div>
                    <span class="hero-eyebrow">Violation report</span>
                    <h1 id="violation-title"><?= htmlspecialchars($studentName); ?></h1>
                    <div class="hero-meta">
                        <?php if ($studentId !== ''): ?>
                            <span class="meta-chip">ID <?= htmlspecialchars($studentId); ?></span>
                        <?php endif; ?>
                        <span class="meta-chip"><?= htmlspecialchars($category); ?></span>
                        <span class="meta-chip"><?= htmlspecialchars($type); ?></span>
                    </div>
                </div>
                <?php if ($status !== ''): ?>
                    <span class="status-pill <?= htmlspecialchars($statusClass); ?>">
                        <?= htmlspecialchars($status); ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($reportedDisplay !== ''): ?>
                <div class="hero-bottom">
                    <div class="meta-block">
                        <span class="meta-label">Reported</span>
                        <span class="meta-value"><?= htmlspecialchars($reportedDisplay); ?></span>
                    </div>
                    <?php if ($reportedRelative !== ''): ?>
                        <div class="meta-block">
                            <span class="meta-label">Day</span>
                            <span class="meta-value"><?= htmlspecialchars($reportedRelative); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="content-grid">
            <article class="info-card">
                <h2>Overview</h2>
                <dl class="info-list">
                    <div class="info-row">
                        <dt>Student</dt>
                        <dd><?= htmlspecialchars($studentName); ?></dd>
                    </div>
                    <div class="info-row">
                        <dt>Category</dt>
                        <dd><?= htmlspecialchars($category); ?></dd>
                    </div>
                    <div class="info-row">
                        <dt>Type</dt>
                        <dd><?= htmlspecialchars($type); ?></dd>
                    </div>
                </dl>
            </article>

            <article class="summary-card">
                <h2>Description</h2>
                <?php if ($description !== ''): ?>
                    <p><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')); ?></p>
                <?php else: ?>
                    <p class="muted"><em>No additional description was provided.</em></p>
                <?php endif; ?>
            </article>
        </section>

        <section class="evidence-panel">
            <header>
                <div>
                    <h2>Photo evidence</h2>
                    <p class="muted">Visual context supplied with the report.</p>
                </div>
            </header>
            <?php if ($photoSrc !== ''): ?>
                <figure>
                    <img src="<?= htmlspecialchars($photoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Photo evidence for <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                    <figcaption><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></figcaption>
                </figure>
            <?php else: ?>
                <div class="empty-evidence">
                    <span class="muted">No photo evidence uploaded.</span>
                </div>
            <?php endif; ?>
        </section>
    </main>
<?php else: ?>
    <main class="violation-shell">
        <section class="empty-state">
            <h2>Violation not found</h2>
            <p class="muted">The report you’re looking for doesn’t exist or was removed.</p>
        </section>
    </main>
<?php endif; ?>
</body>
</html>
