<?php
session_start();

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
        <select id="account_type" name="account_type" required>
            <option value="">--Select--</option>
            <option value="faculty">Faculty</option>
            <option value="student">Student</option>
            <option value="ccdu">CCDU Staff</option>
        </select><br><br>

        <div id="studentForm" class="form-container">
            <h3>Student Registration</h3>

            <form action="" method="POST" enctype="multipart/form-data">

            <label>Student Id:</label>
            <input type="numbers" name="student_id" id="student_id" pattern="^\d{4}-\{4}$" required><br>

            <label>First Name: </label>
            <input type="text" name="first_name" id="first_name" required>

            <label>Middle Name: </label>
            <input type="text" name="first_name" id="first_name" required>

            <label>Last Name: </label>
            <input type="text" name="first_name" id="first_name" required>

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
            <input type = "text" id="password" name="password" value="<?php echo isset ($tempPassword) ? $tempPassword : ''; ?>" required><br><br>
            <button type="button" onclick ="generatePass()">Generate Password</button>


        </div>
</body>
</html>