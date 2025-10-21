<?php
// Validator Login (mirrors main login UI while preserving validator functionality)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to dashboard
if (!empty($_SESSION['validator_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Flash messages
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$errorMsg = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$alertMessage = '';
$alertType = '';
if ($errorMsg !== '') {
    $alertMessage = $errorMsg;
    $alertType = 'error';
} elseif ($msg !== '') {
    $alertMessage = $msg;
    $alertType = ($msg === 'loggedout' || stripos($msg, 'success') !== false) ? 'info' : 'error';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1, viewport-fit=cover"
  />
  <title>Validator Login - Moral Matrix</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">

  <!-- Styles -->
  <link rel="stylesheet" href="../css/validators_login.css" />
  <link rel="stylesheet" href="../css/shared-header.css" />

  <!-- Inline alert styles to match main login page banner -->
  <style>
    .alert{
      margin: 0 0 14px;
      padding: 10px 12px;
      border-radius: 8px;
      font-weight: 600;
      border: 1px solid transparent;
    }
    .alert.error{
      background: #fee2e2;
      color: #991b1b;
      border-color: #fecaca;
    }
    .alert.info{
      background: #dcfce7;
      color: #166534;
      border-color: #bbf7d0;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../includes/home_header.php'; ?>

  <main class="login-page">
    <div class="violation-message">
      <h3>VALIDATOR PORTAL</h3>
      <p>
        Authorized personnel only. Use your assigned credentials to access validator tools.
        For assistance, contact your system administrator.
      </p>
    </div>

    <div class="login-box" role="form" aria-labelledby="validator-welcome">
      <h3 id="validator-welcome" class="login-welcome">VALIDATOR LOGIN</h3>

      <?php if ($alertMessage !== '') : ?>
        <div class="alert <?= htmlspecialchars($alertType) ?>" role="alert" aria-live="assertive">
          <?= htmlspecialchars($alertMessage) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="validator_login_process.php" autocomplete="off" novalidate>
        <label for="user_input">USERNAME OR EMAIL</label>
        <input
          id="user_input"
          name="user_input"
          type="text"
          placeholder="Enter username or email"
          required
          autofocus
          autocapitalize="none"
          autocomplete="username"
        >

        <label for="password">PASSWORD</label>
        <div class="password-wrap">
          <input
            id="password"
            name="password"
            type="password"
            placeholder="Enter your password"
            required
            autocomplete="current-password"
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
          <label class="remember-me" for="remember">
            <input type="checkbox" id="remember" name="remember" /> <span>Remember Me</span>
          </label>
          <a href="#" class="forgot-password">Forgot Password?</a>
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
