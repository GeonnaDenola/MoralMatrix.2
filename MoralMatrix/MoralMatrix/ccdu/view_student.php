<?php
include '../config.php';
include '../includes/header.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if(!isset($_GET['student_id'])){
    die("No student selected.");
}

$student_id = $_GET['student_id'];
$sql = "SELECT * FROM student_account WHERE student_id=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();

$result = $stmt->get_result();

$student = $result->fetch_assoc();
$stmt->close();
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

   <div class="left-container">
        <div id="pageButtons">
            <?php include 'page_buttons.php'?>
        </div>
    </div>

    <div class = "right-container">
        <?php if($student): ?>
            <div class="profile">
                <?php
                    if(!empty($student['photo'])){
                        $photo = !empty($student['photo']) ? '../admin/uploads/' . $student['photo'] : 'placeholder.png';
                    } else {
                        $photo = '';
                    }
                ?>

                <img src="<?= $photo ?>" alt="Profile">
                <p><strong>Student ID:</strong> <?= $student['student_id'] ?></p>
                <h2><?= $student['first_name'] . " " . $student['middle_name'] . " " . $student['last_name'] ?></h2>
                
                <p><strong>Course:</strong> <?= $student['course'] ?></p>
                <p><strong>Year Level:</strong> <?= $student['level'] ?></p>
                <p><strong>Section:</strong> <?= $student['section'] ?></p>
                <p><strong>Institute:</strong> <?= $student['institute'] ?></p>
                <p><strong>Guardian:</strong> <?= $student['guardian'] ?> (<?= $student['guardian_mobile'] ?>)</p>
                <p><strong>Email:</strong> <?= $student['email'] ?></p>
                <p><strong>Mobile:</strong> <?= $student['mobile'] ?></p>
            </div>
        <?php else: ?>   
            <p>Student not found.</p> 
        <?php endif; ?>

        <div class="">
            <div class="add-violation-btn">
                    <button id="showFormBtn" onclick="toggleViolationForm(this)">Add Violation</button>
            </div>  
            <div class="violationForm-container" id="violationForm" style="display:none;">
              <?php
                include 'add_violation.php';
               ?>
            </div>

        </div>
        
    </div>

<script>
    function toggleViolationForm(button){
        const formContainer = document.getElementById("violationForm");
        if(formContainer.style.display === "none" || formContainer.style.display === ""){
            formContainer.style.display = "block";
            button.textContent = "Hide Violation Form";
        }else{
            formContainer.style.display = "none";
            button.textContent="Add Violation";
        }
    }

</script>
</body>
</html>