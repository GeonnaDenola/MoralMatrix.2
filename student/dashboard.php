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
        WHERE student_id = ?
        ORDER BY reported_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// ---------- INCLUDE HEADER ----------
include '../includes/student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>
<link rel="stylesheet" href="../css/student_dashboard.css">
</head>
<body>

<div class="dashboard-container">
    <h2 class="welcome-text">Welcome, <?= htmlspecialchars($first_name) ?>!</h2>
    <h3 class="section-title">Your Violation History</h3>

    <div class="card-container">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="card">
                    <?php if (!empty($row['photo'])): ?>
                        <div class="card-image">
                            <img src="../ccdu/uploads/<?= htmlspecialchars($row['photo']) ?>" alt="Evidence">
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h4 class="offense-title"><?= htmlspecialchars($row['offense_category']) ?> - <?= htmlspecialchars($row['offense_type']) ?></h4>
                        <p><strong>Date:</strong> <?= htmlspecialchars($row['reported_at']) ?></p>
                        <p><strong>Details:</strong> <?= htmlspecialchars($row['offense_details']) ?></p>
                        <p><strong>Description:</strong> <?= htmlspecialchars($row['description']) ?></p>
                    </div>
                    <div class="card-footer">
                        <span class="badge <?= strtolower($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span>
                    </div>
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
