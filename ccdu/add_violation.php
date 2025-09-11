<?php
include '../config.php';
include '../includes/header.php';
include 'page_buttons.php';

$studentId = $_GET['student_id'] ?? '';
if (!$studentId) {
    echo "<p>No student selected!</p>";
    exit;
}

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ========= INSERT HANDLER ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_id       = $_POST['student_id']       ?? '';
    $offense_category = $_POST['offense_category'] ?? '';
    $offense_type     = $_POST['offense_type']     ?? '';
    $description      = $_POST['description']      ?? '';

    if (!$student_id || !$offense_category || !$offense_type) {
        die("Missing required fields.");
    }

    // Initialize $picked to avoid undefined variable warnings
    $detailGroups = [
        'id_offense','uniform_offense','civilian_offense','accessories_offense',
        'conduct_offense','gadget_offense','acts_offense',
        'substance_offense','integrity_offense','violence_offense',
        'property_offense','threats_offense'
    ];
    $picked = []; 
    foreach ($detailGroups as $g) {
        if (!empty($_POST[$g]) && is_array($_POST[$g])) {
            $picked = array_merge($picked, $_POST[$g]);
        }
    }

    $offense_details = $picked ? json_encode($picked, JSON_UNESCAPED_UNICODE) : null;

    $photo = null;
    if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $photo = file_get_contents($_FILES['photo']['tmp_name']);
    }

    $sql = "INSERT INTO student_violation
            (student_id, offense_category, offense_type, offense_details, description, photo)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmtIns = $conn->prepare($sql);
    if (!$stmtIns) die("Prepare failed: ".$conn->error);

    $null = NULL;
    $stmtIns->bind_param("sssssb",
        $student_id, $offense_category, $offense_type,
        $offense_details, $description, $null
    );
    if ($photo !== null) {
        $stmtIns->send_long_data(5, $photo);
    }

    if (!$stmtIns->execute()) {
        die("Insert failed: ".$stmtIns->error);
    }
    $stmtIns->close();

    header("Location: view_student.php?student_id=" . urlencode($student_id) . "&saved=1");
    exit;
}  // <-- THIS closes the POST handler
/* ========= END INSERT HANDLER ========= */

