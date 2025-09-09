<?php
include '../includes/header.php';

require '../config.php';

$servername = $database_settings['servername'];
$username = $database_settings['username'];
$password = $database_settings['password'];
$dbname = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$flashMsg = "";
$errorMsg = "";

// Initialize form values
$formValues = [
    'account_type' => '',
    'student_id' => '', 'faculty_id' => '', 'ccdu_id' => '', 'security_id' => '',
    'first_name' => '', 'middle_name' => '', 'last_name' => '',
    'mobile' => '', 'email' => '', 'institute' => '', 'course' => '', 'level' => '', 'section' => '',
    'guardian' => '', 'guardian_mobile' => '', 'password' => ''
];

// Keep submitted values
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    foreach ($formValues as $key => $val) {
        $formValues[$key] = $_POST[$key] ?? '';
    }

    $account_type = $formValues['account_type'];

    // Check duplicate email
    $stmtCheck = $conn->prepare("SELECT id_number FROM accounts WHERE email = ?");
    $stmtCheck->bind_param("s", $formValues['email']);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck && $resultCheck->num_rows > 0) {
        $errorMsg = "⚠️ Email already registered!";
    }
    $stmtCheck->close();

    if (empty($errorMsg)) {
        // Handle photo upload
        $photo = "";
        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . "/uploads/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $photo = time() . "_" . basename($_FILES["photo"]["name"]);
            $targetPath = $uploadDir . $photo;
            if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetPath)) {
                $errorMsg = "⚠️ Error uploading photo.";
                $photo = "";
            }
        }

        if (empty($errorMsg)) {
            $hashedPassword = password_hash($formValues['password'], PASSWORD_DEFAULT);

            switch ($account_type) {
                case "student":
                    $stmt = $conn->prepare("INSERT INTO student_account (student_id, first_name, middle_name, last_name, mobile, email, institute, course, level, section, guardian, guardian_mobile, photo)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssssssssss",
                        $formValues['student_id'], $formValues['first_name'], $formValues['middle_name'], $formValues['last_name'],
                        $formValues['mobile'], $formValues['email'], $formValues['institute'], $formValues['course'], $formValues['level'],
                        $formValues['section'], $formValues['guardian'], $formValues['guardian_mobile'], $photo
                    );
                    $idNumber = $formValues['student_id'];
                    break;

                case "faculty":
                    $stmt = $conn->prepare("INSERT INTO faculty_account (faculty_id, first_name, last_name, mobile, email, institute, photo)
                                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssss",
                        $formValues['faculty_id'], $formValues['first_name'], $formValues['last_name'], $formValues['mobile'],
                        $formValues['email'], $formValues['institute'], $photo
                    );
                    $idNumber = $formValues['faculty_id'];
                    break;

                case "ccdu":
                    $stmt = $conn->prepare("INSERT INTO ccdu_account (ccdu_id, first_name, last_name, mobile, email, photo)
                                            VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss",
                        $formValues['ccdu_id'], $formValues['first_name'], $formValues['last_name'], $formValues['mobile'],
                        $formValues['email'], $photo
                    );
                    $idNumber = $formValues['ccdu_id'];
                    break;

                case "security":
                    $stmt = $conn->prepare("INSERT INTO security_account (security_id, first_name, last_name, mobile, email, photo)
                                            VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss",
                        $formValues['security_id'], $formValues['first_name'], $formValues['last_name'], $formValues['mobile'],
                        $formValues['email'], $photo
                    );
                    $idNumber = $formValues['security_id'];
                    break;

                default:
                    $errorMsg = "⚠️ Invalid account type.";
            }

            if (empty($errorMsg)) {
                if ($stmt->execute()) {
                    $stmtAcc = $conn->prepare("INSERT INTO accounts (id_number, email, password, account_type) VALUES (?, ?, ?, ?)");
                    $stmtAcc->bind_param("ssss", $idNumber, $formValues['email'], $hashedPassword, $account_type);
                    if ($stmtAcc->execute()) {
                        $flashMsg = "✅ Account added successfully!";
                        $formValues = array_map(fn($v) => '', $formValues);
                    } else {
                        $errorMsg = "⚠️ Error inserting into accounts table.";
                    }
                    $stmtAcc->close();
                } else {
                    $errorMsg = "⚠️ Error inserting into {$account_type}_account table.";
                }
                $stmt->close();
            }
        }
    }
}

