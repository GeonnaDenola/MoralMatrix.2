<?php
include '../includes/header.php';
include '../config.php';

include __DIR__ . '/_scanner.php';

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="/MoralMatrix/css/global.css">
</head>
<body>

<!-- Sidebar toggle (hamburger) -->
<button id="sidebarToggle" class="sidebar-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">☰</button>

<!-- Off-canvas sidebar -->
<aside id="sidebar" class="sidebar" aria-hidden="true">
  <div class="sidebar-header">
    <span>Menu</span>
    <button id="sidebarClose" class="sidebar-close" aria-label="Close">✕</button>
  </div>

  <div id="pageButtons" class="sidebar-links">
    <?php include 'page_buttons.php' ?>
  </div>
</aside>

<!-- Backdrop for the sidebar -->
<div id="sidebarBackdrop" class="sidebar-backdrop hidden"></div>

    <div class="right-container">
     <h2 class="page-title">Dashboard</h2>
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
    // Fetch and render students
    function loadStudents(filters = {}) {
        fetch("get_students.php")
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById("studentContainer");
            container.innerHTML = ""; // Clear previous content

            // Filter results
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

            // Render student cards
            filtered.forEach(student => {
                const card = document.createElement("div");
                card.classList.add("card");

                card.onclick = () => viewStudent(student.student_id);

                card.innerHTML = `
                    <div class="left">
                        <img src="${student.photo ? '../admin/uploads/' + student.photo : 'placeholder.png'}" alt="Photo">
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

    // Apply filters
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

    // Predefined dropdown data
    const courses = {
        'IBCE': ['BSIT','BSCA', 'BSA', 'BSOA', 'BSE', 'BSMA'],
        'IHTM': ['BSTM','BSHM'],
        'IAS': ['BSBIO', 'ABH', 'BSLM'],
        'ITE': ['BSED-ENG', 'BSED-FIL', 'BSED-MATH', 'BEED', 'BSED-SS', 'BTVTE', 'BSED-SCI', 'BPED']
    };

    const levels = ["1", "2", "3", "4"];
    const sections = ["A", "B", "C", "D"];

    // Elements
    const instituteSelect = document.querySelector(".institute");
    const courseSelect = document.querySelector(".course");
    const levelSelect = document.querySelector(".level");
    const sectionSelect = document.querySelector(".section");

    // Update course dropdown when institute changes
    instituteSelect.addEventListener("change", function () {
        const selectedInstitute = this.value;

        // reset course dropdown
        courseSelect.innerHTML = '<option value="">--Course--</option>';

        if (selectedInstitute && courses[selectedInstitute]) {
            courses[selectedInstitute].forEach(course => {
                const opt = document.createElement("option");
                opt.value = course;
                opt.textContent = course;
                courseSelect.appendChild(opt);
            });
        }

        // reload students immediately by institute
        filterStudents();
    });

    // Reload students directly when course is changed (independent filter)
    courseSelect.addEventListener("change", filterStudents);

    // Populate levels dropdown once
    levels.forEach(level => {
        const opt = document.createElement("option");
        opt.value = level;
        opt.textContent = level;
        levelSelect.appendChild(opt);
    });

    // Populate sections dropdown once
    sections.forEach(section => {
        const opt = document.createElement("option");
        opt.value = section;
        opt.textContent = section;
        sectionSelect.appendChild(opt);
    });

    // Add event listeners for secondary refiners
    levelSelect.addEventListener("change", filterStudents);
    sectionSelect.addEventListener("change", filterStudents);
    document.getElementById("search").addEventListener("keyup", filterStudents);

    // Load all students on page load
    loadStudents();

(function(){
  const sidebar   = document.getElementById('sidebar');
  const openBtn   = document.getElementById('sidebarToggle');
  const closeBtn  = document.getElementById('sidebarClose');
  const backdrop  = document.getElementById('sidebarBackdrop');

  function openSidebar(){
    sidebar.classList.add('open');
    sidebar.setAttribute('aria-hidden','false');
    openBtn.setAttribute('aria-expanded','true');
    backdrop.classList.remove('hidden');
    document.body.classList.add('modal-open'); // reuse scroll lock
  }
  function closeSidebar(){
    sidebar.classList.remove('open');
    sidebar.setAttribute('aria-hidden','true');
    openBtn.setAttribute('aria-expanded','false');
    backdrop.classList.add('hidden');
    document.body.classList.remove('modal-open');
  }

  openBtn.addEventListener('click', openSidebar);
  closeBtn.addEventListener('click', closeSidebar);
  backdrop.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') closeSidebar(); });
})();
</script> 
</body>
</html>
