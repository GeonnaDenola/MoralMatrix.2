<?php

include '../includes/header.php';
include '../config.php';
include 'page_buttons.php';


$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
     die("Connection failed: " . $conn->connect_error); 
}


$sql = "SELECT validator_id, v_username, created_at, expires_at, active 
        FROM validator_account
        ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result === false){
    die("Query error: " .$conn->error);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h3>Community Service Validators</h3>

    <div class="community_service_btns">
        <a href= "add_validator.php">
            <button>Create Account</button>
        </a>
    </div>

    <div class="card-container">
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card">
                <h3><?php echo htmlspecialchars($row['v_username']); ?></h3>
                <p><b>Created:</b> <?php echo date("M d, Y", strtotime($row['created_at'])); ?></p>
                <p>
                    <span class="status <?php echo $row['active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $row['active'] ? "Active" : "Inactive"; ?>
                    </span>
                </p>
            </div>
        <?php endwhile; ?>

    </div>

<script>
document.addEventListener('click', e => {
  const card = e.target.closest('.card[data-href]');
  if (!card) return;
  // allow modifier keys to open in new tab/window via a synthetic anchor
  if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
  window.location = card.dataset.href;
});
document.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    const card = document.activeElement.closest?.('.card[data-href]');
    if (card) window.location = card.dataset.href;
  }
});
</script>

</body>
</html>