<?php

include '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if(!isset($_GET['id']) || empty($_GET['id'])){
    die("No admin selected.");
}

$id = intval($_GET['id']);
$result = $conn->query("SELECT * FROM admin_account WHERE record_id = $id");

if($result->num_rows === 0){
    die("Admin not found.");
}



$admin = $result->fetch_assoc();
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

    <h2>Edit Admin Account</h2>

    <form action="update_admin.php" method="post" enctype="multipart/form-data">
        
        <input type="hidden" name="record_id" value="<?php echo $id; ?>">

        <label for = "admin_id">ID Number:</label><br>
        <input type ="number" id="admin_id" name="admin_id" value="<?php echo $admin['admin_id']; ?>" ><br><br>

        <label for = "first_name">First Name:</label><br>
        <input type = "text" id="first_name" name="first_name"  value="<?php echo $admin['first_name']; ?>"><br><br>

        <label for = "last_name">Last Name:</label><br>
        <input type = "text" id="last_name" name="last_name" value="<?php echo $admin['last_name']; ?>"><br><br>

        <label for = "middle_name">Middle Name:</label><br>
        <input type = "text" id="middle_name" name="middle_name" value="<?php echo $admin['middle_name']; ?>"><br><br>

        <label for = "mobile">Contact Number:</label><br>
        <input type ="number" id="mobile" name="mobile" value="<?php echo $admin['mobile']; ?>"><br><br>

        <label for = "email">Email:</label><br>
        <input type ="email" id="email" name="email" value="<?php echo $admin['email']; ?>"><br><br>

        <label for="photo">Profile Picture:</label><br>
        <?php if (!empty($admin['photo'])): ?>
            <img id="photoPreview" src="../uploads/<?php echo htmlspecialchars($admin['photo']); ?>" alt="Profile Picture" width="100"><br>
        <?php else: ?>
            <img id="photoPreview" src="" alt="No photo" width="100" style="display:none;"><br>
        <?php endif; ?>

        <input type="file" id="photo" name="photo" accept="image/png, image/jpeg" onchange="previewPhoto(this)"><br><br>


        <button type="submit">Update</button>

    </form>

        <script>
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

</body>
</html>