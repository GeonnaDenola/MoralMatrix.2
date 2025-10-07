<?php
// login.php — Validator Login (styled like the reference page)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, send to dashboard
if (!empty($_SESSION['validator_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Optional messages
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$errorMsg = '';
if (isset($_SESSION['error'])) {
    $errorMsg = $_SESSION['error'];
    unset($_SESSION['error']);
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
  <link rel="stylesheet" href="../css/validator_login.css" />
</head>
<body>
  <header>
    <nav>
      <ul class="nav-left">
        <li><a href="../home.php" aria-label="Go to home">MORAL MATRIX</a></li>
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

  <main class="login-page">
    <!-- You can rename or customize this callout -->
    <div class="violation-message">
      <h3>VALIDATOR PORTAL</h3>
      <p>
        Authorized personnel only. Use your assigned credentials to access validator tools.
        For assistance, contact your system administrator.
      </p>
    </div>

    <div class="login-box" role="form" aria-labelledby="validator-welcome">
      <h3 id="validator-welcome" class="login-welcome">VALIDATOR LOGIN</h3>

      <?php if (!empty($errorMsg) || !empty($msg)) : ?>
        <?php
          // Class based on message type
          $isInfo = ($msg === 'loggedout' || stripos($msg, 'success') !== false);
          $alertClass = $isInfo ? 'alert info' : 'alert error';
          $displayMsg = $errorMsg ?: $msg;
        ?>
        <div class="<?= htmlspecialchars($alertClass) ?>">
          <?= htmlspecialchars($displayMsg) ?>
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

      // Close on Escape
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && body.classList.contains('menu-open')) setMenu(false);
      });

      // Ensure menu closes if resized to desktop
      const mq = window.matchMedia('(min-width: 521px)');
      mq.addEventListener('change', () => {
        if (mq.matches && body.classList.contains('menu-open')) setMenu(false);
      });
    })();
  </script>
</body>
</html>
