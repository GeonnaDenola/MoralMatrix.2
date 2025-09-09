<?php
include '../../includes/header.php';
include '../../config.php';

if(!isset($_GET['id'])){
    die("No faculty ID provided.");
}

$id = intval($_GET['id']);

$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);

if ($conn->connect_error){
    die("Connection failed: " .$conn->connect_error);
}

$stmt = $conn->prepare("SELECT security_id, first_name, last_name, mobile, email, photo FROM security_account WHERE record_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$security = $result->fetch_assoc();
$stmt->close();
$conn->close();

if(!$security){
    die("Security Personnel not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <a href="../dashboard.php">
        <button type="button">Return to Dashboard</button>   
    </a><br>

    <div id="securityForm" class="form-container">
    <h3>Edit Security Personnel Information</h3>

        <form method="POST" action="update_security.php" enctype="multipart/form-data">

            <input type="hidden" name="record_id" value="<?php echo $id; ?>">

            <label>ID Number:</label>
            <input type="text" name="security_id" value="<?php echo htmlspecialchars($security['security_id']); ?>"  maxlength="9" 
                title="Format: YYYY-NNNN (e.g. 2023-0001)" 
                pattern="^[0-9]{4}-[0-9]{4}$" 
                oninput="this.value = this.value.replace(/[^0-9-]/g, '')" 
            required><br>

            <label>First Name:</label><input type="text" name="first_name" value="<?php echo htmlspecialchars($security['first_name']); ?>" required><br>

            <label>Last Name:</label><input type="text" name="last_name" value="<?php echo htmlspecialchars($security['last_name']); ?>" required><br>

            <label>Mobile:</label><input type="text" name="mobile" value="<?php echo htmlspecialchars($security['mobile']); ?>" 
                maxlength="11"
                placeholder="09XXXXXXXXX"
                pattern="^09[0-9]{9}$"
                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
            required><br>

            <label>Email:</label><input type="email" name="email" value="<?php echo htmlspecialchars($security['email']); ?>" required><br>

            <label>Photo:</label><input type="file" name="photo" onchange="previewPhoto(this,'securityPreview')">
            <img id="securityPreview" width="100"><br>

            <button type="submit">Update Security Personnel Information</button>
        </form>
    </div>
<script>
    function previewPhoto(input, previewID){
        const preview = document.getElementById(previewID);
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