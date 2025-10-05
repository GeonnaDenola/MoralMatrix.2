<?php
declare(strict_types=1);

include '../includes/admin_header.php';

require '../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/email_lib.php'; // provides moralmatrix_mailer()

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/* ---------- DB Connect (exceptions on error) ---------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

try{
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset('utf8mb4');
} catch(Throwable $e){
    http_response_code(500);
    die('Database connection failed.');
}

/* ---------- Helpers ---------- */
function clean($v){ return is_string($v) ? trim($v) : $v; }

/** Save an uploaded photo safely under project_root/uploads/photos and return filename (or '') */
function handle_photo_upload(string $field, string $appRoot, ?string &$err): string{
    $err = '';
    if(!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE){
        return '';
    }
    if($_FILES[$field]['error'] !== UPLOAD_ERR_OK){
        $err = "⚠️ Error uploading photo.";
        return '';
    }
    // Validate size (<= 5MB)
    if($_FILES[$field]['size'] > 5 * 1024 * 1024){
        $err = "⚠️ Photo too large (max 5MB).";
        return '';
    }
    // Validate mime
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES[$field]['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if(!isset($allowed[$mime])){
        $err = "⚠️ Invalid image type. Use JPG/PNG/WebP/GIF.";
        return '';
    }
    $ext   = $allowed[$mime];
    $dir   = $appRoot . '/uploads/photos';
    if(!is_dir($dir) && !mkdir($dir, 0777, true)){
        $err = "⚠️ Cannot create photos directory.";
        return '';
    }
    $name  = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest  = $dir . '/' . $name;
    if(!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)){
        $err = "⚠️ Failed to save uploaded photo.";
        return '';
    }
    return $name; // store filename only
}

/** Build absolute base URL to app root (one level above /admin) */
function app_base_url(): string{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme  = $isHttps ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // /app/admin/add_users.php -> /app
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');        // /app/admin
    $appBase   = rtrim(dirname($scriptDir), '/\\');                           // /app
    return $scheme.'://'.$host.$appBase;
}

/* ---------- Page state ---------- */
$flashMsg = "";
$errorMsg = "";

// Default form values
$formValues = [
    'account_type' => '',
    'student_id' => '', 'faculty_id' => '', 'ccdu_id' => '', 'security_id' => '',
    'first_name' => '', 'middle_name' => '', 'last_name' => '',
    'mobile' => '', 'email' => '', 'institute' => '', 'course' => '', 'level' => '', 'section' => '',
    'guardian' => '', 'guardian_mobile' => '', 'password' => ''
];

$validTypes = ['student','faculty','ccdu','security'];

