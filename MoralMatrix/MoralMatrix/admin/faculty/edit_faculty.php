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

// Fetch Faculty data
$stmt = $conn->prepare("SELECT faculty_id, first_name, last_name, mobile, email, photo, institute FROM faculty_account WHERE record_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$faculty = $result->fetch_assoc();
$stmt->close();
$conn->close();

if(!$faculty){
    die("Faculty not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <a href="../dashboard.php">
        <button type="button">Return to Dashboard</button>   
    </a><br>

    <h3>Edit Faculty Account</h3>

    <div id="facultyForm" class="form-container">
        <form action="update_faculty.php" method="POST" enctype="multipart/form-data">

            <div id="facultyForm" class="form-container">

            <input type="hidden" name="record_id" value="<?php echo $id; ?>">

            <label>ID Number:</label>
            <input type="text" name="faculty_id" value="<?php echo htmlspecialchars($faculty['faculty_id']); ?>" 
                maxlength="9" 
                title="Format: YYYY-NNNN (e.g. 2023-0001)" 
                pattern="^[0-9]{4}-[0-9]{4}$" 
                oninput="this.value = this.value.replace(/[^0-9-]/g, '')" 
            required><br>

            <label>First Name:</label>
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($faculty['first_name']); ?>" required><br>

            <label>Last Name:</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($faculty['last_name']); ?>" required><br>

            <label>Mobile:</label>
            <input type="text" name="mobile" value="<?php echo htmlspecialchars($faculty['mobile']); ?>" 
                maxlength="11"
                placeholder="09XXXXXXXXX"
                pattern="^09[0-9]{9}$"
                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
            required><br>

            <label>Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($faculty['email']); ?>" required><br>

            <label>Institute:</label>
            <select name="institute" required>
                <option value="" disabled selected>--Select--</option>
                <option value="IBCE" <?php echo $faculty['institute']=='IBCE'?'selected':''; ?>>Institute of Business and Computing Education</option>
                <option value="IHTM" <?php echo $faculty['institute']=='IHTM'?'selected':''; ?>>Institute of Hospitality Management</option>
                <option value="IAS" <?php echo $faculty['institute']=='IAS'?'selected':''; ?>>Institute of Arts and Sciences</option>
                <option value="ITE" <?php echo $faculty['institute']=='ITE'?'selected':''; ?>>Institute of Teaching Education</option>
            </select><br>

            <label>Photo:</label><input type="file" name="photo" onchange="previewPhoto(this,'facultyPreview')">
            <img id="facultyPreview" width="100"><br>

            <button type="submit">Update Faculty Information</button>
        </div>
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