$conn->close();

if (empty($formValues['password'])) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    $formValues['password'] = substr(str_shuffle($chars), 0, 10);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Accounts</title>
    <style>
    .form-container { display:none; margin-bottom:20px; }
    </style>
</head>
<body>
    <a href="dashboard.php"><button>Return to Dashboard</button></a>
    <h1>Add New User Accounts</h1>

    <?php if (!empty($errorMsg)): ?>
        <script>alert("<?php echo addslashes($errorMsg); ?>");</script>
    <?php endif; ?>

    <?php if (!empty($flashMsg)): ?>
        <script>alert("<?php echo addslashes($flashMsg); ?>");</script>
    <?php endif; ?>

    <label>Account Type:</label>
    <select id="account_type" onchange="toggleForms()" required>
        <option value="">--Select--</option>
        <option value="student" <?php echo $formValues['account_type']=='student'?'selected':''; ?>>Student</option>
        <option value="faculty" <?php echo $formValues['account_type']=='faculty'?'selected':''; ?>>Faculty</option>
        <option value="ccdu" <?php echo $formValues['account_type']=='ccdu'?'selected':''; ?>>CCDU Staff</option>
        <option value="security" <?php echo $formValues['account_type']=='security'?'selected':''; ?>>Security</option>
    </select>

<!-- STUDENT FORM -->
    <div id="studentForm" class="form-container">
    <h3>Student Registration</h3>
        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="account_type" value="student">
            
            <label>Student ID:</label>
            <input type="text" name="student_id" value="<?php echo htmlspecialchars($formValues['student_id']); ?>" 
                maxlength="9" 
                title="Format: YYYY-NNNN (e.g. 2023-0001)" 
                pattern="^[0-9]{4}-[0-9]{4}$" 
                oninput="this.value = this.value.replace(/[^0-9-]/g, '')"
            required><br>

            <label>First Name:</label>
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($formValues['first_name']); ?>" required><br>

            <label>Middle Name:</label>
            <input type="text" name="middle_name" value="<?php echo htmlspecialchars($formValues['middle_name']); ?>" required><br>
            
            <label>Last Name:</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($formValues['last_name']); ?>" required><br>

            <label>Mobile:</label>
            <input type="text" name="mobile" value="<?php echo htmlspecialchars($formValues['mobile']); ?>" 
                maxlength="11"
                placeholder="09XXXXXXXXX"
                pattern="^09[0-9]{9}$"
                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
            required><br>

            <label>Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($formValues['email']); ?>"  required><br>

            <label>Institute:</label>
            <select name="institute" id="student_institute" onchange="loadCourses('student')" required>
                <option value="" disabled selected>--Select--</option>
                <option value="IBCE" <?php echo $formValues['institute']=='IBCE'?'selected':''; ?>>Institute of Computing and Business Education</option>
                <option value="IHTM" <?php echo $formValues['institute']=='IHTM'?'selected':''; ?>>Institute of Hospitality Management</option>
                <option value="IAS" <?php echo $formValues['institute']=='IAS'?'selected':''; ?>>Institute of Arts and Sciences</option>
                <option value="ITE" <?php echo $formValues['institute']=='ITE'?'selected':''; ?>>Institute of Teaching Education</option>
            </select><br>

            <label>Course:</label>
            <select name="course" id="student_course" required>
                <option value="<?php echo htmlspecialchars($formValues['course']); ?>"><?php echo htmlspecialchars($formValues['course']); ?></option>
            </select><br>

            <label>Year Level:</label>
            <select name="level" required>
                <option value="<?php echo htmlspecialchars($formValues['level']); ?>"><?php echo htmlspecialchars($formValues['level']); ?>--SELECT--</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
            </select><br>

            <label>Section:</label>
            <select name="section" required>
                <option value="<?php echo htmlspecialchars($formValues['section']); ?>"><?php echo htmlspecialchars($formValues['section']); ?>--SELECT--</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
            </select><br>

        <h3>Guardian Contact</h3>

            <label>Guardian Name:</label>
            <input type="text" name="guardian" value="<?php echo htmlspecialchars($formValues['guardian']); ?>" required><br>

            <label>Guardian Mobile:</label>
            <input type="text" name="guardian_mobile" value="<?php echo htmlspecialchars($formValues['guardian_mobile']); ?>" 
                maxlength="11"
                placeholder="09XXXXXXXXX"
                pattern="^09[0-9]{9}$"
                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
            required><br>

            <label>Temporary Password:</label>
            <input type="text" name="password" id="student_password" value="<?php echo htmlspecialchars($formValues['password']); ?>" required>
            <button type="button" onclick="generatePass('student_password')">Generate</button><br>

            <label>Photo:</label>
            <input type="file" name="photo" onchange="previewPhoto(this,'studentPreview')">
            <img id="studentPreview" width="100"><br>

            <button type="submit">Register Student</button>

        </form>
    </div>

