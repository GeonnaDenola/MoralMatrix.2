<!-- create_admin_account.php -->
<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    require 'config.php';

    $servername = $database_settings['servername'];
    $username = $database_settings['username'];
    $password = $database_settings['password'];
    $dbname = $database_settings['dbname'];

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    //$id_number = trim($_POST['id_number']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
   // $account_type =trim($_POST['account_type']);
   $account_type = "super_admin";

    $sql_superAdmin = "INSERT INTO super_admin (id_number, email) VALUES ('$id_number','$email')";

    if ($conn->query($sql_superAdmin) === TRUE){
        $sql_account = "INSERT INTO accounts (id_number, email, password, account_type) VALUES ('$id_number', '$email', '$hashedPassword', '$account_type')";
    }

    if ($conn->query($sql_account) === TRUE) {
        $_SESSION['email'] = $email;
        $_SESSION['account_type'] = $account_type;
        
        header("Location: /MoralMatrix/super_admin/dashboard.php");
        exit();
    } else {
       die("Database error: " .$conn->error);
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account</title>
    <link rel="stylesheet" href="">
   
</head>
<body id="createAdminAccount">
<div class="entry-box">
    <h1>Create Administrator Account</h1>
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
        <input type="email" name="email" required placeholder="Email" ><br>

        <input type="password" name="password" placeholder="Password" required><br>

       
        <input type="hidden" name="account_type" value="super_admin">

        <input type="submit" name="save" value="Save">
    </form>
</div>

</body>
</html>
