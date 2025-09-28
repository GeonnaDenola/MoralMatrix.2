<?php
require_once 'auth_check.php';
include '../includes/header.php';

require_once '../config.php';

// DB connection
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$validator_id = $_SESSION['validator_id'];

// Fetch assigned students
$sql = "SELECT 
            s.student_id,
            CONCAT(s.first_name, ' ', s.middle_name, ' ', s.last_name) AS full_name,
            s.course,
            s.level,
            s.section,
            a.starts_at,
            a.ends_at,
            a.notes
        FROM validator_student_assignment a
        INNER JOIN student_account s ON a.student_id = s.student_id
        WHERE a.validator_id = ?
        ORDER BY a.starts_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $validator_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Validator Dashboard</title>
  <style>
    body {
        font-family: Arial, sans-serif;
        background: #f5f7fa;
    }
    h2 {
        color: #333;
    }
    .card-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }
    .card-link {
        text-decoration: none;
        color: inherit;
    }
    .card {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        cursor: pointer;
    }
    .card h3 {
        margin: 0;
        font-size: 18px;
        color: #2c3e50;
    }
    .card p {
        margin: 5px 0;
        color: #555;
    }
    .status {
        font-weight: bold;
        color: #27ae60;
    }
    .status.ongoing {
        color: #2980b9;
    }
  </style>
</head>
<body>
  <h2>Welcome, <?= htmlspecialchars($_SESSION['v_username']) ?>!</h2>

  <h3>Assigned Students</h3>

  <div class="card-container">
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <a class="card-link" href="student_details.php?student_id=<?= urlencode($row['student_id']) ?>">
          <div class="card">
            <h3><?= htmlspecialchars($row['full_name']) ?></h3>
            <p><strong>ID:</strong> <?= htmlspecialchars($row['student_id']) ?></p>
            <p><strong>Course:</strong> <?= htmlspecialchars($row['course']) ?></p>
            <p><strong>Level:</strong> <?= htmlspecialchars($row['level']) ?> - <?= htmlspecialchars($row['section']) ?></p>
            <p><strong>Starts At:</strong> <?= htmlspecialchars($row['starts_at']) ?></p>
            <p><strong>Ends At:</strong> 
              <?php if ($row['ends_at']): ?>
                <span class="status"><?= htmlspecialchars($row['ends_at']) ?></span>
              <?php else: ?>
                <span class="status ongoing">Ongoing</span>
              <?php endif; ?>
            </p>
            <?php if (!empty($row['notes'])): ?>
              <p><strong>Notes:</strong> <?= htmlspecialchars($row['notes']) ?></p>
            <?php endif; ?>
          </div>
        </a>
      <?php endwhile; ?>
    <?php else: ?>
      <p>No students are currently assigned to you.</p>
    <?php endif; ?>
  </div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
