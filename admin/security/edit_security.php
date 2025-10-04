<?php
include '../../includes/admin_header.php';
include '../../config.php';

if(!isset($_GET['id'])){ die("No Security personnel ID provided."); }
$id = intval($_GET['id']);

$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);

if ($conn->connect_error){ die("Connection failed: " .$conn->connect_error); }

$stmt = $conn->prepare("SELECT security_id, first_name, last_name, mobile, email, photo FROM security_account WHERE record_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$security = $result->fetch_assoc();
$stmt->close();
$conn->close();

if(!$security){ die("Security Personnel not found."); }

/* If DB stores only a filename, point this to your uploads dir, e.g.:
$photoUrl = !empty($security['photo']) ? '/MoralMatrix/uploads/security/' . htmlspecialchars($security['photo']) : '';
If it already stores a usable path/URL, keep as-is: */
$photoUrl = !empty($security['photo']) ? htmlspecialchars($security['photo']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Security Personnel</title>
  <link rel="stylesheet" href="/MoralMatrix/css/global.css">

  <style>
    /* Make sure the form shows even if global.css hides .form-container */
    #securityForm.form-container{ display:block !important; visibility:visible !important; opacity:1 !important; }

    /* Ensure the native file input is visible (some global styles hide it) */
    input[type="file"]{ display:inline-block !important; }

    /* Preview box */
    #securityPreview{
      width:120px; height:120px; object-fit:cover; border-radius:10px;
      background:#0f172a; border:1px solid #0b1220;
      display: <?php echo $photoUrl ? 'block' : 'none'; ?>;
    }

    .photo-row{ display:flex; align-items:center; gap:16px; }

    /* ---------- ADD: super overrides so #photo can't be hidden/covered ---------- */
    #securityForm .photo-row #photo{
      display: inline-block !important;
      visibility: visible !important;
      opacity: 1 !important;

      position: relative !important;   /* undo sr-only tricks */
      width: auto !important;
      height: auto !important;
      pointer-events: auto !important;
      clip: auto !important;
      clip-path: none !important;
      overflow: visible !important;

      z-index: 1000 !important;        /* above overlaps */
      transform: none !important;

      -webkit-appearance: auto !important;
      appearance: auto !important;

      outline: 1px dashed #6b7280 !important; /* temp debug, remove if you want */
    }
    /* prevent preview from capturing clicks if it overlaps */
    #securityForm .photo-row #securityPreview{
      pointer-events: none !important;
    }
    /* make native button clearly clickable */
    #securityForm .photo-row #photo::file-selector-button{
      padding: 8px 12px;
      border: 1px solid #0b1220;
      border-radius: 8px;
      cursor: pointer;
      font: inherit;
    }
    #securityForm .photo-row #photo::-webkit-file-upload-button{
      padding: 8px 12px;
      border: 1px solid #0b1220;
      border-radius: 8px;
      cursor: pointer;
      font: inherit;
    }
    /* ---------- /ADD ---------- */

    /* optional helper text style */
    .help{ font-size:12px; color:#6b7280; margin:6px 0 14px; }
  </style>
</head>
<body>
  <a href="../dashboard.php">
    <button type="button">Return to Dashboard</button>
  </a><br>

  <div id="securityForm" class="form-container">
    <h3>Edit Security Personnel Information</h3>

    <form method="POST" action="update_security.php" enctype="multipart/form-data">
      <input type="hidden" name="record_id" value="<?php echo $id; ?>">

      <label>ID Number:</label>
      <input
        type="text"
        name="security_id"
        value="<?php echo htmlspecialchars($security['security_id']); ?>"
        maxlength="9"
        title="Format: YYYY-NNNN (e.g. 2023-0001)"
        pattern="^[0-9]{4}-[0-9]{4}$"
        oninput="this.value = this.value.replace(/[^0-9-]/g, '')"
        required
      ><br>

      <label>First Name:</label>
      <input type="text" name="first_name" value="<?php echo htmlspecialchars($security['first_name']); ?>" required><br>

      <label>Last Name:</label>
      <input type="text" name="last_name" value="<?php echo htmlspecialchars($security['last_name']); ?>" required><br>

      <label>Mobile:</label>
      <input
        type="text"
        name="mobile"
        value="<?php echo htmlspecialchars($security['mobile']); ?>"
        maxlength="11"
        placeholder="09XXXXXXXXX"
        pattern="^09[0-9]{9}$"
        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
        required
      ><br>

      <label>Email:</label>
      <input type="email" name="email" value="<?php echo htmlspecialchars($security['email']); ?>" required><br>

      <label>Photo:</label>
      <div class="photo-row">
        <img id="securityPreview" src="<?php echo $photoUrl; ?>" alt="Photo Preview">
        <input id="photo" type="file" name="photo" accept="image/*" onchange="previewPhoto(this,'securityPreview')">
      </div>

      <button type="submit">Update Security Personnel Information</button>
    </form>
  </div>

  <script>
    function previewPhoto(input, previewID){
      const preview = document.getElementById(previewID);
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          preview.src = e.target.result;
          preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
      }
    }
  </script>
</body>
</html>
