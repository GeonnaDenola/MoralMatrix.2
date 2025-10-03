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
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Dashboard</title>

  <!-- New, separated stylesheet -->
  <link rel="stylesheet" href="../css/dashboard_ccd.css"/>
</head>
<body>
  <div class="app">
    <main class="main" role="main">
      <!-- Page header -->
      <header class="page-header">
        <div class="page-header__left">
          <h1 class="page-title">Dashboard</h1>
          <button class="btn btn-ghost" id="toggleFilters" type="button" aria-expanded="false" aria-controls="filters">
            <svg class="btn-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M3 6h18M3 12h18M3 18h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Filters
          </button>
        </div>
        <div class="page-header__right">
          <button class="btn btn-outline" id="btnClear" type="button" aria-label="Clear filters">
            <svg class="btn-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 18L18 6M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Clear
          </button>
        </div>
      </header>

      <!-- Filters -->
      <section class="filters" id="filters" data-collapsed="true">
        <div class="filters__grid">
          <label class="field field--search">
            <span class="field-label sr-only">Search</span>
            <div class="input-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M21 21l-4.2-4.2m1.2-4.8A7 7 0 1 1 5 5a7 7 0 0 1 13 7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
              <input type="text" id="search" class="input" placeholder="Search by ID or name…" aria-label="Search students by ID or name" autocomplete="off">
            </div>
          </label>

          <label class="field">
            <span class="field-label">Institute</span>
            <select class="input institute" aria-label="Filter by Institute">
              <option value="">All Institutes</option>
              <option value="IBCE">IBCE</option>
              <option value="IHTM">IHTM</option>
              <option value="IAS">IAS</option>
              <option value="ITE">ITE</option>
            </select>
          </label>

          <label class="field">
            <span class="field-label">Course</span>
            <select class="input course" aria-label="Filter by Course">
              <option value="">All Courses</option>
            </select>
          </label>

          <label class="field">
            <span class="field-label">Level</span>
            <select class="input level" aria-label="Filter by Level">
              <option value="">All Levels</option>
            </select>
          </label>

          <label class="field">
            <span class="field-label">Section</span>
            <select class="input section" aria-label="Filter by Section">
              <option value="">All Sections</option>
            </select>
          </label>
        </div>
      </section>

      <!-- Cards -->
      <section class="cards" aria-label="Students">
        <div class="cardContainer" id="studentContainer" aria-live="polite"></div>
      </section>
    </main>
  </div>

  <script>
    // --- Options (UI only) ---
    const courses = {
      'IBCE': ['BSIT','BSCA','BSA','BSOA','BSE','BSMA'],
      'IHTM': ['BSTM','BSHM'],
      'IAS' : ['BSBIO','ABH','BSLM'],
      'ITE' : ['BSED-ENG','BSED-FIL','BSED-MATH','BEED','BSED-SS','BTVTE','BSED-SCI','BPED']
    };
    const levels = ["1","2","3","4"];
    const sections = ["A","B","C","D"];

    const instituteSelect = document.querySelector(".institute");
    const courseSelect     = document.querySelector(".course");
    const levelSelect      = document.querySelector(".level");
    const sectionSelect    = document.querySelector(".section");
    const searchInput      = document.getElementById("search");
    const btnClear         = document.getElementById("btnClear");
    const container        = document.getElementById("studentContainer");
    const toggleFiltersBtn = document.getElementById("toggleFilters");
    const filtersEl        = document.getElementById("filters");

    // --- Small helpers ---
    function skeleton(count = 8){
      const items = Array.from({length: count}).map(() => `
        <div class="card skeleton" aria-hidden="true">
          <div class="card-left">
            <div class="avatar sk"></div>
            <div class="meta">
              <div class="sk sk-line"></div>
              <div class="sk sk-line short"></div>
              <div class="sk sk-line tiny"></div>
            </div>
          </div>
          <div class="card-right"><span class="pill sk-pill"></span></div>
        </div>
      `).join("");
      container.innerHTML = items;
    }

    function viewStudent(student_id){
      window.location.href = "view_student.php?student_id=" + encodeURIComponent(student_id);
    }

    function loadStudents(filters = {}) {
      skeleton(8);
      fetch("get_students.php")
        .then(response => response.json())
        .then(data => {
          container.innerHTML = "";

          const q = (filters.search || "").toLowerCase().trim();

          const filtered = data.filter(s => {
            return (!filters.institute || s.institute === filters.institute) &&
                   (!filters.course || s.course === filters.course) &&
                   (!filters.level || s.level === filters.level) &&
                   (!filters.section || s.section === filters.section) &&
                   (!q || (
                    String(s.student_id).toLowerCase().includes(q) ||
                    String(s.first_name).toLowerCase().includes(q) ||
                    String(s.last_name).toLowerCase().includes(q)
                   ));
          });

          if (filtered.length === 0) {
            container.innerHTML = "<div class='empty' role='status'>No student records found.</div>";
            return;
          }

          filtered.forEach(student => {
            const card = document.createElement("button");
            card.type = "button";
            card.className = "card";
            card.onclick = () => viewStudent(student.student_id);

            const mid = student.middle_name ? ` ${student.middle_name}` : '';
            const photo = student.photo ? `../admin/uploads/${student.photo}` : '../admin/uploads/placeholder.png';

            card.innerHTML = `
              <div class="card-left">
                <img class="avatar" src="${photo}" alt="Photo of ${student.first_name} ${student.last_name}" loading="lazy" decoding="async">
                <div class="meta">
                  <div class="id">${student.student_id}</div>
                  <div class="name">${student.last_name}, ${student.first_name}${mid}</div>
                  <div class="sub">${student.institute} • ${student.course} • ${student.level}-${student.section}</div>
                </div>
              </div>
              <div class="card-right">
                <span class="pill" title="Level & Section">${student.level}-${student.section}</span>
              </div>
            `;
            container.appendChild(card);
          });
        })
        .catch(error => {
          container.innerHTML = "<div class='empty error' role='alert'>Error loading data.</div>";
          console.error("Error fetching student data:", error);
        });
    }

    function filterStudents() {
      const filters = {
        institute: instituteSelect.value,
        course: courseSelect.value,
        level: levelSelect.value,
        section: sectionSelect.value,
        search: searchInput.value
      };
      loadStudents(filters);
    }

    // --- Wire up selects ---
    instituteSelect.addEventListener("change", function () {
      const selected = this.value;
      courseSelect.innerHTML = '<option value="">All Courses</option>';
      if (selected && courses[selected]) {
        courses[selected].forEach(c => {
          const opt = document.createElement("option");
          opt.value = c; opt.textContent = c; courseSelect.appendChild(opt);
        });
      }
      filterStudents();
    });

    levels.forEach(level => {
      const opt = document.createElement("option");
      opt.value = level; opt.textContent = level; levelSelect.appendChild(opt);
    });
    sections.forEach(section => {
      const opt = document.createElement("option");
      opt.value = section; opt.textContent = section; sectionSelect.appendChild(opt);
    });

    courseSelect.addEventListener("change", filterStudents);
    levelSelect.addEventListener("change", filterStudents);
    sectionSelect.addEventListener("change", filterStudents);
    searchInput.addEventListener("input", filterStudents);

    // Clear filters button
    btnClear.addEventListener("click", () => {
      searchInput.value = "";
      instituteSelect.value = "";
      courseSelect.innerHTML = '<option value="">All Courses</option>';
      levelSelect.value = "";
      sectionSelect.value = "";
      loadStudents();
    });

    // Mobile filter toggle (UI only)
    function setFiltersCollapsed(collapsed) {
      filtersEl.dataset.collapsed = String(collapsed);
      toggleFiltersBtn.setAttribute('aria-expanded', String(!collapsed));
    }
    toggleFiltersBtn.addEventListener('click', () => {
      const collapsed = filtersEl.dataset.collapsed !== 'false';
      setFiltersCollapsed(!collapsed);
    });
    // Show filters automatically on wider screens
    const mq = window.matchMedia('(min-width: 900px)');
    function handleMQ(e){ setFiltersCollapsed(!e.matches ? true : false); }
    mq.addEventListener ? mq.addEventListener('change', handleMQ) : mq.addListener(handleMQ);
    handleMQ(mq);

    // Initial load
    loadStudents();
  </script>
</body>
</html>
