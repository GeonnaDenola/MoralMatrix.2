<?php
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Moral Matrix – Admin</title>
  <link rel="stylesheet" href="/MoralMatrix/css/global.css">
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

  <script>
    const selectEl   = document.getElementById('accountType');
    const container  = document.getElementById('accountContainer');
    const titleEl    = document.getElementById('sectionTitle');

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
        viewAccount(left.dataset.id, left.dataset.type);
      }
    });

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

          container.innerHTML = ''; // clear
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
                  <span class="icon">✏️</span><span class="label">Edit</span>
                </button>
                <button type="button" class="btn delete" data-id="${acc.record_id}" data-type="${acc.account_type}">
                  <span class="icon">🗑️</span><span class="label">Delete</span>
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

    function viewAccount(id, type){
      window.location.href = `view_account.php?id=${encodeURIComponent(id)}&type=${encodeURIComponent(type)}`;
    }

    const capitalize = s => s ? s.charAt(0).toUpperCase() + s.slice(1) : s;

    // tiny escape to avoid breaking HTML when inserting strings
    function escapeHtml(str){
      return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
  </script>
</body>
</html>
