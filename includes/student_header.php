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
    'role'     => ''
];

$accountTypeRaw = $_SESSION['account_type'] ?? '';
$accountTypeKey = strtolower((string)$accountTypeRaw);

if ($accountTypeRaw !== '') {
    $headerUser['role'] = ucwords(str_replace('_', ' ', (string)$accountTypeRaw));
}

if (!empty($_SESSION['record_id']) && $accountTypeKey !== '') {
    $recordId = (int) $_SESSION['record_id'];

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

            $accountStmt = $headerConn->prepare("SELECT id_number FROM accounts WHERE record_id = ? LIMIT 1");
            if ($accountStmt) {
                $accountStmt->bind_param("i", $recordId);
                $accountStmt->execute();
                $accountRes = $accountStmt->get_result();
                $accountRow = $accountRes ? $accountRes->fetch_assoc() : null;
                $accountStmt->close();

                if ($accountRow && !empty($accountRow['id_number'])) {
                    $idNumber = $accountRow['id_number'];
                    $table = '';
                    $idCol = '';
                    $fields = '';

                    switch ($accountTypeKey) {
                        case 'student':
                            $table = 'student_account';
                            $idCol = 'student_id';
                            $fields = 'first_name, middle_name, last_name, photo';
                            break;
                        case 'faculty':
                            $table = 'faculty_account';
                            $idCol = 'faculty_id';
                            $fields = 'first_name, last_name, photo';
                            break;
                        case 'ccdu':
                            $table = 'ccdu_account';
                            $idCol = 'ccdu_id';
                            $fields = 'first_name, last_name, photo';
                            break;
                        case 'security':
                            $table = 'security_account';
                            $idCol = 'security_id';
                            $fields = 'first_name, last_name, photo';
                            break;
                        case 'administrator':
                        case 'admin':
                        case 'super_admin':
                            $table = 'admin_account';
                            $idCol = 'admin_id';
                            $fields = 'first_name, middle_name, last_name, photo';
                            break;
                        default:
                            $table = '';
                    }

                    if ($table !== '') {
                        $detailSql = sprintf(
                            "SELECT %s FROM %s WHERE %s = ? LIMIT 1",
                            $fields,
                            $table,
                            $idCol
                        );
                        $detailStmt = $headerConn->prepare($detailSql);
                        if ($detailStmt) {
                            $detailStmt->bind_param("s", $idNumber);
                            $detailStmt->execute();
                            $detailRes = $detailStmt->get_result();
                            $detail = $detailRes ? $detailRes->fetch_assoc() : null;
                            $detailStmt->close();

                            if ($detail) {
                                $char = static function (string $value): string {
                                    if ($value === '') {
                                        return '';
                                    }
                                    if (function_exists('mb_substr')) {
                                        return mb_substr($value, 0, 1);
                                    }
                                    return substr($value, 0, 1);
                                };

                                $first = trim((string)($detail['first_name'] ?? ''));
                                $middle = trim((string)($detail['middle_name'] ?? ''));
                                $last = trim((string)($detail['last_name'] ?? ''));
                                $middleInitial = $middle !== '' ? strtoupper($char($middle)) . '. ' : '';
                                $fullName = trim($first . ' ' . $middleInitial . $last);

                                $headerUser['name'] = $fullName !== '' ? $fullName : ($_SESSION['email'] ?? 'My Profile');

                                $initials = '';
                                if ($first !== '') { $initials .= strtoupper($char($first)); }
                                if ($last !== '') { $initials .= strtoupper($char($last)); }
                                if ($initials === '' && !empty($_SESSION['email'])) {
                                    $initials = strtoupper($_SESSION['email'][0]);
                                }
                                $headerUser['initials'] = $initials !== '' ? $initials : 'U';

                                if (!empty($detail['photo'])) {
                                    $uploadDir = realpath(__DIR__ . '/../admin/uploads/');
                                    $photoFile = basename((string)$detail['photo']);
                                    if ($uploadDir && is_file($uploadDir . DIRECTORY_SEPARATOR . $photoFile)) {
                                        $headerUser['photo'] = $asset('admin/uploads/' . $photoFile);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if (isset($headerConn) && $headerConn instanceof mysqli) {
            $headerConn->close();
        }
    }
}

if ($headerUser['name'] === '') {
    $headerUser['name'] = $_SESSION['email'] ?? 'My Profile';
}
if ($headerUser['initials'] === '') {
    $headerUser['initials'] = strtoupper(substr($headerUser['name'], 0, 1));
}

$profileUrl = $asset('profile.php');
$logoutUrl = $asset('logout.php');
$homeUrl = $asset('student/dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Moral Matrix</title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($asset('css/header.css'), ENT_QUOTES); ?>">
  <style>
    .hamburger {
      display: none;
      position: relative;
      width: 44px;
      height: 44px;
      padding: 0;
      border-radius: 50%;
      border: 0;
      background: none;
      cursor: pointer;
      margin-left: 12px;
      transition: transform 0.2s ease;
    }
    .hamburger::before {
      content: '';
      position: absolute;
      inset: 6px;
      border-radius: 18px;
      background: rgba(0, 0, 0, 0.18);
      border: 1px solid rgba(255, 255, 255, 0.28);
      backdrop-filter: blur(10px);
      transition: background 0.2s ease, border-color 0.2s ease;
    }
    .hamburger:hover {
      transform: translateY(-1px);
    }
    .hamburger:hover::before {
      background: rgba(0, 0, 0, 0.28);
      border-color: rgba(255, 255, 255, 0.45);
    }
    .hamburger span {
      position: absolute;
      left: 50%;
      width: 18px;
      height: 3px;
      margin-left: -9px;
      background: var(--text-on-header);
      border-radius: 99px;
      transition: transform 0.3s ease, opacity 0.3s ease;
    }
    .hamburger span:nth-child(1) { top: 16px; }
    .hamburger span:nth-child(2) { top: 21px; }
    .hamburger span:nth-child(3) { top: 26px; }
    .hamburger.is-active span:nth-child(1) {
      transform: translateY(5px) rotate(45deg);
    }
    .hamburger.is-active span:nth-child(2) {
      opacity: 0;
    }
    .hamburger.is-active span:nth-child(3) {
      transform: translateY(-5px) rotate(-45deg);
    }
    .hamburger:focus-visible {
      outline: 2px solid rgba(255, 255, 255, 0.6);
      outline-offset: 4px;
    }

    .header-nav .nav-right .dropdown-menu {
      min-width: 180px;
    }
    .header-nav .nav-divider {
      display: none;
    }

    @media (max-width: 1100px) {
      .header-nav .nav-links {
        gap: 18px;
      }
    }

    @media (max-width: 980px) {
      .hamburger {
        display: flex;
        margin-left: auto;
      }
      .hamburger::before {
        background: rgba(0, 0, 0, 0.25);
      }

      .header-nav {
        flex: 0;
        position: fixed;
        top: var(--header-h);
        right: 0;
        bottom: 0;
        flex-direction: column;
        align-items: stretch;
        justify-content: flex-start;
        gap: 20px;
        padding: 28px 24px 36px;
        background: #131417;
        width: min(340px, 88vw);
        transform: translateX(100%);
        transition: transform 0.3s ease;
        z-index: 1500;
        box-shadow: -6px 0 24px rgba(0, 0, 0, 0.32);
        overflow-y: auto;
      }

      .header-nav.active {
        transform: translateX(0);
      }

      .header-nav .nav-links {
        order: 3;
        flex: 0;
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
        padding-top: 6px;
      }

      .header-nav .nav-links .nav-link {
        display: block;
        width: 100%;
        padding: 10px 2px 12px;
        text-align: left;
        font-size: 1.05rem;
        font-weight: 600;
        color: #f5f6fa;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 0;
        letter-spacing: 0.02em;
      }

      .header-nav .nav-links .nav-link:last-child {
        border-bottom: none;
        padding-bottom: 4px;
      }

      .header-nav .nav-right {
        order: 1;
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
        margin-left: 0;
        padding: 20px 18px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
      }

      .header-nav .profile-chip {
        width: 100%;
        justify-content: flex-start;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: none;
      }

      .header-nav .profile-chip:hover {
        background: rgba(0, 0, 0, 0.28);
      }

      .header-nav .dropdown .dropdown-toggle {
        width: 100%;
        justify-content: space-between;
        padding: 11px 16px;
        border-radius: 14px;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #f5f6fa;
      }

      .header-nav .dropdown .dropdown-toggle:hover {
        background: rgba(0, 0, 0, 0.28);
      }

      .header-nav .dropdown-menu {
        position: static;
        transform: none;
        opacity: 1 !important;
        visibility: visible !important;
        box-shadow: none;
        border: none;
        padding: 0;
        margin-top: 12px;
        background: none;
      }

      .header-nav .dropdown-menu form {
        width: 100%;
      }

      .header-nav .nav-divider {
        order: 2;
        display: block;
        height: 1px;
        background: linear-gradient(90deg, rgba(255,255,255,0.35), rgba(255,255,255,0));
        margin: 4px 0 2px;
      }

      body.nav-open {
        overflow: hidden;
      }
    }

    .header-nav .dropdown-item {
      display: block;
      width: 100%;
      color: #ffffff;
      background: #e53935;
      border: none;
      border-radius: 6px;
      padding: 10px 16px;
      text-align: center;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.3s, transform 0.2s;
      margin-top: 8px;
    }
    .header-nav .dropdown-item:hover {
      background: #d32f2f;
      transform: scale(1.02);
    }
  </style>
</head>
<body>

<header class="site-header" role="banner">
  <div class="header-inner">
    <a href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES); ?>" class="brand" aria-label="Moral Matrix home">
      MORAL MATRIX
    </a>

    <nav class="header-nav" id="headerNav">
      <div class="nav-links">
        <a href="<?php echo htmlspecialchars($asset('student/dashboard.php'), ENT_QUOTES); ?>" class="nav-link<?php echo activeClass('dashboard.php'); ?>">Dashboard</a>
        <a href="<?php echo htmlspecialchars($asset('student/student_handbook.php'), ENT_QUOTES); ?>" class="nav-link<?php echo activeClass('student_handbook.php'); ?>">Student Handbook</a>
      </div>

      <div class="nav-divider" aria-hidden="true"></div>

      <div class="nav-right">
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
                Confirm logout
              </button>
            </form>
          </div>
        </details>
      </div>
    </nav>

    <button class="hamburger" id="hamburgerBtn" type="button" aria-label="Toggle menu" aria-controls="headerNav" aria-expanded="false">
      <span></span>
      <span></span>
      <span></span>
    </button>
  </div>
</header>

<script>
  (function(){
    const dropdowns = Array.from(document.querySelectorAll('.site-header .dropdown'));

    dropdowns.forEach(function(dd){
      const summary = dd ? dd.querySelector('summary') : null;
      if(!dd || !summary) return;

      const syncExpanded = function(){
        summary.setAttribute('aria-expanded', dd.hasAttribute('open') ? 'true' : 'false');
      };

      syncExpanded();
      dd.addEventListener('toggle', syncExpanded);

      document.addEventListener('click', function(e){
        if(!dd.contains(e.target)){
          dd.removeAttribute('open');
          syncExpanded();
        }
      });

      document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
          dd.removeAttribute('open');
          syncExpanded();
        }
      });
    });
  })();

  (function(){
    const hamburger = document.getElementById('hamburgerBtn');
    const nav = document.getElementById('headerNav');
    if(!hamburger || !nav) return;

    const toggleNav = function(force){
      const shouldOpen = typeof force === 'boolean' ? force : !nav.classList.contains('active');
      nav.classList.toggle('active', shouldOpen);
      document.body.classList.toggle('nav-open', shouldOpen);
      hamburger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
      hamburger.classList.toggle('is-active', shouldOpen);
    };

    hamburger.addEventListener('click', function(){
      toggleNav();
    });

    document.addEventListener('click', function(event){
      const target = event.target;
      if(!nav.contains(target) && !hamburger.contains(target) && nav.classList.contains('active')){
        toggleNav(false);
      }
    });

    document.addEventListener('keydown', function(event){
      if(event.key === 'Escape' && nav.classList.contains('active')){
        toggleNav(false);
        hamburger.focus();
      }
    });

    window.addEventListener('resize', function(){
      if(window.innerWidth > 980 && nav.classList.contains('active')){
        toggleNav(false);
      }
    });

    nav.querySelectorAll('.nav-link, .dropdown-item, .profile-chip').forEach(function(item){
      item.addEventListener('click', function(){
        if(window.innerWidth <= 980){
          toggleNav(false);
        }
      });
    });
  })();
</script>
</body>
</html>
