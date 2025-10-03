<?php
// Start output buffering to prevent "headers already sent" issues
ob_start();

require_once '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die("Connection failed: " . $conn->connect_error);
}

// Accept id from GET (view) or POST (toggle form)
$validator_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($validator_id <= 0) {
    http_response_code(400);
    die("Invalid validator ID.");
}

/* --- Handle status toggle BEFORE any output or includes --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $sql = "UPDATE validator_account SET active = NOT active WHERE validator_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $validator_id);
    $stmt->execute();
    $stmt->close();

    // Redirect back to the same page (safe: no prior output)
    header("Location: validator_details.php?id=" . $validator_id);
    exit;
}

/* --- Fetch validator details --- */
$sql = "SELECT validator_id, v_username, designation, email, created_at, expires_at, active
        FROM validator_account
        WHERE validator_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $validator_id);
$stmt->execute();
$result = $stmt->get_result();
$validator = $result->fetch_assoc();
$stmt->close();

if (!$validator) {
    http_response_code(404);
    die("Validator not found.");
}

$isActive = (int)$validator['active'] === 1;

// Format dates safely
$createdDisplay = $validator['created_at']
    ? date("M d, Y h:i A", strtotime($validator['created_at']))
    : '—';

$expiresRaw = trim((string)$validator['expires_at']);
$expiresDisplay = ($expiresRaw && $expiresRaw !== '0000-00-00 00:00:00')
    ? date("M d, Y h:i A", strtotime($expiresRaw))
    : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Validator Details • <?php echo htmlspecialchars($validator['v_username']); ?></title>
  <link rel="stylesheet" href="../css/validator_details.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <?php
    // Include your header/sidebar AFTER handling redirects
    include '../includes/header.php';
    include 'page_buttons.php';
  ?>

  <main class="vd-page">
    <div class="vd-wrap">
      <div class="vd-card">
        <header class="vd-card__header">
          <div class="vd-avatar" aria-hidden="true">
            <?php echo strtoupper(substr($validator['v_username'], 0, 1)); ?>
          </div>
          <div class="vd-title">
            <h1 class="vd-h1"><?php echo htmlspecialchars($validator['v_username']); ?></h1>
            <span class="vd-badge <?php echo $isActive ? 'vd-badge--success' : 'vd-badge--muted'; ?>">
              <?php echo $isActive ? 'Active' : 'Inactive'; ?>
            </span>
          </div>
        </header>

        <div class="vd-grid">
          <div class="vd-row">
            <dt>Email</dt>
            <dd><?php echo htmlspecialchars($validator['email']); ?></dd>
          </div>

          <div class="vd-row">
            <dt>Designation</dt>
            <dd><?php echo htmlspecialchars($validator['designation']); ?></dd>
          </div>

          <div class="vd-row">
            <dt>Created</dt>
            <dd><?php echo $createdDisplay; ?></dd>
          </div>

          <div class="vd-row">
            <dt>Expires</dt>
            <dd><?php echo $expiresDisplay; ?></dd>
          </div>

          <div class="vd-row">
            <dt>Status</dt>
            <dd>
              <span class="vd-status <?php echo $isActive ? 'vd-status--on' : 'vd-status--off'; ?>">
                <?php echo $isActive ? "Active" : "Inactive"; ?>
              </span>
            </dd>
          </div>
        </div>

        <form method="post" class="vd-actions">
          <input type="hidden" name="id" value="<?php echo (int)$validator['validator_id']; ?>" />
          <button
            type="submit"
            name="toggle_status"
            class="vd-btn <?php echo $isActive ? 'vd-btn--danger' : 'vd-btn--success'; ?>"
            onclick="return confirm('Are you sure you want to <?php echo $isActive ? 'deactivate' : 'activate'; ?> this account?');"
          >
            <?php echo $isActive ? "Deactivate" : "Activate"; ?>
          </button>
          <button type="button" class="vd-btn vd-btn--ghost" onclick="history.back()">Back</button>
        </form>
      </div>

      <p class="vd-footnote">ID: <?php echo (int)$validator['validator_id']; ?></p>
    </div>
  </main>

  <?php ob_end_flush(); ?>
</body>
</html>
