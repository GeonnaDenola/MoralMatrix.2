<?php
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isHome = in_array($currentScript, ['home.php', 'index.php'], true);
$isHandbook = $currentScript === 'handbook.php';
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$homeHref = $baseUrl !== '' ? $baseUrl . '/home.php' : 'home.php';
$handbookHref = $baseUrl !== '' ? $baseUrl . '/handbook.php' : 'handbook.php';
$loginHref = $baseUrl !== '' ? $baseUrl . '/login.php' : 'login.php';
$validatorHref = $baseUrl !== '' ? $baseUrl . '/validator/validator_login.php' : 'validator/validator_login.php';
$aboutHref = $isHome ? '#about' : $homeHref . '#about';
$servicesHref = $isHome ? '#services' : $homeHref . '#services';
$logoSrc = $baseUrl !== '' ? $baseUrl . '/assets/cert/logo2.png' : 'assets/cert/logo2.png';
?>

<!-- Header -->
<header class="site-header">
  <div class="container header-inner">
    <!-- logo (now pinned to the left by CSS) -->
    <a class="brand" href="<?= htmlspecialchars($homeHref, ENT_QUOTES) ?>">
     <img class="brand-logo" src="<?= htmlspecialchars($logoSrc, ENT_QUOTES) ?>" alt="Moral Matrix logo" />
      <span class="brand-name">MORAL MATRIX</span>
    </a> 

    <!-- Centered top nav: visible on desktop per CSS -->
    <nav class="primary-nav" aria-label="Primary">
      <ul>
        <li><a href="<?= htmlspecialchars($homeHref, ENT_QUOTES) ?>"<?= $isHome ? ' aria-current="page"' : '' ?>>Home</a></li>
        <li><a href="<?= htmlspecialchars($aboutHref, ENT_QUOTES) ?>">About</a></li>
        <li><a href="<?= htmlspecialchars($handbookHref, ENT_QUOTES) ?>"<?= $isHandbook ? ' aria-current="page"' : '' ?>>Policies on Student Conduct</a></li>
        <li><a href="<?= htmlspecialchars($servicesHref, ENT_QUOTES) ?>">Services</a></li>
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
      <a href="<?= htmlspecialchars($homeHref, ENT_QUOTES) ?>" role="menuitem"<?= $isHome ? ' aria-current="page"' : '' ?>>Home</a>
    </li>
    <li data-desktop-hide="primary">
      <a href="<?= htmlspecialchars($aboutHref, ENT_QUOTES) ?>" role="menuitem">About</a>
    </li>
    <li data-desktop-hide="primary">
      <a href="<?= htmlspecialchars($handbookHref, ENT_QUOTES) ?>" role="menuitem"<?= $isHandbook ? ' aria-current="page"' : '' ?>>Policies on Student Conduct</a>
    </li>
    <li data-desktop-hide="primary">
      <a href="<?= htmlspecialchars($servicesHref, ENT_QUOTES) ?>" role="menuitem">Services</a>
    </li>
    <li class="divider" aria-hidden="true" data-desktop-hide="primary"></li>
    <li><a href="<?= htmlspecialchars($loginHref, ENT_QUOTES) ?>" role="menuitem">Student Login</a></li>
    <li><a href="<?= htmlspecialchars($loginHref, ENT_QUOTES) ?>" role="menuitem">Faculty Login</a></li>
    <li><a href="<?= htmlspecialchars($loginHref, ENT_QUOTES) ?>" role="menuitem">Security Login</a></li>
    <li><a href="<?= htmlspecialchars($loginHref, ENT_QUOTES) ?>" role="menuitem">CCDU Login</a></li>
    <li><a href="<?= htmlspecialchars($loginHref, ENT_QUOTES) ?>" role="menuitem">Admin Login</a></li>
    <li><a href="<?= htmlspecialchars($validatorHref, ENT_QUOTES) ?>" role="menuitem">Validator Login</a></li>
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
