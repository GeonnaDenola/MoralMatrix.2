<?php
include '../includes/student_header.php';
include '../config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Handbook</title>
    <link rel="stylesheet" href="../css/student_handbook.css">
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <h2>Contents</h2>
            <ul>
                <li><a href="#light-offenses">Light Offenses</a></li>
                <li><a href="#moderate-offenses">Less Grave Offenses</a></li>
                <li><a href="#grave-offenses">Grave Offenses</a></li>
            </ul>
        </aside>

        <main class="content">
            <header>
                <h1>Student Handbook</h1>
                <p class="subtitle">Standards of Conduct</p>
            </header>

            <section id="light-offenses" class="handbook-section">
                <h2>1. Light Offenses</h2>
                <p>Light offenses are punished by fine or warning. Commission of three light offenses aggravates the nature of offense to less grave (moderate) and grave depending on the likelihood of habitual delinquency. The following are considered light offenses:</p>
                <ul>
                    <li>Violation of the Policy on ID, school uniform and attire</li>
                    <li>Violation of the Policy on the use of school facilities</li>
                    <li>Loitering along the hallway during class hours</li>
                </ul>
            </section>

            <section id="moderate-offenses" class="handbook-section">
                <h2>2. Less Grave Offenses (Moderate)</h2>
                <p>Offenses which are not very serious in nature. Suspension from school not to exceed three (3) days may be imposed. Parents must be informed by the Office of the Discipline Services or the Dean of any misconduct requiring disciplinary action.</p>
                <ul>
                    <li>Use of curses and vulgar words and roughness in all aspects of behavior.</li>
                    <li>Use of cellular phones and other gadgets during classes and/or academic functions. Playing loud music inside the classroom or corridors during break time.</li>
                    <li>Posting of posters, streamers, or banners within school premises without prior permission or approval.</li>
                    <li>Public display of intimacy inside or outside the college while in uniform.</li>
                    <li>Deliberate cutting of classes or walking out during class hours.</li>
                    <li>Playing loud music and performing other disruptive acts during classes.</li>
                </ul>
            </section>

            <section id="grave-offenses" class="handbook-section">
                <h2>3. Grave Offenses</h2>
                <p>For a persistent offender or one guilty of a serious offense, a suspension for not more than one (1) year may be imposed. The school should forward information to the Commission of Higher Education Regional Office within ten (10) days of the case resolution.</p>
                <ul>
                    <li>Smoking, gambling, or drinking hard drinks while in school uniform, even outside campus</li>
                    <li>Vandalism</li>
                    <li>Theft and willful destruction of school equipment and properties</li>
                    <li>Hooliganism and brawls on campus</li>
                    <li>Violation of the Dangerous Drugs Law and other related laws</li>
                    <li>Forging, falsifying, and tampering of official school documents and records</li>
                    <li>Carrying firearms, explosives, or deadly weapons over 1.5 inches within school premises</li>
                    <li>Use of offensive words or disrespectful behavior towards faculty, administrators, non-teaching personnel, or co-students</li>
                    <li>Dishonesty and cheating in any forms</li>
                    <li>Gross misconduct</li>
                    <li>Hazing</li>
                    <li>Drunkenness/Bringing intoxicating beverages inside campus</li>
                    <li>Assaulting a co-student or school personnel, including cybercrime violations</li>
                    <li>Instigating or leading illegal strikes or activities stopping classes</li>
                    <li>Preventing or threatening anyone from entering the school or attending classes</li>
                </ul>
            </section>
        </main>
    </div>

    <script>
        // Smooth scroll for sidebar links
        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', function(e){
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({ behavior: 'smooth' });
            });
        });
    </script>
</body>
</html>
