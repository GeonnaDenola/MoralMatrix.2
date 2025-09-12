<?php // ccdu/qr_hello.php ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>QR → Hello world</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:24px;background:#f9fafb}
    .wrap{display:grid;gap:14px;max-width:560px}
    .qr{display:inline-block;padding:12px;background:#fff;border:1px solid #e5e7eb;border-radius:12px}
    code{background:#f3f4f6;padding:2px 6px;border-radius:6px}
    .note{color:#6b7280}
  </style>
  <!-- Client-side QR generator (no server libs needed) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
</head>
<body>
  <div class="wrap">
    <h2>Scan this QR to open <code>hello_world.php</code></h2>

    <div class="qr"><div id="qrcode" style="width:240px;height:240px"></div></div>

    <p><strong>Target URL:</strong><br>
      <a id="target" href="#" target="_blank" rel="noopener"></a>
    </p>
    <p class="note">
      Tip: Open this page using your PC’s LAN IP (e.g. <code>http://192.168.x.x/…</code>) so phones can reach it.
    </p>
  </div>

  <script>
  // Build URL to hello_world.php relative to THIS page location
  // If you loaded qr_hello.php as http://192.168.x.x/MoralMatrix/ccdu/qr_hello.php,
  // the target becomes http://192.168.x.x/MoralMatrix/ccdu/hello_world.php
  document.addEventListener('DOMContentLoaded', function(){
    const target = new URL('hello_world.php', location.href).toString();
    const a = document.getElementById('target');
    a.textContent = target; a.href = target;

    // Render QR
    new QRCode(document.getElementById('qrcode'), {
      text: target,
      width: 240,
      height: 240,
      correctLevel: QRCode.CorrectLevel.M
    });
  });
  </script>
</body>
</html>
