
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
        <select id="violationClass">
            <option value="">--SELECT--</option>
            <option value="light">Light</option>
            <option value="moderate">Moderate</option>
            <option value="grave">Grave</option>
        </select>

        <div id="formContainer">
        </div>
    </div>

<script>

    const studentId = '<?= $student_id ?>';

    document.getElementById('violationClass').addEventListener('change', function(){
        const level  = this.value;
        const container = document.getElementById('formContainer');

        if (!level){
            container.innerHTML = '';
            return;
        }

        fetch('violationForms/' + level + '_forms.php?student_id=' + studentId)
            .then(response => response.text())
            .then(html => container.innerHTML = html)
            .catch(err => container.innerHTML = '<p>Error loadnig form</p>');
    });
</script>
</body>
</html>