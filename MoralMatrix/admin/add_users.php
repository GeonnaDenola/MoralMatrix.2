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
//STUDENT
    if ($_POST['account_type'] === "student"){
        $student_id = $_POST['student_id'];
        $first_name = $_POST['first_name'];
        $middle_name = $_POST['middle_name'];
        $last_name = $_POST['last_name'];
        $mobile = $_POST['mobile'];
        $email = $_POST['email'];
        $institute = $_POST['institute'];
        $course = $_POST['course'];
        $level = $_POST['level'];
        $section = $_POST['section'];
        $guardian = $_POST['guardian'];
        $guardian_mobile = $_POST['guardian_mobile'];
        $password = $_POST['password'];
        $photo = "";

        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
            $photo = file_get_contents($_FILES["photo"]["tmp_name"]);
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $account_type = "student";

        $sql_student = "INSERT INTO student_account (student_id, first_name, middle_name, last_name, mobile, email, institute, course, level, section, guardian, guardian_mobile, photo) VALUE ('$student_id', '$first_name', '$middle_name', '$last_name', '$mobile', '$email', '$institute', '$course', '$level', '$section', '$guardian', '$guardian_mobile', '$photo')";

        if ($conn->query($sql_student) === TRUE){
            $sql_account = "INSERT INTO accounts (id_number, email, password, account_type) VALUE ('$student_id', '$email', '$hashedPassword', '$account_type')";

            if ($conn->query($sql_account) === TRUE){
                echo "Account Added Succesfully";
            } else {
                echo "Error registering account";
            }
        } else {
            echo "Error inserting";
        }
        
        $conn->close();
        

    }

