<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$mm_base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
if ($mm_base === '') {
  $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
  $dir_path = rtrim(str_replace('\\', '/', __DIR__), '/');
  if ($doc_root !== '' && strpos($dir_path, $doc_root) === 0) {
    $relative = substr($dir_path, strlen($doc_root)); 
    $mm_base = rtrim(preg_replace('#/includes/?$#', '', $relative), '/');
    if ($mm_base === '') {
      $mm_base = '';
    }
  }
}
$notifications_api_url = ($mm_base === '' ? '' : $mm_base) . '/api/notifications_api.php';
?>
<style>
  .nb { position: relative; display: inline-flex; align-items: center; }
  .nb-btn {
    position: relative;
    width: 44px;
    height: 44px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.06));
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    transition: background-color .2s ease, transform .2s ease, box-shadow .2s ease;
  }
  .nb-btn:hover,
  .nb.nb--open .nb-btn {
    background: rgba(255, 255, 255, 0.24);
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.18);
    transform: translateY(-1px);
  }
  .nb-btn:focus-visible {
    outline: 2px solid #facc15;
    outline-offset: 2px;
  }
  .nb-icon { display: inline-flex; }
  .nb-icon svg { width: 22px; height: 22px; fill: currentColor; }
  .nb-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 20px;
    height: 20px;
    border-radius: 999px;
    background: #ef4444;
    color: #ffffff;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
    box-shadow: 0 4px 10px rgba(248, 113, 113, 0.45);
    opacity: 0;
    transform: scale(0.75);
    transform-origin: center;
    transition: opacity .2s ease, transform .2s ease;
  }
  .nb-badge.nb-badge--visible {
    opacity: 1;
    transform: scale(1);
  }
  .nb-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 12px);
    width: min(420px, 90vw);
    max-height: 70vh;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.18);
    border: 1px solid rgba(148, 163, 184, 0.15);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-6px) scale(0.98);
    transform-origin: top right;
    transition: opacity .22s ease, transform .22s ease, visibility 0s linear .22s;
    z-index: 50;
    overflow: hidden;
  }
  .nb-panel::before {
    content: '';
    position: absolute;
    top: -8px;
    right: 24px;
    width: 16px;
    height: 16px;
    background: #ffffff;
    border-top: 1px solid rgba(148, 163, 184, 0.15);
    border-left: 1px solid rgba(148, 163, 184, 0.15);
    transform: rotate(45deg);
    box-shadow: -4px -4px 10px rgba(15, 23, 42, 0.05);
  }
  .nb.nb--open .nb-panel {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
    transition-delay: 0s;
  }
  .nb-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px;
    background: linear-gradient(135deg, #c53030, #9b1c1c);
    color: #ffffff;
  }
  .nb-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 0.02em;
  }
  .nb-header button {
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.35);
    padding: 6px 12px;
    border-radius: 999px;
    color: #ffffff;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color .2s ease, border-color .2s ease;
  }
  .nb-header button:hover {
    background: rgba(255, 255, 255, 0.28);
    border-color: rgba(255, 255, 255, 0.55);
  }
  .nb-header button:disabled {
    opacity: 0.55;
    cursor: default;
  }
  .nb-header button:disabled:hover {
    background: rgba(255, 255, 255, 0.16);
    border-color: rgba(255, 255, 255, 0.35);
  }
  .nb-list {
    padding: 0;
    margin: 0;
    list-style: none;
    overflow-y: auto;
    max-height: 55vh;
    background: #ffffff;
  }
  .nb-item {
    padding: 16px 18px;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
    transition: background-color .2s ease;
  }
  .nb-item:last-child { border-bottom: none; }
  .nb-item.unread {
    background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);
  }
  .nb-title {
    margin: 0 0 6px;
    font-size: 14px;
    font-weight: 600;
    color: #111827;
  }
  .nb-body {
    margin: 0 0 10px;
    font-size: 13px;
    color: #475569;
    line-height: 1.5;
  }
  .nb-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    color: #64748b;
  }
  .nb-actions {
    display: inline-flex;
    gap: 10px;
    align-items: center;
  }
  .nb-actions a {
    color: #b91c1c;
    text-decoration: none;
    font-weight: 500;
  }
  .nb-actions a:hover { text-decoration: underline; }
  .nb-empty {
    padding: 28px 24px;
    text-align: center;
    color: #6b7280;
    font-size: 14px;
  }
  .nb-empty strong {
    display: block;
    margin-bottom: 6px;
    color: #111827;
    font-size: 15px;
  }
  @media (max-width: 600px) {
    .nb-panel { width: min(360px, 92vw); }
    .nb-item { padding: 14px 16px; }
  }
</style>

