<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$successMsg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$errorMsg = isset($_GET['error']) ? trim($_GET['error']) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Forgot Password - Moral Matrix</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="css/login.css" />
  <link rel="stylesheet" href="css/shared-header.css" />

  <style>
    .alert-error,
    .alert-success{
      margin: 0 0 14px;
      padding: 10px 12px;
      border-radius: 8px;
      font-weight: 600;
    }
    .alert-error{
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }
    .alert-success{
      background: #dcfce7;
      color: #166534;
      border: 1px solid #bbf7d0;
    }
    .form-options.single-link{
      justify-content: flex-end;
    }
    .login-page.login-page--forgot{
      grid-template-columns: minmax(280px, 440px);
      grid-template-areas: "form";
      align-items: center;
      justify-items: center;
      align-content: center;
    }
    @media (max-width: 980px){
      .login-page.login-page--forgot{
        grid-template-columns: minmax(0, 1fr);
      }
    }
  </style>
</head>
<body>
   <?php include __DIR__ . '/includes/home_header.php'; ?>
  <main class="login-page login-page--forgot">
    <div class="login-box" role="form">
      <h3 class="login-welcome">Forgot Password</h3>

      <?php if ($errorMsg !== ''): ?>
        <div class="alert-error" role="alert" aria-live="assertive">
          <?= htmlspecialchars($errorMsg) ?>
        </div>
      <?php endif; ?>

      <?php if ($successMsg !== ''): ?>
        <div class="alert-success" role="status" aria-live="polite">
          <?= htmlspecialchars($successMsg) ?>
        </div>
      <?php endif; ?>

      <form action="send_reset_link.php" method="POST" novalidate>
        <label for="email">EMAIL</label>
        <input
          type="email"
          name="email"
          id="email"
          placeholder="Enter your registered email"
          autocomplete="email"
          required
        />

        <button type="submit" class="btn-login">Send Reset Link</button>
      </form>

      <div class="form-options single-link">
        <a href="login.php" class="forgot-password" >Back to Login</a>
      </div>
    </div>
  </main>

  <script>
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
      mq.addEventListener('change', () => {
        if (mq.matches && body.classList.contains('menu-open')) setMenu(false);
      });
    })();
  </script>
</body>
</html>

