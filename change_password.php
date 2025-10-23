<?php
session_start();
require __DIR__ . '/config.php';

$database_settings = $database_settings ?? []; // fallback if config didn't set
$servername = $database_settings['servername'] ?? 'localhost';
$username   = $database_settings['username'] ?? '';
$password   = $database_settings['password'] ?? '';
$dbname     = $database_settings['dbname'] ?? '';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$recordId    = isset($_SESSION['record_id']) ? (int) $_SESSION['record_id'] : null;
$accountType = strtolower($_SESSION['account_type'] ?? ""); // normalize lowercase
$message     = "";

// handle POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newPassword     = $_POST['new_password'] ?? "";
    $confirmPassword = $_POST['confirm_password'] ?? "";

    if (empty($newPassword) || empty($confirmPassword)) {
        $message = "Please fill in all fields.";
        $message_type = "warning";
    } elseif (($newPassword) !== ($confirmPassword)) {
        $message = "Passwords do not match.";
        $message_type = "warning";
    } elseif (strlen($newPassword) < 6) {
        $message = "Password must be at least 6 characters.";
        $message_type = "warning";
    } elseif (is_null($recordId)) {
        $message = "Session expired. Please log in again.";
        $message_type = "danger";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $sql = "UPDATE accounts SET password=?, change_pass=0 WHERE record_id=?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $message = "Server error. Try again later.";
            $message_type = "danger";
        } else {
            $stmt->bind_param("si", $hashedPassword, $recordId);
            if ($stmt->execute()) {
                // redirect map
                $redirects = [
                    "student"       => "student/dashboard.php",
                    "faculty"       => "faculty/dashboard.php",
                    "security"      => "security/dashboard.php",
                    "ccdu"          => "ccdu/dashboard.php",
                    "administrator" => "admin/index.php"
                ];

                if (isset($redirects[$accountType])) {
                    header("Location: " . $redirects[$accountType]);
                    exit();
                } else {
                    // if account type invalid, show success but do not redirect
                    $message = "Password updated but account type unknown for redirect.";
                    $message_type = "success";
                }
            } else {
                $message = "Error updating password. Try again.";
                $message_type = "danger";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Change Password - Moral Matrix</title>

  <!-- Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">

  <!-- Styles (external) -->
  <link rel="stylesheet" href="css/change_password.css" />
  <link rel="stylesheet" href="css/shared-header.css" />
</head>
<body>
 <?php include __DIR__ . '/includes/home_header.php'; ?>
  <main class="change-page">
    <div class="change-box" role="form" aria-labelledby="change-heading">
      <h3 id="change-heading" class="change-welcome">Change Password</h3>

      <?php if (!empty($message)) : ?>
        <div class="alert <?= htmlspecialchars($message_type ?? 'info') ?>"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <label for="new_password">NEW PASSWORD</label>
        <div class="password-wrap">
          <input
            type="password"
            name="new_password"
            id="new_password"
            placeholder="Enter new password"
            autocomplete="new-password"
            required
          />
        </div>

        <label for="confirm_password">CONFIRM PASSWORD</label>
        <div class="password-wrap">
          <input
            type="password"
            name="confirm_password"
            id="confirm_password"
            placeholder="Confirm new password"
            autocomplete="new-password"
            required
          />
        </div>

        <div class="form-options" style="justify-content:center;">
          <!-- placeholder area: could add hints or password strength next -->
        </div>

        <button type="submit" class="btn-change">UPDATE PASSWORD</button>
      </form>
    </div>
  </main>
</body>
</html>
