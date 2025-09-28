<?php
include '../includes/header.php';
include '../config.php';
include __DIR__ . '/_scanner.php';

$active = basename($_SERVER['PHP_SELF']);
function activeClass($file){ global $active; return $active === $file ? ' is-active' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard</title>
  <link rel="stylesheet" href="/MoralMatrix/css/global.css"/>
</head>
<body>

  <!-- Keep your original Menu button exactly as before -->
  <button id="openMenu" class="menu-launcher" aria-controls="sideSheet" aria-expanded="false">Menu</button>
  <div class="page-top-pad"></div>

  <!-- Scrim -->
  <div id="sheetScrim" class="sidesheet-scrim" aria-hidden="true"></div>

  <!-- LEFT Sidebar (drawer) -->
  <nav id="sideSheet" class="sidesheet" aria-hidden="true" role="dialog" aria-label="Main menu">
    <div class="sidesheet-header">
      <span>Menu</span>
      <button id="closeMenu" class="sidesheet-close" aria-label="Close menu">✕</button>
    </div>

    <div class="sidesheet-rail">
      <a class="nav-tile<?php echo activeClass('dashboard.php'); ?>" href="dashboard.php" <?php echo $active==='dashboard.php'?'aria-current="page"':''; ?>>Dashboard</a>
      <div class="rail-sep"></div>
      <a class="nav-tile<?php echo activeClass('pending_reports.php'); ?>" href="pending_reports.php" <?php echo $active==='pending_reports.php'?'aria-current="page"':''; ?>>Pending Reports</a>
      <div class="rail-sep"></div>
      <a class="nav-tile<?php echo activeClass('community_validators.php'); ?>" href="community_validators.php" <?php echo $active==='community_validators.php'?'aria-current="page"':''; ?>>Community Service Validators</a>
      <div class="rail-sep"></div>
      <a class="nav-tile<?php echo activeClass('summary_report.php'); ?>" href="summary_report.php" <?php echo $active==='summary_report.php'?'aria-current="page"':''; ?>>Summary Report</a>
    </div>
  </nav>

  <div class="right-container">
    <h2>Dashboard</h2>
    <input type="text" id="search" placeholder="Search...">

    <div class="sort">
      <p>Sort by:</p>

      <select class="institute">
        <option value="">--Institute--</option>
        <option value="IBCE">IBCE</option>
        <option value="IHTM">IHTM</option>
        <option value="IAS">IAS</option>
        <option value="ITE">ITE</option>
      </select>

      <select class="course">
        <option value="">--Course--</option>
      </select>

      <select class="level">
        <option value="">--Level--</option>
      </select>

      <select class="section">
        <option value="">--Section--</option>
      </select>
    </div>

    <div class="cardContainer" id="studentContainer">
      Loading...
    </div>
  </div>

  <script>
    // --- Sidebar (drawer) open/close behavior ---
    const sheet = document.getElementById('sideSheet');
    const scrim = document.getElementById('sheetScrim');
    const openBtn = document.getElementById('openMenu');
    const closeBtn = document.getElementById('closeMenu');

    let lastFocusedEl = null;

    function trapFocus(container, e){
      const focusables = container.querySelectorAll(
        'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
      );
      if (focusables.length === 0) return;
      const first = focusables[0];
      const last = focusables[focusables.length - 1];

      if (e.key === 'Tab') {
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault(); first.focus();
        }
      }
    }

    function openSheet(){
      lastFocusedEl = document.activeElement;
      sheet.classList.add('open');
      scrim.classList.add('open');
      sheet.setAttribute('aria-hidden','false');
      scrim.setAttribute('aria-hidden','false');
      openBtn.setAttribute('aria-expanded','true');
      document.body.classList.add('no-scroll');

      // Focus inside the drawer
      setTimeout(() => {
        const firstLink = sheet.querySelector('.nav-tile, button, a, [tabindex]:not([tabindex="-1"])');
        (firstLink || sheet).focus();
      }, 10);

      sheet.addEventListener('keydown', focusTrapHandler);
    }

    function closeSheet(){
      sheet.classList.remove('open');
      scrim.classList.remove('open');
      sheet.setAttribute('aria-hidden','true');
      scrim.setAttribute('aria-hidden','true');
      openBtn.setAttribute('aria-expanded','false');
      document.body.classList.remove('no-scroll');

      sheet.removeEventListener('keydown', focusTrapHandler);

      if (lastFocusedEl) lastFocusedEl.focus();
    }

    function focusTrapHandler(e){ trapFocus(sheet, e); }

    openBtn.addEventListener('click', openSheet);
    closeBtn.addEventListener('click', closeSheet);
    scrim.addEventListener('click', closeSheet);
    document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') closeSheet(); });

    // --- Existing page logic (students/filtering) ---
    function loadStudents(filters = {}) {
      fetch("get_students.php")
        .then(response => response.json())
        .then(data => {
          const container = document.getElementById("studentContainer");
          container.innerHTML = "";

          let filtered = data.filter(student => {
            return (!filters.institute || student.institute === filters.institute) &&
                   (!filters.course || student.course === filters.course) &&
                   (!filters.level || student.level === filters.level) &&
                   (!filters.section || student.section === filters.section) &&
                   (!filters.search || (
                      student.student_id.toLowerCase().includes(filters.search) ||
                      student.first_name.toLowerCase().includes(filters.search) ||
                      student.last_name.toLowerCase().includes(filters.search)
                   ));
          });

          if (filtered.length === 0) {
            container.innerHTML = "<p>No student records found.</p>";
            return;
          }

          filtered.forEach(student => {
            const card = document.createElement("div");
            card.classList.add("card");
            card.onclick = () => viewStudent(student.student_id);

            card.innerHTML = `
              <div class="left">
                <img src="${student.photo ? '../admin/uploads/' + student.photo : '../admin/uploads/placeholder.png'}" alt="Photo">
                <div class="info">
                  <strong>${student.student_id}</strong><br>
                  ${student.last_name}, ${student.first_name} ${student.middle_name}<br>
                  ${student.institute}<br>
                  ${student.course} - ${student.level}${student.section}
                </div>
              </div>
            `;
            container.appendChild(card);
          });
        })
        .catch(error => {
          document.getElementById("studentContainer").innerHTML = "<p>Error Loading Data.</p>";
          console.error("Error fetching student data: ", error);
        });
    }

    function filterStudents() {
      const filters = {
        institute: document.querySelector(".institute").value,
        course: document.querySelector(".course").value,
        level: document.querySelector(".level").value,
        section: document.querySelector(".section").value,
        search: document.getElementById("search").value.toLowerCase()
      };
      loadStudents(filters);
    }

    function viewStudent(student_id){
      window.location.href = "view_student.php?student_id=" + student_id;
    }

    const courses = {
      'IBCE': ['BSIT','BSCA', 'BSA', 'BSOA', 'BSE', 'BSMA'],
      'IHTM': ['BSTM','BSHM'],
      'IAS': ['BSBIO', 'ABH', 'BSLM'],
      'ITE': ['BSED-ENG', 'BSED-FIL', 'BSED-MATH', 'BEED', 'BSED-SS', 'BTVTE', 'BSED-SCI', 'BPED']
    };

    const levels = ["1", "2", "3", "4"];
    const sections = ["A", "B", "C", "D"];

    const instituteSelect = document.querySelector(".institute");
    const courseSelect = document.querySelector(".course");
    const levelSelect = document.querySelector(".level");
    const sectionSelect = document.querySelector(".section");

    instituteSelect.addEventListener("change", function () {
      const selectedInstitute = this.value;
      courseSelect.innerHTML = '<option value="">--Course--</option>';

      if (selectedInstitute && courses[selectedInstitute]) {
        courses[selectedInstitute].forEach(course => {
          const opt = document.createElement("option");
          opt.value = course;
          opt.textContent = course;
          courseSelect.appendChild(opt);
        });
      }
      filterStudents();
    });

    courseSelect.addEventListener("change", filterStudents);

    levels.forEach(level => {
      const opt = document.createElement("option");
      opt.value = level;
      opt.textContent = level;
      levelSelect.appendChild(opt);
    });

    sections.forEach(section => {
      const opt = document.createElement("option");
      opt.value = section;
      opt.textContent = section;
      sectionSelect.appendChild(opt);
    });

    levelSelect.addEventListener("change", filterStudents);
    sectionSelect.addEventListener("change", filterStudents);
    document.getElementById("search").addEventListener("keyup", filterStudents);

    loadStudents();
  </script>
</body>
</html>
