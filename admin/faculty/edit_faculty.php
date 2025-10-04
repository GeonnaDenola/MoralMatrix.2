<?php
include '../../includes/admin_header.php';
include '../../config.php';

if (!isset($_GET['id'])) { die("No faculty ID provided."); }
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

$photoUrl = !empty($faculty['photo']) ? htmlspecialchars($faculty['photo']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Faculty</title>
   <link rel="stylesheet" href="../../css/edit_common.css">
</head>
<body>

<main class="content">
  <section id="facultyForm" class="form-container" role="region" aria-labelledby="formTitle">
    <div class="form-header">
      <h2 id="formTitle">Edit Faculty Account</h2>
      <a class="back-link" href="../dashboard.php" aria-label="Return to Dashboard">← Back to Dashboard</a>
    </div>

    <form action="update_faculty.php" method="POST" enctype="multipart/form-data" class="form-grid" novalidate>
      <input type="hidden" name="record_id" value="<?php echo $id; ?>">

      <!-- ID Number -->
      <div class="field">
        <label for="faculty_id">ID Number</label>
        <input
          type="text"
          id="faculty_id"
          name="faculty_id"
          value="<?php echo htmlspecialchars($faculty['faculty_id']); ?>"
          maxlength="9"
          title="Format: YYYY-NNNN (e.g. 2023-0001)"
          pattern="^[0-9]{4}-[0-9]{4}$"
          inputmode="numeric"
          placeholder="0000-0000"
          oninput="this.value = this.value.replace(/[^0-9-]/g, '')"
          required
        >
        <small class="hint">Format: <code>YYYY-NNNN</code></small>
      </div>

      <!-- First Name -->
      <div class="field">
        <label for="first_name">First Name</label>
        <input type="text" id="first_name" name="first_name"
               value="<?php echo htmlspecialchars($faculty['first_name']); ?>" required>
      </div>

      <!-- Last Name -->
      <div class="field">
        <label for="last_name">Last Name</label>
        <input type="text" id="last_name" name="last_name"
               value="<?php echo htmlspecialchars($faculty['last_name']); ?>" required>
      </div>

      <!-- Mobile -->
      <div class="field">
        <label for="mobile">Mobile</label>
        <input
          type="text"
          id="mobile"
          name="mobile"
          value="<?php echo htmlspecialchars($faculty['mobile']); ?>"
          maxlength="11"
          placeholder="09XXXXXXXXX"
          pattern="^09[0-9]{9}$"
          inputmode="numeric"
          oninput="this.value = this.value.replace(/[^0-9]/g, '')"
          required
        >
      </div>

      <!-- Email -->
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email"
               value="<?php echo htmlspecialchars($faculty['email']); ?>" required>
      </div>

      <!-- Institute -->
      <div class="field">
        <label for="institute">Institute</label>
        <select id="institute" name="institute" required>
          <option value="">-- Select --</option>
          <option value="IBCE" <?php echo $faculty['institute']=='IBCE'?'selected':''; ?>>Institute of Business and Computing Education</option>
          <option value="IHTM" <?php echo $faculty['institute']=='IHTM'?'selected':''; ?>>Institute of Hospitality Management</option>
          <option value="IAS"  <?php echo $faculty['institute']=='IAS'?'selected':'';  ?>>Institute of Arts and Sciences</option>
          <option value="ITE"  <?php echo $faculty['institute']=='ITE'?'selected':'';  ?>>Institute of Teaching Education</option>
        </select>
      </div>

      <!-- Photo -->
      <div class="field full">
        <label>Photo</label>
        <div class="photo-row">
          <img
            id="facultyPreview"
            src="<?php echo $photoUrl; ?>"
            alt="Photo Preview"
            style="display: <?php echo $photoUrl ? 'block' : 'none'; ?>;"
          >
          <div class="file-input">
            <input id="photo" type="file" name="photo" accept="image/*">
            <label for="photo" class="file-button">Choose Photo</label>
            <div id="file-name" class="file-name">JPG or PNG, up to 3MB</div>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="actions full">
        <button type="submit" class="btn_submit">Update Faculty Information</button>
      </div>
    </form>
  </section>
</main>

<script>
  // Preview + filename
  const fileInput = document.getElementById('photo');
  const fileName  = document.getElementById('file-name');
  const preview   = document.getElementById('facultyPreview');

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      const file = this.files && this.files[0] ? this.files[0] : null;
      if (file) {
        fileName.textContent = file.name;
        const reader = new FileReader();
        reader.onload = e => {
          preview.src = e.target.result;
          preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
      } else {
        fileName.textContent = 'JPG or PNG, up to 3MB';
      }
    });
  }
</script>
</body>
</html>
