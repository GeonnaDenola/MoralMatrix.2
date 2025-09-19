<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Redirect already logged-in users based on account type */
if (isset($_SESSION['email'])) {
    switch ($_SESSION['account_type'] ?? '') {
        case 'super_admin': header("Location: /MoralMatrix/super_admin/dashboard.php"); exit;
        case 'administrator': header("Location: /MoralMatrix/admin/index.php"); exit;
        case 'faculty': header("Location: /MoralMatrix/faculty/index.php"); exit;
        case 'student': header("Location: /MoralMatrix/student/index.php"); exit;
        case 'ccdu': header("Location: /MoralMatrix/ccdu/index.php"); exit;
        case 'security': header("Location: /MoralMatrix/security/index.php"); exit;
        default: header("Location: /MoralMatrix/dashboard.php"); exit;
    }
}

$errorMsg = '';
if (isset($_SESSION['error'])) {
  $errorMsg = $_SESSION['error'];
  unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - Moral Matrix</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="login.css" />
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
      <ul class="nav-right"></ul>
    </nav>
  </header>

  <main class="login-page">
    <div class="violation-message">
      <h3>STUDENT VIOLATION</h3>
      <p>
        All students are expected to strictly follow the school’s rules and regulations at all times.
        Any misconduct, inappropriate behavior, or violation of these policies will lead to appropriate
        disciplinary action in accordance with the student handbook. Please ensure that you act responsibly
        and respectfully within the school premises and during all school-related activities.
      </p>
    </div>

    <div class="login-box">
      <h3 class="login-welcome">WELCOME</h3>

      <form action="login_process.php" method="POST" novalidate>
        <label for="email">EMAIL</label>
        <input type="email" name="email" id="email" placeholder="Enter your email" autocomplete="username" required />

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
          <label class="remember-me">
            <input type="checkbox" id="remember" />Remember Me
          </label>
          <a href="#" class="forgot-password">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-login">LOGIN</button>
      </form>

      <?php if (!empty($errorMsg)) : ?>
        <p class="error-msg"><?= htmlspecialchars($errorMsg) ?></p>
      <?php endif; ?>
    </div>
  </main>

  <script>
    (function () {
      const pwd = document.getElementById('password');
      const btn = document.querySelector('.toggle-password');
      if (!pwd || !btn) return;

      // default: show eye, hide eye-off
      const eye = btn.querySelector('.icon-eye');
      const eyeOff = btn.querySelector('.icon-eye-off');
      eye.style.display = 'inline';
      eyeOff.style.display = 'none';

      function setState(show){
        pwd.type = show ? 'text' : 'password';
        eye.style.display = show ? 'none' : 'inline';
        eyeOff.style.display = show ? 'inline' : 'none';
        btn.classList.toggle('is-visible', show);
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      }

      btn.addEventListener('click', () => setState(pwd.type === 'password'));

      // Optional: press-and-hold peek
      btn.addEventListener('mousedown', () => setState(true));
      ['mouseup','mouseleave','blur'].forEach(evt =>
        btn.addEventListener(evt, () => setState(false))
      );
    })();
  </script>
</body>
</html>
