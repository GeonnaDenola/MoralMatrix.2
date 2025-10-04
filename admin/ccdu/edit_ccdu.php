<?php
include '../../includes/admin_header.php';
include '../../config.php';

if(!isset($_GET['id'])){ die("No CCDU Staff ID provided."); }
$id = intval($_GET['id']);

$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);
if ($conn->connect_error){ die("Connection failed: " .$conn->connect_error); }

/* Fetch CCDU data */
$stmt = $conn->prepare("
  SELECT ccdu_id, first_name, last_name, mobile, email, photo
  FROM ccdu_account
  WHERE record_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$ccdu = $result->fetch_assoc();
$stmt->close();
$conn->close();

if(!$ccdu){ die("CCDU staff not found."); }

/* If the photo column is a filename, prefix your uploads dir:
   $photoUrl = !empty($ccdu['photo']) ? '/MoralMatrix/uploads/ccdu/' . $ccdu['photo'] : '';
   If it stores a path/URL already, keep as is: */
$photoUrl = !empty($ccdu['photo']) ? htmlspecialchars($ccdu['photo']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit CCDU Staff</title>

  <!-- Global (if you have one) -->
  <link rel="stylesheet" href="/MoralMatrix/css/global.css">
  <!-- Page CSS -->
  <link rel="stylesheet" href="ccdu-edit.css">
</head>
<body>

  <!-- Centered Return button -->
  <div class="top-actions">
    <a class="btn-return" href="../dashboard.php">Return to Dashboard</a>
  </div>

  <div id="ccduForm" class="mm-form-card">
    <h3>Edit CCDU Staff Information</h3>

    <form action="update_ccdu.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="record_id" value="<?php echo $id; ?>">

      <label>ID Number:</label>
      <input
        type="text"
        name="ccdu_id"
        value="<?php echo htmlspecialchars($ccdu['ccdu_id']); ?>"
        maxlength="9"
        title="Format: YYYY-NNNN (e.g. 2023-0001)"
        pattern="^[0-9]{4}-[0-9]{4}$"
        oninput="this.value = this.value.replace(/[^0-9-]/g, '')"
        required
      >

      <label>First Name:</label>
      <input type="text" name="first_name" value="<?php echo htmlspecialchars($ccdu['first_name']); ?>" required>

      <label>Last Name:</label>
      <input type="text" name="last_name" value="<?php echo htmlspecialchars($ccdu['last_name']); ?>" required>

      <label>Mobile:</label>
      <input
        type="text"
        name="mobile"
        value="<?php echo htmlspecialchars($ccdu['mobile']); ?>"
        maxlength="11"
        placeholder="09XXXXXXXXX"
        pattern="^09[0-9]{9}$"
        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
        required
      >

      <label>Email:</label>
      <input type="email" name="email" value="<?php echo htmlspecialchars($ccdu['email']); ?>" required>

      <!-- Photo row (preview left, uploader right) -->
      <label>Photo:</label>
      <div class="photo-row">
        <img
          id="ccduPreview"
          src="<?php echo $photoUrl; ?>"
          alt="Photo Preview"
          style="display: <?php echo $photoUrl ? 'block' : 'none'; ?>;"
        >
        <input
          id="photo"
          type="file"
          name="photo"
          accept="image/*"
          onchange="previewPhoto(this,'ccduPreview')"
        >
      </div>

      <button type="submit">Update CCDU Staff Information</button>
    </form>
  </div>

  <script>
    // Live preview for the uploader
    function previewPhoto(input, previewID){
      const preview = document.getElementById(previewID);
      const file = input.files && input.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (e) => {
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    }
  </script>
</body>
</html>
