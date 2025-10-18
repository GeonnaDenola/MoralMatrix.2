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

$accountTypeRaw = $_SESSION['account_type'] ?? 'administrator';
$accountTypeKey = strtolower((string)$accountTypeRaw);
if ($accountTypeRaw !== '') {
    $headerUser['role'] = ucwords(str_replace('_', ' ', (string)$accountTypeRaw));
}

if (!empty($_SESSION['record_id'])) {
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

                    $detailStmt = $headerConn->prepare("
                        SELECT first_name, middle_name, last_name, photo
                        FROM admin_account
                        WHERE admin_id = ?
                        LIMIT 1
                    ");
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

                            $headerUser['name'] = $fullName !== '' ? $fullName : ($_SESSION['email'] ?? 'Administrator');

                            $initials = '';
                            if ($first !== '') { $initials .= strtoupper($char($first)); }
                            if ($last !== '') { $initials .= strtoupper($char($last)); }
                            if ($initials === '' && !empty($_SESSION['email'])) {
                                $initials = strtoupper($_SESSION['email'][0]);
                            }
                            $headerUser['initials'] = $initials !== '' ? $initials : 'A';

                            if (!empty($detail['photo'])) {
                                $photoFile = basename((string)$detail['photo']);
                                $uploadDir = realpath(__DIR__ . '/../admin/uploads/');
                                if ($uploadDir && is_file($uploadDir . DIRECTORY_SEPARATOR . $photoFile)) {
                                    $headerUser['photo'] = $asset('admin/uploads/' . $photoFile);
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
    $headerUser['name'] = $_SESSION['email'] ?? 'Administrator';
}
if ($headerUser['initials'] === '') {
    $headerUser['initials'] = strtoupper(substr($headerUser['name'], 0, 1));
}

$profileUrl = $asset('profile.php');
$logoutUrl = $asset('logout.php');
$homeUrl = $asset('admin/dashboard.php');
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
      <?php include $_SERVER['DOCUMENT_ROOT'].'/MoralMatrix/includes/notif_bar.php'; ?>

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
      <span class="brand-title">MCC Admin</span>
    </div>
  </div>

  <div class="nav-group">
    <a class="nav-item<?php echo activeClass('dashboard.php'); ?>"
       href="<?php echo htmlspecialchars($asset('admin/dashboard.php'), ENT_QUOTES); ?>"
       <?php echo $active==='dashboard.php'?'aria-current="page"':''; ?>>
      <span class="nav-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
      </span>
      <span class="nav-label">Dashboard</span>
    </a>

    <a class="nav-item<?php echo activeClass('add_users.php'); ?>"
       href="<?php echo htmlspecialchars($asset('admin/add_users.php'), ENT_QUOTES); ?>"
       <?php echo $active==='add_users.php'?'aria-current="page"':''; ?>>
      <span class="nav-ico" aria-hidden="true">
        <svg viewBox="0 0 16 16" width="20" height="20" fill="currentColor" role="img" focusable="false">
          <path d="M1 14s-1 0-1-1 1-4 7-4 7 3 7 4-1 1-1 1H1zm10-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
          <path fill-rule="evenodd" d="M16 8a.5.5 0 0 0-.5-.5H14V6a.5.5 0 0 0-1 0v1.5H11.5a.5.5 0 0 0 0 1H13V10a.5.5 0 0 0 1 0V8.5h1.5A.5.5 0 0 0 16 8z"/>
        </svg>
      </span>
      <span class="nav-label">Add Users</span>
    </a>

    <a class="nav-item<?php echo activeClass('summary_report.php'); ?>"
       href="<?php echo htmlspecialchars($asset('admin/summary_report.php'), ENT_QUOTES); ?>"
       <?php echo $active==='summary_report.php'?'aria-current="page"':''; ?>>
      <span class="nav-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 5v14h18V5H3zm4 12H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V7h2v2zm12 8H9v-2h10v2zm0-4H9v-2h10v2zm0-4H9V7h10v2z"/></svg>
      </span>
      <span class="nav-label">Summary Report</span>
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
