<?php
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isHome = in_array($currentScript, ['home.php', 'index.php'], true);
$isHandbook = $currentScript === 'handbook.php';
$aboutHref = $isHome ? '#about' : 'home.php#about';
$servicesHref = $isHome ? '#services' : 'home.php#services';
?>

<!-- Header -->
<header class="site-header">
  <div class="container header-inner">
    <!-- logo (now pinned to the left by CSS) -->
    <a class="brand" href="home.php">MORAL MATRIX</a>

    <!-- Centered top nav: visible on desktop per CSS -->
    <nav class="primary-nav" aria-label="Primary">
      <ul>
        <li><a href="home.php"<?= $isHome ? ' aria-current="page"' : '' ?>>Home</a></li>
        <li><a href="<?= $aboutHref ?>">About</a></li>
        <li><a href="handbook.php"<?= $isHandbook ? ' aria-current="page"' : '' ?>>Policies on Student Conduct</a></li>
        <li><a href="<?= $servicesHref ?>">Services</a></li>
      </ul>
    </nav>

    <!-- Hamburger -->
    <button
      class="hamburger"
      id="hamburgerBtn"
      aria-label="Open menu"
      aria-controls="navDrawer"
      aria-expanded="false"
      type="button"
    >
      <span class="bar"></span>
      <span class="bar"></span>
      <span class="bar"></span>
    </button>
  </div>
</header>

<!-- Off-canvas Navigation -->
<div class="nav-overlay" id="navOverlay" hidden></div>

<nav class="nav-drawer" id="navDrawer" aria-hidden="true">
  <div class="drawer-header">
    <strong>Navigation</strong>
    <button class="close-drawer" id="closeDrawer" aria-label="Close menu">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
  <ul class="drawer-links" role="menu">
    <li data-desktop-hide="primary">
      <a href="home.php" role="menuitem"<?= $isHome ? ' aria-current="page"' : '' ?>>Home</a>
    </li>
    <li data-desktop-hide="primary">
      <a href="<?= $aboutHref ?>" role="menuitem">About</a>
    </li>
    <li data-desktop-hide="primary">
      <a href="handbook.php" role="menuitem"<?= $isHandbook ? ' aria-current="page"' : '' ?>>Policies on Student Conduct</a>
    </li>
    <li data-desktop-hide="primary">
      <a href="<?= $servicesHref ?>" role="menuitem">Services</a>
    </li>
    <li class="divider" aria-hidden="true" data-desktop-hide="primary"></li>
    <li><a href="login.php" role="menuitem">Student Login</a></li>
    <li><a href="login.php" role="menuitem">Faculty Login</a></li>
    <li><a href="login.php" role="menuitem">Security Login</a></li>
    <li><a href="/moralmatrix/validator/validator_login.php" role="menuitem">Validator Login</a></li>
  </ul>
</nav>

<script>
  (function () {
    const btn = document.getElementById('hamburgerBtn');
    const drawer = document.getElementById('navDrawer');
    const overlay = document.getElementById('navOverlay');
    const closeBtn = document.getElementById('closeDrawer');
    if (!btn || !drawer || !overlay || !closeBtn) return;

    const focusable = () => drawer.querySelectorAll('a, button:not([disabled])');

    function openDrawer() {
      drawer.classList.add('open');
      drawer.setAttribute('aria-hidden', 'false');
      overlay.hidden = false;
      document.body.classList.add('no-scroll');
      btn.setAttribute('aria-expanded', 'true');

      const items = focusable();
      if (items.length) items[0].focus();
    }

    function closeDrawer() {
      drawer.classList.remove('open');
      drawer.setAttribute('aria-hidden', 'true');
      overlay.hidden = true;
      document.body.classList.remove('no-scroll');
      btn.setAttribute('aria-expanded', 'false');
      btn.focus();
    }

    btn.addEventListener('click', () => {
      if (drawer.classList.contains('open')) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });

    closeBtn.addEventListener('click', closeDrawer);
    overlay.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.classList.contains('open')) {
        closeDrawer();
      }
    });

    drawer.addEventListener('keydown', (event) => {
      if (event.key !== 'Tab') return;
      const items = Array.from(focusable());
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  })();
</script>
