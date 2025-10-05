<?php
include '../includes/superadmin_header.php';
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Super Admin - Admin Accounts</title>
  <!-- make sure this path matches your project -->
  <link rel="stylesheet" href="../css/superadmin_dashboard.css" />
</head>
<body>
  <!-- Main page content container (no header/sidebar here; you mentioned you already have those) -->
  <main class="content-wrap">
    <div class="content-inner">
      <div class="actions-bar">
        <h3 class="page-title">Admin Accounts</h3>
        <div class="actions-right">
          <div class="searchBar">
            <input id="searchInput" class="search-input" type="search" placeholder="Search by name, id, email..." />
          </div>
          <button class="btn primary" onclick="openModal()">Add Administrator</button>
        </div>
      </div>

      <section class="list-area">
        <div class="list-heading">
          <span class="muted">Account List</span>
        </div>

        <div class="container" id="adminContainer">
          Loading...
        </div>
      </section>
    </div>
  </main>

  <!-- Popup Modal -->
  <div id="adminModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="modalTitle">
    <div class="modal-content">
      <button class="close-btn" onclick="closeModal()" aria-label="Close modal">&times;</button>
      <h2 id="modalTitle">Add New Admin Account</h2>

      <?php if (!empty($errorMsg)): ?>
        <script>alert("<?php echo addslashes($errorMsg); ?>");</script>
      <?php endif; ?>
      <?php if (!empty($flashMsg)): ?>
        <script>alert("<?php echo addslashes($flashMsg); ?>");</script>
      <?php endif; ?>

      <form action="" method="post" enctype="multipart/form-data" class="admin-form">
        <div class="grid two">
          <label>ID Number:
            <input type="text" name="admin_id" maxlength="9"
              pattern="^[0-9]{4}-[0-9]{4}$"
              value="<?php echo htmlspecialchars($formValues['admin_id']); ?>"
              oninput="this.value = this.value.replace(/[^0-9-]/g,'')" required>
          </label>

          <label>Mobile:
            <input type="text" name="mobile" maxlength="11" placeholder="09XXXXXXXXX"
              pattern="^09[0-9]{9}$"
              oninput="this.value = this.value.replace(/[^0-9]/g,'')"
              value="<?php echo htmlspecialchars($formValues['mobile']); ?>" required>
          </label>
        </div>

        <div class="grid three">
          <label>First Name:
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($formValues['first_name']); ?>" required>
          </label>
          <label>Middle Name:
            <input type="text" name="middle_name" value="<?php echo htmlspecialchars($formValues['middle_name']); ?>" required>
          </label>
          <label>Last Name:
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($formValues['last_name']); ?>" required>
          </label>
        </div>

        <label>Email:
          <input type="email" name="email" value="<?php echo htmlspecialchars($formValues['email']); ?>" required>
        </label>

        <div class="file-row">
          <div class="photo-col">
            <label>Profile Picture:</label>
            <img id="photoPreview" src="" alt="No photo" width="120" style="display:none;">
            <input type="file" name="photo" accept="image/png, image/jpeg" onchange="previewPhoto(this)">
          </div>

          <div class="pass-col">
            <label for="password">Temporary Password:</label>
            <div class="temp-pass-row">
              <input type="text" id="password" name="password"
                value="<?php echo htmlspecialchars($formValues['password']); ?>" required>
              <button type="button" class="btn" onclick="generatePass()">Generate</button>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn primary large">Add Admin Account</button>
          <button type="button" class="btn" onclick="closeModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
// Open / Close Modal - ensure the overlay centers and body doesn't scroll
function openModal() {
  const modal = document.getElementById("adminModal");
  modal.style.display = "block";       // show overlay (modal uses absolute centering)
  modal.classList.add('show');        // optional: for animation hook
  document.body.classList.add('modal-open'); // lock body scroll
  modal.setAttribute('aria-hidden', 'false');
  // focus first input for accessibility
  const f = modal.querySelector('input[name="admin_id"]');
  if (f) f.focus();
}

function closeModal() {
  const modal = document.getElementById("adminModal");
  modal.style.display = "none";
  modal.classList.remove('show');
  document.body.classList.remove('modal-open');
  modal.setAttribute('aria-hidden', 'true');
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

  // Load admins dynamically with labels (kept as you had it)
  function loadAdmins(){
    fetch("/MoralMatrix/super_admin/get_admin.php")
      .then(response => response.json())
      .then(data=>{
        const container = document.getElementById("adminContainer");
        container.innerHTML = "";

        if (!Array.isArray(data) || data.length === 0){
          container.innerHTML = "<p class='muted'>No records found.</p>";
          return;
        }

        data.forEach(admin => {
          const card = document.createElement("div");
          card.classList.add("card");
          card.innerHTML = `
            <div class="card-left">
              <img class="avatar" src="${admin.photo ? '../uploads/' + admin.photo : 'placeholder.png'}" alt="Photo">
              <div class="info">
                <p class="meta"><strong>ID:</strong> ${admin.admin_id}</p>
                <p class="name">${admin.first_name} ${admin.middle_name} ${admin.last_name}</p>
                <p class="meta">${admin.email}</p>
                <p class="meta">Mobile: ${admin.mobile}</p>
              </div>
            </div>
            <div class="card-actions">
              <button class="btn small" onclick="editAdmin(${admin.record_id})">Edit</button>
              <button class="btn danger small" onclick="deleteAdmin(${admin.record_id})">Delete</button>
            </div>
          `;
          container.appendChild(card);
        });
      })
      .catch(error => {
        const c = document.getElementById("adminContainer");
        c.innerHTML = "<p class='muted'>Error Loading Data.</p>";
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

  // optional: connect search input to client-side filter
  document.addEventListener('DOMContentLoaded', function(){
    const search = document.getElementById('searchInput');
    search.addEventListener('input', function(){
      const q = this.value.trim().toLowerCase();
      const cards = document.querySelectorAll('#adminContainer .card');
      cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(q) ? '' : 'none';
      });
    });

    loadAdmins();
  });
  </script>
</body>
</html>
