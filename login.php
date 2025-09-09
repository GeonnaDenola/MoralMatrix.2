<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect already logged-in users based on account type
if (isset($_SESSION['email'])) {
    switch ($_SESSION['account_type']) {
        case 'super_admin':
            header("Location: /MoralMatrix/super_admin/dashboard.php");
            exit;
        case 'administrator':
            header("Location: /MoralMatrix/admin/index.php");
            exit;
        case 'faculty':
            header("Location: /MoralMatrix/faculty/index.php");
            exit;
        case 'student':
            header("Location: /MoralMatrix/student/index.php");
            exit;
        case 'ccdu':
            header("Location: /MoralMatrix/ccdu/index.php");
            exit;
        case 'security':
            header("Location: /MoralMatrix/security/index.php");
            exit;
        default:
            header("Location: /MoralMatrix/dashboard.php");
            exit;
    }
}

$errorMsg='';
if (isset($_SESSION['error'])){
  $errorMsg = $_SESSION['error'];
  unset($_SESSION['error']);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - Moral Matrix</title>
  <link rel="stylesheet" href="login.css" />
</head>
<body>
  <header>
    <nav>
      <ul class="nav-left">
        <li><a href="index.php">MORAL MATRIX</a></li>
      </ul>
      <ul class="nav-center">
        <li><a href="#">ABOUT</a></li>
        <li><a href="#">SERVICES</a></li>
      </ul>
      <ul class="nav-right">
      </ul>
    </nav>
  </header>

  <main class="login-page">
 <div class="violation-message">
    <h3>STUDENT VIOLATION</h3>
    <p>All students are expected to strictly follow the school’s rules and regulations at all times. Any misconduct, inappropriate behavior, or violation of these policies will lead to appropriate disciplinary action in accordance with the student handbook. Please ensure that you act responsibly and respectfully within the school premises and during all school-related activities.
</p>
  </div>

    <div class="login-box">
        <h3 class="login-welcome">WELCOME</h3>
            <form action="login_process.php" method="POST">

                <label for="email">EMAIL</label>
                <input type="email" name= "email" id="email" placeholder="Enter your email" required />

                <label for="password">PASSWORD</label>
                <input type="password" name="password" id="password" placeholder="Enter your password" required />

        <div class="form-options">
            <label class="remember-me">
            <input type="checkbox" id="remember" />Remember Me</label>
            
            <a href="#" class="forgot-password">Forgot Password?</a>
        </div>

  <button type="submit" class="btn_login">LOGIN</button>
</form>

<?php if (!empty($errorMsg)) : ?>
  <p><?= htmlspecialchars($errorMsg) ?></p>
<?php endif; ?>

    </div>
  </main>
</body>
</html>