<!-- FACULTY, CCDU, SECURITY forms are similar, just adjust IDs and names -->

    <div id="facultyForm" class="form-container">
    <h3>Faculty Registration</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="account_type" value="faculty">

            <label>ID Number:</label>
            <input type="text" name="faculty_id" value="<?php echo htmlspecialchars($formValues['faculty_id']); ?>" 
                maxlength="9" 
                title="Format: YYYY-NNNN (e.g. 2023-0001)" 
                pattern="^[0-9]{4}-[0-9]{4}$" 
                oninput="this.value = this.value.replace(/[^0-9-]/g, '')" 
            required><br>

            <label>First Name:</label>
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($formValues['first_name']); ?>" required><br>

            <label>Last Name:</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($formValues['last_name']); ?>" required><br>

            <label>Mobile:</label>
            <input type="text" name="mobile" value="<?php echo htmlspecialchars($formValues['mobile']); ?>" 
                maxlength="11"
                placeholder="09XXXXXXXXX"
                pattern="^09[0-9]{9}$"
                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
            required><br>

            <label>Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($formValues['email']); ?>" required><br>

            <label>Institute:</label>
            <select name="institute" required>
                <option value="" disabled selected>--Select--</option>
                <option value="IBCE" <?php echo $formValues['institute']=='IBCE'?'selected':''; ?>>Institute of Business and Computing Education</option>
                <option value="IHTM" <?php echo $formValues['institute']=='IHTM'?'selected':''; ?>>Institute of Hospitality Management</option>
                <option value="IAS" <?php echo $formValues['institute']=='IAS'?'selected':''; ?>>Institute of Arts and Sciences</option>
                <option value="ITE" <?php echo $formValues['institute']=='ITE'?'selected':''; ?>>Institute of Teaching Education</option>
            </select><br>

            <label>Password:</label><input type="text" name="password" id="faculty_password" value="<?php echo htmlspecialchars($formValues['password']); ?>" required>
            <button type="button" onclick="generatePass('faculty_password')">Generate</button><br>

            <label>Photo:</label><input type="file" name="photo" onchange="previewPhoto(this,'facultyPreview')">
            <img id="facultyPreview" width="100"><br>

            <button type="submit">Register Faculty</button>

        </form>
    </div>

<!-- CCDU -->
    <div id="ccduForm" class="form-container">
    <h3>CCDU Staff Registration</h3>
        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="account_type" value="ccdu">

            <label>ID Number:</label>
            <input type="text" name="ccdu_id" value="<?php echo htmlspecialchars($formValues['ccdu_id']); ?>" 
                maxlength="9" 
                title="Format: YYYY-NNNN (e.g. 2023-0001)" 
                pattern="^[0-9]{4}-[0-9]{4}$" 
                oninput="this.value = this.value.replace(/[^0-9-]/g, '')" 
            required><br>

            <label>First Name:</label>
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($formValues['first_name']); ?>" required><br>

            <label>Last Name:</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($formValues['last_name']); ?>" required><br>

            <label>Mobile:</label>
            <input type="text" name="mobile" value="<?php echo htmlspecialchars($formValues['mobile']); ?>" maxlength="11"
                    placeholder="09XXXXXXXXX"
                    pattern="^09[0-9]{9}$"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
            required><br>

            <label>Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($formValues['email']); ?>" required><br>

            <label>Password:</label>
            <input type="text" name="password" id="ccdu_password" value="<?php echo htmlspecialchars($formValues['password']); ?>" required>
            <button type="button" onclick="generatePass('ccdu_password')">Generate</button><br>

            <label>Photo:</label><input type="file" name="photo" onchange="previewPhoto(this,'ccduPreview')">
            <img id="ccduPreview" width="100"><br>

            <button type="submit">Register CCDU</button>

        </form>
    </div>

