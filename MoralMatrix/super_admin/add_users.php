<?php
session_start();


if ($_SERVER["REQUEST_METHOD"] == "POST"){

    require '../config.php';

    $servername = $database_settings['servername'];
    $username = $database_settings['username'];
    $password = $database_settings['password'];
    $dbname = $database_settings['dbname'];

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST"){
        $admin_id = $_POST["admin_id"];
        $first_name = $_POST["first_name"];
        $last_name = $_POST["last_name"];
        $middle_name = $_POST["middle_name"];
        $mobile = $_POST["mobile"];
        $email = $_POST["email"];
        $password = $_POST["password"];
        $photo = "";
       /* $f_create = $_POST["f_create"];
        $f_update = $_POST["f_update"];
        $f_delete = $_POST["f_delete"];
        $s_create = $_POST["s_create"];
        $s_update = $_POST["s_update"];
        $s_delete = $_POST["s_delete"];
        $a_create = $_POST["a_create"];
        $a_update = $_POST["a_update"];
        $a_delete = $_POST["a_delete"];
        $c_create = $_POST["c_create"];
        $c_update = $_POST["c_update"];
        $c_delete = $_POST["c_delete"];*/
    }

        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
        $photo = $_FILES["photo"]["name"];

        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $targetFile = $targetDir . basename($photo);

        if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
            echo "⚠️ Error uploading photo.";
        }
    }


    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $account_type = "administrator";

    $sql_admin = "INSERT INTO admin_account (admin_id, first_name, last_name, middle_name, mobile, email, photo) VALUES ('$admin_id', '$first_name', '$last_name', '$middle_name', '$mobile', '$email', '$photo')";

    if ($conn->query($sql_admin) === TRUE){
        $sql_account = "INSERT INTO accounts (id_number, email, password, account_type) VALUES ('$admin_id', '$email', '$hashedPassword', '$account_type')";

        if($conn->query($sql_account) === TRUE){
            echo "Account Added successfully";
        } else {
            echo "Error inserting account data";
        }
    } else {
        echo "Error inserting";
    }

    $conn->close();
   
}
//generate temporary pass
    function generateTempPassword($lenght = 10){
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
        return substr(str_shuffle($chars), 0, $lenght);
    }
$tempPassword = generateTempPassword();
 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1> Add New Admin Account</h1>

    <form action="" method="post" enctype="multipart/form-data">

    <!--
        <label for="account_type">Account Type:</label><br>
        <select id="account_type" name="account_type" required>
            <option value="">--Select--</option>
            <option value="admin">Administrator</option>
            <option value="faculty">Faculty</option>
            <option value="student">Student</option>
            <option value="ccdu">CCDU Staff</option>
        </select><br><br>
-->

        <label for = "admin_id">ID Number:</label><br>
        <input type ="number" id="admin_id" name="admin_id" required><br><br>

        <label for = "first_name">First Name:</label><br>
        <input type = "text" id="first_name" name="first_name" required><br><br>

        <label for = "last_name">Last Name:</label><br>
        <input type = "text" id="last_name" name="last_name" required><br><br>

        <label for = "middle_name">Middle Name:</label><br>
        <input type = "text" id="middle_name" name="middle_name" required><br><br>

        <label for = "mobile">Contact Number:</label><br>
        <input type ="number" id="mobile" name="mobile" required><br><br>

        <label for = "email">Email:</label><br>
        <input type ="email" id="email" name="email" required><br><br>

        <label for = "photo">Profile Picture:</label><br>
        <input type ="file" id="photo" name="photo" accept="image/png, image/jpeg" required><br><br>

        <label for = "password">Temporary Password:</label><br>
        <input type = "text" id="password" name="password" value="<?php echo isset ($tempPassword) ? $tempPassword : ''; ?>" required><br><br>
        <button type="button" onclick ="generatePass()">Generate Password</button>

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
</script>

        <div>
            <img id="preview" src="" alt="Image Preview">
        </div><br><br>

   

        <button type="submit" class= "btn_submit">Add Admin Account</button>
</body>
</html>