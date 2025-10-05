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
  <link rel="stylesheet" href="/MoralMatrix/css/header.css">

</head>
<body>

<!-- ===== Sticky Header (always above sidebar) ===== -->
<header class="site-header" role="banner">
  <div class="header-inner">
    <a href="dashboard.php" class="brand" aria-label="Moral Matrix home">
      MORAL MATRIX
    </a>

    <div class="actions">
      <details class="dropdown" id="logoutDropdown">
        <summary class="dropdown-toggle" aria-haspopup="menu" aria-expanded="false">
          <span>Logout</span>
          <svg class="chevron" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M5.4 7.5l4.6 4.7 4.6-4.7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </summary>

        <div class="dropdown-menu" role="menu" aria-label="Logout menu">
          <form action="../logout.php" method="post">
            <button type="submit" name="logout" class="dropdown-item" role="menuitem">
              <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 17l5-5-5-5M21 12H9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M13 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
              Confirm logout
            </button>
          </form>
        </div>
      </details>
    </div>
  </div>
</header>

<!-- ===== Fixed Sidebar (starts BELOW header; behind header z-order) ===== -->
<nav class="sidebar" aria-label="Main menu">
  <div class="brand">
    <div class="brand-mark" aria-hidden="true">M</div>
    <div class="brand-text">
      <span class="brand-title">Super Admin</span>
    </div>
  </div>

  <div class="nav-group">
    <a class="nav-item<?php echo activeClass('dashboard.php'); ?>"
       href="/moralmatrix/super_admin/dashboard.php"
       <?php echo $active==='dashboard.php'?'aria-current="page"':''; ?>>
      <span class="nav-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
      </span>
      <span class="nav-label">Dashboard</span>
    </a>
<a class="nav-item<?php echo activeClass('report_student.php'); ?>"
   href="/moralmatrix/super_admin/add_users.php"
   <?php echo $active==='report_student.php' ? 'aria-current="page"' : ''; ?>>
  <span class="nav-ico" aria-hidden="true">
    <!-- User + (scales with font via CSS) -->
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
      <!-- body -->
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
      <!-- head -->
      <circle cx="9" cy="7" r="4"></circle>
      <!-- plus sign (to the right) -->
      <line x1="20" y1="8" x2="20" y2="14"></line>
      <line x1="17" y1="11" x2="23" y2="11"></line>
    </svg>
  </span>
  <span class="nav-label">Add Admin Users</span>
</a>


    <a class="nav-item<?php echo activeClass('summary_report.php'); ?>"
       href="/moralmatrix/admin/summary_report.php"
       <?php echo $active==='summary_report.php'?'aria-current="page"':''; ?>>
      <span class="nav-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 5v14h18V5H3zm4 12H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V7h2v2zm12 8H9v-2h10v2zm0-4H9v-2h10v2zm0-4H9V7h10v2z"/></svg>
      </span>
      <span class="nav-label">Summary Report</span>
    </a>

  </div>
</nav>

<!-- Example content wrapper -->
<main class="page">
  <!-- Your page content -->
</main>

<script>
  // Accessibility niceties for the dropdown
  (function(){
    const dd = document.getElementById('logoutDropdown');
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
  })();
</script>
</body>
</html>
