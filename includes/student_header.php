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

    .header-nav {
      display: flex;
      gap: 24px;
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
    }

    .header-nav .dropdown {
      display: none;
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

      .header-nav .nav-link,
      .header-nav .dropdown {
        width: 100%;
        display: block;
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

      .actions {
        display: none;
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

    <nav class="header-nav">
      <a href="<?php echo htmlspecialchars($asset('student/dashboard.php'), ENT_QUOTES); ?>" class="nav-link<?php echo activeClass('dashboard.php'); ?>">Dashboard</a>
      <a href="<?php echo htmlspecialchars($asset('student/student_handbook.php'), ENT_QUOTES); ?>" class="nav-link<?php echo activeClass('student_handbook.php'); ?>">Student Handbook</a>

      <details class="dropdown" id="logoutDropdownMobile">
        <summary class="dropdown-toggle" aria-haspopup="menu" aria-expanded="false">
          Logout
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
    </nav>

    <div class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
      <span></span>
      <span></span>
      <span></span>
    </div>

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

      <details class="dropdown" id="logoutDropdownDesktop">
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
  </div>
</header>

<script>
  (function(){
    const dropdowns = [
      document.getElementById('logoutDropdownDesktop'),
      document.getElementById('logoutDropdownMobile')
    ];

    dropdowns.forEach(function(dd){
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

  (function(){
    const hamburger = document.getElementById('hamburgerBtn');
    const nav = document.querySelector('.header-nav');
    if(!hamburger || !nav) return;
    hamburger.addEventListener('click', function(){
      nav.classList.toggle('active');
    });
  })();
</script>
</body>
</html>
