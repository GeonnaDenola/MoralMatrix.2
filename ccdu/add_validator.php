<?php
// === App chrome (kept) ===
include '../includes/header.php';
include 'page_buttons.php';

require '../config.php';
require_once __DIR__ . '/../lib/email_lib.php';
include __DIR__ . '/_scanner.php';

// === DB connect ===
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$errorMsg = "";
$flashMsg = "";

// Preserve form values
$formValues = [
  'username'        => '',
  'password'        => '',
  'email'           => '',
  'validator_type'  => 'inside',
  'designation'     => '',
  'student_id'      => '',
  'active'          => '1',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  foreach ($formValues as $k => $v) { $formValues[$k] = $_POST[$k] ?? ''; }

  $v_username     = trim($formValues['username']);
  $v_password     = (string)$formValues['password'];
  $email          = trim($formValues['email']);
  $validator_type = ($formValues['validator_type'] === 'outside') ? 'outside' : 'inside';
  $student_id     = trim($formValues['student_id']);
  $active         = ($formValues['active'] === '0') ? 0 : 1;
  $designation    = trim($formValues['designation']);

  // Basic validations
  if ($v_username === '' || $v_password === '') {
      $errorMsg = "⚠️ Username and temporary password are required.";
  }

  // Password policy: >=12 chars, letters + numbers (no symbols)
  if (!$errorMsg) {
      if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{12,}$/', $v_password)) {
          $errorMsg = "⚠️ Password must be at least 12 characters and include letters and numbers (no symbols).";
      }
  }

  // Email (optional)
  if (!$errorMsg && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errorMsg = "⚠️ Please enter a valid email address or leave it blank.";
  }

  // Duplicate username
  if (!$errorMsg) {
      $stmtCheck = $conn->prepare("SELECT validator_id FROM validator_account WHERE v_username = ?");
      $stmtCheck->bind_param("s", $v_username);
      $stmtCheck->execute();
      $result = $stmtCheck->get_result();
      if ($result && $result->num_rows > 0) { $errorMsg = "⚠️ Username already exists!"; }
      $stmtCheck->close();
  }

  $plainPassword  = $v_password;
  $hashedPassword = password_hash($v_password, PASSWORD_DEFAULT);

  if (!$errorMsg) {
      $stmt = $conn->prepare("INSERT INTO validator_account (v_username, v_password, email, active, validator_type, designation) VALUES (?, ?, ?, ?, ?, ?)");
      if (!$stmt) {
          $errorMsg = "⚠️ Server error preparing statement.";
      } else {
          $stmt->bind_param("sssiss", $v_username, $hashedPassword, $email, $active, $validator_type, $designation);

          if ($stmt->execute()) {
              $new_validator_id = (int)$stmt->insert_id;
              $stmt->close();

              // Optional immediate assignment
              if ($student_id !== '') {
                  $stmtS = $conn->prepare("SELECT 1 FROM student_account WHERE student_id = ?");
                  $stmtS->bind_param("s", $student_id);
                  $stmtS->execute();
                  $okStudent = (bool)$stmtS->get_result()->fetch_row();
                  $stmtS->close();

                  if ($okStudent) {
                      $sqlA = "INSERT INTO validator_student_assignment (validator_id, student_id)
                               VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE student_id = VALUES(student_id)";
                      $stmtA = $conn->prepare($sqlA);
                      if ($stmtA) {
                          $stmtA->bind_param("is", $new_validator_id, $student_id);
                          if (!$stmtA->execute()) { $errorMsg = "⚠️ Validator created, but failed to assign student."; }
                          $stmtA->close();
                      }
                  } else {
                      $errorMsg = "⚠️ Validator created, but Student ID not found for assignment.";
                  }
              }

              // Send credentials (optional if email present)
              if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                  try {
                      $mail = moralmatrix_mailer();
                      if (method_exists($mail, 'isHTML')) $mail->isHTML(true);
                      $mail->addAddress($email, $v_username);

                      $isHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
                      $scheme   = $isHttps ? 'https' : 'http';
                      $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
                      $scriptDir= rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'); // e.g., /admin
                      $appBase  = rtrim(dirname($scriptDir), '/\\');                   // up one level
                      $loginUrl = $scheme.'://'.$host.$appBase.'/validator/validator_login.php';

                      $u = htmlspecialchars($v_username, ENT_QUOTES, 'UTF-8');
                      $p = htmlspecialchars($plainPassword, ENT_QUOTES, 'UTF-8');
                      $L = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

                      $mail->Subject = 'Your Community Validator Account';
                      $mail->Body = "
                        <div style=\"font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;line-height:1.6\">
                          <p>Hi <strong>{$u}</strong>,</p>
                          <p>Your validator account has been created.</p>
                          <p><strong>Username:</strong> {$u}<br>
                             <strong>Temporary password:</strong> {$p}</p>
                          <p>Please sign in and change your password immediately:</p>
                          <p><a href=\"{$L}\">{$L}</a></p>
                          <p>— Moral Matrix</p>
                        </div>";
                      $mail->AltBody = "Hi {$v_username},\n\nUsername: {$v_username}\nTemporary password: {$plainPassword}\n\nLogin: {$loginUrl}\n\n— Moral Matrix";
                      @$mail->send();
                  } catch (Throwable $mailErr) {
                      $errorMsg = "⚠️ Validator created, but email could not be sent.";
                      error_log('Validator welcome email error: '.$mailErr->getMessage());
                  }
              }

              if ($errorMsg === "") {
                  $flashMsg  = "✅ Validator account created successfully.";
                  // Reset some fields (keep toggles)
                  $formValues['username']    = '';
                  $formValues['password']    = '';
                  $formValues['email']       = '';
                  $formValues['student_id']  = '';
                  if ($validator_type === 'outside') { $formValues['designation'] = ''; }
              }
          } else {
              $dup = ($conn->errno === 1062 || $stmt->errno === 1062);
              $errorMsg = $dup ? "⚠️ Username already exists!" : "⚠️ Error inserting into validator_account.";
              $stmt->close();
          }
      }
  }
}

