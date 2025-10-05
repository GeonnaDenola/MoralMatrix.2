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

if(!isset($_GET['id']) || empty($_GET['id'])){
    die("No admin selected.");
}

$id = intval($_GET['id']);
$result = $conn->query("SELECT * FROM admin_account WHERE record_id = $id");

if($result->num_rows === 0){
    die("Admin not found.");
}



$admin = $result->fetch_assoc();
$conn->close();
?>
<!-- edit_admin.php - HTML + PHP + JS (CSS moved to css/edit_admin.css) -->
<?php
// assume $id and $admin are provided by your PHP controller as before
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Edit Admin Account</title>

  <!-- Inter font (system fallback included) -->
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

  <!-- External CSS (place the file at css/edit_admin.css) -->
  <link rel="stylesheet" href="../css/edit_admin.css">
</head>
<body>
  <div class="edit-admin-page">

    <div class="card" role="region" aria-labelledby="edit-admin-title">
      <div class="card-header">
        <div class="title-wrap">
          <a href="../super_admin/dashboard.php" class="back-btn" aria-label="Back to list">
            <!-- simple inline SVG chevron -->
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
              <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back
          </a>

          <div>
            <h2 id="edit-admin-title" class="card-title">Edit Admin Account</h2>
            <div class="card-sub">Manage admin details and profile picture</div>
          </div>
        </div>

        <div class="meta-actions" aria-hidden="true">
          <!-- You can add contextual actions here if needed -->
        </div>
      </div>

      <form class="admin-form" action="update_admin.php" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="record_id" value="<?php echo $id; ?>">

        <div class="grid">
          <div class="field">
            <label for="admin_id">ID Number</label>
            <input type="number" id="admin_id" name="admin_id" value="<?php echo htmlspecialchars($admin['admin_id'] ?? ''); ?>" autocomplete="off">
          </div>

          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" autocomplete="email">
          </div>

          <div class="field">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($admin['first_name'] ?? ''); ?>">
          </div>

          <div class="field">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($admin['last_name'] ?? ''); ?>">
          </div>

          <div class="field">
            <label for="middle_name">Middle Name</label>
            <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($admin['middle_name'] ?? ''); ?>">
          </div>

          <div class="field">
            <label for="mobile">Contact Number</label>
            <input type="tel" id="mobile" name="mobile" value="<?php echo htmlspecialchars($admin['mobile'] ?? ''); ?>">
          </div>
        </div>

        <!-- Photo uploader block -->
        <div class="photo-section">
          <label class="photo-label">Profile Picture</label>

          <div class="uploader" id="photo-uploader">
            <input type="file" id="photo" name="photo" accept="image/png, image/jpeg, image/webp" hidden>

            <div class="uploader-drop" tabindex="0" role="button" aria-label="Upload profile picture (drag & drop or browse)">
              <?php if (!empty($admin['photo'])): ?>
                <img id="photoPreview" class="preview-img" src="../uploads/<?php echo htmlspecialchars($admin['photo']); ?>" alt="Profile Picture">
              <?php else: ?>
                <img id="photoPreview" class="preview-img" alt="Profile Picture" style="display:none;">
              <?php endif; ?>

              <div class="uploader-instructions" aria-hidden="true">
                <div><strong>Drop a photo</strong> or <button type="button" class="uploader-browse">browse</button></div>
                <div class="uploader-hint">PNG · JPG · WebP — up to 5 MB</div>
              </div>
            </div>

            <div class="uploader-meta" aria-live="polite">
              <span class="uploader-filename">No file chosen</span>
              <span class="uploader-size"></span>
            </div>

            <div class="uploader-actions">
              <button type="button" class="uploader-remove" aria-label="Remove selected photo">Remove</button>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  /* uploader script (keeps original functionality, single #photo input) */
  (function () {
    const root      = document.getElementById('photo-uploader');
    const input     = document.getElementById('photo');
    const drop      = root.querySelector('.uploader-drop');
    const preview   = document.getElementById('photoPreview');
    const browseBtn = root.querySelector('.uploader-browse');
    const removeBtn = root.querySelector('.uploader-remove');
    const nameEl    = root.querySelector('.uploader-filename');
    const sizeEl    = root.querySelector('.uploader-size');

    const MAX_BYTES = 5 * 1024 * 1024;
    const OK_TYPES  = ['image/jpeg','image/png','image/webp'];

    function formatBytes(b){
      const u=['B','KB','MB','GB'];
      let i=0, n=b||0;
      while(n>=1024 && i<u.length-1){ n/=1024; i++; }
      return b===0 ? '' : `${n.toFixed(n<10&&i?1:0)} ${u[i]}`;
    }

    function setMeta(file){
      if(!file){ nameEl.textContent = 'No file chosen'; sizeEl.textContent=''; return; }
      nameEl.textContent = file.name;
      sizeEl.textContent = ` • ${formatBytes(file.size)}`;
    }

    function showPreview(file){
      if (!file) return;
      if (preview._objectUrl) { URL.revokeObjectURL(preview._objectUrl); }
      preview._objectUrl = URL.createObjectURL(file);
      preview.src = preview._objectUrl;
      preview.style.display = 'block';
      root.classList.add('has-image');
    }

    function clearPreview(){
      input.value = '';
      if (preview._objectUrl) { URL.revokeObjectURL(preview._objectUrl); preview._objectUrl = null; }
      preview.removeAttribute('src');
      preview.style.display = 'none';
      root.classList.remove('has-image');
      setMeta(null);
    }

    function validate(file){
      if(!OK_TYPES.includes(file.type)){ alert('Please use PNG, JPG, or WebP.'); return false; }
      if(file.size > MAX_BYTES){ alert('Max size is 5 MB.'); return false; }
      return true;
    }

    function handleFiles(files){
      const f = files && files[0]; if(!f) return;
      if(!validate(f)){ clearPreview(); return; }
      setMeta(f); showPreview(f);
    }

    // Open picker
    browseBtn?.addEventListener('click', () => input.click());
    drop.addEventListener('click', (e)=>{ if(e.target===drop) input.click(); });
    drop.addEventListener('keydown', (e)=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); input.click(); } });

    // Click preview to reselect
    preview.addEventListener('click', () => input.click());

    // Clipboard paste support
    drop.addEventListener('paste', (e)=>{ const f=[...(e.clipboardData?.files||[])][0]; if(f) handleFiles([f]); });

    // Input & DnD
    input.addEventListener('change', ()=> handleFiles(input.files));
    ['dragenter','dragover'].forEach(ev=> drop.addEventListener(ev,(e)=>{e.preventDefault(); drop.classList.add('dragover');}));
    ['dragleave','drop'].forEach(ev=> drop.addEventListener(ev,(e)=>{e.preventDefault(); drop.classList.remove('dragover');}));
    drop.addEventListener('drop',(e)=> handleFiles(e.dataTransfer.files));

    // Remove
    removeBtn.addEventListener('click', clearPreview);

    // Initialize if server provided an existing photo
    if(preview.getAttribute('src')){ root.classList.add('has-image'); setMeta({name: preview.src.split('/').pop(), size: 0}); }
  })();
  </script>
</body>
</html>
