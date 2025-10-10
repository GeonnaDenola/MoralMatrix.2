<?php
session_start();
include '../includes/header.php';
include '../config.php';
include 'page_buttons.php';
include __DIR__ . '/_scanner.php';

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT 
    sv.violation_id,
    sv.student_id,
    sa.first_name AS student_first_name,
    sa.last_name AS student_last_name,
    sa.course,
    sa.level,
    sa.section,
    sv.offense_category,
    sv.offense_type,
    sv.description,
    sv.status,
    sv.submitted_role,
    sv.reported_at,
    CASE sv.submitted_role
        WHEN 'faculty' THEN CONCAT(fa.first_name, ' ', fa.last_name)
        WHEN 'security' THEN CONCAT(se.first_name, ' ', se.last_name)
    END AS submitter_name
FROM student_violation sv
JOIN student_account sa 
    ON sv.student_id = sa.student_id
LEFT JOIN faculty_account fa 
    ON sv.submitted_by = fa.faculty_id AND sv.submitted_role = 'faculty'
LEFT JOIN security_account se 
    ON sv.submitted_by = se.security_id AND sv.submitted_role = 'security'
WHERE sv.status = 'pending'
ORDER BY sv.reported_at DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Pending Reports</title>
    <link rel="stylesheet" href="../css/pending_reports.css"/>
</head>
<body>
    <main class="pr-page" style = "padding-top: var(--header-h);">
        <div class="pr-head">
            <h1 class="pr-title">Pending Reports</h1>
            <?php if ($result instanceof mysqli_result): ?>
                <span class="pr-count" aria-label="Total pending">
                    <?= (int)$result->num_rows ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Success/error banner -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="pr-banner">
                <?php if ($_GET['msg'] === 'approved'): ?>
                    ✅ Report approved successfully.
                <?php elseif ($_GET['msg'] === 'rejected'): ?>
                    ❌ Report rejected successfully.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($result instanceof mysqli_result && $result->num_rows > 0): ?>
            <div class="pr-grid">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $violationId     = (int)$row['violation_id'];
                        $photo           = isset($row['photo']) ? trim($row['photo']) : '';
                        $firstName       = htmlspecialchars($row['student_first_name'] ?? '', ENT_QUOTES, 'UTF-8');
                        $lastName        = htmlspecialchars($row['student_last_name']  ?? '', ENT_QUOTES, 'UTF-8');
                        $studentId       = htmlspecialchars($row['student_id']         ?? '', ENT_QUOTES, 'UTF-8');
                        $course          = htmlspecialchars($row['course']            ?? '', ENT_QUOTES, 'UTF-8');
                        $level           = htmlspecialchars($row['level']             ?? '', ENT_QUOTES, 'UTF-8');
                        $section         = htmlspecialchars($row['section']           ?? '', ENT_QUOTES, 'UTF-8');
                        $categoryRaw     = htmlspecialchars($row['offense_category']   ?? '', ENT_QUOTES, 'UTF-8');
                        $category        = $categoryRaw !== '' ? ucfirst($categoryRaw) : '';
                        $type            = htmlspecialchars($row['offense_type']       ?? '', ENT_QUOTES, 'UTF-8');
                        $description     = htmlspecialchars($row['description']        ?? '', ENT_QUOTES, 'UTF-8');
                        $submitter       = htmlspecialchars($row['submitter_name']     ?? '', ENT_QUOTES, 'UTF-8');
                        $submittedRole   = htmlspecialchars($row['submitted_role']     ?? '', ENT_QUOTES, 'UTF-8');
                        $courseLine      = trim($course . ' ' . $level . '-' . $section);
                    ?>
                    <article class="pr-card">
                        <div class="pr-media">
                            <?php if ($photo !== ''): ?>
                                <img
                                    src="uploads/<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="Evidence photo for <?= $firstName . ' ' . $lastName ?>"
                                    loading="lazy"
                                />
                            <?php else: ?>
                                <img
                                    src="placeholder.png"
                                    alt="No evidence photo provided"
                                    loading="lazy"
                                />
                            <?php endif; ?>
                        </div>

                        <div class="pr-body">
                            <h2 class="pr-name"><?= $firstName . ' ' . $lastName ?></h2>

                            <div class="pr-meta">
                                <div class="pr-meta-row">
                                    <span class="pr-label">Student ID</span>
                                    <span class="pr-value"><?= $studentId ?></span>
                                </div>
                                <div class="pr-meta-row">
                                    <span class="pr-label">Course</span>
                                    <span class="pr-value"><?= $courseLine ?></span>
                                </div>
                                <div class="pr-chips">
                                    <?php if ($category): ?>
                                        <span class="pr-chip pr-chip--category"><?= $category ?></span>
                                    <?php endif; ?>
                                    <?php if ($type): ?>
                                        <span class="pr-chip pr-chip--type"><?= $type ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($description): ?>
                                    <p class="pr-desc"><?= $description ?></p>
                                <?php endif; ?>
                                <p class="pr-submitted">
                                    <span class="pr-label">Submitted by</span>
                                    <span class="pr-value"><?= $submitter ?> (<?= ucfirst($submittedRole) ?>)</span>
                                </p>
                            </div>

                            <div class="pr-actions">
                                <!-- One handler file -->
                                <form action="approve_violation.php" method="post" class="pr-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="id" value="<?= $violationId ?>"/>
                                    <input type="hidden" name="action" value="approve"/>
                                    <button type="submit" class="btn btn-approve" aria-label="Approve report for <?= $firstName . ' ' . $lastName ?>">Approve</button>
                                </form>

                                <form action="approve_violation.php" method="post" class="pr-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="id" value="<?= $violationId ?>"/>
                                    <input type="hidden" name="action" value="reject"/>
                                    <button type="submit" class="btn btn-reject" aria-label="Reject report for <?= $firstName . ' ' . $lastName ?>">Reject</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <section class="pr-empty">
                <img src="empty-state.svg" alt="" aria-hidden="true"/>
                <h2>No pending violations</h2>
                <p>Everything looks clear for now. New reports will show up here as they come in.</p>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