$conn->close();

// Default temp password on first load (12 chars, letters+numbers)
if ($_SERVER["REQUEST_METHOD"] !== "POST" && empty($formValues['password'])) {
  $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  $out = '';
  for ($i = 0; $i < 12; $i++) { $out .= $chars[random_int(0, strlen($chars)-1)]; }
  $formValues['password'] = $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create Validator Account</title>
  <link rel="stylesheet" href="../css/add_validator.css"/>
</head>
<body>

  <!-- Title bar shares the same layout as the section below -->
  <div class="cx5-titlebar">
    <h1 class="cx5-title">Create Community Validator Account</h1>
  </div>

  <!-- Inline banners (optional) -->
  <?php if (!empty($errorMsg)): ?>
    <div class="cx5-titlebar">
      <div class="cx5-banner cx5-banner--error" role="alert" aria-live="assertive">
        <span class="cx5-banner__icon">⚠️</span>
        <span class="cx5-banner__text"><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($flashMsg)): ?>
    <div class="cx5-titlebar">
      <div class="cx5-banner cx5-banner--success" role="status" aria-live="polite">
        <span class="cx5-banner__icon">✅</span>
        <span class="cx5-banner__text"><?php echo htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
    </div>
  <?php endif; ?>

  <main class="cx5-shell">
    <section class="cx5-card" aria-labelledby="vHdr">
      <header class="cx5-card__header">
        <div class="cx5-card__kicker">Validator setup</div>
        <h2 id="vHdr" class="cx5-card__title">Account & permissions</h2>
        <p class="cx5-card__hint">Use the toggles and fields below. Email is optional — if provided, we send credentials automatically.</p>
      </header>

      <form action="" method="post" class="cx5-form" autocomplete="off">
        <div class="cx5-grid">
          <!-- Column A -->
          <div class="cx5-col">
            <div class="cx5-field">
              <label for="username" class="cx5-label">Username <span class="cx5-req">*</span></label>
              <input type="text" id="username" name="username" class="cx5-input"
                     placeholder="Enter a unique username"
                     value="<?php echo htmlspecialchars($formValues['username'] ?? ''); ?>"
                     required />
            </div>

            <div class="cx5-field">
              <label for="email" class="cx5-label">Email <span class="cx5-hint">(optional)</span></label>
              <input type="email" id="email" name="email" class="cx5-input"
                     placeholder="name@example.com"
                     value="<?php echo htmlspecialchars($formValues['email'] ?? ''); ?>" />
            </div>

            <div class="cx5-field">
              <label for="password" class="cx5-label">Temporary Password <span class="cx5-req">*</span></label>
              <div class="cx5-inputgroup">
                <input
                  type="text"
                  id="password"
                  name="password"
                  class="cx5-input"
                  placeholder="At least 12 characters (letters & numbers)"
                  value="<?php echo htmlspecialchars($formValues['password'] ?? ''); ?>"
                  minlength="12"
                  pattern="(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{12,}"
                  required
                  aria-describedby="pwHelp"
                />
                <button type="button" class="cx5-btn cx5-btn--ghost" onclick="cx5GeneratePw()" aria-label="Generate password">Generate</button>
                <button type="button" class="cx5-btn cx5-btn--ghost" onclick="cx5CopyPw()" aria-label="Copy password">Copy</button>
              </div>
              <p id="pwHelp" class="cx5-assist">Use letters and numbers only; minimum 12 characters. “Generate” creates a random one.</p>
            </div>
          </div>

          <!-- Column B -->
          <div class="cx5-col">
            <div class="cx5-field">
              <div class="cx5-label">Validator Type</div>
              <div class="cx5-segmented" role="radiogroup" aria-label="Validator Type">
                <?php $isInside = ($formValues['validator_type'] ?? 'inside') === 'inside'; ?>
                <input type="radio" id="vt-in"  name="validator_type" value="inside"  <?php echo $isInside ? 'checked' : ''; ?> />
                <label for="vt-in">Inside Campus</label>
                <input type="radio" id="vt-out" name="validator_type" value="outside" <?php echo !$isInside ? 'checked' : ''; ?> />
                <label for="vt-out">Outside Campus</label>
              </div>
            </div>

            <div class="cx5-field" id="designation_wrap">
              <label for="designation" class="cx5-label">Designation <span class="cx5-hint">(inside validators)</span></label>
              <input type="text" id="designation" name="designation" class="cx5-input"
                     placeholder="e.g., Guidance Counselor"
                     value="<?php echo htmlspecialchars($formValues['designation'] ?? ''); ?>" />
            </div>

            <div class="cx5-field">
              <div class="cx5-label">Status</div>
              <div class="cx5-segmented" role="radiogroup" aria-label="Status">
                <?php $isActive = ($formValues['active'] ?? '1') === '1'; ?>
                <input type="radio" id="st-act" name="active" value="1" <?php echo $isActive ? 'checked' : ''; ?> />
                <label for="st-act">Active</label>
                <input type="radio" id="st-in"  name="active" value="0" <?php echo !$isActive ? 'checked' : ''; ?> />
                <label for="st-in">Inactive</label>
              </div>
            </div>

            <details class="cx5-details">
              <summary>Optional: assign a student now</summary>
              <div class="cx5-field">
                <label for="student_id" class="cx5-label">Student ID</label>
                <input type="text" id="student_id" name="student_id" class="cx5-input"
                       placeholder="Enter a valid Student ID"
                       value="<?php echo htmlspecialchars($formValues['student_id'] ?? ''); ?>" />
              </div>
            </details>
          </div>
        </div>

        <div class="cx5-actions">
          <button type="submit" class="cx5-btn cx5-btn--primary">Register Validator</button>
        </div>
      </form>
    </section>
  </main>

  <script>
  // Generate password (letters + numbers, 12 chars)
  function cx5GeneratePw(){
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    let pass = "";
    if (window.crypto?.getRandomValues) {
      const arr = new Uint32Array(12);
      window.crypto.getRandomValues(arr);
      for (let i=0;i<arr.length;i++) pass += chars[arr[i] % chars.length];
    } else {
      for (let i=0;i<12;i++) pass += chars.charAt(Math.floor(Math.random()*chars.length));
    }
    const el = document.getElementById('password');
    el.value = pass;
    el.focus();
    el.setSelectionRange(0, pass.length);
  }

  // Copy password
  async function cx5CopyPw(){
    const val = document.getElementById('password').value || '';
    try {
      if (navigator.clipboard?.writeText) { await navigator.clipboard.writeText(val); }
      else {
        const el = document.getElementById('password');
        el.select(); el.setSelectionRange(0, val.length);
        document.execCommand('copy');
      }
    } catch(e){}
  }

  // Show/hide Designation for inside/outside
  function cx5ToggleDesignation(){
    const inside = document.getElementById('vt-in').checked;
    const wrap = document.getElementById('designation_wrap');
    wrap.style.display = inside ? "" : "none";
  }
  document.addEventListener('DOMContentLoaded', () => {
    cx5ToggleDesignation();
    document.getElementById('vt-in').addEventListener('change', cx5ToggleDesignation);
    document.getElementById('vt-out').addEventListener('change', cx5ToggleDesignation);
  });
  </script>
</body>
</html>
