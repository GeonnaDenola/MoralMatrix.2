<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=p, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <header>
        <div class="nav-left">
            <a href="">moral matrix</a>
        </div>

        <div class="nav-right">
            <form action="/MoralMatrix/logout.php" method="post">
                <button type="submit" name="logout">Logout</button>
            </form>
        </div>
    </header>

    <p>WELCOME ADMIN</p>

    <a href="add_users.php">
        <button>Add Users</button>
    </a>

    <div class="btn-group" id="buttons"></div>

    <div id="cardsContainer"></div>

    <div id="details"></div>

    <script>
        let accountsData = [];

        function loadAccounts(){
            fetch("get_accounts.php")
            .then(res => res.json())
            .then(data => {
                accountsData();
                renderSections("all");
            })
            .catch(err => {
                console.error(err);
                document.getElementById("cardsContainer").innerHTML = "Error loading accounts";
            });
        }
       
        function renderButtons(){
            const types =  ["all", ...new Set(accountsData.map(acc => acc.account_type))];
            const btnContainer = document.getElementById("buttons");
            btnContainer.innerHTML = "";

            types.forEach(type => {
                const btn = document.createElement("button");
                btn.classList.add("btn");
                if (type === "all") btn.classList.add("active");
                btn.innerText = type.toUpperCase();
                btn.onclick = () => {
                    document.querySelectorAll(".btn").forEach(b => b.classList.remove("active"));
                    btn.classList.add("active");
                    renderSections(type);
                };
                btnContainer.appendChild(btn);
            });
        }

        function renderSections(filterType){
            const container = document.getElementById("cardsContainer");
            container.innerHTML = "";

            const types = filterType === "all" ? [...new Set(accountsData.map(acc => acc.account_type))] : [filterType];

            types.forEach(type => {
                const filtered = accountsData.filter(acc => acc.account_type === type);

                if (filtered.length === 0) return;

                const section = document.createElement("div");
                section.classList.add("section");
                section.innerHTML = `<h2>${type.toUpperCase()} Accounts</h2>`;

                const cards = document.createElement("div");
                cards.classList.add("cards");

                filtered.forEach(acc => {
                    const card = document.createElement("div");
                    card.classList.add("card");
                    card.innerHTML = `
                        <strong>${acc.id_number}</strong><br>
                        ${acc.details.first_name ?? ""} ${acc.details.middle_name ?? ""} ${acc.details.last_name ?? ""}<br>
                        ${acc.email}
                    `;
                    card.onclick = () => showDetails(acc);
                    cards.appendChild(card);
                });

                section.appendChild(cards);
                container.appendChild(section);
            });
        }

        function showDetails(acc){
            const box = document.getElementById("details");
            box.style.display = "block";

            let html = `<h3>${acc.account_type.toUpperCase()} Account</h3>`;
            for (let key in acc.details) {
                html += `<strong>${key}</strong>: ${acc.details[key]}<br>`;
            }

            box.innerHTML = html;
        }

        loadAccounts();
        

    </script>
</body>
</html>