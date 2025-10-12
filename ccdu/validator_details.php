<?php
// Output buffer so redirects work even if includes echo content
ob_start();
require_once '../config.php';

// --- DB connect (kept same behavior) ---
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die("Connection failed: " . $conn->connect_error);
}

// --- ID from GET/POST ---
$validator_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($validator_id <= 0) {
    http_response_code(400);
    die("Invalid validator ID.");
}

// --- Toggle status first (no output before header) ---
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

    header("Location: validator_details.php?id=" . $validator_id);
    exit;
}

// --- Fetch details ---
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
$result    = $stmt->get_result();
$validator = $result->fetch_assoc();
$stmt->close();

if (!$validator) {
    http_response_code(404);
    die("Validator not found.");
}

$isActive = (int)$validator['active'] === 1;

// Safe date formatting
$createdDisplay = !empty($validator['created_at'])
    ? date("M d, Y h:i A", strtotime($validator['created_at']))
    : '—';

$expiresRaw = trim((string)($validator['expires_at'] ?? ''));
$expiresDisplay = ($expiresRaw && $expiresRaw !== '0000-00-00 00:00:00')
    ? date("M d, Y h:i A", strtotime($expiresRaw))
    : '—';

// Include your top header / sidebar AFTER logic so redirects succeed
include '../includes/header.php';
include 'page_buttons.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Validator Details • <?php echo htmlspecialchars($validator['v_username']); ?></title>
  <link rel="stylesheet" href="../css/validators.css">

</head>
<body>

<!--
  Layout notes:
  - We assume your site has a fixed red topbar and a fixed dark left sidebar (per your screenshot).
  - The CSS below adds safe offsets so this main content is never hidden behind them.
  - If your actual sizes differ, tweak the two CSS variables --mm-topbar and --mm-sidebar in the CSS file.
-->

<main class="mm-shell has-sidebar" aria-labelledby="pageTitle">
  <div class="mm-container">
    <!-- Breadcrumbs -->
    <nav class="mm-breadcrumbs" aria-label="Breadcrumb">
      <ol>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="validators_list.php">Community Service Validators</a></li>
        <li aria-current="page"><?php echo htmlspecialchars($validator['v_username']); ?></li>
      </ol>
    </nav>

    <!-- Header Card -->
    <section class="mm-headercard">
      <div class="mm-id">
        <div class="mm-avatar" aria-hidden="true">
          <?php echo strtoupper(substr((string)$validator['v_username'], 0, 1)); ?>
        </div>
        <div class="mm-titlewrap">
          <h1 id="pageTitle" class="mm-h1">
            <?php echo htmlspecialchars($validator['v_username']); ?>
          </h1>
          <div class="mm-sub">
            <span class="mm-badge <?php echo $isActive ? 'mm-badge--on' : 'mm-badge--off'; ?>">
              <span class="mm-dot" aria-hidden="true"></span>
              <?php echo $isActive ? 'Active' : 'Inactive'; ?>
            </span>
          </div>
        </div>
      </div>

      <div class="mm-actions">
        <form method="post" class="mm-actions__form" aria-label="Actions">
          <input type="hidden" name="id" value="<?php echo (int)$validator['validator_id']; ?>" />
          <button
            type="submit"
            name="toggle_status"
            class="mm-btn <?php echo $isActive ? 'mm-btn--danger' : 'mm-btn--success'; ?>"
            onclick="return confirm('Are you sure you want to <?php echo $isActive ? 'deactivate' : 'activate'; ?> this account?');"
          >
            <?php echo $isActive ? "Deactivate" : "Activate"; ?>
          </button>

          <button type="button" class="mm-btn mm-btn--ghost" onclick="history.back()">
            Back
          </button>
        </form>
      </div>
    </section>

    <!-- Info Grid -->
    <section class="mm-grid">
      <article class="mm-card">
        <h2 class="mm-h2">Profile</h2>
        <dl class="mm-list">
          <div>
            <dt>Email</dt>
            <dd>
              <span id="mm-email"><?php echo htmlspecialchars($validator['email']); ?></span>
              <button class="mm-chip" type="button" onclick="copyEmail()" title="Copy email">Copy</button>
            </dd>
          </div>

          <div>
            <dt>Designation</dt>
            <dd><?php echo htmlspecialchars($validator['designation']); ?></dd>
          </div>

          <div>
            <dt>Created</dt>
            <dd><?php echo $createdDisplay; ?></dd>
          </div>

          <div>
            <dt>Expires</dt>
            <dd><?php echo $expiresDisplay; ?></dd>
          </div>

          <div>
            <dt>Status</dt>
            <dd>
              <span class="mm-status <?php echo $isActive ? 'mm-status--on' : 'mm-status--off'; ?>">
                <span class="mm-dot" aria-hidden="true"></span>
                <?php echo $isActive ? 'Active' : 'Inactive'; ?>
              </span>
            </dd>
          </div>
        </dl>
      </article>

      <article class="mm-card mm-card--aside">
        <h2 class="mm-h2">Quick Stats</h2>
        <ul class="mm-stats">
          <li>
            <span class="mm-k">ID</span>
            <span class="mm-v"><?php echo (int)$validator['validator_id']; ?></span>
          </li>
          <li>
            <span class="mm-k">Created</span>
            <span class="mm-v"><?php echo $createdDisplay; ?></span>
          </li>
          <li>
            <span class="mm-k">Expires</span>
            <span class="mm-v"><?php echo $expiresDisplay; ?></span>
          </li>
        </ul>
      </article>
    </section>

    <section class="mm-footnote">
      <p>Need to update validator details? Use your admin list to edit designation or expiry.</p>
    </section>
  </div>
</main>

<script>
  function copyEmail() {
    const el = document.getElementById('mm-email');
    const text = el ? el.textContent.trim() : '';
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
      const btn = event.currentTarget;
      const old = btn.textContent;
      btn.textContent = 'Copied';
      setTimeout(() => btn.textContent = old, 1200);
    });
  }
</script>

<?php ob_end_flush(); ?>
</body>
</html>
