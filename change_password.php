<?php
session_start();
include 'config.php';

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
    } elseif ($newPassword !== $confirmPassword) {
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
                    $message = "Password updated — but account type unknown for redirect.";
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
</head>
<body>
  <header>
    <nav>
      <ul class="nav-left">
        <li><a href="home.php">MORAL MATRIX</a></li>
      </ul>

      <ul class="nav-center">
        <li><a href="#">ABOUT</a></li>
        <li><a href="#">SERVICES</a></li>
      </ul>

      <ul class="nav-right">
        <li>
          <button
            class="hamburger"
            aria-label="Open menu"
            aria-controls="mobile-menu"
            aria-expanded="false"
          >
            <span class="line"></span>
            <span class="line"></span>
            <span class="line"></span>
          </button>
        </li>
      </ul>
    </nav>
  </header>

  <!-- Backdrop + Slide-in Mobile Menu -->
  <div class="menu-backdrop" aria-hidden="true"></div>
  <aside id="mobile-menu" class="mobile-menu" role="dialog" aria-modal="true" aria-label="Mobile navigation">
    <div style="display:flex; align-items:center; gap:12px;">
      <div class="mobile-title">Menu</div>
      <button class="close-menu" aria-label="Close menu" title="Close">✕</button>
    </div>
    <ul class="mobile-links" role="menu">
      <li role="none"><a role="menuitem" href="#">ABOUT</a></li>
      <li role="none"><a role="menuitem" href="#">SERVICES</a></li>
    </ul>
  </aside>

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

  <script>
    /* --- Mobile hamburger / menu --- */
    (function () {
      const body = document.body;
      const hamBtn = document.querySelector('.hamburger');
      const menu = document.getElementById('mobile-menu');
      const backdrop = document.querySelector('.menu-backdrop');
      const closeBtn = menu?.querySelector('.close-menu');

      if (!hamBtn || !menu || !backdrop) return;

      function setMenu(open){
        body.classList.toggle('menu-open', open);
        body.classList.toggle('no-scroll', open);
        hamBtn.classList.toggle('is-active', open);
        hamBtn.setAttribute('aria-expanded', String(open));
        hamBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        if (open) {
          const firstLink = menu.querySelector('a,button,[tabindex]:not([tabindex="-1"])');
          firstLink && firstLink.focus();
        } else {
          hamBtn.focus();
        }
      }

      function toggle(){ setMenu(!body.classList.contains('menu-open')); }

      hamBtn.addEventListener('click', toggle);
      closeBtn && closeBtn.addEventListener('click', () => setMenu(false));
      backdrop.addEventListener('click', () => setMenu(false));

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && body.classList.contains('menu-open')) setMenu(false);
      });

      const mq = window.matchMedia('(min-width: 521px)');
      if (mq.addEventListener) {
        mq.addEventListener('change', () => {
          if (mq.matches && body.classList.contains('menu-open')) setMenu(false);
        });
      } else {
        mq.addListener(() => {
          if (mq.matches && body.classList.contains('menu-open')) setMenu(false);
        });
      }
    })();
  </script>
</body>
</html>
