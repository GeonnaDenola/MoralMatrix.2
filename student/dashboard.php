<?php
// ---------- CONFIG & SESSION ----------
require '../config.php';
session_start();

// ---------- SESSION CHECK ----------
if (!isset($_SESSION['student_id'])) {
    $_SESSION['error'] = "You’re not logged in or your session expired. Please sign in again.";
    header("Location: ../login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// ---------- DB CONNECTION ----------
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------- FETCH STUDENT NAME ----------
$sqlname = "SELECT first_name, middle_name, last_name FROM student_account WHERE student_id = ?";
$stmtname = $conn->prepare($sqlname);
$stmtname->bind_param("s", $student_id);
$stmtname->execute();
$result = $stmtname->get_result();
$student = $result->fetch_assoc();
$stmtname->close();

$first_name = $student['first_name'] ?? 'Student';

// ---------- FETCH VIOLATIONS ----------
$sql = "SELECT offense_category, offense_type, offense_details, description, photo, status, submitted_by, reported_at
        FROM student_violation
        WHERE student_id = ? AND status = 'Approved'
        ORDER BY reported_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// ---------- COMMUNITY SERVICE HOURS ----------
require_once __DIR__ . '/../ccdu/violation_hrs.php';

$requiredHours  = communityServiceHours($conn, $student_id);
$loggedHours    = communityServiceLogged($conn, $student_id);
$remainingHours = communityServiceRemaining($conn, $student_id);

// ---------- INCLUDE HEADER ----------
include '../includes/student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>
<link rel="stylesheet" href="../css/student_dashboard.css?v=<?= time() ?>">
</head>
<body>

<div class="dashboard-container" style="margin-top: 80px;">
    <h2 class="welcome-text">Welcome, <?= htmlspecialchars($first_name) ?>!</h2>

    <h3 class="section-title">Your Community Service Hours</h3>

    <div class="hours-cards">
        <div class="hours-card">
            <h4>Required</h4>
            <p class="hours required"><?= number_format($requiredHours, 2) ?> h</p>
        </div>
        <div class="hours-card">
            <h4>Logged</h4>
            <p class="hours logged"><?= number_format($loggedHours, 2) ?> h</p>
        </div>
        <div class="hours-card">
            <h4>Remaining</h4>
            <p class="hours remaining"><?= number_format($remainingHours, 2) ?> h</p>
        </div>
    </div>
    
    <h3 class="section-title">Your Violation History</h3>

    <div class="card-container">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="card">

                    <?php
                    // --- IMAGE HANDLING ---
                    if (!empty($row['photo'])):
                        $photoFile = trim($row['photo']);
                        $photoPath = "../ccdu/uploads/" . basename($photoFile);

                        // Avoid duplicate folder if photo path already contains ccdu/uploads/
                        if (str_contains($photoFile, 'ccdu/uploads/')) {
                            $photoPath = "../" . ltrim($photoFile, '/');
                        }
                    ?>
                        <div class="card-image">
                            <img src="<?= htmlspecialchars($photoPath) ?>" alt="Evidence"
                                 onerror="this.style.display='none'">
                        </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <h4 class="offense-title">
                            <?= htmlspecialchars(labelize($row['offense_category'])) ?> -
                            <?= htmlspecialchars(labelize($row['offense_type'])) ?>
                        </h4>

                        <p><strong>Details:</strong> <?= htmlspecialchars(labelize($row['offense_details'])) ?></p>
                        <p><strong>Description:</strong> <?= htmlspecialchars(labelize($row['description'])) ?></p>

                        <?php if (!empty($row['reported_at'])): ?>
                            <p class="violation-date">
                                <strong>Recorded on:</strong>
                                <?= date('F j, Y g:i A', strtotime($row['reported_at'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Optional footer if needed later -->
                    <!--
                    <div class="card-footer">
                        <span class="badge <?= strtolower($row['status']) ?>">
                            <?= htmlspecialchars($row['status']) ?>
                        </span>
                    </div>
                    -->
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-violations">No violations recorded.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
