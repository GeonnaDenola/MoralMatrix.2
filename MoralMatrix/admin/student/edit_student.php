<?php
include '../../includes/header.php';
include '../../config.php';

if (!isset($_GET['id'])) {
    die("No student ID provided.");
}

$id = intval($_GET['id']);

$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch student data
$stmt = $conn->prepare("SELECT student_id, first_name, middle_name, last_name, mobile, email, institute, course, level, section, guardian, guardian_mobile, photo FROM student_account WHERE record_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$student) {
    die("Student not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student</title>
</head>
<body>

<a href="../dashboard.php">
    <button type="button">Return to Dashboard</button>
</a>

<div id="studentForm" class="form-container">
    <h3>Edit Student Information</h3>

    <form action="update_student.php" method="POST" enctype="multipart/form-data">

        <input type="hidden" name="account_type" value="student">
        <input type="hidden" name="record_id" value="<?php echo $id; ?>">

        <label>Student Id:</label>
        <input type="text" name="student_id" id="student_id" maxlength="9"
               pattern="^[0-9]{4}-[0-9]{4}$"
               oninput="this.value = this.value.replace(/[^0-9-]/g, '')"
               required value="<?php echo htmlspecialchars($student['student_id']); ?>"><br>

        <label>First Name:</label>
        <input type="text" name="first_name" id="first_name" required value="<?php echo htmlspecialchars($student['first_name']); ?>">

        <label>Middle Name:</label>
        <input type="text" name="middle_name" id="middle_name" required value="<?php echo htmlspecialchars($student['middle_name']); ?>">

        <label>Last Name:</label>
        <input type="text" name="last_name" id="last_name" required value="<?php echo htmlspecialchars($student['last_name']); ?>">

        <label>Contact Number:</label>
        <input type="number" name="mobile" id="mobile" required value="<?php echo htmlspecialchars($student['mobile']); ?>">

        <label>Email:</label>
        <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($student['email']); ?>"><br>

        <label for="institute">Institute:</label><br>
        <select id="institute" name="institute" onchange="loadCourses()" required>
            <option value="">--Select--</option>
            <option value="IBCE" <?php if($student['institute']=='IBCE') echo 'selected'; ?>>Institute of Business and Computing Education</option>
            <option value="IHTM" <?php if($student['institute']=='IHTM') echo 'selected'; ?>>Institute of Hospitality and Management</option>
            <option value="IAS" <?php if($student['institute']=='IAS') echo 'selected'; ?>>Institute of Arts and Sciences</option>
            <option value="ITE" <?php if($student['institute']=='ITE') echo 'selected'; ?>>Institute of Teaching Education</option>
        </select><br>

        <label for="course">Course:</label>
        <select id="course" name="course" required>
            <option value="<?php echo htmlspecialchars($student['course']); ?>" selected><?php echo htmlspecialchars($student['course']); ?></option>
        </select><br>

        <label for="level">Year Level:</label>
        <select id="level" name="level" required>
            <option value="">--Select--</option>
            <option value="1" <?php if($student['level']==1) echo 'selected'; ?>>1st Year</option>
            <option value="2" <?php if($student['level']==2) echo 'selected'; ?>>2nd Year</option>
            <option value="3" <?php if($student['level']==3) echo 'selected'; ?>>3rd Year</option>
            <option value="4" <?php if($student['level']==4) echo 'selected'; ?>>4th Year</option>
        </select>

        <label for="section">Section:</label>
        <select id="section" name="section" required>
            <option value="">--Select--</option>
            <option value="A" <?php if($student['section']=='A') echo 'selected'; ?>>A</option>
            <option value="B" <?php if($student['section']=='B') echo 'selected'; ?>>B</option>
            <option value="C" <?php if($student['section']=='C') echo 'selected'; ?>>C</option>
        </select><br><br>

        <label for="photo">Profile Picture:</label><br>
          <?php if (!empty($student['photo'])): ?>
            <img id="photoPreview" src="../uploads/<?php echo htmlspecialchars($student['photo']); ?>" alt="Profile Picture" width="100"><br>
        <?php else: ?>
            <img id="photoPreview" src="" alt="No photo" width="100" style="display:none;"><br>
        <?php endif; ?>

        <input type="file" id="photo" name="photo" accept="image/png, image/jpeg" onchange="previewPhoto(this)"><br><br>
        

        <h3>Emergency Contact</h3>

        <label>Guardian's Name:</label>
        <input type="text" name="guardian" id="guardian" required value="<?php echo htmlspecialchars($student['guardian']); ?>">

        <label>Guardian's Contact Number:</label>
        <input type="number" name="guardian_mobile" id="guardian_mobile" required value="<?php echo htmlspecialchars($student['guardian_mobile']); ?>"><br><br>

        <button type="submit" class="btn_submit">Update Student Information</button>
    </form><br><br>
</div>

<script>
    function loadCourses() {
        // Optional: populate courses dynamically based on institute
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

</body>
</html>
