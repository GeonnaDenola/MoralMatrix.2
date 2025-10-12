<?php
include '../includes/admin_header.php';

/**
 * Detect which viewer file exists so we don't break existing routes.
 * (Keeps the same behavior you had.)
 */
$__try1 = __DIR__ . '/account_view.php';
$__try2 = __DIR__ . '/view_account.php';
$__VIEW_FILE = file_exists($__try1) ? 'account_view.php' : (file_exists($__try2) ? 'view_account.php' : 'view_account.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Moral Matrix – Admin</title>

  <!-- Redesigned light UI -->
  <link rel="stylesheet" href="../css/admin_dashboard.css" />
  <link rel="stylesheet" href="../css/admin_search.css" />
</head>
<body>
  <main class="page">
    <section class="canvas" aria-labelledby="welcomeTitle">
      <header class="page-head">
        <div>
          <h1 id="welcomeTitle" class="welcome-admin-title">WELCOME ADMIN</h1>
          <p class="subtitle">Manage users across Students, Faculty, Security, and CCDU with search, filters, and quick actions.</p>
        </div>

        <!-- Primary Actions -->
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
          <button type="button" class="icon-btn" id="refreshBtn" title="Refresh list" aria-label="Refresh list">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path d="M3.51 9a9 9 0 0 1 14.13-3.36L23 10"></path>
              <path d="M20.49 15a9 9 0 0 1-14.13 3.36L1 14"></path>
            </svg>
          </button>
        </div>
      </header>

      <!-- Controls Row -->
      <section class="controls">
        <!-- Search -->
<!-- Search -->
<div class="filter-stack search-stack">
  <label for="accountSearch" class="sr-only">Search</label>
  <div class="search-input-wrapper">
    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="11" cy="11" r="8"></circle>
      <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
    </svg>
    <input type="text" id="accountSearch" placeholder="Search by name, email, or ID…" autocomplete="off" />
  </div>
</div>

        <!-- Filter -->
        <div class="filter-stack">
          <label for="accountType">Filter by Account Type</label>
          <select id="accountType" class="filter-select">
            <option value="">— Select Account Type —</option>
            <option value="student">Student</option>
            <option value="faculty">Faculty</option>
            <option value="security">Security</option>
            <option value="ccdu">CCDU</option>
          </select>
        </div>

        <!-- Sort -->
        <div class="filter-stack">
          <label for="sortBy">Sort</label>
          <select id="sortBy" class="filter-select">
            <option value="name_asc">Name (A–Z)</option>
            <option value="name_desc">Name (Z–A)</option>
            <option value="id_asc">ID (Asc)</option>
            <option value="id_desc">ID (Desc)</option>
          </select>
        </div>

        <!-- Page size -->
        <div class="filter-stack">
          <label for="pageSize">Items per page</label>
          <select id="pageSize" class="filter-select">
            <option>10</option>
            <option selected>12</option>
            <option>20</option>
            <option>30</option>
            <option>50</option>
          </select>
        </div>
      </section>

      <!-- Panel -->
      <section class="panel" aria-labelledby="sectionTitle">
        <header class="panel__header">
          <div>
            <h3 id="sectionTitle" class="section-title">Accounts</h3>
            <p id="resultsMeta" class="meta muted">Please select an account type.</p>
          </div>
        </header>

        <div class="panel__content">
          <!-- Cards / Empty state / Skeleton -->
          <div id="accountContainer" class="cards-wrap" aria-live="polite">
            <div class="empty">
              <div class="empty-illustration" aria-hidden="true"></div>
              <div>
                <strong>No account type selected.</strong>
                <div class="muted">Choose a type above to load accounts.</div>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <footer class="pagination-wrap" id="pagination" aria-label="Pagination">
            <!-- Will be rendered by JS -->
          </footer>
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

  <!-- Toast -->
  <div class="toast" id="toast" role="status" aria-live="polite" aria-atomic="true"></div>

  <script>
    // Keep your existing endpoints; VIEW_PAGE is auto-detected.
    const VIEW_PAGE = '<?= $GLOBALS['__VIEW_FILE'] ?? $__VIEW_FILE ?>';

    // Elements
    const selectEl    = document.getElementById('accountType');
    const container   = document.getElementById('accountContainer');
    const metaEl      = document.getElementById('resultsMeta');
    const searchEl    = document.getElementById('accountSearch');
    const sortEl      = document.getElementById('sortBy');
    const pageSizeEl  = document.getElementById('pageSize');
    const pagination  = document.getElementById('pagination');
    const refreshBtn  = document.getElementById('refreshBtn');

    // Modal
    const modal       = document.getElementById('accountModal');
    const modalTitle  = document.getElementById('accountModalTitle');
    const frame       = document.getElementById('accountFrame');
    const closeBtn    = modal.querySelector('.close');

    // State
    let accountsData = []; // current type dataset
    const state = {
      type: '',
      query: '',
      sort: 'name_asc',
      page: 1,
      pageSize: parseInt(pageSizeEl.value, 10) || 12
    };

    // ====== Event listeners ======
    selectEl.addEventListener('change', () => { state.type = selectEl.value; state.page = 1; loadAccounts(); });
    sortEl.addEventListener('change', () => { state.sort = sortEl.value; state.page = 1; render(); });
    pageSizeEl.addEventListener('change', () => { state.pageSize = parseInt(pageSizeEl.value, 10) || 12; state.page = 1; render(); });
    refreshBtn.addEventListener('click', () => { if (!state.type) return; loadAccounts(true); });

    // Debounced search
    let t;
    searchEl.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => { state.query = (searchEl.value || '').toLowerCase(); state.page = 1; render(); }, 120);
    });

    // Card click actions (delegated)
    container.addEventListener('click', (e) => {
      const editBtn = e.target.closest('.btn.edit');
      const delBtn  = e.target.closest('.btn.delete');
      const left    = e.target.closest('.card .left');

      if (editBtn) editAccount(editBtn.dataset.id, editBtn.dataset.type);
      else if (delBtn) deleteAccount(delBtn.dataset.id, delBtn.dataset.type);
      else if (left) openAccountModal(left.dataset.id, left.dataset.type);
    });

    // Modal controls
    closeBtn.addEventListener('click', closeAccountModal);
    modal.addEventListener('click', (e) => { if(e.target === modal) closeAccountModal(); });
    document.addEventListener('keydown', (e) => { if(e.key === 'Escape') closeAccountModal(); });

    // ====== Core UI helpers ======
    function openAccountModal(id, type) {
      const map = {student:'Student', faculty:'Faculty', security:'Security', ccdu:'CCDU'};
      modalTitle.textContent = (map[type] || 'Account') + ' Details';
      frame.src = `${VIEW_PAGE}?id=${encodeURIComponent(id)}&type=${encodeURIComponent(type)}`;
      modal.classList.add('show');
    }
    function closeAccountModal(){
      modal.classList.remove('show');
      frame.src = 'about:blank';
    }

    function showToast(msg, variant = 'info'){
      const el = document.getElementById('toast');
      el.textContent = msg;
      el.className = `toast show ${variant}`;
      setTimeout(() => el.classList.remove('show'), 2200);
    }

    // ====== Data loading / rendering ======
    function loadAccounts(forceRefresh = false){
      const selectedType = state.type;
      if (!selectedType){
        metaEl.textContent = 'Please select an account type.';
        container.innerHTML = emptyStateHTML('Please select an account type.');
        pagination.innerHTML = '';
        accountsData = [];
        return;
      }

      // Loading skeleton
      metaEl.textContent = 'Loading…';
      container.innerHTML = skeletonHTML(6);
      pagination.innerHTML = '';

      fetch('get_accounts.php' + (forceRefresh ? `?t=${Date.now()}` : ''))
        .then(r => r.json())
        .then(data => {
          // Filter to selected type (same as your original logic)
          accountsData = (data || []).filter(acc => acc.account_type === selectedType);
          state.page = 1; // reset to first page
          render();
        })
        .catch(err => {
          console.error('Error fetching accounts:', err);
          container.innerHTML = `<div class="empty"><strong>Couldn’t load data.</strong><div class="muted">Please try again.</div></div>`;
          metaEl.textContent = 'Error loading data.';
        });
    }

    function applyFilters(){
      // Search
      let list = accountsData.filter(acc => {
        const full = `${acc.first_name || ''} ${acc.last_name || ''}`.trim().toLowerCase();
        const email = (acc.email || '').toLowerCase();
        const id = (String(acc.user_id || '')).toLowerCase();
        if (!state.query) return true;
        return full.includes(state.query) || email.includes(state.query) || id.includes(state.query);
      });

      // Sort
      const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
      const byName = (a,b) => collator.compare(`${a.last_name||''}, ${a.first_name||''}`, `${b.last_name||''}, ${b.first_name||''}`);
      const byId   = (a,b) => collator.compare(String(a.user_id||''), String(b.user_id||''));

      switch(state.sort){
        case 'name_desc': list.sort((a,b)=>-byName(a,b)); break;
        case 'id_asc':    list.sort(byId); break;
        case 'id_desc':   list.sort((a,b)=>-byId(a,b)); break;
        default:          list.sort(byName);
      }

      return list;
    }

    function render(){
      const list = applyFilters();
      const total = list.length;
      const pages = Math.max(1, Math.ceil(total / state.pageSize));

      // Keep page in range
      if (state.page > pages) state.page = pages;
      if (state.page < 1) state.page = 1;

      // Slice
      const start = (state.page - 1) * state.pageSize;
      const end   = start + state.pageSize;
      const pageItems = list.slice(start, end);

      renderAccounts(pageItems);

      // Meta / pagination summary like your screenshot
      metaEl.textContent =
        total ? `Page ${state.page} of ${pages} • ${total} total`
              : 'No records found.';

      renderPagination({ page: state.page, pages, total });
    }

    function renderAccounts(list){
      if (!list.length){
        container.innerHTML = emptyStateHTML('No records found.');
        return;
      }

      container.innerHTML = '';
      list.forEach(acc => {
        const card = document.createElement('article');
        card.className = 'card';
        card.innerHTML = `
          <div class="left" data-id="${escapeAttr(acc.record_id)}" data-type="${escapeAttr(acc.account_type)}" tabindex="0" role="button" aria-label="View account ${escapeAttr(acc.first_name)} ${escapeAttr(acc.last_name)}">
            <img src="${escapeAttr(acc.photo || 'assets/default-avatar.png')}" alt="Photo of ${escapeAttr(acc.first_name)} ${escapeAttr(acc.last_name)}">
            <div class="info">
              <div class="muted">ID: <strong>${escapeHtml(acc.user_id)}</strong></div>
              <div class="name">${escapeHtml(acc.first_name)} ${escapeHtml(acc.last_name)}</div>
              <div class="muted">${escapeHtml(acc.email || '')}</div>
              <div class="muted">${escapeHtml(acc.mobile || '')}</div>
            </div>
          </div>
          <div class="actions">
            <button type="button" class="btn edit" data-id="${escapeAttr(acc.record_id)}" data-type="${escapeAttr(acc.account_type)}" aria-label="Edit ${escapeAttr(acc.first_name)} ${escapeAttr(acc.last_name)}">
              <span class="label">Edit</span>
            </button>
            <button type="button" class="btn delete" data-id="${escapeAttr(acc.record_id)}" data-type="${escapeAttr(acc.account_type)}" aria-label="Delete ${escapeAttr(acc.first_name)} ${escapeAttr(acc.last_name)}">
              <span class="label">Delete</span>
            </button>
          </div>
        `;
        container.appendChild(card);
      });
    }

