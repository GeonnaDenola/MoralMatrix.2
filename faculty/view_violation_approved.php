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
    <main class="layout" role="main" aria-labelledby="violation-title">
        <section class="violation-card" role="region">
            <div class="card-header">
                <h2 id="violation-title">Violation Report</h2>
                <span
                    class="status-badge <?= strtolower(str_replace(' ', '-', $violation['status'])) ?>">
                    <?= htmlspecialchars($violation['status']) ?>
                </span>
            </div>

            <dl class="details">
                <div class="row">
                    <dt>Student</dt>
                    <dd><?= htmlspecialchars($violation['first_name']." ".$violation['last_name']) ?></dd>
                </div>

                <div class="row">
                    <dt>Category</dt>
                    <dd><?= htmlspecialchars($violation['offense_category']) ?></dd>
                </div>

                <div class="row">
                    <dt>Type</dt>
                    <dd><?= htmlspecialchars($violation['offense_type']) ?></dd>
                </div>

                <div class="row">
                    <dt>Description</dt>
                    <dd><?= htmlspecialchars($violation['description']) ?></dd>
                </div>
            </dl>

            <?php if (!empty($violation['photo'])): ?>
                <figure class="evidence">
                    <img
                        src="../uploads/<?= htmlspecialchars($violation['photo']) ?>"
                        alt="Photo evidence for <?= htmlspecialchars($violation['first_name'].' '.$violation['last_name']) ?>"
                        loading="lazy">
                    <figcaption>Photo evidence</figcaption>
                </figure>
            <?php else: ?>
                <p class="muted no-photo"><em>No photo evidence uploaded.</em></p>
            <?php endif; ?>

            <div class="meta">
                <small>Reported at: <?= htmlspecialchars($violation['reported_at']) ?></small>
            </div>
        </section>
    </main>
<?php else: ?>
    <main class="layout">
        <section class="empty-state">
            <h2>Violation not found</h2>
            <p class="muted">The report you’re looking for doesn’t exist or was removed.</p>
        </section>
    </main>
<?php endif; ?>
</body>
</html>
