<?php
include '../includes/header.php';
include 'page_buttons.php';

require '../config.php';
require_once __DIR__ . '/../lib/email_lib.php';

include __DIR__ . '/_scanner.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$errorMsg = "";
$flashMsg = "";

// Keep form values after errors
$formValues = [
    'username'   => '',
    'password'   => '',
    'email'      => '',
    'validator_type' => 'inside',
    'designation' => '',
    // Optional: assign a student immediately (can be blank)
    'student_id' => '',
    // Optional toggle
    'active'     => '1',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($formValues as $k => $v) {
        $formValues[$k] = $_POST[$k] ?? '';
    }

    $v_username = trim($formValues['username']);
    $v_password = (string)$formValues['password'];
    $email = trim($formValues['email']);
    $validator_type = $formValues['validator_type'];
    $student_id = trim($formValues['student_id']);
    $active     = $formValues['active'] === '0' ? 0 : 1;
    $designation = trim($formValues['designation']);

    // Basic validations
    if ($v_username === '' || $v_password === '') {
        $errorMsg = "⚠️ Username and password are required.";
    }

    

    // Duplicate username
    if (!$errorMsg) {
        $stmtCheck = $conn->prepare("SELECT validator_id FROM validator_account WHERE v_username = ?");
        $stmtCheck->bind_param("s", $v_username);
        $stmtCheck->execute();
        $result = $stmtCheck->get_result();
        if ($result && $result->num_rows > 0) {
            $errorMsg = "⚠️ Username already exists!";
        }
        $stmtCheck->close();
    }

    $plainPassword  = $v_password;
    $hashedPassword = password_hash($v_password, PASSWORD_DEFAULT);

    // Proceed if OK
    if (!$errorMsg) {

       $stmt = $conn->prepare("INSERT INTO validator_account 
          (v_username, v_password, email, active, validator_type, designation) 
          VALUES (?, ?, ?, ?, ?, ?)");
      if (!$stmt) {
          $errorMsg = "⚠️ Server error preparing statement.";
      } else {
          $stmt->bind_param("sssiss",
              $v_username,
              $hashedPassword,   // <-- was $v_password
              $email,
              $active,
              $validator_type,
              $designation
          );

            if ($stmt->execute()) {
                $new_validator_id = (int)$stmt->insert_id;
                $stmt->close();

                // Optional: immediate assignment to a student if provided
                if ($student_id !== '') {
                    // Validate student exists
                    $stmtS = $conn->prepare("SELECT 1 FROM student_account WHERE student_id = ?");
                    $stmtS->bind_param("s", $student_id);
                    $stmtS->execute();
                    $okStudent = (bool)$stmtS->get_result()->fetch_row();
                    $stmtS->close();

                    if ($okStudent) {
                        // Upsert assignment
                        $sqlA = "INSERT INTO validator_student_assignment (validator_id, student_id)
                                 VALUES (?, ?)
                                 ON DUPLICATE KEY UPDATE student_id = VALUES(student_id)";
                        $stmtA = $conn->prepare($sqlA);
                        if ($stmtA) {
                            $stmtA->bind_param("is", $new_validator_id, $student_id);
                            if (!$stmtA->execute()) {
                                $errorMsg = "⚠️ Validator created, but failed to assign student.";
                            }
                            $stmtA->close();
                        }
                    } else {
                        $errorMsg = "⚠️ Validator created, but Student ID not found for assignment.";
                    }
                }

                // Send credentials email if email is present & valid
              if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                  try {
                      $mail = moralmatrix_mailer();              // from ../lib/email_lib.php
                      if (method_exists($mail, 'isHTML')) $mail->isHTML(true);

                      $mail->addAddress($email, $v_username);

                      // Build login URL (app root assumed one level above this script's folder)
                      $isHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
                      $scheme   = $isHttps ? 'https' : 'http';
                      $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
                      $scriptDir= rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'); // e.g. /admin
                      $appBase  = rtrim(dirname($scriptDir), '/\\');                    // go up one level
                      $loginUrl = $scheme.'://'.$host.$appBase.'/validator/validator_login.php';

                      // Safely embed values
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
                      $mail->AltBody = "Hi {$v_username},\n\n"
                                    . "Your validator account has been created.\n\n"
                                    . "Username: {$v_username}\n"
                                    . "Temporary password: {$plainPassword}\n\n"
                                    . "Login: {$loginUrl}\n\n"
                                    . "— Moral Matrix";

                      @$mail->send();
                  } catch (Throwable $mailErr) {
                      // Account exists; surface a soft warning and log details
                      $errorMsg = "⚠️ Validator created, but email could not be sent.";
                      error_log('Validator welcome email error: '.$mailErr->getMessage());
                  }
              }


                if ($errorMsg === "") {
                    $flashMsg  = "✅ Validator account created successfully.";
                    // Reset form values except active
                    $formValues['username'] = '';
                    $formValues['password'] = '';
                    $formValues['student_id'] = '';
                    $formValues['email'] = '';
                    
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

// Server-side default temp password (if field is empty on initial load)
if ($_SERVER["REQUEST_METHOD"] !== "POST" && empty($formValues['password'])) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    $formValues['password'] = substr(str_shuffle($chars), 0, 10);
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

<h1 style="margin-left:250px;">Create Community Validator Account</h1>

<?php if (!empty($errorMsg)): ?>
<script>alert("<?php echo addslashes($errorMsg); ?>");</script>
<?php endif; ?>

<?php if (!empty($flashMsg)): ?>
<script>alert("<?php echo addslashes($flashMsg); ?>");</script>
<?php endif; ?>

<main class="page">
  <section class="card">
    <form action="" method="post" autocomplete="off" class="validator-form">

      <div class="field">
        <label for="validator_type" class="label">Validator Type</label>
        <select name="validator_type" id="validator_type" required onchange="toggleDesignation()" class="input">
          <option value="inside" <?php echo ($formValues['validator_type'] ?? '')==='inside'?'selected':''; ?>>Inside Campus</option>
          <option value="outside" <?php echo ($formValues['validator_type'] ?? '')==='outside'?'selected':''; ?>>Outside Campus</option>
        </select>
      </div>

      <div class="field" id="designation_field">
        <label for="designation" class="label">
          Designation <span class="hint">(for inside validators)</span>
        </label>
        <input type="text" id="designation" name="designation" class="input"
               value="<?php echo htmlspecialchars($formValues['designation'] ?? ''); ?>"/>
      </div>

      <div class="field">
        <label for="username" class="label">Username <span class="required">*</span></label>
        <input type="text" id="username" name="username" class="input"
               value="<?php echo htmlspecialchars($formValues['username'] ?? ''); ?>" required />
      </div>

      <div class="field">
        <label for="email" class="label">Email</label>
        <input type="email" id="email" name="email" class="input"
               value="<?php echo htmlspecialchars($formValues['email'] ?? ''); ?>" />
      </div>

      <div class="field">
        <label for="password" class="label">Temporary Password <span class="required">*</span></label>
        <div class="input-group">
          <input type="text" id="password" name="password" class="input" required
                 value="<?php echo htmlspecialchars($formValues['password'] ?? ''); ?>" />
          <button type="button" class="btn ghost" onclick="generatePass()" aria-label="Generate password">
            Generate
          </button>
        </div>
        <p class="assist">12 characters, mixed letters & numbers. Click “Generate”.</p>
      </div>

      <div class="field">
        <label for="active" class="label">Status</label>
        <select name="active" id="active" class="input">
          <option value="1" <?php echo ($formValues['active'] ?? '')==='1'?'selected':''; ?>>Active</option>
          <option value="0" <?php echo ($formValues['active'] ?? '')==='0'?'selected':''; ?>>Inactive</option>
        </select>
      </div>

      <div class="actions">
        <button type="submit" class="btn primary">Register Validator</button>
      </div>
    </form>
  </section>
</main>

<script>
function generatePass() {
  const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  let pass = "";
  if (window.crypto && window.crypto.getRandomValues) {
    const arr = new Uint32Array(12);
    window.crypto.getRandomValues(arr);
    for (let i = 0; i < arr.length; i++) pass += chars[arr[i] % chars.length];
  } else {
    for (let i = 0; i < 12; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  document.getElementById('password').value = pass;
}

function toggleDesignation() {
  const sel = document.getElementById('validator_type');
  const field = document.getElementById('designation_field');
  field.style.display = (sel.value === 'inside') ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleDesignation);
</script>

</body>
</html>
