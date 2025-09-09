<?php
$studentId = $_GET['student_id'] ?? '';

if (!$studentId) { 
    echo "<p>No student selected!</p>"; 
    exit; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Violation</title>
</head>
<body>

<div class="formContainer">
    <p>Light Offense</p>
    <form id="lightForm" method="POST">

        <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">
        <input type="hidden" name="violation_level" value="light">

        <label>Date:</label>
        <input type="date" name="violationDate" value="<?= date('Y-m-d') ?>" required><br><br>

        <label>Nature of Offense:</label>
        <select id="selectCategory" name="category" onchange="displayCheckbox()" required>
            <option value="">--Select--</option>
            <option value="id">ID</option>
            <option value="dress_code">Dress Code</option>
            <option value="loitering">Loitering</option>
            <option value="unauthorized_use_of_facilities">Unauthorized use of campus facilities</option>
        </select><br><br>

        <!-- ID Offense -->
        <div id="idCheckbox" class="checkboxGroup" style="display: none;">
            <label><input type="checkbox" name="id_violation" value="no_id"> No ID</label><br>
            <label><input type="checkbox" name="id_violation" value="borrowed_id"> Borrowed ID</label>
        </div>

        <!-- Dress Code Offense -->
        <div id="dressCodeCheckbox" class="offense-specification" style="display: none;">
            <label><input type="checkbox" name="dressCode_violation" value="socks">Socks</label><br>
            <label><input type="checkbox" name="dressCode_violation" value="pants">Pants</label><br>
            <label><input type="checkbox" name="dressCode_violation" value="skirt">Skirt</label><br>
            <label><input type="checkbox" name="dressCode_violation" value="blouse">Blouse</label><br>
            <label><input type="checkbox" name="dressCode_violation" value="polo">Polo</label><br>
            <label><input type="checkbox" name="dressCode_violation" value="revealing_clothes">Revealing Clothes</label><br>
            <label><input type="checkbox" name="dressCode_violation" value="civilian_clothes">Civilian Clothes during Uniform days</label><br>
            <label>Others: <input type="text" name="dressCode_violation" placeholder="Specify"></label>
        </div>

        <br>
        <label>Description:</label>
        <input type="text" name="description"><br><br>

        <button type="submit">Add Violation</button>
    </form>
<script>
    function displayCheckbox(){
        var selectCategory = document.getElementById("selectCategory");
        var selectedValue = selectCategory.value;

        document.getElementByIdd("idCheckbox").style.display = "none";

        if (selectedValue === "id"){
            document.getElementById("idCheckbox").style.display = "block";
        } else if (selectedValue === "dress_code"){
            document.getElementById("dressCodeCheckbox").style.display = "block";
        }
    }
</script>
</div>


</body>
</html>
