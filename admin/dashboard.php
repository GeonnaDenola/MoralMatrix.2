<?php
include '../includes/admin_header.php';

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
  <link rel="stylesheet" href="../css/admin_dashboard.css" />
  <link rel="stylesheet" href="../css/admin_search.css" /> <!-- New CSS for search -->
</head>
<body>
  <main class="page">
    <section class="canvas" aria-labelledby="welcomeTitle">
      <h1 id="welcomeTitle" class="welcome-admin-title">WELCOME ADMIN</h1>

      <!-- Toolbar -->
      <div class="toolbar">
        <a class="add-users-link" href="add_users.php">
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

   <!-- Search Bar -->
<div class="search-bar">
  <div class="search-input-wrapper">
    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="11" cy="11" r="8"></circle>
      <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
    </svg>
    <input type="text" id="accountSearch" placeholder="Search by name, email, or ID..." />
  </div>
</div>


      <!-- Filter + Results Panel -->
      <section class="panel" aria-labelledby="sectionTitle">
        <header class="panel__header">
          <div class="filter-stack">
            <label for="accountType">Filter by Account Type</label>
            <select id="accountType" class="filter-select">
              <option value="">-- Select Account Type --</option>
              <option value="student">Student</option>
              <option value="faculty">Faculty</option>
              <option value="security">Security</option>
              <option value="ccdu">CCDU</option>
            </select>
          </div>
        </header>

        <div class="panel__content">
          <h4 id="sectionTitle" class="sub-title">Accounts</h4>

          <!-- Cards / Empty state -->
          <div id="accountContainer" class="cards-wrap" aria-live="polite">
            <div class="empty">Please select an account type.</div>
          </div>
        </div>
      </section>
    </section>
  </main>

  <!-- Modal -->
  <div id="accountModal" class="account-modal" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle">
    <div class="dialog">
      <header class="dialog__header">
        <div id="accountModalTitle" class="title">Account</div>
        <button type="button" class="close" aria-label="Close">&times;</button>
      </header>
      <iframe id="accountFrame" src="" title="Account details"></iframe>
    </div>
  </div>

  <script>
    const VIEW_PAGE = 'view_account.php';
    const selectEl   = document.getElementById('accountType');
    const container  = document.getElementById('accountContainer');
    const titleEl    = document.getElementById('sectionTitle');
    const searchEl   = document.getElementById('accountSearch');

    const modal      = document.getElementById('accountModal');
    const modalTitle = document.getElementById('accountModalTitle');
    const frame      = document.getElementById('accountFrame');
    const closeBtn   = modal.querySelector('.close');

    let accountsData = []; // Store loaded accounts for search/filter

    selectEl.addEventListener('change', loadAccounts);
    searchEl.addEventListener('input', filterAccounts);

    container.addEventListener('click', (e) => {
      const editBtn = e.target.closest('.btn.edit');
      const delBtn  = e.target.closest('.btn.delete');
      const left    = e.target.closest('.card .left');

      if (editBtn) editAccount(editBtn.dataset.id, editBtn.dataset.type);
      else if (delBtn) deleteAccount(delBtn.dataset.id, delBtn.dataset.type);
      else if (left) openAccountModal(left.dataset.id, left.dataset.type);
    });

    closeBtn.addEventListener('click', closeAccountModal);
    modal.addEventListener('click', (e) => { if(e.target === modal) closeAccountModal(); });
    document.addEventListener('keydown', (e) => { if(e.key === 'Escape') closeAccountModal(); });

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
        accountsData = [];
        return;
      }

      titleEl.textContent = capitalize(selectedType) + ' Accounts';
      container.innerHTML = '<div class="empty">Loading…</div>';

      fetch('get_accounts.php')
        .then(r => r.json())
        .then(data => {
          accountsData = (data || []).filter(acc => acc.account_type === selectedType);
          renderAccounts(accountsData);
        })
        .catch(err => {
          console.error('Error fetching accounts:', err);
          container.innerHTML = '<div class="empty">Error loading data.</div>';
        });
    }

    function filterAccounts(){
      const query = searchEl.value.toLowerCase();
      const filtered = accountsData.filter(acc =>
        (acc.first_name + ' ' + acc.last_name).toLowerCase().includes(query) ||
        (acc.email || '').toLowerCase().includes(query) ||
        (acc.user_id || '').toLowerCase().includes(query)
      );
      renderAccounts(filtered);
    }

    function renderAccounts(list){
      if (!list.length){
        container.innerHTML = '<div class="empty">No records found.</div>';
        return;
      }

      container.innerHTML = '';
      list.forEach(acc => {
        const card = document.createElement('article');
        card.className = 'card';
        card.innerHTML = `
          <div class="left" data-id="${acc.record_id}" data-type="${acc.account_type}" tabindex="0" role="button" aria-label="View account ${escapeHtml(acc.first_name)} ${escapeHtml(acc.last_name)}">
            <img src="${acc.photo || 'assets/default-avatar.png'}" alt="Photo of ${escapeHtml(acc.first_name)} ${escapeHtml(acc.last_name)}">
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
    }

    function editAccount(id, type){
      let editPage = '';
      switch(type){
        case 'student': editPage='student/edit_student.php'; break;
        case 'faculty': editPage='faculty/edit_faculty.php'; break;
        case 'security': editPage='security/edit_security.php'; break;
        case 'ccdu': editPage='ccdu/edit_ccdu.php'; break;
        default: alert('Unknown account type.'); return;
      }
      window.location.href = `${editPage}?id=${encodeURIComponent(id)}`;
    }

    function deleteAccount(id, type){
      if(!confirm('Are you sure you want to delete this account?')) return;
      fetch('delete_accounts.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`id=${encodeURIComponent(id)}&type=${encodeURIComponent(type)}`
      })
      .then(r=>r.json())
      .then(result=>{
        if(result && result.success){ alert('Account deleted successfully.'); loadAccounts(); }
        else alert('Error: '+(result?.error||'Unknown error'));
      })
      .catch(err=>{ console.error('Delete error:', err); alert('Delete failed.'); });
    }

    function capitalize(s){ return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
    function escapeHtml(str){
      return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                             .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
  </script>
</body>
</html>
