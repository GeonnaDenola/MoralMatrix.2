<?php
// ccdu/_scanner.php — robust USB scanner listener + diagnostics
declare(strict_types=1);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base   = defined('BASE_URL')
  ? rtrim(BASE_URL, '/')
  : (rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\') ?: ''); // project root from /ccdu/...
$qrGate = $scheme.'://'.$host.$base.'/qr.php';
?>
<script>
(() => {
  // ===== Config =====
  const QR_GATE_URL = <?= json_encode($qrGate) ?>; // e.g. http://localhost/MoralMatrix/qr.php
  const GAP_RESET_MS = 80;     // new burst if idle > this
  const AUTO_FIRE_MS = 140;    // if no Enter, fire when idle reaches this
  const MAX_LEN = 256;

  // ===== State =====
  let active = true;           // toggle with Ctrl+Shift+S
  let buf = '';                // incoming chars
  let lastTs = 0;              // last key time
  let idleTimer = null;

  // ===== Tiny overlay for visibility =====
  const ui = document.createElement('div');
  ui.style.cssText = 'position:fixed;right:10px;bottom:10px;z-index:99999;'
    + 'font:12px system-ui;background:#eef6ff;color:#0369a1;'
    + 'padding:6px 10px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08);opacity:.9';
  ui.textContent = 'Scan ready (Ctrl+Shift+S to toggle)';
  const line = document.createElement('div');
  line.style.cssText = 'margin-top:4px;color:#64748b;max-width:40vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
  document.addEventListener('DOMContentLoaded', () => { ui.appendChild(line); document.body.appendChild(ui); });

  function setStatus(msg, ok=true){
    ui.style.background = ok ? '#eef6ff' : '#fee2e2';
    ui.style.color = ok ? '#0369a1' : '#991b1b';
    ui.firstChild.nodeValue = msg;
  }
  function showReady(){
    setStatus(active ? 'Scan ready (Ctrl+Shift+S to toggle)'
                     : 'Scan paused (Ctrl+Shift+S to toggle)', true);
  }
  function resetBuf(){
    buf = '';
    lastTs = 0;
    clearTimeout(idleTimer);
    idleTimer = null;
    line.textContent = '';
  }
  function armIdleFire(){
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => { if (buf) process(buf); }, AUTO_FIRE_MS);
  }

  // ===== Navigation helper + clickable fallback =====
  function navigate(url){
    try{
      if (window.top && window.top.location && window.top.location.origin === window.location.origin){
        window.top.location.assign(url);
        return;
      }
    }catch(e){/* ignore cross-origin */}
    window.location.assign(url);
  }

  // ===== Parser / router =====
  function normalize(s){
    // convert fancy dashes (– — etc.) to hyphen, trim spaces/newlines
    return (s || '').replace(/[\u2010-\u2015]/g, '-').trim();
  }

  function process(raw){
    let val = normalize(raw);
    if (!val){ resetBuf(); return; }
    line.textContent = val;

    let dest = null;

    // 1) Full URL
    if (/^https?:\/\//i.test(val)) dest = val;

    // 2) Path-only URL (/MoralMatrix/qr.php?... or /ccdu/...)
    if (!dest && val.startsWith('/')) dest = val;

    // 3) student_id anywhere (e.g. "SID: 0000-0000")
    if (!dest){
      const mId = val.match(/([0-9]{4}-[0-9]{4})/);
      if (mId) dest = `${QR_GATE_URL}?student_id=${encodeURIComponent(mId[1])}`;
    }

    // 4) 64-hex key anywhere
    if (!dest){
      const mK = val.match(/[a-f0-9]{64}/i);
      if (mK) dest = `${QR_GATE_URL}?k=${mK[0]}`;
    }

    // 5) explicit query fragments
    if (!dest){
      const qsId = val.match(/student_id=([0-9]{4}-[0-9]{4})/i);
      if (qsId) dest = `${QR_GATE_URL}?student_id=${encodeURIComponent(qsId[1])}`;
    }
    if (!dest){
      const qsK = val.match(/(?:^|[?&])k=([a-f0-9]{64})/i);
      if (qsK) dest = `${QR_GATE_URL}?k=${qsK[1]}`;
    }

    if (dest){
      if (dest.startsWith('/')) dest = location.origin + dest;
      
      console.log('[scanner] navigating to:', dest);
      // clickable fallback in case browser blocks immediate nav
      const link = document.createElement('a');
      link.href = dest;
      link.textContent = 'Open scanned link';
      link.style.cssText = 'margin-left:8px;text-decoration:underline;cursor:pointer';
      ui.appendChild(link);
      // try to navigate now
      setTimeout(() => navigate(dest), 0);
      // remove link after a few seconds
      setTimeout(() => { link.remove?.(); }, 4000);
      resetBuf();
      return;
    }

    setStatus('Invalid scan (see preview)', false);
    setTimeout(showReady, 1500);
    resetBuf();
  }

  // ===== Hotkey: pause/resume =====
  window.addEventListener('keydown', e => {
    if (e.ctrlKey && e.shiftKey && e.code === 'KeyS'){
      active = !active;
      showReady();
    }
  });

  // ===== Capture keystrokes globally =====
  window.addEventListener('keydown', e => {
    if (!active) return;

    if (e.key === 'Enter'){
      e.preventDefault();
      if (buf) process(buf);
      resetBuf();
      return;
    }

    if (e.key && e.key.length === 1){
      const now = performance.now();
      if (lastTs && (now - lastTs) > GAP_RESET_MS){
        buf = '';
        line.textContent = '';
      }
      lastTs = now;

      if (buf.length < MAX_LEN){
        buf += e.key;
        line.textContent = buf;
      }
      armIdleFire();
      return;
    }

    if (e.key === 'Backspace'){
      buf = buf.slice(0, -1);
      line.textContent = buf;
      armIdleFire();
    }
  }, true);

  // ===== Also handle scanners that paste the whole payload (no per-key events) =====
  window.addEventListener('paste', (e) => {
    if (!active) return;
    const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
    if (text){ process(text); resetBuf(); }
  });

  // Keep the page focused so scanners type here
  window.addEventListener('blur', () => { if (active) window.focus(); });
})();
</script>