function renderPagination({ page, pages, total }){
  if (!accountsData.length){
    pagination.innerHTML = '';
    return;
  }
  const prevDisabled = page <= 1 ? 'disabled' : '';
  const nextDisabled = page >= pages ? 'disabled' : '';

  pagination.innerHTML = `
    <div class="pager">
      <div class="pager-legend">Page ${page} of ${pages} • ${total} total</div>
      <div class="pager-buttons">
        <button type="button" class="pager-btn" id="prevPage" ${prevDisabled}>← Prev</button>
        <button type="button" class="pager-btn" id="nextPage" ${nextDisabled}>Next →</button>
      </div>
    </div>
  `;

  pagination.querySelector('#prevPage')?.addEventListener('click', () => {
    if (state.page > 1){ state.page--; render(); }
  });
  pagination.querySelector('#nextPage')?.addEventListener('click', () => {
    if (state.page < pages){ state.page++; render(); }
  });
}


    // ====== Actions ======
    function editAccount(id, type){
      let editPage = '';
      switch(type){
        case 'student':  editPage='student/edit_student.php'; break;
        case 'faculty':  editPage='faculty/edit_faculty.php'; break;
        case 'security': editPage='security/edit_security.php'; break;
        case 'ccdu':     editPage='ccdu/edit_ccdu.php'; break;
        default: alert('Unknown account type.'); return;
      }
      window.location.href = `${editPage}?id=${encodeURIComponent(id)}`;
    }

    function deleteAccount(id, type){
      if(!confirm('Are you sure you want to delete this account?')) return;
      fetch('delete_accounts.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`id=${encodeURIComponent(id)}&type=${encodeURIComponent(type)}`
      })
      .then(r=>r.json())
      .then(result=>{
        if(result && result.success){
          showToast('Account deleted successfully.','success');
          loadAccounts(true);
        } else {
          showToast('Error: ' + (result?.error || 'Unknown error'),'error');
        }
      })
      .catch(err=>{
        console.error('Delete error:', err);
        showToast('Delete failed.','error');
      });
    }

    // ====== Utils ======
    function emptyStateHTML(message){
      return `
        <div class="empty">
          <div class="empty-illustration" aria-hidden="true"></div>
          <div>
            <strong>${escapeHtml(message)}</strong>
            <div class="muted">Try changing filters or search.</div>
          </div>
        </div>
      `;
    }

    function skeletonHTML(n=6){
      return `
        <div class="skeleton-list">
          ${Array.from({length:n}).map(()=>`
            <div class="card skeleton">
              <div class="left">
                <div class="avatar shimmer"></div>
                <div class="lines">
                  <div class="line shimmer"></div>
                  <div class="line short shimmer"></div>
                  <div class="line tiny shimmer"></div>
                </div>
              </div>
              <div class="actions">
                <div class="btn sk-btn shimmer"></div>
                <div class="btn sk-btn shimmer"></div>
              </div>
            </div>
          `).join('')}
        </div>
      `;
    }

    function capitalize(s){ return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
    function escapeHtml(str){
      return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                             .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
    function escapeAttr(str){ return escapeHtml(str).replace(/"/g, '&quot;'); }

    // ====== Kickoff (no auto-load until a type is selected) ======
    // (Matches your previous UX where the user selects a type first.)
  </script>
</body>
</html>