/* ---------- POST handler ---------- */
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Keep submitted
    foreach($formValues as $k => $v){
        $formValues[$k] = clean($_POST[$k] ?? '');
    }
    $account_type = $formValues['account_type'];

    try{
        if(!in_array($account_type, $validTypes, true)){
            throw new RuntimeException('⚠️ Invalid account type.');
        }

        // Basic validation
        if(!filter_var($formValues['email'], FILTER_VALIDATE_EMAIL)){
            throw new RuntimeException('⚠️ Invalid email address.');
        }

        // Check duplicate email in accounts
        $stmt = $conn->prepare("SELECT 1 FROM accounts WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $formValues['email']);
        $stmt->execute();
        if($stmt->get_result()->num_rows > 0){
            throw new RuntimeException('⚠️ Email already registered!');
        }
        $stmt->close();

        // Decide ID column & value by type
        $idColumn = [
            'student'  => 'student_id',
            'faculty'  => 'faculty_id',
            'ccdu'     => 'ccdu_id',
            'security' => 'security_id',
        ][$account_type];
        $idNumber = $formValues[$idColumn];

        // Check duplicate ID in the specific table to fail fast
        $tableByType = [
            'student'  => 'student_account',
            'faculty'  => 'faculty_account',
            'ccdu'     => 'ccdu_account',
            'security' => 'security_account',
        ][$account_type];
        $stmt = $conn->prepare("SELECT 1 FROM {$tableByType} WHERE {$idColumn} = ? LIMIT 1");
        $stmt->bind_param("s", $idNumber);
        $stmt->execute();
        if($stmt->get_result()->num_rows > 0){
            throw new RuntimeException('⚠️ ID already exists for this account type.');
        }
        $stmt->close();

        // Handle photo upload
        $appRoot = dirname(__DIR__); // project root
        $photoErr = '';
        $photoFilename = handle_photo_upload('photo', $appRoot, $photoErr);
        if($photoErr){
            throw new RuntimeException($photoErr);
        }

        // Prepare password
        if($formValues['password'] === ''){
            $formValues['password'] = bin2hex(random_bytes(5)); // 10 chars
        }
        $hashedPassword = password_hash($formValues['password'], PASSWORD_DEFAULT);

        /* ===== TRANSACTION ===== */
        $conn->begin_transaction();

        /* Insert into specific account table */
        switch($account_type){
            case 'student':
                $stmt = $conn->prepare("
                    INSERT INTO student_account
                        (student_id, first_name, middle_name, last_name, mobile, email, institute, course, level, section, guardian, guardian_mobile, photo)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssssssssssss",
                    $formValues['student_id'], $formValues['first_name'], $formValues['middle_name'], $formValues['last_name'],
                    $formValues['mobile'], $formValues['email'], $formValues['institute'], $formValues['course'],
                    $formValues['level'], $formValues['section'], $formValues['guardian'], $formValues['guardian_mobile'],
                    $photoFilename
                );
                break;

            case 'faculty':
                $stmt = $conn->prepare("
                    INSERT INTO faculty_account
                        (faculty_id, first_name, last_name, mobile, email, institute, photo)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssssss",
                    $formValues['faculty_id'], $formValues['first_name'], $formValues['last_name'],
                    $formValues['mobile'], $formValues['email'], $formValues['institute'], $photoFilename
                );
                break;

            case 'ccdu':
                $stmt = $conn->prepare("
                    INSERT INTO ccdu_account
                        (ccdu_id, first_name, last_name, mobile, email, photo)
                    VALUES
                        (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "ssssss",
                    $formValues['ccdu_id'], $formValues['first_name'], $formValues['last_name'],
                    $formValues['mobile'], $formValues['email'], $photoFilename
                );
                break;

            case 'security':
                $stmt = $conn->prepare("
                    INSERT INTO security_account
                        (security_id, first_name, last_name, mobile, email, photo)
                    VALUES
                        (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "ssssss",
                    $formValues['security_id'], $formValues['first_name'], $formValues['last_name'],
                    $formValues['mobile'], $formValues['email'], $photoFilename
                );
                break;
        }
        $stmt->execute();
        $stmt->close();

        /* Insert into accounts table */
        $stmtAcc = $conn->prepare("INSERT INTO accounts (id_number, email, password, account_type) VALUES (?, ?, ?, ?)");
        $stmtAcc->bind_param("ssss", $idNumber, $formValues['email'], $hashedPassword, $account_type);
        $stmtAcc->execute();
        $stmtAcc->close();

        /* Student QR generate & save + key record */
        $qrFilePath = null;
        if($account_type === 'student'){
            // ensure qr_key exists or insert a new one
            $qrKey = bin2hex(random_bytes(32)); // 64 hex
            // try insert; if duplicate due to unique(student_id), fetch existing
            $insQR = $conn->prepare("INSERT IGNORE INTO student_qr_keys (student_id, qr_key) VALUES (?, ?)");
            $insQR->bind_param("ss", $idNumber, $qrKey);
            $insQR->execute();
            $affected = $conn->affected_rows;
            $insQR->close();

            if($affected === 0){
                $sel = $conn->prepare("SELECT qr_key FROM student_qr_keys WHERE student_id = ? LIMIT 1");
                $sel->bind_param("s", $idNumber);
                $sel->execute();
                $row = $sel->get_result()->fetch_assoc();
                if(!empty($row['qr_key'])){
                    $qrKey = $row['qr_key'];
                }
                $sel->close();
            }

            // Build absolute URL to resolver at app root
            $qrURL = app_base_url() . '/qr.php?k=' . urlencode($qrKey);

            // Generate SVG
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                'scale'      => 6,
                'eccLevel'   => QRCode::ECC_L,
            ]);
            $svg = (new QRCode($options))->render($qrURL);

            // Save as project_root/uploads/qrcodes/{student_id}.svg
            $qrDir = $appRoot . '/uploads/qrcodes';
            if(!is_dir($qrDir) && !mkdir($qrDir, 0777, true)){
                throw new RuntimeException('⚠️ Failed to create QR directory.');
            }
            $qrFilePath = $qrDir . '/' . $idNumber . '.svg';
            if(file_put_contents($qrFilePath, $svg) === false){
                throw new RuntimeException('⚠️ Failed to save QR file.');
            }
        }

        // All good
        $conn->commit();
        $flashMsg = "✅ Account added successfully!";

        // Send welcome email (post-commit, non-blocking)
        try{
            $mail = moralmatrix_mailer();
            if(method_exists($mail, 'isHTML')) $mail->isHTML(true);

            $toEmail = $formValues['email'];
            $toName  = trim(($formValues['first_name'] ?? '').' '.($formValues['last_name'] ?? '')) ?: $toEmail;
            $mail->addAddress($toEmail, $toName);

            $idLabel = [
                'student'  => 'Student ID',
                'faculty'  => 'Faculty ID',
                'ccdu'     => 'CCDU ID',
                'security' => 'Security ID'
            ][$account_type] ?? 'ID';

            $loginUrl = app_base_url() . '/login.php';

            $html = '
                <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;line-height:1.5">
                    <h2>Welcome, '.htmlspecialchars($toName).'</h2>
                    <p>Your account has been created.</p>
                    <p><strong>'.$idLabel.':</strong> '.htmlspecialchars($idNumber).'</p>
                    <p>Sign in here: <a href="'.htmlspecialchars($loginUrl).'">'.htmlspecialchars($loginUrl).'</a></p>'.
                    ($account_type==='student' ? '<p>Your QR code is attached (SVG).</p>' : '').
                '</div>';

            $mail->Subject = 'Welcome to MoralMatrix';
            $mail->Body    = $html;
            $mail->AltBody = strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", $html));

            if($account_type === 'student' && $qrFilePath && is_file($qrFilePath)){
                $mail->addAttachment($qrFilePath);
            }
            @$mail->send();
        } catch(Throwable $mailErr){
            error_log('Welcome email error: '.$mailErr->getMessage());
        }

        // Clear form
        foreach($formValues as $k => $v){ $formValues[$k] = ''; }

    } catch(Throwable $e){
        // Rollback if in tx
        if($conn->errno === 0){ /* nothing */ }
        try { $conn->rollback(); } catch(Throwable $ignore){}
        $msg = $e->getMessage();
        // If this was a mysqli exception, prefer its message
        $errorMsg = $msg ?: "⚠️ Something went wrong while adding the account.";
    }
}

$conn->close();

// Generate a default password for UI if empty
if (empty($formValues['password'])) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
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

<main class="au-wrap">
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

    <!-- Account type chooser (UI only) -->
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
        // Keep the chosen type in hidden input of the shown form (for persistence in $formValues)
        const inputs = document.querySelectorAll('input[name="account_type"]');
        inputs.forEach(i => i.value = selected || i.value);
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
