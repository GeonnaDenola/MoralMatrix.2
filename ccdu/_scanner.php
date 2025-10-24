<?php
// ccdu/_scanner.php — QR scanner router (fixed for Hostinger + localhost)
declare(strict_types=1);

// Only include config once (safe for repeated includes)
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}

/**
 * Build a QR gateway URL that always matches your BASE_URL.
 * For example:
 *   Local:      http://localhost/MoralMatrix/qr.php
 *   Hostinger:  https://mccmoralmatrix.com/qr.php
 */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base   = rtrim(BASE_URL, '/');

// --- Force the Hostinger domain in production ---
if (!$base && $host !== 'mccmoralmatrix.com') {
    $host = 'mccmoralmatrix.com';
}

$qrGate = $scheme . '://' . $host . $base . '/qr.php';
?>
<script>
(() => {
  const QR_GATE_URL = <?= json_encode($qrGate) ?>;
  const GAP_RESET_MS = 80;
  const AUTO_FIRE_MS = 140;
  const MAX_LEN = 256;

  let active = true;
  let buf = '';
  let lastTs = 0;
  let idleTimer = null;

  const ui = document.createElement('div');
  ui.style.cssText = 'position:fixed;right:10px;bottom:10px;z-index:99999;font:12px system-ui;background:#eef6ff;color:#0369a1;padding:6px 10px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08);opacity:.9';
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
    setStatus(active ? 'Scan ready (Ctrl+Shift+S to toggle)' : 'Scan paused (Ctrl+Shift+S to toggle)', true);
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

  function navigate(url){
    try {
      if (window.top && window.top.location && window.top.location.origin === window.location.origin) {
        window.top.location.assign(url);
        return;
      }
    } catch(e) {}
    window.location.assign(url);
  }

  function normalize(s){
    return (s || '').replace(/[\u2010-\u2015]/g, '-').trim();
  }

  function process(raw){
    let val = normalize(raw);
    if (!val){ resetBuf(); return; }
    line.textContent = val;

    let dest = null;

    if (/^https?:\/\//i.test(val)) dest = val;
    if (!dest && val.startsWith('/')) dest = val;

    if (!dest){
      const mId = val.match(/([0-9]{4}-[0-9]{4})/);
      if (mId) dest = `${QR_GATE_URL}?student_id=${encodeURIComponent(mId[1])}`;
    }
    if (!dest){
      const mK = val.match(/[a-f0-9]{64}/i);
      if (mK) dest = `${QR_GATE_URL}?k=${mK[0]}`;
    }
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
      const link = document.createElement('a');
      link.href = dest;
      link.textContent = 'Open scanned link';
      link.style.cssText = 'margin-left:8px;text-decoration:underline;cursor:pointer';
      ui.appendChild(link);
      setTimeout(() => navigate(dest), 0);
      setTimeout(() => { link.remove?.(); }, 4000);
      resetBuf();
      return;
    }

    setStatus('Invalid scan (see preview)', false);
    setTimeout(showReady, 1500);
    resetBuf();
  }

  window.addEventListener('keydown', e => {
    if (e.ctrlKey && e.shiftKey && e.code === 'KeyS'){
      active = !active;
      showReady();
    }
  });

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

  window.addEventListener('paste', (e) => {
    if (!active) return;
    const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
    if (text){ process(text); resetBuf(); }
  });
  window.addEventListener('blur', () => { if (active) window.focus(); });
})();
</script>
