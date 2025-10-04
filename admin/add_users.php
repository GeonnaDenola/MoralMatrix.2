<?php
include '../includes/admin_header.php';

require '../config.php';

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__ . '/../lib/email_lib.php'; // has moralmatrix_mailer()

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;


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

                    /* =======================
                    QR: insert + generate (students only)
                    ======================= */
                    if ($account_type === 'student') {
                        // use $idNumber (already set to the student's ID) so we never depend on $formValues after reset
                        $studentIdForQR = $idNumber;

                        // 1) insert or reuse qr_key
                        $qrKey = bin2hex(random_bytes(32)); // 64 hex
                        $insQR = $conn->prepare("INSERT INTO student_qr_keys (student_id, qr_key) VALUES (?, ?)");
                        $insQR->bind_param("ss", $studentIdForQR, $qrKey);
                        if(!$insQR->execute()){
                            // fetch existing if duplicate or other constraint
                            $sel = $conn->prepare("SELECT qr_key FROM student_qr_keys WHERE student_id = ? LIMIT 1");
                            $sel->bind_param("s", $studentIdForQR);
                            $sel->execute();
                            $row = $sel->get_result()->fetch_assoc();
                            if (!empty($row['qr_key'])) {
                                $qrKey = $row['qr_key'];
                            } else {
                                error_log('QR insert failed for '.$studentIdForQR.' : '.$insQR->error);
                            }
                            $sel->close();
                        }
                        $insQR->close();

                        // 2) Build resolver URL (absolute)
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host   = $_SERVER['HTTP_HOST'];
                        $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // e.g. /admin
                        
                       $qrURL  = $scheme.'://'.$host.$base.'/../qr.php?k='.urlencode($qrKey);


                        // 3) Generate SVG (no GD required)
                        $options = new \chillerlan\QRCode\QROptions([
                            'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
                            'scale'      => 6,
                            'eccLevel'   => \chillerlan\QRCode\QRCode::ECC_L,
                        ]);
                        $svg = (new \chillerlan\QRCode\QRCode($options))->render($qrURL);

                        // 4) Save to /uploads/qrcodes/{student_id}.svg (project root uploads)
                        $qrDir  = dirname(__DIR__) . '/uploads/qrcodes'; // from /admin to project root
                        if (!is_dir($qrDir)){
                            if(!mkdir($qrDir, 0777, true)){
                                error_log('Failed to mkdir: '.$qrDir);
                            }
                        }

                        $qrFile = $qrDir . DIRECTORY_SEPARATOR . $studentIdForQR . '.svg';
                        $bytes  = @file_put_contents($qrFile, $svg);

                        if($bytes === false){
                            // log helpful diagnostics
                            error_log('QR save failed: file='.$qrFile
                                .' dir_exists='.(is_dir($qrDir)?'1':'0')
                                .' dir_writable='.(is_writable($qrDir)?'1':'0')
                                .' parent='.dirname($qrDir));
                        }
                    }
                    /* ========= end QR block ========= */

                    $flashMsg = "✅ Account added successfully!";
                    $formValues = array_map(fn($v) => '', $formValues);

                } else {
                    $errorMsg = "⚠️ Error inserting into accounts table: ".$conn->error;
                }
                $stmtAcc->close();
            } else {
                $errorMsg = "⚠️ Error inserting into {$account_type}_account table: ".$conn->error;
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Add User Accounts</title>
    <link rel="stylesheet" href="../css/add_users.css"/>
</head>
<body>

<main class="au-wrap"><!-- centered container that plays nice with your header/sidebar -->
    <!-- Top row: title left, button right -->
    <div class="au-header">
        <h1 class="page-title">Add New User Accounts</h1>
        <a class="btn btn-outline" href="dashboard.php">Return to Dashboard</a>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="notice notice-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashMsg)): ?>
        <div class="notice notice-success"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <!-- Account type chooser -->
    <section class="card">
        <div class="field-row">
            <label for="account_type" class="label">Account Type</label>
            <select id="account_type" class="select" onchange="toggleForms()" required>
                <option value="">-- Select --</option>
                <option value="student"  <?= ($formValues['account_type'] ?? '')==='student'  ? 'selected' : '' ?>>Student</option>
                <option value="faculty"  <?= ($formValues['account_type'] ?? '')==='faculty'  ? 'selected' : '' ?>>Faculty</option>
                <option value="ccdu"     <?= ($formValues['account_type'] ?? '')==='ccdu'     ? 'selected' : '' ?>>CCDU Staff</option>
                <option value="security" <?= ($formValues['account_type'] ?? '')==='security' ? 'selected' : '' ?>>Security</option>
            </select>
        </div>
    </section>

    <!-- ========== STUDENT ========== -->
    <section id="studentForm" class="card form-container">
        <h2 class="section-title">Student Registration</h2>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="account_type" value="student" />

            <div class="field">
                <label class="label">Student ID</label>
                <input class="input" type="text" name="student_id"
                    value="<?= htmlspecialchars($formValues['student_id'] ?? '') ?>"
                    maxlength="9" title="Format: YYYY-NNNN (e.g. 2023-0001)"
                    pattern="^[0-9]{4}-[0-9]{4}$"
                    oninput="this.value = this.value.replace(/[^0-9-]/g, '')" required />
            </div>

            <div class="field">
                <label class="label">First Name</label>
                <input class="input" type="text" name="first_name"
                    value="<?= htmlspecialchars($formValues['first_name'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Middle Name</label>
                <input class="input" type="text" name="middle_name"
                    value="<?= htmlspecialchars($formValues['middle_name'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Last Name</label>
                <input class="input" type="text" name="last_name"
                    value="<?= htmlspecialchars($formValues['last_name'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Mobile</label>
                <input class="input" type="text" name="mobile"
                    value="<?= htmlspecialchars($formValues['mobile'] ?? '') ?>"
                    maxlength="11" placeholder="09XXXXXXXXX"
                    pattern="^09[0-9]{9}$"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" required />
            </div>

            <div class="field">
                <label class="label">Email</label>
                <input class="input" type="email" name="email"
                    value="<?= htmlspecialchars($formValues['email'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Institute</label>
                <select class="select" name="institute" id="student_institute" onchange="loadCourses('student')" required>
                    <option value="" disabled <?= empty($formValues['institute']) ? 'selected' : '' ?>>-- Select --</option>
                    <option value="IBCE" <?= ($formValues['institute'] ?? '')==='IBCE' ? 'selected' : '' ?>>Institute of Computing and Business Education</option>
                    <option value="IHTM" <?= ($formValues['institute'] ?? '')==='IHTM' ? 'selected' : '' ?>>Institute of Hospitality Management</option>
                    <option value="IAS"  <?= ($formValues['institute'] ?? '')==='IAS'  ? 'selected' : '' ?>>Institute of Arts and Sciences</option>
                    <option value="ITE"  <?= ($formValues['institute'] ?? '')==='ITE'  ? 'selected' : '' ?>>Institute of Teaching Education</option>
                </select>
            </div>

            <div class="field">
                <label class="label">Course</label>
                <select class="select" name="course" id="student_course" data-selected="<?= htmlspecialchars($formValues['course'] ?? '') ?>" required>
                    <option value="" disabled selected>-- Select --</option>
                </select>
            </div>

            <div class="field">
                <label class="label">Year Level</label>
                <select class="select" name="level" required>
                    <option value="" disabled <?= empty($formValues['level']) ? 'selected' : '' ?>>-- Select --</option>
                    <option value="1" <?= ($formValues['level'] ?? '')==='1' ? 'selected' : '' ?>>1</option>
                    <option value="2" <?= ($formValues['level'] ?? '')==='2' ? 'selected' : '' ?>>2</option>
                    <option value="3" <?= ($formValues['level'] ?? '')==='3' ? 'selected' : '' ?>>3</option>
                    <option value="4" <?= ($formValues['level'] ?? '')==='4' ? 'selected' : '' ?>>4</option>
                </select>
            </div>

            <div class="field">
                <label class="label">Section</label>
                <select class="select" name="section" required>
                    <option value="" disabled <?= empty($formValues['section']) ? 'selected' : '' ?>>-- Select --</option>
                    <option value="A" <?= ($formValues['section'] ?? '')==='A' ? 'selected' : '' ?>>A</option>
                    <option value="B" <?= ($formValues['section'] ?? '')==='B' ? 'selected' : '' ?>>B</option>
                    <option value="C" <?= ($formValues['section'] ?? '')==='C' ? 'selected' : '' ?>>C</option>
                </select>
            </div>

            <div class="divider span-2">Guardian Contact</div>

            <div class="field">
                <label class="label">Guardian Name</label>
                <input class="input" type="text" name="guardian"
                    value="<?= htmlspecialchars($formValues['guardian'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Guardian Mobile</label>
                <input class="input" type="text" name="guardian_mobile"
                    value="<?= htmlspecialchars($formValues['guardian_mobile'] ?? '') ?>"
                    maxlength="11" placeholder="09XXXXXXXXX"
                    pattern="^09[0-9]{9}$"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" required />
            </div>

            <div class="field">
                <label class="label">Temporary Password</label>
                <div class="with-btn">
                    <input class="input" type="text" name="password" id="student_password"
                           value="<?= htmlspecialchars($formValues['password'] ?? '') ?>" required />
                    <button type="button" class="btn btn-secondary" onclick="generatePass('student_password')">Generate</button>
                </div>
            </div>

            <div class="field">
                <label class="label">Photo</label>
                <label class="file">
                    <input type="file" name="photo" accept="image/*"
                           onchange="previewPhoto(this,'studentPreview')" />
                    <span>Choose file</span>
                </label>
            </div>

            <!-- Large, centered preview at the very bottom -->
            <div class="photo-preview-row span-2">
                <img id="studentPreview" class="preview preview-lg" alt="Preview" />
            </div>

            <div class="actions span-2">
                <button type="submit" class="btn btn-primary">Register Student</button>
            </div>
        </form>
    </section>

    <!-- ========== FACULTY ========== -->
    <section id="facultyForm" class="card form-container">
        <h2 class="section-title">Faculty Registration</h2>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="account_type" value="faculty" />

            <div class="field">
                <label class="label">ID Number</label>
                <input class="input" type="text" name="faculty_id"
                    value="<?= htmlspecialchars($formValues['faculty_id'] ?? '') ?>"
                    maxlength="9" title="Format: YYYY-NNNN (e.g. 2023-0001)"
                    pattern="^[0-9]{4}-[0-9]{4}$"
                    oninput="this.value = this.value.replace(/[^0-9-]/g, '')" required />
            </div>

            <div class="field">
                <label class="label">First Name</label>
                <input class="input" type="text" name="first_name"
                    value="<?= htmlspecialchars($formValues['first_name'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Last Name</label>
                <input class="input" type="text" name="last_name"
                    value="<?= htmlspecialchars($formValues['last_name'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Mobile</label>
                <input class="input" type="text" name="mobile"
                    value="<?= htmlspecialchars($formValues['mobile'] ?? '') ?>"
                    maxlength="11" placeholder="09XXXXXXXXX"
                    pattern="^09[0-9]{9}$"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" required />
            </div>

            <div class="field">
                <label class="label">Email</label>
                <input class="input" type="email" name="email"
                    value="<?= htmlspecialchars($formValues['email'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Institute</label>
                <select class="select" name="institute" required>
                    <option value="" disabled <?= empty($formValues['institute']) ? 'selected' : '' ?>>-- Select --</option>
                    <option value="IBCE" <?= ($formValues['institute'] ?? '')==='IBCE' ? 'selected' : '' ?>>Institute of Business and Computing Education</option>
                    <option value="IHTM" <?= ($formValues['institute'] ?? '')==='IHTM' ? 'selected' : '' ?>>Institute of Hospitality Management</option>
                    <option value="IAS"  <?= ($formValues['institute'] ?? '')==='IAS'  ? 'selected' : '' ?>>Institute of Arts and Sciences</option>
                    <option value="ITE"  <?= ($formValues['institute'] ?? '')==='ITE'  ? 'selected' : '' ?>>Institute of Teaching Education</option>
                </select>
            </div>

            <div class="field">
                <label class="label">Password</label>
                <div class="with-btn">
                    <input class="input" type="text" name="password" id="faculty_password"
                           value="<?= htmlspecialchars($formValues['password'] ?? '') ?>" required />
                    <button type="button" class="btn btn-secondary" onclick="generatePass('faculty_password')">Generate</button>
                </div>
            </div>

            <div class="field">
                <label class="label">Photo</label>
                <label class="file">
                    <input type="file" name="photo" accept="image/*"
                           onchange="previewPhoto(this,'facultyPreview')" />
                    <span>Choose file</span>
                </label>
            </div>

            <div class="photo-preview-row span-2">
                <img id="facultyPreview" class="preview preview-lg" alt="Preview" />
            </div>

            <div class="actions span-2">
                <button type="submit" class="btn btn-primary">Register Faculty</button>
            </div>
        </form>
    </section>

    <!-- ========== CCDU ========== -->
    <section id="ccduForm" class="card form-container">
        <h2 class="section-title">CCDU Staff Registration</h2>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="account_type" value="ccdu" />

            <div class="field">
                <label class="label">ID Number</label>
                <input class="input" type="text" name="ccdu_id"
                    value="<?= htmlspecialchars($formValues['ccdu_id'] ?? '') ?>"
                    maxlength="9" title="Format: YYYY-NNNN (e.g. 2023-0001)"
                    pattern="^[0-9]{4}-[0-9]{4}$"
                    oninput="this.value = this.value.replace(/[^0-9-]/g, '')" required />
            </div>

            <div class="field">
                <label class="label">First Name</label>
                <input class="input" type="text" name="first_name"
                    value="<?= htmlspecialchars($formValues['first_name'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Last Name</label>
                <input class="input" type="text" name="last_name"
                    value="<?= htmlspecialchars($formValues['last_name'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Mobile</label>
                <input class="input" type="text" name="mobile"
                    value="<?= htmlspecialchars($formValues['mobile'] ?? '') ?>"
                    maxlength="11" placeholder="09XXXXXXXXX"
                    pattern="^09[0-9]{9}$"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" required />
            </div>

            <div class="field">
                <label class="label">Email</label>
                <input class="input" type="email" name="email"
                    value="<?= htmlspecialchars($formValues['email'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Password</label>
                <div class="with-btn">
                    <input class="input" type="text" name="password" id="ccdu_password"
                           value="<?= htmlspecialchars($formValues['password'] ?? '') ?>" required />
                    <button type="button" class="btn btn-secondary" onclick="generatePass('ccdu_password')">Generate</button>
                </div>
            </div>

            <div class="field">
                <label class="label">Photo</label>
                <label class="file">
                    <input type="file" name="photo" accept="image/*"
                           onchange="previewPhoto(this,'ccduPreview')" />
                    <span>Choose file</span>
                </label>
            </div>

            <div class="photo-preview-row span-2">
                <img id="ccduPreview" class="preview preview-lg" alt="Preview" />
            </div>

            <div class="actions span-2">
                <button type="submit" class="btn btn-primary">Register CCDU</button>
            </div>
        </form>
    </section>

    <!-- ========== SECURITY ========== -->
    <section id="securityForm" class="card form-container">
        <h2 class="section-title">Security Registration</h2>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="account_type" value="security" />

            <div class="field">
                <label class="label">ID Number</label>
                <input class="input" type="text" name="security_id"
                    value="<?= htmlspecialchars($formValues['security_id'] ?? '') ?>"
                    maxlength="9" title="Format: YYYY-NNNN (e.g. 2023-0001)"
                    pattern="^[0-9]{4}-[0-9]{4}$"
                    oninput="this.value = this.value.replace(/[^0-9-]/g, '')" required />
            </div>

            <div class="field">
                <label class="label">First Name</label>
                <input class="input" type="text" name="first_name"
                    value="<?= htmlspecialchars($formValues['first_name'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Last Name</label>
                <input class="input" type="text" name="last_name"
                    value="<?= htmlspecialchars($formValues['last_name'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Mobile</label>
                <input class="input" type="text" name="mobile"
                    value="<?= htmlspecialchars($formValues['mobile'] ?? '') ?>"
                    maxlength="11" placeholder="09XXXXXXXXX"
                    pattern="^09[0-9]{9}$"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" required />
            </div>

            <div class="field">
                <label class="label">Email</label>
                <input class="input" type="email" name="email"
                    value="<?= htmlspecialchars($formValues['email'] ?? '') ?>" required />
            </div>

            <div class="field">
                <label class="label">Password</label>
                <div class="with-btn">
                    <input class="input" type="text" name="password" id="security_password"
                           value="<?= htmlspecialchars($formValues['password'] ?? '') ?>" required />
                    <button type="button" class="btn btn-secondary" onclick="generatePass('security_password')">Generate</button>
                </div>
            </div>

            <div class="field">
                <label class="label">Photo</label>
                <label class="file">
                    <input type="file" name="photo" accept="image/*"
                           onchange="previewPhoto(this,'securityPreview')" />
                    <span>Choose file</span>
                </label>
            </div>

            <div class="photo-preview-row span-2">
                <img id="securityPreview" class="preview preview-lg" alt="Preview" />
            </div>

            <div class="actions span-2">
                <button type="submit" class="btn btn-primary">Register Security</button>
            </div>
        </form>
    </section>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        toggleForms();

        const typeSel = document.getElementById('account_type');
        const saved = localStorage.getItem('au_type');
        if (!typeSel.value && saved) { typeSel.value = saved; toggleForms(); }
        typeSel.addEventListener('change', () => localStorage.setItem('au_type', typeSel.value));

        const inst = document.getElementById('student_institute');
        if (inst && inst.value) loadCourses('student');
    });

    function toggleForms(){
        const selected = document.getElementById('account_type').value;
        ['student','faculty','ccdu','security'].forEach(t=>{
            const el = document.getElementById(t+'Form');
            if (!el) return;
            el.style.display = (selected===t) ? 'block' : 'none';
        });
        const active = document.getElementById(selected+'Form');
        if (active) active.scrollIntoView({behavior:'smooth', block:'start'});
    }

    function generatePass(inputId){
        const chars="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        let pass=""; for(let i=0;i<10;i++) pass+=chars[Math.floor(Math.random()*chars.length)];
        const input=document.getElementById(inputId);
        input.value=pass; input.focus(); input.select();
    }

    function previewPhoto(input, previewId){
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]){
            const reader = new FileReader();
            reader.onload = e => { preview.src=e.target.result; preview.style.display='block'; };
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.src=''; preview.style.display='none';
        }
    }

    function loadCourses(type){
        const institute = document.getElementById(type+'_institute').value;
        const courseSelect = document.getElementById(type+'_course');
        const courses = {
            'IBCE':['BSIT','BSCA','BSA','BSOA','BSE','BSMA'],
            'IHTM':['BSTM','BSHM'],
            'IAS':['BSBIO','ABH','BSLM'],
            'ITE':['BSED-ENG','BSED-FIL','BSED-MATH','BEED','BSED-SS','BTVTE','BSED-SCI','BPED']
        };
        courseSelect.innerHTML = '<option value="" disabled selected>-- Select --</option>';
        if (courses[institute]){
            const selected = courseSelect.dataset.selected || '';
            courses[institute].forEach(c=>{
                const opt = document.createElement('option');
                opt.value=c; opt.textContent=c;
                if (c===selected) opt.selected = true;
                courseSelect.appendChild(opt);
            });
        }
    }
</script>
</body>
</html>
