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

$validator_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($validator_id <= 0) {
    die("Invalid validator ID.");
}

/* --- Handle status toggle --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $sql = "UPDATE validator_account SET active = NOT active WHERE validator_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $validator_id);
    $stmt->execute();
    $stmt->close();

    // Refresh page after toggle
    header("Location: validator_details.php?id=" . $validator_id);
    exit;
}

/* --- Fetch validator details --- */
$sql = "SELECT validator_id, v_username, designation, email, created_at, expires_at, active 
        FROM validator_account 
        WHERE validator_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $validator_id);
$stmt->execute();
$result = $stmt->get_result();
$validator = $result->fetch_assoc();
$stmt->close();

if (!$validator) {
    die("Validator not found.");
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
    <div class="details-container">
        <h2><?php echo htmlspecialchars($validator['v_username']); ?></h2>
        <p><b>Email:</b> <?php echo htmlspecialchars($validator['email']); ?></p>
        <p><b>Designation:</b> <?php echo htmlspecialchars($validator['designation']); ?></p>
        <p><b>Created At:</b> <?php echo date("M d, Y h:i A", strtotime($validator['created_at'])); ?></p>
        <p><b>Expires At:</b> 
        <?php echo $validator['expires_at'] ? date("M d, Y h:i A", strtotime($validator['expires_at'])) : '—'; ?>
        </p>
        <p><b>Status:</b> 
        <span class="status <?php echo $validator['active'] ? 'active' : 'inactive'; ?>">
            <?php echo $validator['active'] ? "Active" : "Inactive"; ?>
        </span>
        </p>

        <form method="post" style="margin-top: 20px;">
            <button type="submit" name="toggle_status">
                <?php echo $validator['active'] ? "Deactivate" : "Activate"; ?>
            </button>
        </form>
  </div>
</body>
</body>
</html>