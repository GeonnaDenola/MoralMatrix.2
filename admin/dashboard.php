<?php

include '../includes/header.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=p, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
<!--    <header>
        <div class="nav-left">
            <a href="">moral matrix</a>
        </div>

        <div class="nav-right">
            <form action="/MoralMatrix/logout.php" method="post">
                <button type="submit" name="logout">Logout</button>
            </form>
        </div>
    </header>
-->
    <h2>WELCOME ADMIN</h2>

    <a href="add_users.php">
        <button>Add Users</button>
    </a>

    <h3>Accounts List</h3>

    <!-- Dropdown to select account type -->
<div>
    <label for="accountType">Filter by Account Type: </label>
    <select id="accountType" onchange="loadAccounts()">
        <option value="">-- Select Account Type --</option>
        <option value="student">Student</option>
        <option value="faculty">Faculty</option>
        <option value="security">Security</option>
        <option value="ccdu">CCDU</option>
    </select>
</div>

<h3 id="sectionTitle">Accounts</h3>
<div class="container" id="accountContainer">Please select an account type.</div>

<script>
function loadAccounts(){
    const selectedType = document.getElementById("accountType").value;
    const container = document.getElementById("accountContainer");
    const title = document.getElementById("sectionTitle");

    if(!selectedType){
        container.innerHTML = "<p>Please select an account type.</p>";
        title.textContent = "Accounts";
        return;
    }

    title.textContent = selectedType.charAt(0).toUpperCase() + selectedType.slice(1) + " Accounts";
    container.innerHTML = "Loading...";

    fetch("get_accounts.php")
        .then(response => response.json())
        .then(data=>{
            container.innerHTML = "";

            const filtered = data.filter(acc => acc.account_type === selectedType);

            if (filtered.length === 0){
                container.innerHTML = "<p>No records found.</p>";
                return;
            }

            filtered.forEach(acc => {
                const card = document.createElement("div");
                card.classList.add("card");

                card.innerHTML = `
                    <div class="left" onclick="viewAccount(${acc.record_id}, '${acc.account_type}')" style="cursor:pointer;">
                        <img src="${acc.photo}" alt="Photo">
                        <div class="info">
                            <strong>ID:</strong> ${acc.user_id}<br>
                            <strong>Name:</strong> ${acc.first_name} ${acc.last_name}<br>
                            <strong>Email:</strong> ${acc.email}<br>
                            <strong>Mobile:</strong> ${acc.mobile}
                        </div>
                    </div>
                    <div class="actions">
                        <button onclick="editAccount(${acc.record_id}, '${acc.account_type}'); event.stopPropagation();">✏️ Edit</button>
                        <button onclick="deleteAccount(${acc.record_id}, '${acc.account_type}'); event.stopPropagation();">🗑 Delete</button>
                    </div>
                `;
                container.appendChild(card);
            });
        })
        .catch(error => {
            container.innerHTML = "<p>Error loading data.</p>";
            console.error("Error fetching accounts: ", error);
        });
}

function editAccount(id, type){
    window.location.href = "edit_account.php?id=" + id + "&type=" + type;
}

function deleteAccount(id, type){
    if(!confirm("Are you sure you want to delete this account?")) return;

    fetch("delete_accounts.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "id=" + id + "&type=" + type
    })
    .then(response => response.json())
    .then(result => {
        if(result.success){
            alert("Account deleted successfully.");
            loadAccounts();
        }else{
            alert("Error: " + result.error);
        }
    })
    .catch(err => console.error("Delete error: ", err));
}
function viewAccount(id){
    window.location.href = "view_account.php?id=" + id + "&type=" + type;
}

</script>
</body>
</html>