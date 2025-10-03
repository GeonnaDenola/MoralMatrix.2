<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../lib/email_lib.php'; // moralmatrix_mailer()
require __DIR__ . '/../../config.php';       // DB config

function createAccountAndSendEmail($accountType, $data) {
    global $database_settings;

    $conn = new mysqli(
        $database_settings['servername'],
        $database_settings['username'],
        $database_settings['password'],
        $database_settings['dbname']
    );
    if ($conn->connect_error) {
        die("DB connection failed: " . $conn->connect_error);
    }

    // Generate temp password
    $temp_password   = bin2hex(random_bytes(4));
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

    // Map tables
    $table_map = [
        'student'  => 'student_account',
        'faculty'  => 'faculty_account',
        'security' => 'security_account',
        'ccdu'     => 'ccdu_account',
        'admin'    => 'admin_account'
    ];

    // Map ID column names
    $columns_map = [
        'student'  => 'student_id',
        'faculty'  => 'faculty_id',
        'security' => 'security_id',
        'ccdu'     => 'ccdu_id',
        'admin'    => 'admin_id'
    ];

    if (!isset($table_map[$accountType])) {
        throw new Exception("Invalid account type");
    }

    $table   = $table_map[$accountType];
    $id_col  = $columns_map[$accountType];

    // Insert account
    $stmt = $conn->prepare("INSERT INTO {$table} 
        ({$id_col}, first_name, last_name, email, password) 
        VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "sssss",
        $data['id'], $data['first_name'], $data['last_name'], $data['email'], $hashed_password
    );

    if ($stmt->execute()) {
        // Send email
        try {
            $mail = moralmatrix_mailer();
            $mail->addAddress($data['email'], $data['first_name'] . " " . $data['last_name']);
            $mail->Subject = ucfirst($accountType) . " Account Created - Moral Matrix";
            $mail->Body    = "
                <h2>Welcome to Moral Matrix</h2>
                <p>Dear <strong>{$data['first_name']} {$data['last_name']}</strong>,</p>
                <p>Your <b>{$accountType}</b> account has been created.</p>
                <p><b>Temporary Password:</b> {$temp_password}</p>
                <p>Please log in and change your password immediately.</p>
            ";
            $mail->AltBody = "Hello {$data['first_name']},\nYour {$accountType} account was created.\nTemporary Password: {$temp_password}\nChange it after login.";

            $mail->send();
            echo ucfirst($accountType) . " account created & email sent!";
        } catch (Exception $e) {
            echo ucfirst($accountType) . " account created, but email failed: {$mail->ErrorInfo}";
        }
    } else {
        echo "Error creating account: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
