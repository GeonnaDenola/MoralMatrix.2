<?php
include '../includes/header.php';
include 'page_buttons.php';
require '../config.php';


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
    $student_id = trim($formValues['student_id']);
    $active     = $formValues['active'] === '0' ? 0 : 1;

    // Basic validations
    if ($v_username === '' || $v_password === '') {
        $errorMsg = "⚠️ Username and password are required.";
    }

 /*   if (!$errorMsg && !preg_match('/^[A-Za-z0-9._\-\s]{3,50}$/', $v_username)) {
        $errorMsg = "⚠️ Username must be 3–50 chars (letters, numbers, dot, underscore, dash).";
    }
*/
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

    // Proceed if OK
    if (!$errorMsg) {

        $stmt = $conn->prepare("INSERT INTO validator_account (v_username, v_password, active) VALUES (?, ?, ?)");
        if (!$stmt) {
            $errorMsg = "⚠️ Server error preparing statement.";
        } else {
            $stmt->bind_param("ssi", $v_username, $v_password, $active);
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

                if ($errorMsg === "") {
                    $flashMsg  = "✅ Validator account created successfully.";
                    // Reset form values except active
                    $formValues['username'] = '';
                    $formValues['password'] = '';
                    $formValues['student_id'] = '';
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Validator Account</title>
</head>
<body>
<h1>Create Community Validator Account</h1>

<a href="dashboard.php"><button type="button">Return to Dashboard</button></a>

<?php if (!empty($errorMsg)): ?>
<script>alert("<?php echo addslashes($errorMsg); ?>");</script>
<?php endif; ?>

<?php if (!empty($flashMsg)): ?>
<script>alert("<?php echo addslashes($flashMsg); ?>");</script>
<?php endif; ?>

<form action="" method="post" autocomplete="off">
  <label>Username:</label><br>
  <input type="text" name="username"
         value="<?php echo htmlspecialchars($formValues['username']); ?>"
         required><br><br>

  <label>Temporary Password:</label><br>
  <input type="text" id="password" name="password"
         value="<?php echo htmlspecialchars($formValues['password']); ?>"
         required><br>
  <button type="button" onclick="generatePass()">Generate Password</button><br><br>

  <label>Status:</label><br>
  <select name="active">
    <option value="1" <?php echo $formValues['active']==='1'?'selected':''; ?>>Active</option>
    <option value="0" <?php echo $formValues['active']==='0'?'selected':''; ?>>Inactive</option>
  </select><br><br>

  <!-- Optional: immediately assign a student to this validator -->
  <label>Assign Student (optional):</label><br>
  <input type="text" name="student_id" placeholder="####-####"
         value="<?php echo htmlspecialchars($formValues['student_id']); ?>"><br><br>

  <button type="submit">Create Validator</button>
</form>

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
</script>

</body>
</html>