<!-- SECURITY -->
    <div id="securityForm" class="form-container">
    <h3>Security Registration</h3>
        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="account_type" value="security">

            <label>ID Number:</label>
            <input type="text" name="security_id" value="<?php echo htmlspecialchars($formValues['security_id']); ?>"  maxlength="9" 
                title="Format: YYYY-NNNN (e.g. 2023-0001)" 
                pattern="^[0-9]{4}-[0-9]{4}$" 
                oninput="this.value = this.value.replace(/[^0-9-]/g, '')" 
            required><br>

            <label>First Name:</label><input type="text" name="first_name" value="<?php echo htmlspecialchars($formValues['first_name']); ?>" required><br>

            <label>Last Name:</label><input type="text" name="last_name" value="<?php echo htmlspecialchars($formValues['last_name']); ?>" required><br>

            <label>Mobile:</label><input type="text" name="mobile" value="<?php echo htmlspecialchars($formValues['mobile']); ?>" 
                maxlength="11"
                placeholder="09XXXXXXXXX"
                pattern="^09[0-9]{9}$"
                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
            required><br>

            <label>Email:</label><input type="email" name="email" value="<?php echo htmlspecialchars($formValues['email']); ?>" required><br>

            <label>Password:</label><input type="text" name="password" id="security_password" value="<?php echo htmlspecialchars($formValues['password']); ?>" required>
            <button type="button" onclick="generatePass('security_password')">Generate</button><br>

            <label>Photo:</label><input type="file" name="photo" onchange="previewPhoto(this,'securityPreview')">
            <img id="securityPreview" width="100"><br>

            <button type="submit">Register Security</button>
        </form>
    </div>

        <script>
            window.onload = toggleForms;
                function toggleForms(){
                    const selected = document.getElementById("account_type").value;
                    ['student','faculty','ccdu','security'].forEach(t=>{
                        document.getElementById(t+'Form').style.display = (selected===t)?'block':'none';
                    });
                }

                function generatePass(inputId){
                    let chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
                    let pass = "";
                    for(let i=0;i<10;i++) pass+=chars.charAt(Math.floor(Math.random()*chars.length));
                    document.getElementById(inputId).value = pass;
                }

                function previewPhoto(input, previewId){
                    const preview = document.getElementById(previewId);
                    if(input.files && input.files[0]){
                        const reader = new FileReader();
                        reader.onload = function(e){ preview.src=e.target.result; preview.style.display='block'; }
                        reader.readAsDataURL(input.files[0]);
                    }
                }

                // Example function to dynamically populate courses based on institute
                function loadCourses(type){
                    const institute = document.getElementById(type+'_institute').value;
                    const courseSelect = document.getElementById(type+'_course');
                    const courses = {
                        'IBCE': ['BSIT','BSCA','BSA', 'BSOA', 'BSE', 'BSMA'],
                        'IHTM': ['BSTM','BSHM'],
                        'IAS': ['BSBIO', 'ABH', 'BSLM'],
                        'ITE': ['BSED-ENG', 'BSED-FIL', 'BSED-MATH', 'BEED', 'BSED-SS', 'BTVTE', 'BSED-SCI', 'BPED']
                    };
                    courseSelect.innerHTML = '';
                    if(courses[institute]){
                        courses[institute].forEach(c=>{
                            let opt = document.createElement('option');
                            opt.value=c; opt.text=c;
                            courseSelect.appendChild(opt);
                        });
                    }
            }
        </script>
    </body>
</html>
