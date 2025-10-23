<?php
require '../auth.php';
require_role('super_admin');

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
          <div class="searchBar" role="search">
            <span class="search-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
            </span>
            <input
              id="searchInput"
              class="search-input"
              type="search"
              placeholder="Search by name, ID, email..."
              aria-label="Search admin accounts"
            />
          </div>

          <button type="button" class="btn" onclick="openModal()">Add Administrator</button>
        </div>
      </div>

      <section class="list-area" aria-label="Admin account list">
        <div class="list-heading">
          <span class="muted">Account List</span>
        </div>

        <div class="container" id="adminContainer" role="list" aria-live="polite">
          <!-- populated dynamically -->
        </div>

        <div class="pagination-bar" id="paginationBar">
          <span class="pagination-info" id="paginationInfo">Loading...</span>
          <div class="pagination-controls">
            <button type="button" class="pagination-btn" id="paginationPrev" disabled>
              <span aria-hidden="true">←</span>
              <span>Prev</span>
            </button>
            <button type="button" class="pagination-btn" id="paginationNext" disabled>
              <span>Next</span>
              <span aria-hidden="true">→</span>
            </button>
          </div>
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

<!-- ...existing code... -->
<div class="file-row">
  <div class="photo-col">
    <label>Profile Picture:</label>
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
<div class="photo-preview-wrap">
  <img id="photoPreview" src="" alt="No photo" style="display:none;">
