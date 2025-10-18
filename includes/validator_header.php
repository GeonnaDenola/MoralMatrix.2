<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    include_once __DIR__ . '/../config.php';
}
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$asset = static function (string $path) use ($baseUrl): string {
    $trimmed = ltrim($path, '/');
    return ($baseUrl !== '' ? $baseUrl : '') . '/' . $trimmed;
};

if (!isset($active)) { $active = basename($_SERVER['PHP_SELF']); }
if (!function_exists('activeClass')) {
  function activeClass($file){ global $active; return $active === $file ? ' is-active' : ''; }
}

$headerUser = [
    'name'     => '',
    'initials' => '',
    'photo'    => '',
    'role'     => 'Validator'
];

if (!empty($_SESSION['validator_id'])) {
    $validatorId = (int)$_SESSION['validator_id'];

    if (isset($database_settings) && is_array($database_settings)) {
        $dbSettings = $database_settings;
    } else {
        include_once __DIR__ . '/../config.php';
        $dbSettings = isset($database_settings) && is_array($database_settings) ? $database_settings : [];
    }

    if (!empty($dbSettings)) {
        $headerConn = @new mysqli(
            $dbSettings['servername'],
            $dbSettings['username'],
            $dbSettings['password'],
            $dbSettings['dbname']
        );

        if (!$headerConn->connect_error) {
            $headerConn->set_charset('utf8mb4');

            $detailStmt = $headerConn->prepare("SELECT v_username, email, validator_type, designation FROM validator_account WHERE validator_id = ? LIMIT 1");
            if ($detailStmt) {
                $detailStmt->bind_param("i", $validatorId);
                $detailStmt->execute();
                $detailRes = $detailStmt->get_result();
                $detail = $detailRes ? $detailRes->fetch_assoc() : null;
                $detailStmt->close();

                if ($detail) {
                    $name = trim((string)($detail['v_username'] ?? ''));
                    $headerUser['name'] = $name !== '' ? $name : ($_SESSION['v_username'] ?? 'Validator');

                    $roleParts = [];
                    if (!empty($detail['validator_type'])) {
                        $roleParts[] = ucfirst((string)$detail['validator_type']);
                    }
                    $roleParts[] = 'Validator';
                    if (!empty($detail['designation'])) {
                        $roleParts[] = (string)$detail['designation'];
                    }
                    $headerUser['role'] = implode(' · ', $roleParts);

                    $initials = '';
                    $parts = preg_split('/\s+/', $headerUser['name']);
                    foreach ($parts as $part) {
                        $trim = trim($part);
                        if ($trim === '') continue;
                        $initials .= strtoupper(mb_substr($trim, 0, 1));
                        if (strlen($initials) >= 2) break;
                    }
                    if ($initials === '' && !empty($_SESSION['v_username'])) {
                        $initials = strtoupper($_SESSION['v_username'][0]);
                    }
                    $headerUser['initials'] = $initials !== '' ? $initials : 'V';
                }
            }
        }

        if (isset($headerConn) && $headerConn instanceof mysqli) {
            $headerConn->close();
        }
    }
}

if ($headerUser['name'] === '') {
    $headerUser['name'] = $_SESSION['v_username'] ?? 'Validator';
}
if ($headerUser['initials'] === '') {
    $headerUser['initials'] = strtoupper(substr($headerUser['name'], 0, 1));
}

$profileUrl = $asset('profile.php');
$logoutUrl = $asset('logout.php');
$homeUrl = $asset('validator/dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Moral Matrix</title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($asset('css/header.css'), ENT_QUOTES); ?>">

</head>
<body>

<!-- ===== Sticky Header (always above sidebar) ===== -->
<header class="site-header" role="banner">
  <div class="header-inner">
    <a href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES); ?>" class="brand" aria-label="Moral Matrix home">
      MORAL MATRIX
    </a>

    <div class="actions">
      <a class="profile-chip" href="<?php echo htmlspecialchars($profileUrl, ENT_QUOTES); ?>" aria-label="View profile">
        <span class="profile-avatar">
          <?php if (!empty($headerUser['photo'])): ?>
            <img src="<?php echo htmlspecialchars($headerUser['photo'], ENT_QUOTES); ?>" alt="Profile photo">
          <?php else: ?>
            <span class="profile-initials"><?php echo htmlspecialchars($headerUser['initials'], ENT_QUOTES); ?></span>
          <?php endif; ?>
        </span>
        <span class="profile-text">
          <span class="profile-name"><?php echo htmlspecialchars($headerUser['name'], ENT_QUOTES); ?></span>
          <?php if (!empty($headerUser['role'])): ?>
            <span class="profile-role"><?php echo htmlspecialchars($headerUser['role'], ENT_QUOTES); ?></span>
          <?php endif; ?>
        </span>
      </a>

      <details class="dropdown" id="logoutDropdown">
        <summary class="dropdown-toggle" aria-haspopup="menu" aria-expanded="false">
          <span>Logout</span>
          <svg class="chevron" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M5.4 7.5l4.6 4.7 4.6-4.7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </summary>

        <div class="dropdown-menu" role="menu" aria-label="Logout menu">
          <form action="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES); ?>" method="post">
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
      <span class="brand-title">Validator</span>
    </div>
  </div>

  <div class="nav-group">
    <a class="nav-item<?php echo activeClass('dashboard.php'); ?>"
       href="<?php echo htmlspecialchars($asset('validator/dashboard.php'), ENT_QUOTES); ?>"
       <?php echo $active==='dashboard.php'?'aria-current="page"':''; ?>>
      <span class="nav-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
      </span>
      <span class="nav-label">Dashboard</span>
    </a>
  </div>
</nav>

<main class="page">
</main>

<script>
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
