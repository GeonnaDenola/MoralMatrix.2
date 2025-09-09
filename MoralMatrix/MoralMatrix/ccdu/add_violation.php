
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

    <div id="violationForm">

        <label>Level of Violation: </label>
        <select>
            <option value="">--SELECT--<option>
            <option value="light">Light<option>
            <option value="moderate">Moderate<option>
            <option value="grave">Grave<option>
        </select>

        <div id="formContainer">
            <?php
                switch($student['classification']){
                    case '1':
                        include 'violationForms/';
                        break;
                }
            ?>
        </div>
    </div>
</body>
</html>