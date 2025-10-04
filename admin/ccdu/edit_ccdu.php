<?php
include '../../includes/admin_header.php';
include '../../config.php';

if (!isset($_GET['id'])) { die("No CCDU Staff ID provided."); }
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

if (!$ccdu) { die("CCDU staff not found."); }

$photoUrl = !empty($ccdu['photo']) ? htmlspecialchars($ccdu['photo']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit CCDU Staff</title>
   <link rel="stylesheet" href="../../css/edit_common.css">
</head>
<body>

<main class="content">
  <section id="ccduForm" class="form-container" role="region" aria-labelledby="formTitle">
    <div class="form-header">
      <h2 id="formTitle">Edit CCDU Staff</h2>
      <a class="back-link" href="../dashboard.php" aria-label="Return to Dashboard">← Back to Dashboard</a>
    </div>

    <form action="update_ccdu.php" method="POST" enctype="multipart/form-data" class="form-grid" novalidate>
      <input type="hidden" name="record_id" value="<?php echo $id; ?>">

      <!-- ID Number -->
      <div class="field">
        <label for="ccdu_id">ID Number</label>
        <input
          type="text"
          id="ccdu_id"
          name="ccdu_id"
          value="<?php echo htmlspecialchars($ccdu['ccdu_id']); ?>"
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
               value="<?php echo htmlspecialchars($ccdu['first_name']); ?>" required>
      </div>

      <!-- Last Name -->
      <div class="field">
        <label for="last_name">Last Name</label>
        <input type="text" id="last_name" name="last_name"
               value="<?php echo htmlspecialchars($ccdu['last_name']); ?>" required>
      </div>

      <!-- Mobile -->
      <div class="field">
        <label for="mobile">Mobile</label>
        <input
          type="text"
          id="mobile"
          name="mobile"
          value="<?php echo htmlspecialchars($ccdu['mobile']); ?>"
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
               value="<?php echo htmlspecialchars($ccdu['email']); ?>" required>
      </div>

      <!-- Photo -->
      <div class="field full">
        <label>Photo</label>
        <div class="photo-row">
          <img
            id="ccduPreview"
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
        <button type="submit" class="btn_submit">Update CCDU Staff Information</button>
      </div>
    </form>
  </section>
</main>

<script>
  // Preview + filename
  const fileInput = document.getElementById('photo');
  const fileName  = document.getElementById('file-name');
  const preview   = document.getElementById('ccduPreview');

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
