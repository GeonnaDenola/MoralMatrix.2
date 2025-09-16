<?php
include '../includes/header.php';

// Build the web path of this folder (e.g., /MoralMatrix/admin)
$__BASE_PATH = str_replace('\\','/', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));

// Detect which viewer file exists
$__try1 = __DIR__ . '/account_view.php';
$__try2 = __DIR__ . '/view_account.php';
$__VIEW_FILE = file_exists($__try1) ? 'account_view.php' : (file_exists($__try2) ? 'view_account.php' : 'account_view.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Moral Matrix – Admin</title>
  <link rel="stylesheet" href="/MoralMatrix/css/global.css">

  <!-- Modal styles (scoped to this page) -->
  <style>
    /* overlay */
    .account-modal{
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: rgba(0,0,0,.6);
      z-index: 2000;
    }
    .account-modal.show{ display:flex; }

    /* dialog */
    .account-modal .dialog{
      width: min(900px, 96vw);
      height: min(90vh, 720px);
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 12px 32px rgba(0,0,0,.35);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .account-modal header{
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 12px;
      border-bottom: 1px solid #e5e7eb;
      background: #fff;
    }
    .account-modal header .title{
      font-weight: 700;
      font-size: 16px;
    }
    .account-modal .close{
      border: 0;
      background: transparent;
      font-size: 22px;
      line-height: 1;
      cursor: pointer;
      padding: 6px 10px;
      color: #111;
    }
    .account-modal iframe{
      border: 0;
      width: 100%;
      height: 100%;
      background: #fff;
    }
  /* === Make avatar ring + left accent bar RED === */
:root{
  --accent-red: #464646;      /* red-500 */
  --accent-red-dark: #464646; /* optional darker shade */
}

/* Left vertical accent on each card */
#accountContainer .card{
  /* if your green strip is a border-left, this flips it to red */
  border-left: 6px solid var(--accent-red) !important;
  border-radius: 12px; /* keep your rounded card edges */
  position: relative;
}
/* if your strip is drawn with ::before, this forces it to red too */
#accountContainer .card::before{
  content:"";
  position:absolute; left:0; top:0; bottom:0; width:6px;
  background: var(--accent-red) !important;
  border-radius: 6px 0 0 6px;
}

/* Avatar ring around the photo inside .left */
#accountContainer .card .left img{
  border-radius: 9999px;

  /* If your current ring is a BORDER, this line recolors it: */
  border-color: var(--accent-red) !important;

  /* If your current ring is a BOX-SHADOW “ring”, this recolors it: */
  box-shadow: 0 0 0 3px var(--accent-red) !important;
}

/* (Optional) a hover/selected state in red as well */
#accountContainer .card:hover{
  border-left-color: var(--accent-red-dark) !important;
}
#accountContainer .card.active,
#accountContainer .card.is-selected{
  border-left-color: var(--accent-red-dark) !important;
}
#accountContainer .card.active .left img,
#accountContainer .card.is-selected .left img{
  box-shadow: 0 0 0 3px var(--accent-red-dark) !important;
  border-color: var(--accent-red-dark) !important;
}

