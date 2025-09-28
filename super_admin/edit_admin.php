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


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="/MoralMatrix/css/global.css">
</head>
<body>
<a href="dashboard.php">
    <button type="button">Return to Dashboard</button>
</a>
    <h2>Edit Admin Account</h2>

    <form action="update_admin.php" method="post" enctype="multipart/form-data">
        
        <input type="hidden" name="record_id" value="<?php echo $id; ?>">

        <label for = "admin_id">ID Number:</label><br>
        <input type ="number" id="admin_id" name="admin_id" value="<?php echo $admin['admin_id']; ?>" ><br><br>

        <label for = "first_name">First Name:</label><br>
        <input type = "text" id="first_name" name="first_name"  value="<?php echo $admin['first_name']; ?>"><br><br>

        <label for = "last_name">Last Name:</label><br>
        <input type = "text" id="last_name" name="last_name" value="<?php echo $admin['last_name']; ?>"><br><br>

        <label for = "middle_name">Middle Name:</label><br>
        <input type = "text" id="middle_name" name="middle_name" value="<?php echo $admin['middle_name']; ?>"><br><br>

        <label for = "mobile">Contact Number:</label><br>
        <input type ="number" id="mobile" name="mobile" value="<?php echo $admin['mobile']; ?>"><br><br>

        <label for = "email">Email:</label><br>
        <input type ="email" id="email" name="email" value="<?php echo $admin['email']; ?>"><br><br>
<!-- Photo Uploader -->
<label for="photo">Profile Picture:</label>

<div class="uploader" id="photo-uploader">
  <!-- keep the same name="photo" for PHP -->
  <input type="file" id="photo" name="photo" accept="image/png, image/jpeg, image/webp" hidden>

  <div class="uploader-drop" tabindex="0" aria-label="Upload profile picture (drag & drop or browse)">
    <!-- existing preview if you have one; otherwise empty -->
    <?php if (!empty($admin['photo'])): ?>
      <img id="photoPreview" src="../uploads/<?php echo htmlspecialchars($admin['photo']); ?>" alt="Profile Picture">
    <?php else: ?>
      <img id="photoPreview" alt="Profile Picture" style="display:none;">
    <?php endif; ?>

    <div class="uploader-instructions">
      <strong>Drop a photo</strong> or <button type="button" class="uploader-browse">browse</button>
      <div class="uploader-hint">PNG/JPG/WebP • up to 5&nbsp;MB</div>
    </div>
    </div>

        <div class="uploader-meta">
            <span class="uploader-filename">No file chosen</span>
            <span class="uploader-size"></span>
        </div>

        <div class="uploader-actions">
            <button type="button" class="uploader-remove" aria-label="Remove selected photo">Remove</button>
        </div>
        </div>

        <input type="file" id="photo" name="photo" accept="image/png, image/jpeg" onchange="previewPhoto(this)"><br><br>


        <button type="submit">Update</button>

    </form>

        <script>
    function previewPhoto(input) {
        const preview = document.getElementById('photoPreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
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

  function formatBytes(b){const u=['B','KB','MB','GB'];let i=0,n=b||0;while(n>=1024&&i<u.length-1){n/=1024;i++}return b===0?'':`${n.toFixed(n<10&&i?1:0)} ${u[i]}`}

  function setMeta(file){
    if(!file){ nameEl.textContent = 'No file chosen'; sizeEl.textContent=''; return; }
    nameEl.textContent = file.name; sizeEl.textContent = `• ${formatBytes(file.size)}`;
  }

  function showPreview(file){
    preview.src = URL.createObjectURL(file);
    preview.style.display = 'block';
    root.classList.add('has-image');
  }

  function clearPreview(){
    input.value = '';
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