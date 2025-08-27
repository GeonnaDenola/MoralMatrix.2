<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=p, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <header>
        <div class="nav-left">
            <a href="">moral matrix</a>
        </div>

        <div class="nav-right">
            <form action="/MoralMatrix/logout.php" method="post">
                <button type="submit" name="logout">Logout</button>
            </form>
        </div>
    </header>

    <p>WELCOME ADMIN</p>

    <a href="add_users.php">
        <button>Add Users</button>
    </a>
</body>
</html>