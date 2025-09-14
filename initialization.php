<?php

include __DIR__ . '/config.php';

$servername = $database_settings['servername'];
$username = $database_settings['username'];
$password = $database_settings['password'];
$dbname = $database_settings['dbname'];

// First connect without database to create it
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

// Check if database exists
$result = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($result && $result->num_rows > 0) {
    header("Location: login.php");
    exit();
}

// Create database if not existing
$sqlCreateDatabase = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sqlCreateDatabase) === TRUE) {
    // Database created
} else {
    die("Error creating database: " . $conn->error);
}

$conn->close();

// Now connect to the created database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

function handleQueryError($sql, $conn) {
    die("Error executing query: " . $sql . "<br>" . $conn->error);
}

// Accounts Table
$sqlCreateLoginSchema = "CREATE TABLE IF NOT EXISTS accounts (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL UNIQUE,
    account_type ENUM('super_admin', 'administrator', 'ccdu', 'faculty', 'student', 'security') NOT NULL
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreateLoginSchema) === FALSE) {
    handleQueryError($sqlCreateLoginSchema, $conn);
}

// Super Admin Table
$sqlCreateSuperAdminSchema = "CREATE TABLE IF NOT EXISTS super_admin (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP    
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreateSuperAdminSchema) === FALSE) {
    handleQueryError($sqlCreateSuperAdminSchema, $conn);
}

// Faculty table
$sqlCreateFacultyAccountSchema = "CREATE TABLE IF NOT EXISTS faculty_account (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id VARCHAR(50) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(50) NOT NULL UNIQUE,
    photo BLOB,
    institute VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreateFacultyAccountSchema) === FALSE) {
    handleQueryError($sqlCreateFacultyAccountSchema, $conn);

}

// CCDU table
$sqlCreateCcduAccountSchema = "CREATE TABLE IF NOT EXISTS ccdu_account (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    ccdu_id VARCHAR(50) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL UNIQUE, 
    photo BLOB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreateCcduAccountSchema) === FALSE) {
    handleQueryError($sqlCreateCcduAccountSchema, $conn);

}

// Security table
$sqlCreateSecurityAccountSchema = "CREATE TABLE IF NOT EXISTS security_account (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    security_id VARCHAR(50) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(50) NOT NULL UNIQUE,
    photo BLOB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreateSecurityAccountSchema) === FALSE) {
    handleQueryError($sqlCreateSecurityAccountSchema, $conn);

}

// Student table
$sqlCreateStudentAccountSchema = "CREATE TABLE IF NOT EXISTS student_account (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(50) NOT NULL UNIQUE,
    photo BLOB,
    institute VARCHAR(255),
    course VARCHAR(255),
    level INT,
    section VARCHAR(50),
    guardian VARCHAR(50),
    guardian_mobile VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreateStudentAccountSchema) === FALSE) {
    handleQueryError($sqlCreateStudentAccountSchema, $conn);
}

// Admin table
$sqlCreateAdminAccountSchema = "CREATE TABLE IF NOT EXISTS admin_account (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(50) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(50) NOT NULL UNIQUE,
    photo BLOB,
    f_create VARCHAR(2),
    f_update VARCHAR(2),
    f_delete VARCHAR(2),
    s_create VARCHAR(2),
    s_update VARCHAR(2),
    s_delete VARCHAR(2),
    a_create VARCHAR(2),
    a_update VARCHAR(2),
    a_delete VARCHAR(2),
    c_create VARCHAR(2),
    c_update VARCHAR(2),
    c_delete VARCHAR(2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreateAdminAccountSchema) === FALSE) {
    handleQueryError($sqlCreateAdminAccountSchema, $conn);
}

