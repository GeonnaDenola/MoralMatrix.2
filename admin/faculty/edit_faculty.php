<?php
include '../../includes/admin_header.php';
include '../../config.php';

if(!isset($_GET['id'])){ die("No faculty ID provided."); }
$id = intval($_GET['id']);

$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error){ die("Connection failed: " .$conn->connect_error); }

$stmt = $conn->prepare("
  SELECT faculty_id, first_name, last_name, mobile, email, photo, institute
  FROM faculty_account
  WHERE record_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result  = $stmt->get_result();
$faculty = $result->fetch_assoc();
$stmt->close();
$conn->close();

if(!$faculty){ die("Faculty not found."); }

/* If DB stores only filename, prefix your uploads dir:
   $photoUrl = !empty($faculty['photo']) ? '/MoralMatrix/uploads/faculty/' . $faculty['photo'] : '';
   If it already stores a full/relative path or URL, keep as is: */
$photoUrl = !empty($faculty['photo']) ? htmlspecialchars($faculty['photo']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Faculty</title>

  <!-- Your global styles (optional) -->

  <!-- Page styles -->
  <link rel="stylesheet" href="faculty-edit.css">
</head>
<body>

  <div class="top-actions">
    <a href="../dashboard.php"><button class="btn" type="button">Return to Dashboard</button></a>
  </div>

  <div id="facultyForm" class="mm-form-card">
    <h3>Edit Faculty Account</h3>

    <form action="update_faculty.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="record_id" value="<?php echo $id; ?>">

      <label>ID Number:</label>
      <input
        type="text"
        name="faculty_id"
        value="<?php echo htmlspecialchars($faculty['faculty_id']); ?>"
        maxlength="9"
        title="Format: YYYY-NNNN (e.g. 2023-0001)"
        pattern="^[0-9]{4}-[0-9]{4}$"
        oninput="this.value = this.value.replace(/[^0-9-]/g, '')"
        required
      >

      <label>First Name:</label>
      <input type="text" name="first_name" value="<?php echo htmlspecialchars($faculty['first_name']); ?>" required>

      <label>Last Name:</label>
      <input type="text" name="last_name" value="<?php echo htmlspecialchars($faculty['last_name']); ?>" required>

      <label>Mobile:</label>
      <input
        type="text"
        name="mobile"
        value="<?php echo htmlspecialchars($faculty['mobile']); ?>"
        maxlength="11"
        placeholder="09XXXXXXXXX"
        pattern="^09[0-9]{9}$"
        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
        required
      >

      <label>Email:</label>
      <input type="email" name="email" value="<?php echo htmlspecialchars($faculty['email']); ?>" required>

      <label>Institute:</label>
      <select name="institute" required>
        <option value="">-- Select --</option>
        <option value="IBCE" <?php echo $faculty['institute']=='IBCE'?'selected':''; ?>>Institute of Business and Computing Education</option>
        <option value="IHTM" <?php echo $faculty['institute']=='IHTM'?'selected':''; ?>>Institute of Hospitality Management</option>
        <option value="IAS"  <?php echo $faculty['institute']=='IAS'?'selected':'';  ?>>Institute of Arts and Sciences</option>
        <option value="ITE"  <?php echo $faculty['institute']=='ITE'?'selected':'';  ?>>Institute of Teaching Education</option>
      </select>

      <!-- Photo row (preview left, file input right) -->
      <label>Photo:</label>
      <div class="photo-row">
        <img
          id="facultyPreview"
          src="<?php echo $photoUrl; ?>"
          alt="Photo Preview"
          style="display: <?php echo $photoUrl ? 'block' : 'none'; ?>;"
        >
        <input
          id="photo"
          type="file"
          name="photo"
          accept="image/*"
          onchange="previewPhoto(this,'facultyPreview')"
        >
      </div>

      <button type="submit">Update Faculty Information</button>
    </form>
  </div>

  <script>
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

    (function () {
    const sel = `
      #facultyForm input[type="text"],
      #facultyForm input[type="email"],
      #facultyForm input[type="file"],
      #facultyForm select
    `;
    const fields = document.querySelectorAll(sel);

    function isFilled(el) {
      if (el.type === 'file') return el.files && el.files.length > 0;
      return !!el.value && el.value.trim() !== '';
    }
    function sync(el){ el.classList.toggle('filled', isFilled(el)); }

    fields.forEach(el => {
      // initial (for pre-filled forms)
      sync(el);
      // inputs update while typing
      el.addEventListener('input', () => sync(el));
      // selects & files update on change
      el.addEventListener('change', () => sync(el));
    });
  })();
  </script>
</body>
</html>
