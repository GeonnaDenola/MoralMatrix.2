<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Moral Matrix</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    /* Dropdown button & menu (self-contained) */
    .menu-wrap { position: relative; display: inline-block; }
    .menu-btn {
      appearance: none; border: 1px solid #e5e7eb; background:#fff; color:#111;
      padding: 8px 12px; border-radius: 8px; cursor: pointer; font: inherit;
    }
    .menu-btn:hover { background:#f9fafb; }
    .dropdown {
      position: absolute; right: 0; top: calc(100% + 6px);
      min-width: 160px; background:#fff; border:1px solid #e5e7eb; border-radius: 10px;
      box-shadow: 0 8px 24px rgba(0,0,0,.08); padding: 6px; display: none; z-index: 999;
    }
    .dropdown.open { display: block; }
    .dropdown a {
      display:block; padding:10px 12px; border-radius:8px; color:#111; text-decoration:none;
    }
    .dropdown a:hover { background:#f3f4f6; }
    /* Optional: keep nav lists aligned nicely */
    header nav { display:flex; align-items:center; justify-content:space-between; gap:16px; }
    header nav ul { list-style:none; display:flex; gap:16px; padding:0; margin:0; align-items:center; }
    .btn-login { border:1px solid #e5e7eb; padding:8px 12px; border-radius:8px; }
  </style>
</head>
<body>
  <header>
    <nav>
      <ul class="nav-left">
        <li><a href="#">MORAL MATRIX</a></li>
      </ul>

      <!-- Removed nav-center + standalone LOGIN.
           All actions are now inside this single MENU dropdown. -->
      <ul class="nav-right">
        <li class="menu-wrap">
          <button type="button" class="menu-btn" id="menuBtn"
                  aria-haspopup="true" aria-expanded="false" aria-controls="mainMenu">
            MENU ▾
          </button>
          <div class="dropdown" id="mainMenu" role="menu" aria-labelledby="menuBtn">
            <!-- Pages -->
            <a href="#about" role="menuitem">About</a>
            <a href="handbook.php" role="menuitem">Student Violation Handbook</a>
            <a href="#services" role="menuitem">Services</a>
            <!-- Logins -->
            <a href="login.php" role="menuitem">Student Login</a>
            <a href="login.php" role="menuitem">Faculty Login</a>
            <a href="login.php" role="menuitem">Security Login</a>
            <a href="login.php" role="menuitem">Validator Login</a>
          </div>
        </li>
      </ul>
    </nav>
  </header>

  <section class="hero">  
    <div class="hero-content">
      <h1>Welcome to Moral Matrix</h1>
      <p><b>Where Character Development is the priority.</b></p>
      <a href="https://mcc.edu.ph/" class="btn">Visit MCC Page</a>
    </div>
  </section>

  <main class="content">
    <article class="item">
      <img src="https://scontent.fmnl8-6.fna.fbcdn.net/v/t39.30808-6/459564360_3261054097360131_4116785309627896257_n.jpg?_nc_cat=100&ccb=1-7&_nc_sid=a5f93a&_nc_eui2=AeE5dbSTnGG94ajMRldj5l2xv7IWPBc0sO2_shY8FzSw7dlM1rV5yW6XqSLHtuTuoRsxpSiqQcAv-3ntAys5asUS&_nc_ohc=xEPwPkns6nQQ7kNvwG1WGLO&_nc_oc=AdllrYxG-_MHfphtn7d8zT7pa_rI2sNcskvGbcE4tLRSxWYKRXPLCbh91uDgNWlM9ng&_nc_zt=23&_nc_ht=scontent.fmnl8-6.fna&_nc_gid=wUW_7e4Kt_ChqoT-5KyGSQ&oh=00_AfZaHfA-xj6vHpaN9O29cauICCkhi_R2QOhP-ALD-XuCmA&oe=68CC0724" alt="Item 1">
      <div class="text">
        <h3>Geonna Lyzzet Denola</h3>
        <p><b><i>Team Leader</i></b></p>
        <p>A team leader guides and supports a group to achieve goals. They plan tasks, delegate work, motivate members, monitor progress, and ensure good communication. A team leader resolves conflicts, provides feedback, and reports to management. Key skills include leadership, communication, problem-solving, and time management.</p>
      </div>
    </article>

    <article class="item">
      <img src="https://scontent.fmnl8-3.fna.fbcdn.net/v/t1.6435-9/205982702_588101678836707_4488149285708952543_n.jpg?_nc_cat=101&ccb=1-7&_nc_sid=a5f93a&_nc_eui2=AeGW8gqR5cUfMAcdZP7zDIphNQYOh-yNog81Bg6H7I2iD9ayQEOE_CupEDb7DWE3UskwJFqUr65DY4IQEVH8N-75&_nc_ohc=1ARaBmiYpH0Q7kNvwEh2MsC&_nc_oc=AdnFV0yB7U_5m21--jSuG3MzkV5cOLaoadH29k-uFvBMe8iSyrtKmMTZiMa46ZBe4S8&_nc_zt=23&_nc_ht=scontent.fmnl8-3.fna&_nc_gid=Q65UwqQSomaqc-ckOHlOOA&oh=00_AfY0o3BRiCiErKfMnefQFD22y9M4Zwdk5JYUmEDGqOodQg&oe=68ED9A84" alt="Item 2">
      <div class="text">
        <h3>Marc Christian Paul Ylan</h3>
        <p><b><i>System Analyst</i></b></p>
        <p>A system analyst studies an organization’s processes to design and improve computer systems. They gather requirements, analyze needs, propose solutions, and ensure systems meet business goals. Key skills include problem-solving, communication, and technical knowledge.</p>
      </div>
    </article>

    <article class="item">
      <img src="https://scontent.fmnl8-4.fna.fbcdn.net/v/t39.30808-6/441507173_2590551781140222_7698675451629930267_n.jpg?_nc_cat=102&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeFMAWg_szq1Vmd7ctgkKBbvoWpabeB-TJShalpt4H5MlB_HCMrCYWku1PsyhCLMqd7iYZ9DY9ZaZgrvMXPMf_ma&_nc_ohc=27uehTqGTGQQ7kNvwFYqUSA&_nc_oc=AdnNcNwdu0F_c5183UCWuMFsCgXaW6oBeL4s9kJls_k0lmlLHQNWdU-zoG-FDTp0-8c&_nc_zt=23&_nc_ht=scontent.fmnl8-4.fna&_nc_gid=2S9KfF1B7ZLA9hthd8RD7g&oh=00_Afarf_gaBNitvfysQupFzBjFWQP1hwcCKamh4cLhOJvCaQ&oe=68CBEA73" alt="Item 3">
      <div class="text">
        <h3>Khyle Alegre</h3>
        <p><b><i>Capstone Adviser</i></b></p>
        <p>A capstone adviser guides students through their project by providing feedback, ensuring research quality, and helping them meet academic standards. They mentor, review progress, and support problem-solving.</p>
      </div>
    </article>
  </main>

  <script>
    // Dropdown behavior (toggle, outside click, ESC, keyboard)
    (function(){
      const btn = document.getElementById('menuBtn');
      const menu = document.getElementById('mainMenu');

      function closeMenu() {
        menu.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }
      function openMenu() {
        menu.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
      }

      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.contains('open') ? closeMenu() : openMenu();
      });

      // Close when clicking outside
      document.addEventListener('click', (e) => {
        if (!menu.contains(e.target) && e.target !== btn) closeMenu();
      });

      // Keyboard: ESC closes; Enter/Space on button toggles (native)
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeMenu();
      });

      // Optional: focus trap on first/last link
      const links = menu.querySelectorAll('a');
      if (links.length) {
        links[links.length - 1].addEventListener('keydown', (e) => {
          if (e.key === 'Tab' && !e.shiftKey) closeMenu();
        });
        links[0].addEventListener('keydown', (e) => {
          if (e.key === 'Tab' && e.shiftKey) closeMenu();
        });
      }
    })();
  </script>
</body>
</html>

