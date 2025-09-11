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
)";
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
    password VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP    
)";
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
    password VARCHAR(255) NOT NULL UNIQUE,
    photo BLOB,
    institute VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
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
)";
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
    password VARCHAR(255) NOT NULL UNIQUE,
    photo BLOB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
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
    password VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
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
    password VARCHAR(255) NOT NULL,
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
)";
if ($conn->query($sqlCreateAdminAccountSchema) === FALSE) {
    handleQueryError($sqlCreateAdminAccountSchema, $conn);
}

$sqlCreateStudentViolationSchema = "CREATE TABLE IF NOT EXISTS student_violation (
    violation_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    offense_category ENUM ('light', 'moderate', 'grave') NOT NULL,
    offense_type VARCHAR(50) NOT NULL,
    offense_details TEXT NOT NULL,
    description TEXT NOT NULL,
    photo BLOB,
    reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_account (student_id)
)";
if($conn->query($sqlCreateViolationTableSchema) === FALSE ){
    handleQueryError($sqlCreateViolationTableSchema, $conn);
}

$sqlCreateViolationDetailsSchema = "CREATE TABLE IF NOT EXISTS violation_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    violation_id INT NOT NULL,
    offense_code VARCHAR(100) NOT NULL,   
    offense_label VARCHAR(255) NOT NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (violation_id) REFERENCES violation_table(violation_id)
    ON DELETE CASCADE
)";
if($conn->query($sqlCreateViolationDetailsSchema) === FALSE ){
    handleQueryError($sqlCreateViolationDetailsSchema, $conn);
}


// Redirect to admin creation page
header("Location: create_admin_account.php");
exit();

$conn->close();
?>
