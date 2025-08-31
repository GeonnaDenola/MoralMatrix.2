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
        
        <input type="hidden" name="record_id" value="<?php echo $admin['record_id']; ?>">

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

        <label for = "photo">Profile Picture:</label><br>
        <input type ="file" id="photo" name="photo" accept="image/png, image/jpeg" value="<?php echo $admin['record_id']; ?>"><br><br>

    </form>
</body>
</html>