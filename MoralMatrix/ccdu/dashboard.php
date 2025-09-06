<?php
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

    <div class="left-container">
        <div id="institutesButtons">
            <button type="button" data-value="ibce">IBCE</button>
            <button type="button" data-value="ihtm">IHTM</button>
            <button type="button" data-value="ite">ITE</button>
            <button type="button" data-value="ias">IAS</button>
        </div>
    </div>

    <div class="right-container">
        <h2>Dashboard</h2>
        <input type="text" id="search" placeholder="Search..." onkeyup="">

        <div class="cardContainer" id="studentContainer">
            Loading...
        </div>
    </div>

<script>
    function loadStudents(){
        fetch("get_students.php")
        .then(response => response.json())
        .then(data =>{
            const container = document.getElementById("studentContainer");
            container.innerHTML = ""; // Clear Loading

            if (data.length === 0){
                container.innerHTML = "<p>No student records found.</p>";
                return;
            }

            data.forEach(student =>{
                const card = document.createElement("div");
                card.classList.add("card");

                card.onclick = () => viewStudent(student.record_id);

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

    function viewStudent(id){
        window.location.href="view_student.php?id=" + id;
    }
    loadStudents();
</script> 
</body>
</html>