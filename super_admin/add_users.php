<?php
include '../includes/header.php';

require '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errorMsg = "";
$flashMsg = "";

// Initialize form values to keep after errors
$formValues = [
    'admin_id'    => '',
    'first_name'  => '',
    'last_name'   => '',
    'middle_name' => '',
    'mobile'      => '',
    'email'       => '',
    'password'    => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get POST values and keep them
    foreach ($formValues as $key => $val) {
        $formValues[$key] = $_POST[$key] ?? '';
    }

    $admin_id    = $formValues['admin_id'];
    $first_name  = $formValues['first_name'];
    $last_name   = $formValues['last_name'];
    $middle_name = $formValues['middle_name'];
    $mobile      = $formValues['mobile'];
    $email       = $formValues['email'];
    $password    = $formValues['password'];
    $photo       = "";

    // Check for duplicate email
    $stmtCheck = $conn->prepare("SELECT record_id FROM accounts WHERE email = ?");
    $stmtCheck->bind_param("s", $email);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();

    if ($result && $result->num_rows > 0) {
        $errorMsg = "⚠️ Email already registered!";
    }
    $stmtCheck->close();

    // Proceed if no error
    if (empty($errorMsg)) {

        // Handle photo upload
        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
            $photo = time() . "_" . basename($_FILES["photo"]["name"]);
            $targetDir = "../uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $targetFile = $targetDir . $photo;

            if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
                $errorMsg = "⚠️ Error uploading photo.";
                $photo = "";
            }
        }

        if (empty($errorMsg)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $account_type   = "administrator";

            // Insert into admin_account
            $stmt1 = $conn->prepare("INSERT INTO admin_account (admin_id, first_name, last_name, middle_name, mobile, email, photo) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt1->bind_param("sssssss", $admin_id, $first_name, $last_name, $middle_name, $mobile, $email, $photo);

            if ($stmt1->execute()) {
                // Insert into accounts
                $stmt2 = $conn->prepare("INSERT INTO accounts (id_number, email, password, account_type) 
                                         VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("ssss", $admin_id, $email, $hashedPassword, $account_type);

                if ($stmt2->execute()) {
                    $flashMsg = "✅ Account Added successfully";
                    // Reset form values
                    $formValues = array_map(fn($v) => '', $formValues);
                } else {
                    $errorMsg = "⚠️ Error inserting into accounts table.";
                }
                $stmt2->close();
            } else {
                $errorMsg = "⚠️ Error inserting into admin_account table.";
            }
            $stmt1->close();
        }
    }
}

$conn->close();

// Generate a temporary password if the password field is empty
if (empty($formValues['password'])) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    $formValues['password'] = substr(str_shuffle($chars), 0, 10);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin Account</title>
    <link rel="stylesheet" href="/MoralMatrix/css/global.css">

    <!-- ADDED: Hard overrides to force-show the native file input (even inside modals) -->
    <style>
      /* Apply both globally and when inside a .modal-content container */
      input[type="file"],
      .modal-content input[type="file"] {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        position: relative !important;
        z-index: 2147483647 !important;
        width: 100% !important;
        height: auto !important;
        padding: 6px 8px !important;
        border: 1px solid #ccc !important;
        border-radius: 8px !important;
        background: #fff !important;
        color: #000 !important;
        pointer-events: auto !important;
        -webkit-appearance: auto !important;
        appearance: auto !important;
      }
      /* Make the built-in button obvious */
      input[type="file"]::file-selector-button {
        padding: 8px 12px;
        margin-right: 10px;
        border: 0;
        border-radius: 8px;
        background: #0b5ed7;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
      }
      input[type="file"]::-ms-browse { display: inline-block; } /* old Edge/IE */

      /* Optional: nicer preview without inline styles */
      #photoPreview {
        display: none;            /* shown after a file is picked */
        margin: 10px auto;
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #ccc;
      }
      #fileName {
        display: block;
        margin-top: 6px;
        font-size: .9rem;
        opacity: .85;
        text-align: center;
      }
    </style>
</head>
<body>
<h1>Add New Admin Account</h1>

<a href="dashboard.php">
    <button type="button">Return to Dashboard</button>
</a>

<?php if (!empty($errorMsg)): ?>
<script>alert("<?php echo addslashes($errorMsg); ?>");</script>
<?php endif; ?>

<?php if (!empty($flashMsg)): ?>
<script>alert("<?php echo addslashes($flashMsg); ?>");</script>
<?php endif; ?>

<form action="" method="post" enctype="multipart/form-data">
    <label>ID Number:</label><br>
    <input type="text" name="admin_id" maxlength="9"
           pattern="^[0-9]{4}-[0-9]{4}$"
           value="<?php echo htmlspecialchars($formValues['admin_id']); ?>"
           oninput="this.value = this.value.replace(/[^0-9-]/g,'')" required><br><br>

    <label>First Name:</label><br>
    <input type="text" name="first_name" value="<?php echo htmlspecialchars($formValues['first_name']); ?>" required><br><br>

    <label>Last Name:</label><br>
    <input type="text" name="last_name" value="<?php echo htmlspecialchars($formValues['last_name']); ?>" required><br><br>

    <label>Middle Name:</label><br>
    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($formValues['middle_name']); ?>" required><br><br>

    <label>Mobile:</label><br>
    <input type="text" name="mobile" maxlength="11" placeholder="09XXXXXXXXX"
           pattern="^09[0-9]{9}$"
           oninput="this.value = this.value.replace(/[^0-9]/g,'')"
           value="<?php echo htmlspecialchars($formValues['mobile']); ?>" required><br><br>

    <label>Email:</label><br>
    <input type="email" name="email" value="<?php echo htmlspecialchars($formValues['email']); ?>" required><br><br>

    <label for="photo">Profile Picture:</label><br>
    <!-- CHANGED: add id="photo" so CSS/JS can target; accept stays the same -->
    <input type="file" id="photo" name="photo" accept="image/png, image/jpeg" onchange="previewPhoto(this)"><br>
    <!-- ADDED: file name display -->
    <small id="fileName"></small>
    <!-- CHANGED: remove inline display:none; CSS above controls it -->
    <img id="photoPreview" src="" alt="No photo"><br><br>

    <label>Temporary Password:</label><br>
    <input type="text" id="password" name="password" value="<?php echo htmlspecialchars($formValues['password']); ?>" required><br><br>
    <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="generatePass()">Generate Password</button>
        <button type="submit">Add Admin Account</button>
    </div>

</form>

<script>
function generatePass() {
    let chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    let pass = "";
    for (let i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('password').value = pass;
}

// UPDATED: sturdier preview with filename + basic validations
function previewPhoto(input) {
    const preview  = document.getElementById('photoPreview');
    const fileName = document.getElementById('fileName');

    // Debug: confirm it's not hidden by CSS
    const cs = getComputedStyle(input);
    console.log('file input computed:', cs.display, cs.visibility, cs.opacity, cs.width, cs.height);

    if (!input.files || !input.files[0]) {
        preview.style.display = 'none';
        preview.src = '';
        fileName.textContent = '';
        return;
    }
    const file = input.files[0];

    // Mirror typical server checks (optional)
    if (!/^image\/(png|jpeg)$/.test(file.type)) {
        alert('Please pick a PNG or JPEG.');
        input.value = '';
        preview.style.display = 'none';
        preview.src = '';
        fileName.textContent = '';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        alert('Image too large (max 5MB).');
        input.value = '';
        preview.style.display = 'none';
        preview.src = '';
        fileName.textContent = '';
        return;
    }

    fileName.textContent = file.name;

    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
}
</script>

</body>
</html>
