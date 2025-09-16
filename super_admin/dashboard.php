<?php
include '../includes/header.php';
require '../config.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errorMsg = "";
$flashMsg = "";

// Initialize form values
$formValues = [
    'admin_id'    => '',
    'first_name'  => '',
    'last_name'   => '',
    'middle_name' => '',
    'mobile'      => '',
    'email'       => '',
    'password'    => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    foreach ($formValues as $key => $val) {
        $formValues[$key] = $_POST[$key] ?? '';
    }

    $admin_id    = $formValues['admin_id'];
    $first_name  = $formValues['first_name'];
    $last_name   = $formValues['last_name'];
    $middle_name = $formValues['middle_name'];
    $mobile      = $formValues['mobile'];
    $email       = $formValues['email'];
    $password    = $formValues['password'];
    $photo       = "";

    // Check duplicate email
    $stmtCheck = $conn->prepare("SELECT record_id FROM accounts WHERE email = ?");
    $stmtCheck->bind_param("s", $email);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();
    if ($result && $result->num_rows > 0) {
        $errorMsg = "⚠️ Email already registered!";
    }
    $stmtCheck->close();

    if (empty($errorMsg)) {
        // Handle photo
        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
            $photo = time() . "_" . basename($_FILES["photo"]["name"]);
            $targetDir = "../uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $targetFile = $targetDir . $photo;
            if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
                $errorMsg = "⚠️ Error uploading photo.";
                $photo = "";
            }
        }

        if (empty($errorMsg)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $account_type   = "administrator";

            $stmt1 = $conn->prepare("INSERT INTO admin_account 
                (admin_id, first_name, last_name, middle_name, mobile, email, photo) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt1->bind_param("sssssss", $admin_id, $first_name, $last_name, $middle_name, $mobile, $email, $photo);

            if ($stmt1->execute()) {
                $stmt2 = $conn->prepare("INSERT INTO accounts (id_number, email, password, account_type) 
                                         VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("ssss", $admin_id, $email, $hashedPassword, $account_type);

                if ($stmt2->execute()) {
                    $flashMsg = "✅ Account Added successfully";
                    $formValues = array_map(fn($v) => '', $formValues);
                } else {
                    $errorMsg = "⚠️ Error inserting into accounts table.";
                }
                $stmt2->close();
            } else {
                $errorMsg = "⚠️ Error inserting into admin_account table.";
            }
            $stmt1->close();
        }
    }
}

$conn->close();

if (empty($formValues['password'])) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    $formValues['password'] = substr(str_shuffle($chars), 0, 10);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin - Admin Accounts</title>
  <link rel="stylesheet" href="/MoralMatrix/css/global.css">
</head>
<body>
  <div class="top-container">
    <h2>Super Admin Dashboard - Admin Accounts</h2>
    <button class="btn" onclick="openModal()">Add Administrator</button>
  </div>

  <h3>Account List</h3>
  <div class="searchBar">
  <input id="searchInput" class="search-input" type="search" placeholder="Search...">
