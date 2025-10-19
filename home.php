<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Moral Matrix</title>
  <link rel="stylesheet" href="css/home.css" />
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <!-- Header -->
  <header class="site-header">
    <div class="container header-inner">
      <!-- logo (now pinned to the left by CSS) -->
      <a class="brand" href="home.php">MORAL MATRIX</a>

      <!-- Centered top nav: visible on desktop per CSS -->
      <nav class="primary-nav" aria-label="Primary">
        <ul>
          <li><a href="home.php" aria-current="page">Home</a></li>
          <li><a href="#about">About</a></li>
          <li><a href="handbook.php">Handbook</a></li>
          <li><a href="#services">Services</a></li>
        </ul>
      </nav>

      <!-- Hamburger -->
      <button
        class="hamburger"
        id="hamburgerBtn"
        aria-label="Open menu"
        aria-controls="navDrawer"
        aria-expanded="false"
        type="button"
      >
        <span class="bar"></span>
        <span class="bar"></span>
        <span class="bar"></span>
      </button>
    </div>
  </header>

  <!-- Off-canvas Navigation -->
  <div class="nav-overlay" id="navOverlay" hidden></div>

  <nav class="nav-drawer" id="navDrawer" aria-hidden="true">
    <div class="drawer-header">
      <strong>Navigation</strong>
      <button class="close-drawer" id="closeDrawer" aria-label="Close menu">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>
    <ul class="drawer-links" role="menu">
      <li><a href="home.php" role="menuitem" aria-current="page">Home</a></li>
      <li><a href="#about" role="menuitem">About</a></li>
      <li><a href="handbook.php" role="menuitem">Handbook</a></li>
      <li><a href="#services" role="menuitem">Services</a></li>
      <li><a href="login.php" role="menuitem">Student Login</a></li>
      <li><a href="login.php" role="menuitem">Faculty Login</a></li>
      <li><a href="login.php" role="menuitem">Security Login</a></li>
      <li><a href="/moralmatrix/validator/validator_login.php" role="menuitem">Validator Login</a></li>
    </ul>
  </nav>

  <!-- Hero -->
  <section class="hero" id="about" aria-label="Welcome section">
    <div class="container hero-content">
      <h1>Welcome to Moral Matrix</h1>
      <p>Where character development and community values guide every student journey.</p>
      <div class="hero-actions">
        <a class="btn" href="https://mcc.edu.ph/" rel="noopener">Visit MCC Page</a>
        <a class="btn-secondary" href="handbook.php">Read Student Handbook</a>
      </div>
    </div>
  </section>

  <!-- Main -->
  <main id="main">
    <section class="container cards">
      <!-- Card 1 -->
      <article class="card">
        <figure class="card-media">
          <img
            src="https://scontent.fmnl8-6.fna.fbcdn.net/v/t39.30808-6/459564360_3261054097360131_4116785309627896257_n.jpg?_nc_cat=100&ccb=1-7&_nc_sid=a5f93a&_nc_eui2=AeE5dbSTnGG94ajMRldj5l2xv7IWPBc0sO2_shY8FzSw7dlM1rV5yW6XqSLHtuTuoRsxpSiqQcAv-3ntAys5asUS&_nc_ohc=xEPwPkns6nQQ7kNvwG1WGLO&_nc_oc=AdllrYxG-_MHfphtn7d8zT7pa_rI2sNcskvGbcE4tLRSxWYKRXPLCbh91uDgNWlM9ng&_nc_zt=23&_nc_ht=scontent.fmnl8-6.fna&_nc_gid=wUW_7e4Kt_ChqoT-5KyGSQ&oh=00_AfZaHfA-xj6vHpaN9O29cauICCkhi_R2QOhP-ALD-XuCmA&oe=68CC0724"
            alt="Portrait of Geonna Lyzzet Denola"
            loading="lazy"
          />
        </figure>
        <div class="card-body">
          <h3>Geonna Lyzzet Denola</h3>
          <p class="role"><b><i>Team Leader</i></b></p>
          <p>A team leader guides and supports a group to achieve goals. They plan tasks, delegate work, motivate members, monitor progress, and ensure good communication. A team leader resolves conflicts, provides feedback, and reports to management. Key skills include leadership, communication, problem-solving, and time management.</p>
        </div>
      </article>

      <!-- Card 2 -->
      <article class="card">
        <figure class="card-media">
          <img
            src="https://scontent.fmnl8-3.fna.fbcdn.net/v/t1.6435-9/205982702_588101678836707_4488149285708952543_n.jpg?_nc_cat=101&ccb=1-7&_nc_sid=a5f93a&_nc_eui2=AeGW8gqR5cUfMAcdZP7zDIphNQYOh-yNog81Bg6H7I2iD9ayQEOE_CupEDb7DWE3UskwJFqUr65DY4IQEVH8N-75&_nc_ohc=1ARaBmiYpH0Q7kNvwEh2MsC&_nc_oc=AdnFV0yB7U_5m21--jSuG3MzkV5cOLaoadH29k-uFvBMe8iSyrtKmMTZiMa46ZBe4S8&_nc_zt=23&_nc_ht=scontent.fmnl8-3.fna&_nc_gid=Q65UwqQSomaqc-ckOHlOOA&oh=00_AfY0o3BRiCiErKfMnefQFD22y9M4Zwdk5JYUmEDGqOodQg&oe=68ED9A84"
            alt="Portrait of Marc Christian Paul Ylan"
            loading="lazy"
          />
        </figure>
        <div class="card-body">
          <h3>Marc Christian Paul Ylan</h3>
          <p class="role"><b><i>System Analyst</i></b></p>
          <p>A system analyst studies an organizationâ€™s processes to design and improve computer systems. They gather requirements, analyze needs, propose solutions, and ensure systems meet business goals. Key skills include problem-solving, communication, and technical knowledge.</p>
        </div>
      </article>

      <!-- Card 3 -->
      <article class="card">
        <figure class="card-media">
          <img
            src="https://scontent.fmnl8-4.fna.fbcdn.net/v/t39.30808-6/441507173_2590551781140222_7698675451629930267_n.jpg?_nc_cat=102&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeFMAWg_szq1Vmd7ctgkKBbvoWpabeB-TJShalpt4H5MlB_HCMrCYWku1PsyhCLMqd7iYZ9DY9ZaZgrvMXPMf_ma&_nc_ohc=27uehTqGTGQQ7kNvwFYqUSA&_nc_oc=AdnNcNwdu0F_c5183UCWuMFsCgXaW6oBeL4s9kJls_k0lmlLHQNWdU-zoG-FDTp0-8c&_nc_zt=23&_nc_ht=scontent.fmnl8-4.fna&_nc_gid=2S9KfF1B7ZLA9hthd8RD7g&oh=00_Afarf_gaBNitvfysQupFzBjFWQP1hwcCKamh4cLhOJvCaQ&oe=68CBEA73"
            alt="Portrait of Khyle Alegre"
            loading="lazy"
          />
        </figure>
        <div class="card-body">
          <h3>Khyle Alegre</h3>
          <p class="role"><b><i>Capstone Adviser</i></b></p>
          <p>A capstone adviser guides students through their project by providing feedback, ensuring research quality, and helping them meet academic standards. They mentor, review progress, and support problem-solving.</p>
        </div>
      </article>
    </section>

    <!-- Services anchor to keep link working -->
    <div id="services" class="spacer" aria-hidden="true"></div>
  </main>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="container">
      <p>Â© <span id="year"></span> Moral Matrix</p>
    </div>
  </footer>

  <script>
    (function () {
      const btn = document.getElementById('hamburgerBtn');
      const drawer = document.getElementById('navDrawer');
      const overlay = document.getElementById('navOverlay');
      const closeBtn = document.getElementById('closeDrawer');
      if (!btn || !drawer || !overlay || !closeBtn) return;

      const focusable = () => drawer.querySelectorAll('a, button:not([disabled])');

      function openDrawer() {
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        overlay.hidden = false;
        document.body.classList.add('no-scroll');
        btn.setAttribute('aria-expanded', 'true');

        const items = focusable();
        if (items.length) items[0].focus();
      }

      function closeDrawer() {
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
        overlay.hidden = true;
        document.body.classList.remove('no-scroll');
        btn.setAttribute('aria-expanded', 'false');
        btn.focus();
      }

      btn.addEventListener('click', () => {
        if (drawer.classList.contains('open')) {
          closeDrawer();
        } else {
          openDrawer();
        }
      });

      closeBtn.addEventListener('click', closeDrawer);
      overlay.addEventListener('click', closeDrawer);

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && drawer.classList.contains('open')) {
          closeDrawer();
        }
      });

      drawer.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;
        const items = Array.from(focusable());
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];

        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      });
    })();

    (function () {
      const yearEl = document.getElementById('year');
      if (yearEl) {
        yearEl.textContent = new Date().getFullYear();
      }
    })();

    (function () {
      const backToTop = document.getElementById('backToTop');
      if (!backToTop) return;

      const toggleVisibility = () => {
        if (window.scrollY > 360) {
          backToTop.classList.add('is-visible');
        } else {
          backToTop.classList.remove('is-visible');
        }
      };

      backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });

      window.addEventListener('scroll', toggleVisibility, { passive: true });
      toggleVisibility();
    })();
  </script>
</body>
</html>
