<?php if (session_status() !== PHP_SESSION_ACTIVE) session_start(); ?>
<style>
  .nb { position: relative; display: inline-block; }
  .nb-btn { cursor: pointer; border: 0; background: transparent; display: inline-flex; align-items: center; gap: 8px; padding: 6px; }
  .nb-badge { min-width: 18px; height: 18px; border-radius: 9px; background:#ef4444; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:12px; padding:0 6px; visibility: hidden; }
  .nb-panel { position:absolute; right:0; top:38px; width:420px; max-height:70vh; overflow:auto; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); display:none; }
  .nb-list { padding:6px; }
  .nb-item { padding:10px; border-bottom:1px solid #f1f5f9; }
  .nb-item.unread{ background:#fff7ed; }
  .nb-title{ margin:0 0 4px; font-weight:700; }
  .nb-body { margin:0 0 6px; color:#334155; }
  .nb-meta { display:flex; justify-content:space-between; font-size:12px; color:#64748b; }
  .nb-actions a { font-size:12px; margin-left:8px; }
  .nb-footer{ display:flex; justify-content:flex-end; padding:8px; border-top:1px solid #f1f5f9; }
  .nb-markall{ border:1px solid #e5e7eb; border-radius:8px; background:#fff; padding:6px 10px; cursor:pointer; }
</style>

<div class="nb" id="nb">
  <button class="nb-btn" id="nbBtn" type="button" aria-haspopup="menu" aria-expanded="false" title="Notifications">
    <span aria-hidden="true">🔔</span>
    <span class="nb-badge" id="nbBadge">0</span>
  </button>
  <div class="nb-panel" id="nbPanel" role="menu" aria-label="Notifications">
    <div class="nb-list" id="nbList"></div>
    <div class="nb-footer"><button class="nb-markall" id="nbMarkAll">Mark all read</button></div>
  </div>
</div>

<script>
(function(){
  const API = '/MoralMatrix/notifications_api.php'; // absolute path ✅
  const btn   = document.getElementById('nbBtn');
  const panel = document.getElementById('nbPanel');
  const list  = document.getElementById('nbList');
  const badge = document.getElementById('nbBadge');
  const markAllBtn = document.getElementById('nbMarkAll');

  function esc(s){ return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function when(iso){ try { return new Date((iso||'').replace(' ','T')).toLocaleString(); } catch(e){ return iso||''; } }

  let open = false;
  function toggle(){ open = !open; panel.style.display = open ? 'block' : 'none'; btn.setAttribute('aria-expanded', open ? 'true':'false'); }
  btn.addEventListener('click', toggle);
  document.addEventListener('click', (e) => { if (!panel.contains(e.target) && !btn.contains(e.target)) { open=false; panel.style.display='none'; } });

  async function refresh(){
    try{
      const r = await fetch(API, { credentials:'same-origin' });
      if(!r.ok){ list.innerHTML = '<div class="nb-item">Unable to load notifications.</div>'; return; }
      const j = await r.json();

      const unread = j.unread|0;
      badge.textContent = unread;
      badge.style.visibility = unread > 0 ? 'visible' : 'hidden';

      const items = (j.items||[]).map(it => `
        <div class="nb-item ${!it.read_at ? 'unread':''}" data-id="${it.id}">
          <div class="nb-title">${esc(it.title)}</div>
          ${it.body ? `<div class="nb-body">${esc(it.body)}</div>` : ''}
          <div class="nb-meta">
            <span>${when(it.created_at)}</span>
            <span class="nb-actions">
              ${it.url ? `<a href="${it.url}">Open</a>` : ''}
              ${!it.read_at ? `<a href="#" data-act="read">Mark read</a>` : ''}
            </span>
          </div>
        </div>
      `).join('') || '<div class="nb-item">No notifications.</div>';

      list.innerHTML = items;
    }catch(e){
      list.innerHTML = '<div class="nb-item">Error loading notifications.</div>';
    }
  }

  list.addEventListener('click', async (e) => {
    const a = e.target.closest('a[data-act="read"]');
    if(!a) return;
    e.preventDefault();
    const item = e.target.closest('.nb-item');
    const id = item && item.getAttribute('data-id');
    if(!id) return;
    const fd = new FormData(); fd.append('id', id);
    const r = await fetch(API, { method:'POST', body:fd, credentials:'same-origin' });
    if(r.ok) refresh();
  });

  markAllBtn.addEventListener('click', async (e)=>{
    e.preventDefault();
    const fd = new FormData(); fd.append('mark', 'all');
    const r = await fetch(API, { method:'POST', body:fd, credentials:'same-origin' });
    if(r.ok) refresh();
  });

  // Initial + periodic refresh
  refresh();
  setInterval(refresh, 20000);
  window.addEventListener('focus', refresh);
})();
</script>
