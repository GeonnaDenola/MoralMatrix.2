<?php
include '../config.php';
include '../includes/security_header.php';
include __DIR__ . '/_scanner.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Student</title>
    <link rel="stylesheet" href="../css/security_report_student.css">
</head>
<body>

    <div class="right-container">
        <div class="report-shell">
            <section class="page-header">
                <p class="eyebrow">Report Student</p>
                <h1>Locate students before filing a report</h1>
                <p>Search, filter, and open a student's profile. From there you can capture the full report with supporting details in just a few clicks.</p>
            </section>

            <section class="filters-card">
                <div class="filters-header">
                    <div>
                        <h2>Directory Filters</h2>
                        <p>Use the filters below to narrow the student roster by institute, course, level, or section.</p>
                    </div>
                </div>

                <div class="filters-grid">
                    <div class="form-control search-field">
                        <label for="search">Search student</label>
                        <div class="input-shell">
                            <span class="form-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="7"></circle>
                                    <line x1="20" y1="20" x2="16.65" y2="16.65"></line>
                                </svg>
                            </span>
                            <input type="text" id="search" placeholder="Search by ID or name">
                        </div>
                    </div>

                    <div class="form-control">
                        <label for="institute">Institute</label>
                        <select class="institute" id="institute">
                            <option value="">All institutes</option>
                            <option value="IBCE">IBCE</option>
                            <option value="IHTM">IHTM</option>
                            <option value="IAS">IAS</option>
                            <option value="ITE">ITE</option>
                        </select>
                    </div>

                    <div class="form-control">
                        <label for="course">Course</label>
                        <select class="course" id="course">
                            <option value="">All courses</option>
                        </select>
                    </div>

                    <div class="form-control">
                        <label for="level">Level</label>
                        <select class="level" id="level">
                            <option value="">All levels</option>
                        </select>
                    </div>

                    <div class="form-control">
                        <label for="section">Section</label>
                        <select class="section" id="section">
                            <option value="">All sections</option>
                        </select>
                    </div>
                </div>

                <div class="filters-actions">
                    <button type="button" class="button ghost" id="clearFilters">Clear filters</button>
                    <button type="button" class="button primary" id="refreshStudents">Refresh list</button>
                </div>
            </section>

            <section class="results-card">
                <div class="results-header">
                    <div>
                        <h2>Student directory</h2>
                        <p class="results-subtitle"><span id="resultCount">0</span> students found</p>
                    </div>
                    <div class="results-actions">
                        <span class="results-subtitle">Click a student to open their profile</span>
                    </div>
                </div>


                <div class="cardContainer" id="studentContainer">
                    <div class="loading-state">Loading students...</div>
                </div>

                <!-- PAGER (bottom) -->
                <nav id="pagerBottom" class="pagerbar" aria-label="Pagination"></nav>
            </section>
        </div>
    </div>

