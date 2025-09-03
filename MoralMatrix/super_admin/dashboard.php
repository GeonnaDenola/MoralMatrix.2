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
<!--    <header>
        <nav>
            <ul class="nav-left">
                <li><a href="#">MORAL MATRIX</a></li>
            </ul>  
            
            <form action="/MoralMatrix/logout.php" method="post">
                <button type="submit" name="logout">Logout</button>
            </form>
        </nav>
    </header> 
-->

    <h2>Super Admin Dashboard - Admin Accounts</h2>

    <a href="add_users.php" class="btn">Add Administrator</a>

    <h3>Account List</h3>
    
    <div class="container" id="adminContainer">
        Loading...
    </div>

    <script>
    //Fetch from PHP
    function loadAdmins(){
        fetch("/MoralMatrix/super_admin/get_admin.php")
            .then(response => response.json())
            .then(data=>{
                const container = document.getElementById("adminContainer");
                container.innerHTML = "";// clear Loading

                if (data.length === 0){
                    container.innerHTML = "<p>No records found.</p>";
                    return;
                }

                data.forEach(admin => {
                    const card = document.createElement("div");
                    card.classList.add("card");

                    card.innerHTML = `
                        <div class ="left">
                            <img src="${admin.photo ? 'uploads/' + admin.photo : 'placeholder.png'}" alt= "Photo">

                            <div class="info">
                                <strong>${admin.admin_id}</strong><br>
                                ${admin.first_name} ${admin.middle_name} ${admin.last_name}<br>
                                ${admin.email}<br>
                                ${admin.mobile}
                            </div>
                        </div>
                        <div class="actions">
                            <button onclick="editAdmin(${admin.record_id})">Edit</button>
                            <button onclick="deleteAdmin(${admin.record_id})">Delete</button>
                        </div>
                        `;
                        container.appendChild(card);
                });
            })
            .catch(error => {
                document.getElementById("adminContainer").innerHTML = "<p>Error Loading Data.</p>";
                console.error("Error fetching student data: ", error);
            });
    }

            function editAdmin(id){
                window.location.href = "edit_admin.php?id=" + id;
            }

            function deleteAdmin(id){
                if(!confirm("Are you sure you want to delete this admin?")) return;

                fetch("/MoralMatrix/super_admin/delete_admin.php", {
                    method: "POST",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "id=" + id
                })
                .then(response => response.json())
                .then(result => {
                    if(result.success){
                        alert("Admin deleted successfully.");
                        loadAdmins();
                    }else{
                        alert("Error: " + result.error);
                    }
                })
                .catch(err => console.error("Delete error: ", err));
            }

            loadAdmins();
        
    </script>
    
</body>
</html>