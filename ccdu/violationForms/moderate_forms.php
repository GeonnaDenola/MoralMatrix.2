<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <div class="formContainer" method="POST">
        <p>Moderate Offense</p>
        <form id="moderateForm">

        <input type="hidden" name="moderate" value="moderate">

        <label>Date: </label>
        <input name="date" name="violationDate" value="<?= date('Y-m-d'); ?>" required>

        </form>
    </div>

</body>
</html>