<script>
    const studentContainer = document.getElementById("studentContainer");
    const resultCountEl = document.getElementById("resultCount");
    const searchInput = document.getElementById("search");
    const instituteSelect = document.querySelector(".institute");
    const courseSelect = document.querySelector(".course");
    const levelSelect = document.querySelector(".level");
    const sectionSelect = document.querySelector(".section");
    const pagerTop = document.getElementById("pagerTop");
    const pagerBottom = document.getElementById("pagerBottom");

    // Pagination state
    let currentPage = 1;
    const perPage = 12; // change page size if you like
    let currentFilters = {};

    // Fetch and render students (with pagination)
    function loadStudents(filters = currentFilters, page = currentPage) {
        if (!studentContainer) return;

        currentFilters = filters;
        currentPage = page;

        studentContainer.innerHTML = `<div class="loading-state">Loading students...</div>`;

        fetch("get_students.php")
        .then(response => response.json())
        .then(data => {
            // Filter
            const filtered = data.filter(student => {
                return (!filters.institute || student.institute === filters.institute) &&
                       (!filters.course || student.course === filters.course) &&
                       (!filters.level || student.level === filters.level) &&
                       (!filters.section || student.section === filters.section) &&
                       (!filters.search || (
                           (student.student_id || '').toLowerCase().includes(filters.search) ||
                           (student.first_name || '').toLowerCase().includes(filters.search) ||
                           (student.last_name || '').toLowerCase().includes(filters.search)
                       ));
            });

            // Total count text (always total filtered)
            if (resultCountEl) resultCountEl.textContent = String(filtered.length);

            // Pagination math
            const total = filtered.length;
            const lastPage = Math.max(1, Math.ceil(total / perPage));
            if (currentPage > lastPage) currentPage = lastPage;

            const start = (currentPage - 1) * perPage;
            const end = start + perPage;
            const pageItems = filtered.slice(start, end);

            // Render cards for current page
            studentContainer.innerHTML = "";
            if (!pageItems.length) {
                studentContainer.innerHTML = `<div class="empty-state">We couldn't find any students that match the current filters. Adjust your search criteria and try again.</div>`;
            } else {
                pageItems.forEach(student => {
                    const card = document.createElement("div");
                    card.classList.add("student-card");
                    card.onclick = () => viewStudent(student.student_id);

                    const section = student.section ? student.section : "";
                    const yearLabel = student.level ? `Year ${student.level}${section}` : "Level pending";

                    card.innerHTML = `
                        <img class="student-photo" src="${student.photo ? '../admin/uploads/' + student.photo : 'placeholder.png'}" alt="Student photo">
                        <div class="student-details">
                            <div class="student-id">${student.student_id}</div>
                            <div class="student-name">${student.last_name}, ${student.first_name} ${student.middle_name ?? ""}</div>
                            <div class="student-tags">
                                <span class="badge">${student.institute}</span>
                                <span class="badge">${student.course}</span>
                                <span class="badge">${yearLabel}</span>
                            </div>
                        </div>
                        <span class="chevron">&rarr;</span>
                    `;
                    studentContainer.appendChild(card);
                });
            }

            // Render pagerbars
            renderPager(pagerTop, total, currentPage, lastPage);
            renderPager(pagerBottom, total, currentPage, lastPage);
        })
        .catch(error => {
            if (resultCountEl) resultCountEl.textContent = "0";
            if (studentContainer) {
                studentContainer.innerHTML = `<div class="empty-state error-state">We couldn't load the student list right now. Please try again shortly.</div>`;
            }
            if (pagerTop) pagerTop.innerHTML = '';
            if (pagerBottom) pagerBottom.innerHTML = '';
            console.error("Error fetching student data: ", error);
        });
    }

    function renderPager(container, total, page, lastPage) {
        if (!container) return;
        if (total === 0) { container.innerHTML = ''; return; }

        const prevDisabled = page <= 1;
        const nextDisabled = page >= lastPage;

        container.innerHTML = `
          <div class="pagerbar__status">Page ${page} of ${lastPage} • ${total} total</div>
          <div class="pagerbar__controls">
            <a href="#" class="pagerbtn ${prevDisabled ? 'is-disabled' : ''}" data-nav="prev" aria-disabled="${prevDisabled}">← Prev</a>
            <a href="#" class="pagerbtn ${nextDisabled ? 'is-disabled' : ''}" data-nav="next" aria-disabled="${nextDisabled}">Next →</a>
          </div>
        `;

        const prev = container.querySelector('[data-nav="prev"]');
        const next = container.querySelector('[data-nav="next"]');

        if (prev && !prevDisabled) {
            prev.addEventListener('click', e => {
                e.preventDefault();
                loadStudents(currentFilters, page - 1);
            });
        }
        if (next && !nextDisabled) {
            next.addEventListener('click', e => {
                e.preventDefault();
                loadStudents(currentFilters, page + 1);
            });
        }
    }

    // Apply filters (always go back to page 1)
    function filterStudents() {
        const filters = {
            institute: instituteSelect.value,
            course: courseSelect.value,
            level: levelSelect.value,
            section: sectionSelect.value,
            search: (searchInput.value || '').toLowerCase()
        };
        loadStudents(filters, 1);
    }

    function resetFilters() {
        searchInput.value = "";
        instituteSelect.value = "";
        courseSelect.innerHTML = '<option value="">All courses</option>';
        levelSelect.value = "";
        sectionSelect.value = "";
        filterStudents();
    }

    function viewStudent(student_id){
        window.location.href = "view_student.php?student_id=" + encodeURIComponent(student_id);
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

    // Update course dropdown when institute changes
    instituteSelect.addEventListener("change", function () {
        const selectedInstitute = this.value;
        courseSelect.innerHTML = '<option value="">All courses</option>';
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

    // Reload on other refiners
    courseSelect.addEventListener("change", filterStudents);

    // Populate levels
    levels.forEach(level => {
        const opt = document.createElement("option");
        opt.value = level;
        opt.textContent = level;
        levelSelect.appendChild(opt);
    });

    // Populate sections
    sections.forEach(section => {
        const opt = document.createElement("option");
        opt.value = section;
        opt.textContent = section;
        sectionSelect.appendChild(opt);
    });

    levelSelect.addEventListener("change", filterStudents);
    sectionSelect.addEventListener("change", filterStudents);
    searchInput.addEventListener("keyup", filterStudents);
    document.getElementById("clearFilters").addEventListener("click", resetFilters);
    document.getElementById("refreshStudents").addEventListener("click", filterStudents);

    // Load all students on page load
    loadStudents();
</script>
</body>
</html>
