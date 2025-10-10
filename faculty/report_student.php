<?php

include '../config.php';
include '../includes/faculty_header.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty - Report Student</title>
    <link rel="stylesheet" href="../css/report_student.css">
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

    function loadStudents(filters = {}) {
        if (!studentContainer) {
            return;
        }

        studentContainer.innerHTML = `<div class="loading-state">Loading students...</div>`;

        fetch("get_students.php")
        .then(response => response.json())
        .then(data => {
            studentContainer.innerHTML = "";

            let filtered = data.filter(student => {
                const instituteMatch = !filters.institute || student.institute === filters.institute;
                const courseMatch = !filters.course || student.course === filters.course;
                const levelMatch = !filters.level || student.level === filters.level;
                const sectionMatch = !filters.section || student.section === filters.section;

                let searchMatch = true;
                if (filters.search) {
                    const haystack = `${student.student_id} ${student.first_name} ${student.last_name} ${student.middle_name ?? ''}`.toLowerCase();
                    searchMatch = haystack.includes(filters.search);
                }

                return instituteMatch && courseMatch && levelMatch && sectionMatch && searchMatch;
            });

            if (resultCountEl) {
                resultCountEl.textContent = filtered.length.toString();
            }

            if (filtered.length === 0) {
                studentContainer.innerHTML = `<div class="empty-state">No student records match your filters.</div>`;
                return;
            }

            filtered.forEach(student => {
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
        })
        .catch(error => {
            if (resultCountEl) {
                resultCountEl.textContent = "0";
            }
            if (studentContainer) {
                studentContainer.innerHTML = `<div class="empty-state error-state">We couldn't load the student list right now. Please try again shortly.</div>`;
            }
            console.error("Error fetching student data: ", error);
        });
    }

    function filterStudents() {
        const filters = {
            institute: instituteSelect.value,
            course: courseSelect.value,
            level: levelSelect.value,
            section: sectionSelect.value,
            search: searchInput.value.toLowerCase()
        };
        loadStudents(filters);
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
    searchInput.addEventListener("keyup", filterStudents);
    document.getElementById("clearFilters").addEventListener("click", resetFilters);
    document.getElementById("refreshStudents").addEventListener("click", filterStudents);

    loadStudents();
</script> 
</body>
</html>
