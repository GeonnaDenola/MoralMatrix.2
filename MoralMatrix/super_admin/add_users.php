<?php
session_start();

$errorMsg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require '../config.php';

    $servername = $database_settings['servername'];
    $username   = $database_settings['username'];
    $password   = $database_settings['password'];
    $dbname     = $database_settings['dbname'];

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $admin_id    = $_POST["admin_id"];
    $first_name  = $_POST["first_name"];
    $last_name   = $_POST["last_name"];
    $middle_name = $_POST["middle_name"];
    $mobile      = $_POST["mobile"];
    $email       = $_POST["email"];
    $password    = $_POST["password"];
    $photo       = "";

    // check duplicate email in accounts
    $check = $conn->prepare("SELECT record_id FROM accounts WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result && $result->num_rows > 0) {
        $errorMsg = "⚠️ Email already registered!";
    }
    $check->close();

    if (empty($errorMsg)) {
        // handle photo upload
        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
            $photo = $_FILES["photo"]["name"];
            $targetDir = "../uploads/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $targetFile = $targetDir . basename($photo);

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
                    $_SESSION['flash_msg'] = "✅ Account Added successfully";
                } else {
                    $_SESSION['flash_msg'] = "⚠️ Error inserting into accounts table.";
                }
                $stmt2->close();
            } else {
                $_SESSION['flash_msg'] = "⚠️ Error inserting into admin_account table.";
            }
            $stmt1->close();

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    $conn->close();
}

// generate temporary password
function generateTempPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    return substr(str_shuffle($chars), 0, $length);
}
$tempPassword = generateTempPassword();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin Account</title>
</head>
<body>
    <h1>Add New Admin Account</h1>

    <a href="dashboard.php">
        <button type="button">Return to Dashboard</button>
    </a>


    <form action="" method="post" enctype="multipart/form-data">
        <label for="admin_id">ID Number:</label><br>
        <input type="text" id="admin_id" name="admin_id" maxlength="9"
               title="Format: YYYY-NNNN (e.g. 2023-0001)"
               pattern="^[0-9]{4}-[0-9]{4}$"
               oninput="this.value = this.value.replace(/[^0-9-]/g, '')"
               required><br><br>

        <label for="first_name">First Name:</label><br>
        <input type="text" id="first_name" name="first_name" required><br><br>

        <label for="last_name">Last Name:</label><br>
        <input type="text" id="last_name" name="last_name" required><br><br>

        <label for="middle_name">Middle Name:</label><br>
        <input type="text" id="middle_name" name="middle_name" required><br><br>

        <label for="mobile">Contact Number:</label><br>
        <input type="text" id="mobile" name="mobile" maxlength="11"
               placeholder="09XXXXXXXXX"
               pattern="^09[0-9]{9}$"
               oninput="this.value = this.value.replace(/[^0-9]/g, '')"
               required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>

        <label for="photo">Profile Picture:</label><br>
        <img id="photoPreview" src="" alt="No photo" width="100" style="display:none;"><br>
        <input type="file" id="photo" name="photo" accept="image/png, image/jpeg" onchange="previewPhoto(this)"><br><br>

        <label for="password">Temporary Password:</label><br>
        <input type="text" id="password" name="password" 
               value="<?php echo htmlspecialchars($tempPassword); ?>" required><br><br>
        <button type="button" onclick="generatePass()">Generate Password</button>

        <button type="submit" class="btn_submit">Add Admin Account</button>
    </form>

    <script>
    function generatePass() {
        let chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        let pass = "";
        for (let i = 0; i < 8; i++) {
            pass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById("password").value = pass;
    }

    function previewPhoto(input) {
        const preview = document.getElementById('photoPreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>

    <?php if (!empty($_SESSION['flash_msg'])): ?>
    <script>
        alert("<?php echo addslashes($_SESSION['flash_msg']); ?>");
    </script>
    <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>

</body>
</html>
