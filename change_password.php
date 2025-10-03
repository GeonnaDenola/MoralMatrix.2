<?php
include 'includes/header.php';
include 'config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$recordId    = $_SESSION['record_id'] ?? null;
$accountType = strtolower($_SESSION['account_type'] ?? ""); // normalize lowercase
$message     = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newPassword     = $_POST['new_password'] ?? "";
    $confirmPassword = $_POST['confirm_password'] ?? "";

    if (empty($newPassword) || empty($confirmPassword)) {
        $message = "⚠ Please fill in all fields.";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "⚠ Passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $message = "⚠ Password must be at least 6 characters.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $sql = "UPDATE accounts SET password=?, change_pass=0 WHERE record_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashedPassword, $recordId);

        if ($stmt->execute()) {
            // ✅ redirect map for each account type
            $redirects = [
                "student"  => "student/dashboard.php",
                "faculty"  => "faculty/dashboard.php",
                "security" => "security/dashboard.php",
                "ccdu"     => "ccdu/dashboard.php",
                "administrator"    => "admin/index.php" // 👈 change here if you want admin/dashboard.php
            ];

            if (isset($redirects[$accountType])) {
                header("Location: " . $redirects[$accountType]);
                exit();
            } else {
                $message = "❌ Invalid account type.";
            }
        } else {
            $message = "❌ Error updating password. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">

<div class="d-flex justify-content-center align-items-center" style="min-height:100vh; background:#f8f9fa;">
  <div class="card shadow-lg p-4 rounded-4" style="max-width:400px; width:100%;">
      <h3 class="text-center mb-3">Change Password</h3>
      <?php if (!empty($message)): ?>
          <div class="alert alert-warning p-2 text-center"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <form method="post">
          <div class="mb-3">
              <label for="new_password" class="form-label">New Password</label>
              <input type="password" name="new_password" id="new_password" class="form-control" required>
          </div>
          <div class="mb-3">
              <label for="confirm_password" class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">Update Password</button>
      </form>
  </div>
</div>

</body>
</html>
