<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



/* âœ… Redirect already logged-in users only if they are logged in */
if (!empty($_SESSION['account_type'])) {
    switch ($_SESSION['account_type']) {
        case 'super_admin': header("Location: " . BASE_PATH . "/super_admin/dashboard.php"); exit;
        case 'administrator': header("Location: " . BASE_PATH . "/admin/index.php"); exit;
        case 'faculty': header("Location: " . BASE_PATH . "/faculty/index.php"); exit;
        case 'student': header("Location: " . BASE_PATH . "/student/index.php"); exit;
        case 'ccdu': header("Location: " . BASE_PATH . "/ccdu/index.php"); exit;
        case 'security': header("Location: " . BASE_PATH . "/security/index.php"); exit;
        default: header("Location: " . BASE_PATH . "/dashboard.php"); exit;
    }
}



/* Flash error + old email (set by login_process.php) */
$errorMsg = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$oldEmail = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$homeHref = $baseUrl !== '' ? $baseUrl . '/home.php' : 'home.php';
$logoSrc = $baseUrl !== '' ? $baseUrl . '/assets/cert/logo.jpg' : 'assets/cert/logo.jpg';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1, viewport-fit=cover"
  />
  <title>Login - Moral Matrix</title>

  <!-- Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">

  <!-- Styles -->
  <link rel="stylesheet" href="css/login.css" />
  <link rel="stylesheet" href="css/shared-header.css" />

  <!-- Inline styles just for the error banner -->
  <style>
    .alert-error {
      margin: 0 0 14px;
      padding: 10px 12px;
      border-radius: 8px;
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/includes/home_header.php'; ?>

  
  <main class="login-page">
    <div class="violation-message">
      <h3>STUDENT VIOLATION</h3>
      <p>
        All students are expected to strictly follow the school rules and regulations at all times.
        Any misconduct, inappropriate behavior, or violation of these policies will lead to appropriate
        disciplinary action in accordance with the student handbook. Please ensure that you act responsibly
        and respectfully within the school premises and during all school-related activities.
      </p>
    </div>

    <div class="login-box" role="form">
      <h3 class="login-welcome">WELCOME</h3>

      <!-- Error banner ABOVE the form -->
      <?php if (!empty($errorMsg)) : ?>
        <div class="alert-error" role="alert" aria-live="assertive">
          Invalid email or password.
        </div>
      <?php endif; ?>

      <form action="login_process.php" method="POST" novalidate>
        <label for="email">EMAIL</label>
        <input
          type="email"
          name="email"
          id="email"
          placeholder="Enter your email"
          autocomplete="username"
          required
          value="<?= htmlspecialchars($oldEmail) ?>"
        />

        <label for="password">PASSWORD</label>
        <div class="password-wrap">
          <input
            type="password"
            name="password"
            id="password"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
          />
          <button
            type="button"
            class="toggle-password"
            aria-label="Show password"
            aria-controls="password"
          >
            <!-- eye (show) -->
            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <!-- eye-off (hide) -->
            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="m3 3 18 18"/>
              <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
              <path d="M9.88 4.24A10.94 10.94 0 0 1 12 4c6.5 0 10 8 10 8a18.5 18.5 0 0 1-2.24 3.34"/>
              <path d="M6.61 6.61C3.9 8.28 2 12 2 12a18.53 18.53 0 0 0 6.11 6.11"/>
              <path d="M9.9 17.94A10.94 10.94 0 0 0 12 20"/>
            </svg>
          </button>
        </div>

        <div class="form-options">
          <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-login">LOGIN</button>
      </form>
    </div>
  </main>

  <script>
    /* --- Password show/hide --- */
    (function () {
      const pwd = document.getElementById('password');
      const btn = document.querySelector('.toggle-password');
      if (!pwd || !btn) return;

      const eye = btn.querySelector('.icon-eye');
      const eyeOff = btn.querySelector('.icon-eye-off');
      function setState(show){
        pwd.type = show ? 'text' : 'password';
        eye.style.display = show ? 'none' : 'inline';
        eyeOff.style.display = show ? 'inline' : 'none';
        btn.classList.toggle('is-visible', show);
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      }

      setState(false);

      btn.addEventListener('click', () => setState(pwd.type === 'password'));
      btn.addEventListener('mousedown', () => setState(true));
      ['mouseup','mouseleave','blur','touchend','touchcancel'].forEach(evt =>
        btn.addEventListener(evt, () => setState(false))
      );
    })();

    
  </script>
</body>
</html>