/* === Red theme for the filter select === */
.filter-select{
  appearance: none;           /* hide native arrow */
  -webkit-appearance: none;
  -moz-appearance: none;

  border: 2px solid #ef4444;  /* red border */
  border-radius: 10px;
  padding: 10px 40px 10px 12px;   /* right padding for custom arrow */
  background-color: #fff;
  color: #0b132b;
  line-height: 1.2;

  /* custom red caret/chevron (inline SVG) */
  background-image: url("data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath fill='%23ef4444' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  background-size: 16px 16px;

  transition: border-color .15s ease, box-shadow .15s ease;
}

.filter-select:hover{
  border-color: #dc2626;  /* darker red on hover */
}

.filter-select:focus{
  outline: none;                           /* remove blue outline */
  border-color: #dc2626;
  box-shadow: 0 0 0 4px rgba(239, 68, 68, .15); /* red focus ring */
}

/* Remove the old Windows IE arrow, just in case */
.filter-select::-ms-expand{ display:none; }
  </style>
</head>
<body>
  <div class="page">
    <h1 class="welcome-admin-title">WELCOME ADMIN</h1>

    <div class="add-users">
      <a href="add_users.php">
        <button type="button" class="add-user-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 5v14M5 12h14" />
          </svg>
          Add Users
        </button>
      </a>
    </div>

    <h3 class="section-title">Accounts List</h3>

    <div class="filter-bar">
      <label for="accountType">Filter by Account Type:</label>
      <select id="accountType" class="filter-select">
        <option value="">-- Select Account Type --</option>
        <option value="student">Student</option>
        <option value="faculty">Faculty</option>
        <option value="security">Security</option>
        <option value="ccdu">CCDU</option>
      </select>
    </div>

    <h3 id="sectionTitle" class="section-title">Accounts</h3>

    <!-- Cards / Empty state -->
    <div id="accountContainer">
      <div class="empty">Please select an account type.</div>
    </div>
  </div>

  <!-- Modal -->
  <div id="accountModal" class="account-modal" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle">
    <div class="dialog">
      <header>
        <div id="accountModalTitle" class="title">Account</div>
        <button type="button" class="close" aria-label="Close">&times;</button>
      </header>
      <iframe id="accountFrame" src=""></iframe>
    </div>
  </div>

  <script>
    // Absolute URL to the account viewer inside /admin
    const VIEW_PAGE = "<?php echo $__BASE_PATH . '/' . $__VIEW_FILE; ?>";

    const selectEl   = document.getElementById('accountType');
    const container  = document.getElementById('accountContainer');
    const titleEl    = document.getElementById('sectionTitle');

    const modal      = document.getElementById('accountModal');
    const modalTitle = document.getElementById('accountModalTitle');
    const frame      = document.getElementById('accountFrame');
    const closeBtn   = modal.querySelector('.close');

    selectEl.addEventListener('change', loadAccounts);

    // Event delegation for Edit/Delete + card click
    container.addEventListener('click', (e) => {
      const editBtn = e.target.closest('.btn.edit');
      const delBtn  = e.target.closest('.btn.delete');
      const left    = e.target.closest('.card .left');

      if (editBtn) {
        editAccount(editBtn.dataset.id, editBtn.dataset.type);
      } else if (delBtn) {
        deleteAccount(delBtn.dataset.id, delBtn.dataset.type);
      } else if (left) {
        openAccountModal(left.dataset.id, left.dataset.type); // open viewer in modal
      }
    });

    // Modal handlers
    closeBtn.addEventListener('click', closeAccountModal);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeAccountModal(); // click backdrop to close
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeAccountModal();
    });

    function openAccountModal(id, type){
      const map = {student:'Student', faculty:'Faculty', security:'Security', ccdu:'CCDU'};
      modalTitle.textContent = (map[type] || 'Account') + ' Details';
      frame.src = `${VIEW_PAGE}?id=${encodeURIComponent(id)}&type=${encodeURIComponent(type)}`;
      modal.classList.add('show');
    }
    function closeAccountModal(){
      modal.classList.remove('show');
      frame.src = 'about:blank';
    }

    function loadAccounts(){
      const selectedType = selectEl.value;

      if (!selectedType){
        titleEl.textContent = 'Accounts';
        container.innerHTML = '<div class="empty">Please select an account type.</div>';
        return;
      }

      titleEl.textContent = capitalize(selectedType) + ' Accounts';
      container.innerHTML = '<div class="empty">Loading…</div>';

      fetch('get_accounts.php')
        .then(r => r.json())
        .then(data => {
          const filtered = (data || []).filter(acc => acc.account_type === selectedType);
          if (!filtered.length){
            container.innerHTML = '<div class="empty">No records found.</div>';
            return;
          }

          container.innerHTML = '';
          filtered.forEach(acc => {
            const card = document.createElement('article');
            card.className = 'card';
            card.innerHTML = `
              <div class="left" data-id="${acc.record_id}" data-type="${acc.account_type}">
                <img src="${acc.photo || 'assets/default-avatar.png'}" alt="Photo">
                <div class="info">
                  <div class="muted">ID: <strong>${escapeHtml(acc.user_id)}</strong></div>
                  <div class="name">${escapeHtml(acc.first_name)} ${escapeHtml(acc.last_name)}</div>
                  <div class="muted">${escapeHtml(acc.email || '')}</div>
                  <div class="muted">${escapeHtml(acc.mobile || '')}</div>
                </div>
              </div>
              <div class="actions">
                <button type="button" class="btn edit" data-id="${acc.record_id}" data-type="${acc.account_type}">
                  <span class="label">Edit</span>
                </button>
                <button type="button" class="btn delete" data-id="${acc.record_id}" data-type="${acc.account_type}">
                  <span class="label">Delete</span>
                </button>
              </div>
            `;
            container.appendChild(card);
          });
        })
        .catch(err => {
          console.error('Error fetching accounts:', err);
          container.innerHTML = '<div class="empty">Error loading data.</div>';
        });
    }

    function editAccount(id, type){
      let editPage = '';
      switch (type){
        case 'student':  editPage = 'student/edit_student.php';   break;
        case 'faculty':  editPage = 'faculty/edit_faculty.php';   break;
        case 'security': editPage = 'security/edit_security.php'; break;
        case 'ccdu':     editPage = 'ccdu/edit_ccdu.php';         break;
        default: alert('Unknown account type.'); return;
      }
      window.location.href = `${editPage}?id=${encodeURIComponent(id)}`;
    }

    function deleteAccount(id, type){
      if (!confirm('Are you sure you want to delete this account?')) return;

      fetch('delete_accounts.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${encodeURIComponent(id)}&type=${encodeURIComponent(type)}`
      })
      .then(r => r.json())
      .then(result => {
        if (result && result.success){
          alert('Account deleted successfully.');
          loadAccounts();
        } else {
          alert('Error: ' + (result?.error || 'Unknown error'));
        }
      })
      .catch(err => {
        console.error('Delete error:', err);
        alert('Delete failed.');
      });
    }

    function capitalize(s){ return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
    function escapeHtml(str){
      return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
  </script>
</body>
</html>