</div>

  <div class="container" id="adminContainer">
    Loading...
  </div>

  <!-- ✅ Popup Modal -->
  <div id="adminModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeModal()">&times;</span>
      <h2>Add New Admin Account</h2>

      <?php if (!empty($errorMsg)): ?>
        <script>alert("<?php echo addslashes($errorMsg); ?>");</script>
      <?php endif; ?>
      <?php if (!empty($flashMsg)): ?>
        <script>alert("<?php echo addslashes($flashMsg); ?>");</script>
      <?php endif; ?>

      <form action="" method="post" enctype="multipart/form-data">
        <label>ID Number:</label>
        <input type="text" name="admin_id" maxlength="9"
          pattern="^[0-9]{4}-[0-9]{4}$"
          value="<?php echo htmlspecialchars($formValues['admin_id']); ?>"
          oninput="this.value = this.value.replace(/[^0-9-]/g,'')" required>

        <label>First Name:</label>
        <input type="text" name="first_name" value="<?php echo htmlspecialchars($formValues['first_name']); ?>" required>

        <label>Last Name:</label>
        <input type="text" name="last_name" value="<?php echo htmlspecialchars($formValues['last_name']); ?>" required>

        <label>Middle Name:</label>
        <input type="text" name="middle_name" value="<?php echo htmlspecialchars($formValues['middle_name']); ?>" required>

        <label>Mobile:</label>
        <input type="text" name="mobile" maxlength="11" placeholder="09XXXXXXXXX"
          pattern="^09[0-9]{9}$"
          oninput="this.value = this.value.replace(/[^0-9]/g,'')"
          value="<?php echo htmlspecialchars($formValues['mobile']); ?>" required>

        <label>Email:</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($formValues['email']); ?>" required>

        <label>Profile Picture:</label>
        <img id="photoPreview" src="" alt="No photo" width="100" style="display:none;">
        <input type="file" name="photo" accept="image/png, image/jpeg" onchange="previewPhoto(this)">

        <label>Temporary Password:</label>
        <input type="text" id="password" name="password" value="<?php echo htmlspecialchars($formValues['password']); ?>" required>
        <button type="button" onclick="generatePass()">Generate Password</button>

        <button type="submit">Add Admin Account</button>
      </form>
    </div>
  </div>

  <script>
  // Open / Close Modal
  function openModal() {
    document.getElementById("adminModal").style.display = "flex";
  }
  function closeModal() {
    document.getElementById("adminModal").style.display = "none";
  }
  window.onclick = function(event) {
    if (event.target === document.getElementById("adminModal")) {
      closeModal();
    }
  }

  // Password Generator
  function generatePass() {
    let chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    let pass = "";
    for (let i = 0; i < 10; i++) {
      pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('password').value = pass;
  }

  // Preview Photo
  function previewPhoto(input) {
    const preview = document.getElementById('photoPreview');
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  // Load admins dynamically with labels
  function loadAdmins(){
    fetch("/MoralMatrix/super_admin/get_admin.php")
      .then(response => response.json())
      .then(data=>{
        const container = document.getElementById("adminContainer");
        container.innerHTML = "";

        if (data.length === 0){
          container.innerHTML = "<p>No records found.</p>";
          return;
        }

        data.forEach(admin => {
          const card = document.createElement("div");
          card.classList.add("card");
          card.innerHTML = `
            <div class="left">
              <img src="${admin.photo ? '../uploads/' + admin.photo : 'placeholder.png'}" alt="Photo">
              <div class="info">
                <p><strong>ID Number:</strong> ${admin.admin_id}</p>
                <p><strong>Name:</strong> ${admin.first_name} ${admin.middle_name} ${admin.last_name}</p>
                <p><strong>Email:</strong> ${admin.email}</p>
                <p><strong>Mobile:</strong> ${admin.mobile}</p>
              </div>
            </div>
            <div class="actions">
            <button
              onclick="editAdmin(${admin.record_id})"
              style="background:#2563eb;color:#fff;border:0;border-radius:8px;padding:8px 12px;cursor:pointer"
            >Edit</button>

            <button
              onclick="deleteAdmin(${admin.record_id})"
              style="background:#dc2626;color:#fff;border:0;border-radius:8px;padding:8px 12px;cursor:pointer"
            >Delete</button>
          </div>
            `;
          container.appendChild(card);
        });
      })
      .catch(error => {
        document.getElementById("adminContainer").innerHTML = "<p>Error Loading Data.</p>";
        console.error("Error fetching student data: ", error);
      });
  }

  function editAdmin(id){
    window.location.href = "edit_admin.php?id=" + id;
  }

  function deleteAdmin(id){
    if(!confirm("Are you sure you want to delete this admin?")) return;

    fetch("/MoralMatrix/super_admin/delete_admin.php", {
      method: "POST",
      headers: {"Content-Type": "application/x-www-form-urlencoded"},
      body: "id=" + encodeURIComponent(id)
    })
    .then(response => response.json())
    .then(result => {
      if(result.success){
        alert("Admin deleted successfully.");
        loadAdmins();
      }else{
        alert("Error: " + result.error);
      }
    })
    .catch(err => console.error("Delete error: ", err));
  }

  loadAdmins();
  </script>
</body>
</html>