</div>
<!-- ...existing code... -->

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

  const adminState = {
    all: [],
    filtered: [],
    currentPage: 1,
    pageSize: 3
  };

  const ui = {
    container: document.getElementById("adminContainer"),
    search: document.getElementById("searchInput"),
    paginationInfo: document.getElementById("paginationInfo"),
    prev: document.getElementById("paginationPrev"),
    next: document.getElementById("paginationNext")
  };

  function setLoadingState(message = "Loading...") {
    if (ui.container) {
      ui.container.innerHTML = `<div class="empty-state">${message}</div>`;
    }
    if (ui.paginationInfo) {
      ui.paginationInfo.textContent = message;
    }
    if (ui.prev) ui.prev.disabled = true;
    if (ui.next) ui.next.disabled = true;
  }

  function buildAdminCard(admin) {
    const card = document.createElement("div");
    card.classList.add("card");
    card.setAttribute("role", "listitem");

    const cardLeft = document.createElement("div");
    cardLeft.classList.add("card-left");

    const avatar = document.createElement("img");
    avatar.classList.add("avatar");
    avatar.src = admin.photo ? `../uploads/${admin.photo}` : "placeholder.png";
    const fullName = [admin.first_name, admin.middle_name, admin.last_name]
      .filter(value => !!value && value !== "null")
      .map(value => value.toString().trim())
      .filter(Boolean)
      .join(" ");
    avatar.alt = (fullName || "Administrator") + " profile photo";
    cardLeft.appendChild(avatar);

    const info = document.createElement("div");
    info.classList.add("info");

    const metaId = document.createElement("p");
    metaId.classList.add("meta", "meta-id");
    metaId.textContent = `ID: ${admin.admin_id || "N/A"}`;
    info.appendChild(metaId);

    const nameEl = document.createElement("p");
    nameEl.classList.add("name");
    nameEl.textContent = fullName || "Unnamed Admin";
    info.appendChild(nameEl);

    const emailEl = document.createElement("p");
    emailEl.classList.add("meta");
    emailEl.textContent = admin.email || "No email provided";
    info.appendChild(emailEl);

    const mobileEl = document.createElement("p");
    mobileEl.classList.add("meta");
    mobileEl.textContent = `Mobile: ${admin.mobile || "N/A"}`;
    info.appendChild(mobileEl);

    cardLeft.appendChild(info);

    const actions = document.createElement("div");
    actions.classList.add("card-actions");

    const editBtn = document.createElement("button");
    editBtn.type = "button";
    editBtn.className = "btn ghost small";
    editBtn.textContent = "Edit";
    editBtn.addEventListener("click", function () {
      editAdmin(admin.record_id);
    });
    actions.appendChild(editBtn);

    const deleteBtn = document.createElement("button");
    deleteBtn.type = "button";
    deleteBtn.className = "btn danger small";
    deleteBtn.textContent = "Delete";
    deleteBtn.addEventListener("click", function () {
      deleteAdmin(admin.record_id);
    });
    actions.appendChild(deleteBtn);

    card.append(cardLeft, actions);
    return card;
  }

  function renderAdmins() {
    if (!ui.container) return;

    const total = adminState.filtered.length;
    const totalPages = total > 0 ? Math.ceil(total / adminState.pageSize) : 1;

    adminState.currentPage = Math.min(Math.max(adminState.currentPage, 1), totalPages);

    const start = (adminState.currentPage - 1) * adminState.pageSize;
    const pageItems = adminState.filtered.slice(start, start + adminState.pageSize);

    ui.container.innerHTML = "";

    if (pageItems.length === 0) {
      ui.container.innerHTML = `<div class="empty-state">No records found.</div>`;
    } else {
      pageItems.forEach(function (admin) {
        ui.container.appendChild(buildAdminCard(admin));
      });
    }

    if (ui.paginationInfo) {
      ui.paginationInfo.textContent = total === 0
        ? "No matching records"
        : `Page ${adminState.currentPage} of ${totalPages} • ${total} total`;
    }

    if (ui.prev) ui.prev.disabled = total === 0 || adminState.currentPage <= 1;
    if (ui.next) ui.next.disabled = total === 0 || adminState.currentPage >= totalPages;
  }

  function applySearchFilter(query) {
    const q = (query || "").trim().toLowerCase();
    if (!q) {
      adminState.filtered = [...adminState.all];
    } else {
      adminState.filtered = adminState.all.filter(function (admin) {
        return [admin.admin_id, admin.first_name, admin.middle_name, admin.last_name, admin.email, admin.mobile]
          .some(function (value) {
            return value && value.toString().toLowerCase().includes(q);
          });
      });
    }
    adminState.currentPage = 1;
    renderAdmins();
  }

  function loadAdmins() {
    setLoadingState();
    fetch("/MoralMatrix/super_admin/get_admin.php")
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Network response was not ok");
        }
        return response.json();
      })
      .then(function (data) {
        if (!Array.isArray(data)) {
          data = [];
        }
        adminState.all = data;
        applySearchFilter(ui.search ? ui.search.value : "");
      })
      .catch(function (error) {
        console.error("Error fetching admin data:", error);
        setLoadingState("Unable to load admin data right now.");
      });
  }

  function editAdmin(id) {
    if (id === undefined || id === null || id === "") {
      return;
    }
    window.location.href = "edit_admin.php?id=" + encodeURIComponent(id);
  }

  function deleteAdmin(id) {
    if (id === undefined || id === null || id === "") {
      alert("Missing record identifier.");
      return;
    }

    if (!confirm("Are you sure you want to delete this admin?")) {
      return;
    }

    fetch("/MoralMatrix/super_admin/delete_admin.php", {
      method: "POST",
      headers: {"Content-Type": "application/x-www-form-urlencoded"},
      body: "id=" + encodeURIComponent(id)
    })
    .then(function (response) {
      return response.json();
    })
    .then(function (result) {
      if (result.success) {
        alert("Admin deleted successfully.");
        loadAdmins();
      } else {
        alert("Error: " + (result.error || "Unknown error."));
      }
    })
    .catch(function (err) {
      console.error("Delete error: ", err);
      alert("Unable to delete admin right now.");
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (ui.search) {
      ui.search.addEventListener('input', function (event) {
        applySearchFilter(event.target.value);
      });
    }

    if (ui.prev) {
      ui.prev.addEventListener('click', function () {
        if (adminState.currentPage > 1) {
          adminState.currentPage -= 1;
          renderAdmins();
        }
      });
    }

    if (ui.next) {
      ui.next.addEventListener('click', function () {
        const totalPages = adminState.filtered.length > 0
          ? Math.ceil(adminState.filtered.length / adminState.pageSize)
          : 1;
        if (adminState.currentPage < totalPages) {
          adminState.currentPage += 1;
          renderAdmins();
        }
      });
    }

    loadAdmins();
  });
  </script>
</body>
</html>