// Fetch student data AFTER handling POST
$sql = "SELECT * FROM student_account WHERE student_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
</head>
<body>
    <div class="profile-container">
  <?php if($student): ?>
      <div class="profile">
          <img src="<?= !empty($student['photo']) ? '../admin/uploads/'.$student['photo'] : 'placeholder.png' ?>" alt="Profile">
          <p><strong> <?= $student['student_id'] ?></strong></p>
          <p><strong> <?= $student['first_name'] . " " . $student['middle_name'] . " " . $student['last_name'] ?></strong></p>
          <p><strong><?= $student['course']. " - ".  $student['level'].$student['section']?></strong></p>
      </div>
  <?php else: ?>
      <p>Student not found.</p>
  <?php endif; ?>

  <h3>Add Violation</h3><br>

    <label>Offense Category: </label>
    <select id ="offense_category" onchange="toggleForms()" required>
        <option value="">--SELECT--</option>
        <option value="light">Light</option>
        <option value="moderate">Moderate</option>
        <option value="grave">Grave</option>
    </select>

  <!--LIGHT-->   
    <div id="lightForm" class="form-container">
        <form method="POST" enctype="multipart/form-data">

            <p>Light Offenses</p>

            <input type="hidden" name="offense_category" value="light">
             <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>">
        
            <select id="lightOffenses" name="offense_type">
                <option value="">--Select--</option>
                <option value="id">ID</option>
                <option value="uniform">Dress Code (Uniform)</option>
                <option value="civilian">Reveaing Clothes (Civilian Attire)</option>
                <option value="accessories">Accessories</option>
            </select>
                
                <div id = "light_idCheckbox" style="display:none">
                    <label><input type="checkbox" name="id_offense[]" value="no_id">No ID</label>
                    <label><input type="checkbox" name="id_offense[]" value="burrowed">Burrowed ID</label>
                </div>

                <div id = "light_uniformCheckbox" style="display:none">
                    <label><input type="checkbox" name="uniform_offense[]" value="socks">Socks</label>
                    <label><input type="checkbox" name="uniform_offense[]" value="skirt">Skirt</label>
                </div>

                <div id = "light_civilianCheckbox" style="display:none">
                    <label><input type="checkbox" name="civilian_offense[]" value="crop_top">Crop Top</label>
                    <label><input type="checkbox" name="civilian_offense[]" value="sando">Sando</label>
                </div>

                <div id = "light_accessoriesCheckbox" style="display:none">
                    <label><input type="checkbox" name="accessories_offense[]" value="piercings">Piercing/s</label>
                    <label><input type="checkbox" name="accessories_offense[]" value="hair_color">Loud Hair Color</label>
                </div><br><br>

                <label>Report Description: </label><br>
                <input type="text" id ="description" name="description"><br><br>

                <label>Attach Photo:</label>
                <input type="file" name="photo" accept="image/*">

                <br>
                <button type="submit">Add Violation</button>
        </form>
    </div>

     <!--MODERATE-->   
    <div id="moderateForm" class="form-container">
        <form method="POST" enctype="multipart/form-data">

            <p>Moderate Offenses</p>

            <input type="hidden" name="offense_category" value="moderate">
             <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>">

            <select id="moderateOffenses" name="offense_type">
                <option value="">--Select--</option>
                <option value="improper_conduct">Improper Language & Conduct</option>
                <option value="gadget_misuse">Gadget Misuse</option>
                <option value="unauthorized_acts">Unauthorized Acts</option>

            </select>
                
                <div id = "moderate_improper_conductCheckbox" style="display:none">
                    <label><input type="checkbox" name="conduct_offense[]" value="vulgar">Use of curses and vulgar words</label>
                    <label><input type="checkbox" name="conduct_offense[]" value="rough_behavior">Roughness in behavior</label>
                </div>

                <div id = "moderate_gadget_misuseCheckbox" style="display:none">
                    <label><input type="checkbox" name="gadget_offense[]" value="cp_classes">Use of cellular phones during classes</label>
                    <label><input type="checkbox" name="gadget_offense[]" value="gadgets_functions">Use of gadgets during academic functions</label>
                </div>

                <div id = "moderate_unauthorized_actsCheckbox" style="display:none">
                    <label><input type="checkbox" name="acts_offense[]" value="illegal_posters">Posting posters, streamers, banners without approval</label>
                    <label><input type="checkbox" name="acts_offense[]" value="pda">PDA (Public Display of Affection)</label>
                </div><br><br>

                <label>Report Description: </label><br>
                <input type="text" id ="description" name="description"><br><br>

                <label>Attach Photo:</label>
                <input type="file" name="photo" accept="image/*">

                <br>
                <button type="submit">Add Violation</button>
        </form>
    </div>

     <!--GRAVE-->   
    <div id="graveForm" class="form-container">
        <form method="POST" enctype="multipart/form-data">

            <p>Grave Offenses</p>

            <input type="hidden" name="offense_category" value="grave">
             <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>">

            <select id="graveOffenses" name="offense_type">
                <option value="">--Select--</option>
                <option value="substance_addiction">Substance & Addiction</option>
                <option value="integrity_dishonesty">Academic Integrity & Dishonesty</option>
                <option value="violence_misconduct">Violence & Misconduct</option>
                <option value="property_theft">Property & Theft</option>
                <option value="threats_disrespect">Threats & Disrespect</option>

            </select>
                
                <div id = "grave_substance_addictionCheckbox" style="display:none">
                    <label><input type="checkbox" name="substance_offense[]" value="smoking">Smoking</label>
                    <label><input type="checkbox" name="substance_offense[]" value="gambling">Gambling</label>
                </div>

                <div id = "grave_integrity_dishonestyCheckbox" style="display:none">
                    <label><input type="checkbox" name="integrity_offense[]" value="forgery">Forgery, falsifying, tampering of documents</label>
                    <label><input type="checkbox" name="integrity_offense[]" value="dishonesty">Dishonesty</label>
                </div>

                <div id = "grave_violence_misconductCheckbox" style="display:none">
                    <label><input type="checkbox" name="violence_offense[]" value="assault">Assault</label>
                    <label><input type="checkbox" name="violence_offense[]" value="hooliganism">Hooliganism</label>
                </div>

                <div id = "grave_property_theftCheckbox" style="display:none">
                    <label><input type="checkbox" name="property_offense[]" value="theft">Theft</label>
                    <label><input type="checkbox" name="property_offense[]" value="destruction_of_property">Willful destruction of school property</label>
                </div>

                <div id = "grave_threats_disrespectCheckbox" style="display:none">
                    <label><input type="checkbox" name="threats_offense[]" value="firearms">Carrying deadly weapons, firearms, explosives</label>
                    <label><input type="checkbox" name="threats_offense[]" value="disrespect">Offensive words / disrespectful deeds</label>
                </div><br><br>

                <label>Report Description: </label><br>
                <input type="text" id ="description" name="description"><br><br>

            <label>Attach Photo:</label>
            <input type="file" name="photo" accept="image/*">

            <br>
            <button type="submit">Add Violation</button>
        </form>
    </div>

