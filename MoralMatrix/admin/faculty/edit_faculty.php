<?php

include '../../includes/header.php';
include '../../config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <a href="../dashboard.php">
        <button type="button">Return to Dashboard</button>
    </a><br>

    <h3>Edit Faculty Account</h3>

    <div id="facultyForm" class="form-container">
        <form action="" method="POST" enctype="multipart/form-data">

            <input type="hidden" name="account_type" value="faculty">

        </form>

    </div>
</body>
</html>