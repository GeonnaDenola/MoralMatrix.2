<?php
// login.php — simple login form
session_start();

// If already logged in, send to dashboard
if (!empty($_SESSION['validator_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Optional: show a message passed via GET (e.g. ?msg=loggedout)
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Validator Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; padding:2rem; max-width:480px; margin:auto; }
    form { display:flex; flex-direction:column; gap:.6rem; }
    input { padding:.6rem; font-size:1rem; }
    button { padding:.6rem; font-size:1rem; cursor:pointer; }
    .info { color:#0b6; }
    .error { color:#b00020; }
  </style>
</head>
<body>
  <h2>Validator Login</h2>

  <?php if ($msg): ?>
    <div class="<?= htmlspecialchars($msg === 'loggedout' ? 'info' : 'error') ?>">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="validator_login_process.php" autocomplete="off" novalidate>
    <label for="user_input">Username or Email</label>
    <input id="user_input" name="user_input" type="text" required autofocus>

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required>

    <button type="submit">Login</button>
  </form>
</body>
</html>