<script>
    window.onload = toggleForms;
        function toggleForms(){
            const selected = document.getElementById("offense_category").value;
            ['light', 'moderate', 'grave'].forEach(t=>{
                document.getElementById(t+'Form').style.display = (selected===t)?'block':'none';
            });
        }

    document.getElementById('lightOffenses').addEventListener('change', function() {
        // Hide all checkbox groups
        document.getElementById('light_idCheckbox').style.display = 'none';
        document.getElementById('light_uniformCheckbox').style.display = 'none';
        document.getElementById('light_civilianCheckbox').style.display = 'none';
        document.getElementById('light_accessoriesCheckbox').style.display = 'none';

        // Show the selected checkbox group
        var selected = this.value;
        if(selected) {
            var checkboxDiv = document.getElementById('light_' + selected + 'Checkbox');
            if(checkboxDiv) {
                checkboxDiv.style.display = 'block';
            }
        }
    });

    document.getElementById('moderateOffenses').addEventListener('change', function() {
        // Hide all checkbox groups
        document.getElementById('moderate_improper_conductCheckbox').style.display = 'none';
        document.getElementById('moderate_gadget_misuseCheckbox').style.display = 'none';
        document.getElementById('moderate_unauthorized_actsCheckbox').style.display = 'none';

        // Show the selected checkbox group
        var selected = this.value;
        if(selected) {
            var checkboxDiv = document.getElementById('moderate_' + selected + 'Checkbox');
            if(checkboxDiv) {
                checkboxDiv.style.display = 'block';
            }
        }
    });

    document.getElementById('graveOffenses').addEventListener('change', function() {
        // Hide all checkbox groups
        document.getElementById('grave_substance_addictionCheckbox').style.display = 'none';
        document.getElementById('grave_integrity_dishonestyCheckbox').style.display = 'none';
        document.getElementById('grave_violence_misconductCheckbox').style.display = 'none';
        document.getElementById('grave_property_theftCheckbox').style.display = 'none';
        document.getElementById('grave_threats_disrespectCheckbox').style.display = 'none';
        

        // Show the selected checkbox group
        var selected = this.value;
        if(selected) {
            var checkboxDiv = document.getElementById('grave_' + selected + 'Checkbox');
            if(checkboxDiv) {
                checkboxDiv.style.display = 'block';
            }
        }
    });

</script>

    

</body>
</html>