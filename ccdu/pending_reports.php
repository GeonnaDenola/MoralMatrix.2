<?php
include '../includes/header.php';
include '../config.php';

include 'page_buttons.php';

include __DIR__ . '/_scanner.php';

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


$result = $conn->query($sql)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    <style>
  .violations { padding: 12px; max-width: 980px; margin: 0 auto; }
  .card-link { text-decoration:none; color:inherit; display:block; }
  .card {
    border:1px solid #ddd; border-radius:10px; padding:12px; margin:10px 0;
    display:flex; align-items:center; gap:18px; background:#fff;
    transition:transform .12s, box-shadow .12s;
  }
  .card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.06); cursor:pointer; }
  .card .left { flex: 0 0 120px; text-align:center; }
  .card .left img { width:100px; height:100px; object-fit:cover; border-radius:50%; border:2px solid #eee; }
  .card .info { flex:1; }
  .meta { color:#666; font-size:0.92rem; }
  .debug { background:#fff7ed; border:1px solid #fed7aa; padding:8px 10px; border-radius:8px; margin:12px auto; max-width:980px; font-size:.9rem; }
  .debug table { border-collapse:collapse; }
  .debug th, .debug td { border:1px solid #ddd; padding:4px 8px; }
    </style>
</head>
<body>
    <h3>Pending Reports</h3>

    
    <?php if ($result->num_rows > 0): ?>
        <div class="card-container">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="card">
                    <?php if (!empty($row['photo'])): ?>
                        <img src="uploads/<?= htmlspecialchars($row['photo']) ?>" alt="Evidence">
                    <?php else: ?>
                        <img src="placeholder.png" alt="No Photo">
                    <?php endif; ?>
                   <h3><?php echo $row['student_first_name'] . " " . $row['student_last_name']; ?></h3>
                    <p><strong>Student ID:</strong> <?php echo $row['student_id']; ?></p>
                    <p><strong>Course:</strong> <?php echo $row['course'] . " " . $row['level'] . "-" . $row['section']; ?></p>
                    <p><strong>Category:</strong> <?php echo ucfirst($row['offense_category']); ?></p>
                    <p><strong>Type:</strong> <?php echo $row['offense_type']; ?></p>
                    <p><strong>Description:</strong> <?php echo $row['description']; ?></p>
                    <p><strong>Submitted by:</strong> <?php echo $row['submitter_name']; ?> (<?php echo ucfirst($row['submitted_role']); ?>)</p>

                    
                    <div class="actions">
                        <form action="approve_violation.php" method="post" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $row['violation_id']; ?>">
                            <button type="submit" class="btn-approve">Approve</button>
                        </form>

                        <form action="reject_report.php" method="post" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $row['violation_id']; ?>">
                            <button type="submit" class="btn-reject">Reject</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No pending violations.</p>
    <?php endif; ?>


</body>
</html>