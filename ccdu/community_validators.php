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

/* ---- Status filter: Active by default ---- */
$status = $_GET['status'] ?? 'active'; // default = active
$allowedStatus = ['active','inactive'];
if (!in_array($status, $allowedStatus, true)) $status = 'active';

$activeValue = ($status === 'active') ? 1 : 0;

$sql = "SELECT validator_id, v_username, created_at, expires_at, active, designation
        FROM validator_account
        WHERE active = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
  die("Prepare error: " . $conn->error);
}
$stmt->bind_param('i', $activeValue);
if (!$stmt->execute()) {
  die("Query error: " . $stmt->error);
}
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Community Service Validators</title>
  <link rel="stylesheet" href="../css/community_validator.css"/>
</head>

<body>
  <!-- Do NOT add another header—using your existing layout -->
  <main class="validators-page" aria-labelledby="page-title" style = "padding-top: var(--header-h);>
    <div class="page-header">
      <h1 id="page-title">Community Service Validators</h1>

      <div class="page-actions">
        <a href="add_validator.php" class="btn btn-primary" style="margin-bottom: 10px;">
          Create Account
        </a>
      </div>
    </div>

    <form method="get" class="filters" aria-label="Filters">
      <label class="field">
        <span class="field-label">Status</span>
        <select name="status" class="select">
          <option value="active"   <?= $status==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </label>

      <button type="submit" class="btn btn-outline">Apply</button>
      <a href="?status=active" class="btn btn-ghost" role="button">Reset</a>
    </form>

    <?php if ($result->num_rows === 0): ?>
      <section class="empty-state" role="status" aria-live="polite">
        <div class="empty-emoji" aria-hidden="true">👋</div>
        <h2>No <?= htmlspecialchars($status) ?> validators</h2>
        <p>There aren’t any validators to show with this filter.</p>
        <div class="empty-actions">
          <a class="btn btn-primary" href="add_validator.php">Create Account</a>
          <a class="btn btn-ghost" href="?status=active">Back to Active</a>
        </div>
      </section>
    <?php else: ?>
      <section class="card-container" aria-live="polite">
        <?php while ($row = $result->fetch_assoc()): ?>
          <article class="card" role="link" tabindex="0"
                  data-href="validator_details.php?id=<?php echo $row['validator_id']; ?>"
                  aria-label="Open details for <?php echo htmlspecialchars($row['v_username']); ?>">
            <header class="card-header">
              <h3 class="card-title"><?php echo htmlspecialchars($row['v_username']); ?></h3>
              <span class="status-chip <?php echo $row['active'] ? 'is-active' : 'is-inactive'; ?>">
                <?php echo $row['active'] ? "Active" : "Inactive"; ?>
              </span>
            </header>

            <dl class="meta">
              <div class="meta-row">
                <dt>Created</dt>
                <dd><?php echo date("M d, Y", strtotime($row['created_at'])); ?></dd>
              </div>

              <div class="meta-row">
                <dt>Designation</dt>
                <dd><?php echo htmlspecialchars($row['designation']); ?></dd>
              </div>
            </dl>
          </article>
        <?php endwhile; ?>
      </section>
    <?php endif; ?>
  </main>

  <script>
    // Click with mouse
    document.addEventListener('click', e => {
      const card = e.target.closest('.card[data-href]');
      if (!card) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      window.location = card.dataset.href;
    });

    // Enter key accessibility
    document.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        const card = document.activeElement.closest?.('.card[data-href]');
        if (card) window.location = card.dataset.href;
      }
    });
  </script>
</body>
</html>
