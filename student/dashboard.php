<?php
include '../includes/header.php';
require '../config.php';

include 'menu_buttons.php';

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>
<style>
body { font-family: Arial, sans-serif; background: #f5f7fa;}
h2 { color: #333; }
.card-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top:20px; }
.card { background: #fff; border-radius:10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); overflow:hidden; display:flex; flex-direction:column; }
.card img { width:100%; height:180px; object-fit:cover; }
.card-body { padding:15px; flex:1; }
.card-body h5 { margin:0 0 10px 0; font-size:16px; color:#2c3e50; }
.card-body p { margin:5px 0; font-size:14px; color:#555; }
.card-footer { padding:10px 15px; background:#f9f9f9; display:flex; justify-content:space-between; align-items:center; font-size:13px; }
.badge { padding:3px 7px; border-radius:5px; color:#fff; font-weight:bold; text-transform:capitalize; }
.badge.approved { background:#27ae60; }
.badge.pending { background:#f39c12; }
</style>
</head>
<body>

<h2>Welcome, <?= htmlspecialchars($first_name) ?>!</h2>
<h3>Your Violation History</h3>

<div class="card-container">
<?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
        <div class="card">
            <?php if (!empty($row['photo'])): ?>
                <img src="../ccdu/uploads/<?= htmlspecialchars($row['photo']) ?>" alt="evidence">
            <?php endif; ?>
            <div class="card-body">
                <h5><?= htmlspecialchars($row['offense_category']) ?> - <?= htmlspecialchars($row['offense_type']) ?></h5>
                <p><strong>Date:</strong> <?= htmlspecialchars($row['reported_at']) ?></p>
                <p><strong>Details:</strong> <?= htmlspecialchars($row['offense_details']) ?></p>
                <p><strong>Description:</strong> <?= htmlspecialchars($row['description']) ?></p>
            </div>
            <div class="card-footer">
                <span class="badge <?= strtolower($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span>
                <span>Submitted by: <?= htmlspecialchars($row['submitted_by']) ?></span>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p>No violations recorded.</p>
<?php endif; ?>
</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