// FACULTY
    elseif ($_POST['account_type'] === "faculty"){
        $faculty_id = $_POST['faculty_id'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $mobile = $_POST['mobile'];
        $email = $_POST['email'];
        $institute = $_POST['institute'];
        $password = $_POST['password'];
        $photo = "";

         if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
            $photo = $_FILES["photo"]["name"];
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $account_type = "faculty";

        $sql_faculty = "INSERT INTO faculty_account (faculty_id, first_name, last_name, mobile, email, institute, photo) VALUE ('$faculty_id', '$first_name', '$last_name', '$mobile', '$email', '$institute', '$photo')";

        if ($conn->query($sql_faculty) === TRUE){
            $sql_account = "INSERT INTO accounts (id_number, email, password, account_type) VALUE ('$faculty_id', '$email', '$hashedPassword', '$account_type')";

            if ($conn->query($sql_account) === TRUE){
                echo "Account Added Succesfully";
            } else {
                echo "Error registering account";
            }
        } else {
            echo "Error inserting";
        }
        
        $conn->close();
        
    }
       

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
    <h1>Add New User Accounts</h1>

        <label for="account_type">Account Type:</label><br>
        <select id="account_type" name="account_type" onchange="toggleForms()" required>
            <option value="">--Select--</option>
            <option value="faculty">Faculty</option>
            <option value="student">Student</option>
            <option value="ccdu">CCDU Staff</option>
        </select><br><br>

<!--STUDENT FORM-->
        <div id="studentForm" class="form-container">
            <h3>Student Registration</h3>

            <form action="" method="POST" enctype="multipart/form-data">

                <input type="hidden" name="account_type" value="student">

                <label>Student Id:</label>
                <input type="number" name="student_id" id="student_id" pattern="^\d{4}-\d{4}$" required><br>

                <label>First Name: </label>
                <input type="text" name="first_name" id="first_name" required>

                <label>Middle Name: </label>
                <input type="text" name="middle_name" id="middle_name" required>

                <label>Last Name: </label>
                <input type="text" name="last_name" id="last_name" required>

                <label>Contact Number: </label>
                <input type="number" name="mobile" id="mobile" required>

                <label>Email: </label>
                <input type="email" name="email" id="email" required><br>

                <label for="institute">Institute:</label><br>
                <select id="institute" name="institute" onchange="loadCourses()" required>
                    <option value="">--Select--</option>
                    <option value="IBCE">Institute of Business and Computing Education</option>
                    <option value="IHTM">Institute of Hospitality and Management</option>
                    <option value="IAS">Institute of Arts and Sciences</option>
                    <option value="ITE">Institute of Teaching Education</option>
                </select><br>

                <label for="course">Course:</label>
                <select id="course" name="course" required>
                    <option value="">--Select--</option>
                </select><br>

                <label for="level">Year Level:</label>
                <select id="level" name="level" required>
                    <option value="">--Select--</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>

                <label for="section">Section:</label>
                <select id="section" name="section" required>
                    <option value="">--Select--</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                </select><br><br>

                <label for = "photo">Profile Picture:</label><br>
                <input type ="file" id="photo" name="photo" accept="image/png, image/jpeg" required><br><br>

                <h3>Emergency Contact</h3>

                <label>Guardian's Name: </label>
                <input type="text" name="guardian" id="guardian" required>


                <label>Guardian's Contact Number: </label>
                <input type="number" name="guardian_mobile" id="guardian_mobile" required><br><br>

                <label for = "password">Temporary Password:</label><br>
                <input type = "text" id="student_password" name="password" value="<?php echo isset ($tempPassword) ? $tempPassword : ''; ?>" required><br><br>
                <button type="button" onclick ="generatePass('student_password')">Generate Password</button><br><br>

                <button type="submit" class= "btn_submit">Register Account</button>
            </form><br><br>
        </div>
<!--FACULTY FORM-->
        <div id="facultyForm" class="form-container">
            <h3>Register Faculty Account<h3>

            <form action="" method="POST"  enctype="multipart/form-data">

                <input type="hidden" name="account_type" value="faculty">

                <label>ID Number:</label>
                <input type="number" name="faculty_id" id="faculty_id" pattern="^\d{4}-\d{4}$" required><br>

                <label>First Name: </label>
                <input type="text" name="first_name" id="first_name" required>

                <label>Last Name: </label>
                <input type="text" name="last_name" id="last_name" required>

                <label>Contact Number: </label>
                <input type="number" name="mobile" id="mobile" required>

                <label>Email: </label>
                <input type="email" name="email" id="email" required><br>

                <label for="institute">Institute:</label><br>
                <select id="institute" name="institute" required>
                    <option value="">--Select--</option>
                    <option value="IBCE">Institute of Business and Computing Education</option>
                    <option value="IHTM">Institute of Hospitality and Management</option>
                    <option value="IAS">Institute of Arts and Sciences</option>
                    <option value="ITE">Institute of Teaching Education</option>
                </select><br>

                <label for = "photo">Profile Picture:</label><br>
                <input type ="file" id="photo" name="photo" accept="image/png, image/jpeg" required><br><br>

                <label for = "password">Temporary Password:</label><br>
                <input type = "text" id="faculty_password" name="password" value="<?php echo isset ($tempPassword) ? $tempPassword : ''; ?>" required><br><br>
                <button type="button" onclick ="generatePass('faculty_password')">Generate Password</button><br><br>

                <button type="submit" class= "btn_submit">Register Account</button>
            </form>
        </div>

<!--CCDU FORM-->
        <div id="ccduForm" class="form-container">
            <h3>Register CCDU Staff Account<h3>
                
            <form action="" method="POST"  enctype="multipart/form-data">

                <input type="hidden" name="account_type" value="ccdu">

                <label>ID Number:</label>
                <input type="number" name="faculty_id" id="faculty_id" pattern="^\d{4}-\d{4}$" required><br>

                <label>First Name: </label>
                <input type="text" name="first_name" id="first_name" required>

                <label>Last Name: </label>
                <input type="text" name="last_name" id="last_name" required>

                <label>Contact Number: </label>
                <input type="number" name="mobile" id="mobile" required>

                <label>Email: </label>
                <input type="email" name="email" id="email" required><br>

                <label for = "photo">Profile Picture:</label><br>
                <input type ="file" id="photo" name="photo" accept="image/png, image/jpeg" required><br><br>

                <label for = "password">Temporary Password:</label><br>
                <input type = "text" id="ccdu_password" name="password" value="<?php echo isset ($tempPassword) ? $tempPassword : ''; ?>" required><br><br>
                <button type="button" onclick ="generatePass('ccdu_password')">Generate Password</button><br><br>

                <button type="submit" class= "btn_submit">Register Account</button>
            </form>
        </div>

<script>

    window.onload = function() {
      document.getElementById("studentForm").style.display = "none";
      document.getElementById("facultyForm").style.display = "none";
      document.getElementById("ccduForm").style.display = "none";
    };

    function toggleForms(){
        const selected = document.getElementById("account_type").value

        document.getElementById("studentForm").style.display ="none";
        document.getElementById("facultyForm").style.display ="none";
        document.getElementById("ccduForm").style.display ="none";

        if (selected === "student"){
            document.getElementById("studentForm").style.display = "block";
        } else if (selected === "faculty"){
            document.getElementById("facultyForm").style.display = "block";
        } else if (selected === "ccdu"){
            document.getElementById("ccduForm").style.display = "block";
        }
    }


    const course = {
        IBCE: ["BS Information Technology", "BS Customs Administration", "BS Accounting"],
        IHTM: ["BS in Tourism Management", "BS in Hospitality Management"],
        IAS: ["BA in History", "BS in Biology"],
        ITE: ["English", "Filipino", "PE"]
    };

    function loadCourses(){
        let institute = document.getElementById("institute").value;
        let courseDropdown = document.getElementById("course");

        courseDropdown.innerHTML = '<option value="">--Select Course--</option>';

        if (institute && course[institute]) {
            course[institute].forEach(course => {
                let option  = document.createElement("option");
                option.value = course.toLowerCase().replace(/ /g, "_");
                option.text = course;
                courseDropdown.add(option);
            });
        }
    }

    function generatePass(inputId) {
        let chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        let pass = "";
        for (let i = 0; i < 8; i++) {
            pass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById(inputId).value = pass;
    }
</script>


</body>
</html>