<div class="nb" id="nb">
  <button class="nb-btn" id="nbBtn" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Open notifications">
    <span class="nb-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" role="presentation" focusable="false">
        <path d="M12 22a2.75 2.75 0 0 1-2.61-2h5.22A2.75 2.75 0 0 1 12 22Zm7.5-5.25h-15a1 1 0 0 1-.78-1.63l1.23-1.47a3.29 3.29 0 0 0 .77-2.09v-1.3a5.28 5.28 0 0 1 4.5-5.21V4a1.28 1.28 0 1 1 2.56 0v1.05a5.28 5.28 0 0 1 4.5 5.21v1.3a3.29 3.29 0 0 0 .77 2.09l1.23 1.47a1 1 0 0 1-.78 1.63Z"/>
      </svg>
    </span>
    <span class="nb-badge" id="nbBadge" aria-hidden="true"></span>
  </button>
  <div class="nb-panel" id="nbPanel" role="menu" aria-label="Notifications" aria-hidden="true">
    <div class="nb-header">
      <h3>Notifications</h3>
      <button class="nb-markall" id="nbMarkAll" type="button">Mark all read</button>
    </div>
    <div class="nb-list" id="nbList" role="list"></div>
  </div>
</div>

<script>
(function(){
  const container = document.getElementById('nb');
  const API = <?php echo json_encode($notifications_api_url); ?>; // absolute path used for fetch requests.
  const btn = document.getElementById('nbBtn');
  const panel = document.getElementById('nbPanel');
  const list = document.getElementById('nbList');
  const badge = document.getElementById('nbBadge');
  const markAllBtn = document.getElementById('nbMarkAll');

  function esc(s){
    return (s || '').replace(/[&<>"']/g, m => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    }[m]));
  }
  function when(iso){
    try {
      return new Date((iso || '').replace(' ', 'T')).toLocaleString();
    } catch (e) {
      return iso || '';
    }
  }

  let open = false;
  function setOpen(state){
    open = !!state;
    container.classList.toggle('nb--open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
  }

  btn.addEventListener('click', (event) => {
    event.stopPropagation();
    setOpen(!open);
  });

  document.addEventListener('click', (event) => {
    if (!container.contains(event.target)) {
      setOpen(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && open) {
      setOpen(false);
      btn.focus();
    }
  });

  async function refresh(){
    try{
      const response = await fetch(API, { credentials: 'same-origin' });
      if(!response.ok){
        list.innerHTML = `
          <div class="nb-empty">
            <strong>Unable to load</strong>
            Please try again in a moment.
          </div>`;
        badge.classList.remove('nb-badge--visible');
        badge.setAttribute('aria-hidden', 'true');
        markAllBtn.disabled = true;
        markAllBtn.setAttribute('aria-disabled', 'true');
        return;
      }
      const payload = await response.json();

      const unread = Number(payload.unread) || 0;
      const formatted = unread > 99 ? '99+' : String(unread);
      badge.textContent = unread > 0 ? formatted : '';
      badge.classList.toggle('nb-badge--visible', unread > 0);
      badge.setAttribute('aria-hidden', unread > 0 ? 'false' : 'true');
      markAllBtn.disabled = unread === 0;
      markAllBtn.setAttribute('aria-disabled', unread === 0 ? 'true' : 'false');

      const items = (payload.items || []).map(it => `
        <article class="nb-item ${!it.read_at ? 'unread' : ''}" data-id="${it.id}" role="listitem">
          <h4 class="nb-title">${esc(it.title)}</h4>
          ${it.body ? `<p class="nb-body">${esc(it.body)}</p>` : ''}
          <div class="nb-meta">
            <span>${when(it.created_at)}</span>
            <span class="nb-actions">
              ${it.url ? `<a href="${esc(it.url)}">Open</a>` : ''}
              ${!it.read_at ? `<a href="#" data-act="read">Mark read</a>` : ''}
            </span>
          </div>
        </article>
      `).join('');

      list.innerHTML = items || `
        <div class="nb-empty">
          <strong>All caught up!</strong>
          Nothing new to review right now.
        </div>`;
    }catch(e){
      list.innerHTML = `
        <div class="nb-empty">
          <strong>Something went wrong</strong>
          Error loading notifications.
        </div>`;
      badge.classList.remove('nb-badge--visible');
      badge.setAttribute('aria-hidden', 'true');
      markAllBtn.disabled = true;
      markAllBtn.setAttribute('aria-disabled', 'true');
    }
  }

  list.addEventListener('click', async (event) => {
    const action = event.target.closest('a[data-act="read"]');
    if(!action) return;
    event.preventDefault();
    const item = action.closest('.nb-item');
    const id = item && item.getAttribute('data-id');
    if(!id) return;
    const fd = new FormData();
    fd.append('id', id);
    const response = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' });
    if(response.ok) refresh();
  });

  markAllBtn.addEventListener('click', async (event) => {
    event.preventDefault();
    if(markAllBtn.disabled) return;
    const fd = new FormData();
    fd.append('mark', 'all');
    const response = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' });
    if(response.ok) refresh();
  });

  setOpen(false);
  refresh();
  setInterval(refresh, 20000);
  window.addEventListener('focus', refresh);
})();
</script>
