<?php
include '../../includes/admin_header.php';
include '../../config.php';

if (!isset($_GET['id'])) { die("No student ID provided."); }

$id = intval($_GET['id']);

$conn = new mysqli(
    $database_settings['servername'],
    $database_settings['username'],
    $database_settings['password'],
    $database_settings['dbname']
);

if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Fetch student data
$stmt = $conn->prepare("SELECT student_id, first_name, middle_name, last_name, mobile, email, institute, course, level, section, guardian, guardian_mobile, photo FROM student_account WHERE record_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$student) { die("Student not found."); }

// Build photo URL: if DB stores just a filename in `photo`, this points to your uploads dir
$photoUrl = !empty($student['photo']) ? '../uploads/' . htmlspecialchars($student['photo']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Student</title>
  <link rel="stylesheet" href="../../css/edit_common.css">
</head>
<body>

<!-- PAGE CONTENT WRAPPER (keeps the card centered to the right of your sidebar) -->
<main class="content">
  <section id="studentForm" class="form-container" role="region" aria-labelledby="formTitle">
    <div class="form-header">
      <h2 id="formTitle">Edit Student Information</h2>

      <a class="back-link" href="/moralmatrix/admin/dashboard.php" aria-label="Return to Dashboard">
        ← Back to Dashboard
      </a>
    </div>

    <form action="update_student.php" method="POST" enctype="multipart/form-data" class="form-grid" novalidate>
      <input type="hidden" name="account_type" value="student">
      <input type="hidden" name="record_id" value="<?php echo $id; ?>">

      <!-- Student ID -->
      <div class="field">
        <label for="student_id">Student ID</label>
        <input
          type="text"
          name="student_id"
          id="student_id"
          maxlength="9"
          inputmode="numeric"
          pattern="^[0-9]{4}-[0-9]{4}$"
          placeholder="0000-0000"
          oninput="this.value = this.value.replace(/[^0-9-]/g, '')"
          required
          value="<?php echo htmlspecialchars($student['student_id']); ?>"
        >
        <small class="hint">Format: <code>YYYY-NNNN</code></small>
      </div>

      <!-- First Name -->
      <div class="field">
        <label for="first_name">First Name</label>
        <input type="text" name="first_name" id="first_name" required
               value="<?php echo htmlspecialchars($student['first_name']); ?>">
      </div>

      <!-- Middle Name -->
      <div class="field">
        <label for="middle_name">Middle Name</label>
        <input type="text" name="middle_name" id="middle_name" required
               value="<?php echo htmlspecialchars($student['middle_name']); ?>">
      </div>

      <!-- Last Name -->
      <div class="field">
        <label for="last_name">Last Name</label>
        <input type="text" name="last_name" id="last_name" required
               value="<?php echo htmlspecialchars($student['last_name']); ?>">
      </div>

      <!-- Mobile -->
      <div class="field">
        <label for="mobile">Contact Number</label>
        <input
          type="text"
          name="mobile"
          id="mobile"
          maxlength="11"
          placeholder="09XXXXXXXXX"
          pattern="^09[0-9]{9}$"
          inputmode="numeric"
          oninput="this.value = this.value.replace(/[^0-9]/g, '')"
          required
          value="<?php echo htmlspecialchars($student['mobile']); ?>"
        >
      </div>

      <!-- Email -->
      <div class="field">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" required
               value="<?php echo htmlspecialchars($student['email']); ?>">
      </div>

      <!-- Institute -->
      <div class="field">
        <label for="institute">Institute</label>
        <select id="institute" name="institute" onchange="loadCourses()" required>
          <option value="">-- Select --</option>
          <option value="IBCE" <?php if($student['institute']=='IBCE') echo 'selected'; ?>>Institute of Business and Computing Education</option>
          <option value="IHTM" <?php if($student['institute']=='IHTM') echo 'selected'; ?>>Institute of Hospitality and Management</option>
          <option value="IAS"  <?php if($student['institute']=='IAS')  echo 'selected'; ?>>Institute of Arts and Sciences</option>
          <option value="ITE"  <?php if($student['institute']=='ITE')  echo 'selected'; ?>>Institute of Teaching Education</option>
        </select>
      </div>

      <!-- Course -->
      <div class="field">
        <label for="course">Course</label>
        <select id="course" name="course" required>
          <option value="<?php echo htmlspecialchars($student['course']); ?>" selected>
            <?php echo htmlspecialchars($student['course']); ?>
          </option>
        </select>
      </div>

      <!-- Year Level -->
      <div class="field">
        <label for="level">Year Level</label>
        <select id="level" name="level" required>
          <option value="">-- Select --</option>
          <option value="1" <?php if($student['level']==1) echo 'selected'; ?>>1st Year</option>
          <option value="2" <?php if($student['level']==2) echo 'selected'; ?>>2nd Year</option>
          <option value="3" <?php if($student['level']==3) echo 'selected'; ?>>3rd Year</option>
          <option value="4" <?php if($student['level']==4) echo 'selected'; ?>>4th Year</option>
        </select>
      </div>

      <!-- Section -->
      <div class="field">
        <label for="section">Section</label>
        <select id="section" name="section" required>
          <option value="">-- Select --</option>
          <option value="A" <?php if($student['section']=='A') echo 'selected'; ?>>A</option>
          <option value="B" <?php if($student['section']=='B') echo 'selected'; ?>>B</option>
          <option value="C" <?php if($student['section']=='C') echo 'selected'; ?>>C</option>
        </select>
      </div>

      <!-- Photo -->
      <div class="field full">
        <label>Profile Picture</label>
        <div class="photo-row">
          <img id="photoPreview" src="<?php echo $photoUrl; ?>" alt="Profile Picture">
          <div class="file-input">
            <input type="file" id="photo" name="photo" accept="image/*">
            <label for="photo" class="file-button">Choose Photo</label>
            <div id="file-name" class="file-name">JPG or PNG, up to 3MB</div>
          </div>
        </div>
      </div>

      <!-- Emergency Contact -->
      <div class="separator full" aria-hidden="true"></div>
      <h3 class="section-title full">Emergency Contact</h3>

      <div class="field">
        <label for="guardian">Guardian's Name</label>
        <input type="text" name="guardian" id="guardian" required
               value="<?php echo htmlspecialchars($student['guardian']); ?>">
      </div>

      <div class="field">
        <label for="guardian_mobile">Guardian's Contact Number</label>
        <input
          type="text"
          name="guardian_mobile"
          id="guardian_mobile"
          maxlength="11"
          placeholder="09XXXXXXXXX"
          pattern="^09[0-9]{9}$"
          inputmode="numeric"
          oninput="this.value = this.value.replace(/[^0-9]/g, '')"
          required
          value="<?php echo htmlspecialchars($student['guardian_mobile']); ?>"
        >
      </div>

      <!-- Submit -->
      <div class="actions full">
        <button type="submit" class="btn_submit">Update Student Information</button>
      </div>
    </form>
  </section>
</main>

<script>
  // Optional: populate courses dynamically based on institute
  function loadCourses() {
    // Keep your own list if you want. Leaving blank because options vary per school.
  }

  // Nice file preview + file name update
  const fileInput = document.getElementById('photo');
  const fileName  = document.getElementById('file-name');
  const preview   = document.getElementById('photoPreview');

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
