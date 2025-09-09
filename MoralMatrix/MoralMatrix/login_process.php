<?php

session_start();

include 'config.php';

$servername = $database_settings['servername'];
$username = $database_settings['username'];
$password = $database_settings['password'];
$dbname = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = trim ($_POST['email']);
$inputPassword = $_POST['password'];

//$sql = "SELECT * FROM accounts WHERE id_number = '$id_number'";
//$result = $conn->query($sql);

$stmt = $conn->prepare("SELECT * FROM accounts WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();


if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    if (password_verify($inputPassword, $row['password'])) {
        session_regenerate_id(true);

        $_SESSION['email'] = $row['email'];
        $_SESSION['account_type'] = $row['account_type'];
        $_SESSION['first_name'] = $row['first_name'];
        $_SESSION['photo'] = $row['photo'];
        
        switch ($row['account_type']) {
            case 'super_admin':
                header("Location: /MoralMatrix/super_admin/dashboard.php");
                break;
            case 'administrator':
                header("Location: /MoralMatrix/admin/index.php");
                break;
            case 'faculty':
                header("Location: /MoralMatrix/faculty/index.php");
                break;
            case 'student':
                header("Location: /MoralMatrix/student/index.php");
                break;
            case 'ccdu':
                header("Location: /MoralMatrix/ccdu/index.php");
                break;
            case 'security':
                header("Location: /MoralMatrix/security/index.php");
                break;
            default:
                header("Location: /login.php");
                break;
        }
    } else {
        $_SESSION['error'] = "❌ Wrong password!";
        header("Location: /login.php");
        exit;
        
    }
} else {
    $_SESSION['error'] = "❌ No account found with that email.";
    header("Location: /login.php");
    exit;
    
}

$conn->close();
?>
