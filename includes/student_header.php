<?php
// Active link helper
if (!isset($active)) { $active = basename($_SERVER['PHP_SELF']); }
if (!function_exists('activeClass')) {
  function activeClass($file){ global $active; return $active === $file ? ' is-active' : ''; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Moral Matrix</title>
  <link rel="stylesheet" href="../css/header.css">
  <style>
    /* Hamburger button */
    .hamburger {
      display: none;
      flex-direction: column;
      justify-content: space-between;
      width: 28px;
      height: 20px;
      cursor: pointer;
    }
    .hamburger span {
      display: block;
      height: 3px;
      width: 100%;
      background: var(--text-on-header);
      border-radius: 2px;
      transition: all 0.3s ease;
    }

    /* Mobile nav menu hidden by default */
    .header-nav {
      display: flex;
      gap: 24px;
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
    }

    @media (max-width: 980px) {
      .hamburger { display: flex; }
      .header-nav {
        position: fixed;
        top: var(--header-h);
        right: 0;
        flex-direction: column;
        background: #1C1D21;
        padding: 16px;
        gap: 16px;
        width: 220px;
        height: calc(100% - var(--header-h));
        transform: translateX(100%);
        transition: transform 0.3s ease;
        z-index: 1500;
      }
      .header-nav.active {
        transform: translateX(0);
      }

      /* Move logout into mobile nav */
      .actions {
        display: none;
      }

      .header-nav .nav-link,
      .header-nav .dropdown {
        width: 100%;
      }

      .header-nav .dropdown-menu {
        position: relative;
        top: auto;
        right: auto;
        transform: none;
        opacity: 1 !important;
        visibility: visible !important;
        box-shadow: none;
        border: none;
        background: none;
        padding: 0;
      }

      .header-nav .dropdown-item {
        padding-left: 0;
      }
    }
    /* Mobile logout button - stylish version */
.header-nav .dropdown-item {
  display: block;
  width: 100%;
  color: #ffffff;              /* White text for contrast */
  background: #e53935;         /* Modern red button */
  border: none;
  border-radius: 6px;          /* Slightly rounded corners */
  padding: 10px 16px;          /* Comfortable size */
  text-align: center;          /* Centered text */
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.3s, transform 0.2s;
  margin-top: 8px;            /* Space from other items */
}

.header-nav .dropdown-item:hover {
  background: #d32f2f;         /* Slightly darker red on hover */
  transform: scale(1.02);      /* Subtle pop effect */
}

  </style>
</head>
<body>

<!-- ===== Sticky Header ===== -->
<header class="site-header" role="banner">
  <div class="header-inner">
    <a href="dashboard.php" class="brand" aria-label="Moral Matrix home">
      MORAL MATRIX
    </a>

    <!-- ===== Top Navigation Links ===== -->
    <nav class="header-nav">
      <a href="dashboard.php" class="nav-link<?= activeClass('dashboard.php') ?>">Dashboard</a>
      <a href="student_handbook.php" class="nav-link<?= activeClass('student_handbook.php') ?>">Student Handbook</a>

      <!-- Logout in mobile -->
      <details class="dropdown" id="logoutDropdownMobile">
        <summary class="dropdown-toggle" aria-haspopup="menu" aria-expanded="false">
          Logout
          <svg class="chevron" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M5.4 7.5l4.6 4.7 4.6-4.7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </summary>

        <div class="dropdown-menu" role="menu" aria-label="Logout menu">
          <form action="../logout.php" method="post">
            <button type="submit" name="logout" class="dropdown-item" role="menuitem">
              Confirm logout
            </button>
          </form>
        </div>
      </details>
    </nav>

    <!-- Hamburger button for mobile -->
    <div class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
      <span></span>
      <span></span>
      <span></span>
    </div>

  
      </details>
    </div>
  </div>
</header>

<!-- ===== Page content ===== -->
<main class="page">
  <!-- Your page content -->
</main>

<script>
  // Dropdown accessibility
  (function(){
    const ddDesktop = document.getElementById('logoutDropdownDesktop');
    const ddMobile = document.getElementById('logoutDropdownMobile');

    [ddDesktop, ddMobile].forEach(dd => {
      if(!dd) return;
      const summary = dd.querySelector('summary');

      function syncExpanded(){
        if(!summary) return;
        summary.setAttribute('aria-expanded', dd.hasAttribute('open') ? 'true' : 'false');
      }
      dd.addEventListener('toggle', syncExpanded);

      document.addEventListener('click', function(e){
        if(!dd.contains(e.target)) dd.removeAttribute('open');
      });

      document.addEventListener('keydown', function(e){
        if(e.key === 'Escape') dd.removeAttribute('open');
      });
    });
  })();

  // Hamburger toggle for mobile
  const hamburger = document.getElementById('hamburgerBtn');
  const nav = document.querySelector('.header-nav');

  hamburger?.addEventListener('click', () => {
    nav.classList.toggle('active'); // Show/hide mobile nav
  });
</script>
</body>
</html>