$sqlCreateStudentViolationSchema = "CREATE TABLE IF NOT EXISTS student_violation (
    violation_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    offense_category ENUM('light','moderate','grave') NOT NULL,
    offense_type VARCHAR(50) NOT NULL,
    offense_details TEXT NOT NULL,
    description TEXT NOT NULL,
    photo BLOB,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_by VARCHAR(50) NOT NULL,
    submitter_role ENUM('faculty', 'ccdu', 'security') NOT NULL,
    reviewed_by VARCHAR(50) NULL,
    reviewd_at DATETIME NULL,
    review_notes TEXT NULL,
    reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_violation_status             ON student_violation (status, reported_at);
    INDEX idx_violation_student_status     ON student_violation (student_id, status);
    INDEX idx_violation_submitter          ON student_violation (submitted_by, status);
    INDEX idx_violation_student (student_id),
    CONSTRAINT fk_violation_student
        FOREIGN KEY (student_id)
        REFERENCES student_account (student_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if($conn->query($sqlCreateStudentViolationSchema) === FALSE ){
    handleQueryError($sqlCreateStudentViolationSchema, $conn);
}


$sqlCreateViolationDetailsSchema = "CREATE TABLE IF NOT EXISTS violation_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    violation_id INT NOT NULL,
    offense_code VARCHAR(100) NOT NULL,
    offense_label VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_violation_details_violation
        FOREIGN KEY (violation_id)
        REFERENCES student_violation(violation_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if($conn->query($sqlCreateViolationDetailsSchema) === FALSE ){
    handleQueryError($sqlCreateViolationDetailsSchema, $conn);
}


$sqlCreateStudentQrKeysSchema = "CREATE TABLE IF NOT EXISTS student_qr_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR (50) NOT NULL,
    qr_key CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked TINYINT(1) DEFAULT 0,
    CONSTRAINT fk_qr_student
        FOREIGN KEY (student_id)
        REFERENCES student_account(student_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if($conn->query($sqlCreateStudentQrKeysSchema) === FALSE ){
    handleQueryError($sqlCreateStudentQrKeysSchema, $conn);
}

$sqlIdx = "CREATE INDEX idx_qr_student_id ON student_qr_keys (student_id)";
if ($conn->query($sqlIdx) === FALSE && $conn->errno != 1061) { // 1061 = duplicate key name
    handleQueryError($sqlIdx, $conn);
}

$sqlCreateValidatorAccountSchema = "CREATE TABLE IF NOT EXISTS validator_account (
    validator_id INT AUTO_INCREMENT PRIMARY KEY,
    v_username VARCHAR(50) NOT NULL UNIQUE,
    v_password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    active TINYINT (1) DEFAULT 1  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreateValidatorAccountSchema) === FALSE){
    handleQueryError($sqlCreateValidatorAccountSchema, $conn);
}

$sqlCreateValidatorStudentAssignmentSchema = " CREATE TABLE IF NOT EXISTS validator_student_assignment(
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    validator_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ends_at DATETIME NULL,
    notes VARCHAR(255) NULL
    UNIQUE KEY uniq_validator_student (validator_id, student_id),
    INDEX idx_validator (validator_id),
    INDEX idx_student (student_id),
    FOREIGN KEY (validator_id) REFERENCES validator_account(validator_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES student_account(student_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreateValidatorStudentAssignmentSchema) === FALSE){
    handleQueryError($sqlCreateValidatorStudentAssignmentSchema, $conn);
}

$sqlCreateCommunityServiceEvidenceSchema = "CREATE TABLE IF NOT EXISTS community_service_evidence (
    evidence_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    validator_id INT NOT NULL,
    photo BLOB,
    hours_completed INT NOT NULL,
    performance_rating ENUM('excellent', 'good', 'Fair', 'Poor') NOT NULL,
    remarks TEXT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_account(student_id) ON DELETE CASCADE,
    FOREIGN KEY (validator_id) REFERENCES validator_account(validator_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sqlCreateCommunityServiceEvidenceSchema) === FALSE ){
    handleQueryError($sqlCreateCommunityServiceEvidenceSchema, $conn);
}



// Redirect to admin creation page
header("Location: create_admin_account.php");
exit();

$conn->close();
?>
