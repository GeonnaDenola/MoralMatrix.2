<?php
include '../includes/student_header.php';
include '../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Student Handbook</title>
    <link rel="stylesheet" href="../css/student_handbook.css" />
</head>
<body>

<!-- Skip link for accessibility -->
<a class="skip-link" href="#main-content">Skip to content</a>

<div class="hb-container">
    <!-- Sidebar (Table of Contents) -->
    <aside class="hb-sidebar" aria-labelledby="toc-title">
        <div class="hb-sidebar__section">
            <h2 id="toc-title" class="hb-sidebar__title">Contents</h2>

            <!-- Search -->
            <label class="hb-search" aria-label="Search the handbook">
                <svg class="hb-search__icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="2" fill="none"></circle>
                    <line x1="15" y1="15" x2="22" y2="22" stroke="currentColor" stroke-width="2"></line>
                </svg>
                <input id="hbSearchInput" type="search" class="hb-search__input" placeholder="Search offenses…" autocomplete="off" />
                <button id="hbSearchClear" type="button" class="hb-search__clear" aria-label="Clear search">&times;</button>
            </label>

            <!-- TOC -->
            <nav class="hb-toc" aria-label="Handbook sections">
                <ul>
                    <li><a href="#light-offenses" data-spy-link>Light Offenses</a></li>
                    <li><a href="#moderate-offenses" data-spy-link>Less Grave Offenses</a></li>
                    <li><a href="#grave-offenses" data-spy-link>Grave Offenses</a></li>
                </ul>
            </nav>

            <!-- Quick actions -->
            <div class="hb-quick">
                <button id="hbExpandAll" type="button" class="hb-btn hb-btn--ghost">Expand all</button>
                <button id="hbCollapseAll" type="button" class="hb-btn hb-btn--ghost">Collapse all</button>
                <button id="hbPrint" type="button" class="hb-btn hb-btn--primary">Print</button>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <main id="main-content" class="hb-content" tabindex="-1">
        <header class="hb-header">
            <h1 class="hb-h1">Student Handbook</h1>
            <p class="hb-subtitle">Standards of Conduct</p>
        </header>

        <!-- Section 1 -->
        <section id="light-offenses" class="hb-section" aria-labelledby="light-title">
            <div class="hb-section__header">
                <h2 id="light-title" class="hb-h2">
                    <span class="hb-section__index">1.</span> Light Offenses
                </h2>
                <button class="hb-section__toggle" type="button" aria-expanded="true" aria-controls="light-body">
                    Collapse
                </button>
            </div>

            <div id="light-body" class="hb-section__body">
                <p>
                    Light offenses are punished by fine or warning. Commission of three light offenses aggravates the nature
                    of offense to less grave (moderate) and grave depending on the likelihood of habitual delinquency. The
                    following are considered light offenses:
                </p>
                <ul class="hb-list">
                    <li>Violation of the Policy on ID, school uniform and attire</li>
                    <li>Violation of the Policy on the use of school facilities</li>
                    <li>Loitering along the hallway during class hours</li>
                </ul>
            </div>
        </section>

        <!-- Section 2 -->
        <section id="moderate-offenses" class="hb-section" aria-labelledby="moderate-title">
            <div class="hb-section__header">
                <h2 id="moderate-title" class="hb-h2">
                    <span class="hb-section__index">2.</span> Less Grave Offenses (Moderate)
                </h2>
                <button class="hb-section__toggle" type="button" aria-expanded="true" aria-controls="moderate-body">
                    Collapse
                </button>
            </div>

            <div id="moderate-body" class="hb-section__body">
                <p>
                    Offenses which are not very serious in nature. Suspension from school not to exceed three (3) days may
                    be imposed. Parents must be informed by the Office of the Discipline Services or the Dean of any
                    misconduct requiring disciplinary action.
                </p>
                <ul class="hb-list">
                    <li>Use of curses and vulgar words and roughness in all aspects of behavior.</li>
                    <li>Use of cellular phones and other gadgets during classes and/or academic functions. Playing loud music inside the classroom or corridors during break time.</li>
                    <li>Posting of posters, streamers, or banners within school premises without prior permission or approval.</li>
                    <li>Public display of intimacy inside or outside the college while in uniform.</li>
                    <li>Deliberate cutting of classes or walking out during class hours.</li>
                    <li>Playing loud music and performing other disruptive acts during classes.</li>
                </ul>
            </div>
        </section>

        <!-- Section 3 -->
        <section id="grave-offenses" class="hb-section" aria-labelledby="grave-title">
            <div class="hb-section__header">
                <h2 id="grave-title" class="hb-h2">
                    <span class="hb-section__index">3.</span> Grave Offenses
                </h2>
                <button class="hb-section__toggle" type="button" aria-expanded="true" aria-controls="grave-body">
                    Collapse
                </button>
            </div>

            <div id="grave-body" class="hb-section__body">
                <p>
                    For a persistent offender or one guilty of a serious offense, a suspension for not more than one (1)
                    year may be imposed. The school should forward information to the Commission of Higher Education
                    Regional Office within ten (10) days of the case resolution.
                </p>
                <ul class="hb-list">
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
            </div>
        </section>

        <!-- No results state (shown when search filters everything out) -->
        <div id="hbNoResults" class="hb-empty" hidden>
            <p>No matches found. Try a different keyword.</p>
        </div>
    </main>
</div>

<!-- Back-to-top button -->
<button id="hbBackToTop" type="button" class="hb-backtotop" aria-label="Back to top">↑</button>

<script>
/* ====== Smooth scroll with fixed-header offset ====== */
(function () {
    // If your included site header is fixed, this prevents anchor content from hiding beneath it.
    // Adjust this if your global header height differs.
    const DEFAULT_OFFSET = 80;
    const root = document.documentElement;
    root.style.scrollPaddingTop = DEFAULT_OFFSET + 'px';
    root.style.scrollBehavior = 'smooth';
})();

/* ====== ScrollSpy (highlights active TOC link) ====== */
(function () {
    const links = document.querySelectorAll('[data-spy-link]');
    const sections = Array.from(links).map(l => document.querySelector(l.getAttribute('href'))).filter(Boolean);

    const byId = (id) => Array.from(links).find(a => a.getAttribute('href') === '#' + id);

    const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const link = byId(entry.target.id);
            if (!link) return;
            if (entry.isIntersecting) {
                links.forEach(a => a.classList.remove('is-active'));
                link.classList.add('is-active');
                // Update hash without jumping
                history.replaceState(null, '', '#' + entry.target.id);
            }
        });
    }, { rootMargin: '-40% 0px -55% 0px', threshold: 0 });

    sections.forEach(sec => io.observe(sec));
})();

/* ====== Section collapse/expand ====== */
(function () {
    const toggles = document.querySelectorAll('.hb-section__toggle');
    toggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const controls = document.getElementById(btn.getAttribute('aria-controls'));
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', String(!expanded));
            btn.textContent = expanded ? 'Expand' : 'Collapse';
            controls.hidden = expanded;
        });
    });

    document.getElementById('hbExpandAll').addEventListener('click', () => {
        toggles.forEach(btn => {
            const controls = document.getElementById(btn.getAttribute('aria-controls'));
            btn.setAttribute('aria-expanded', 'true');
            btn.textContent = 'Collapse';
            controls.hidden = false;
        });
    });

    document.getElementById('hbCollapseAll').addEventListener('click', () => {
        toggles.forEach(btn => {
            const controls = document.getElementById(btn.getAttribute('aria-controls'));
            btn.setAttribute('aria-expanded', 'false');
            btn.textContent = 'Expand';
            controls.hidden = true;
        });
    });
})();

/* ====== Keyword search (filters list items and sections) ====== */
(function () {
    const input = document.getElementById('hbSearchInput');
    const clear = document.getElementById('hbSearchClear');
    const sections = document.querySelectorAll('.hb-section');
    const emptyState = document.getElementById('hbNoResults');

    function normalize(str) { return str.toLowerCase().trim(); }

    function highlightText(el, query) {
        // Remove previous highlights
        const markSelector = 'mark[data-hb]';
        el.querySelectorAll(markSelector).forEach(m => m.replaceWith(...m.childNodes));

        if (!query) return;
        const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, {
            acceptNode: (node) => normalize(node.nodeValue).includes(query) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT
        });
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(node => {
            const idx = normalize(node.nodeValue).indexOf(query);
            if (idx >= 0) {
                const range = document.createRange();
                range.setStart(node, idx);
                range.setEnd(node, idx + query.length);
                const mark = document.createElement('mark');
                mark.dataset.hb = '1';
                range.surroundContents(mark);
            }
        });
    }

    function runFilter() {
        const q = normalize(input.value);
        let anyVisible = false;

        sections.forEach(section => {
            const body = section.querySelector('.hb-section__body');
            const items = body.querySelectorAll('.hb-list li');
            let visibleCount = 0;

            items.forEach(li => {
                const text = normalize(li.textContent);
                const match = q === '' || text.includes(q);
                li.hidden = !match;
                if (match) visibleCount++;
                highlightText(li, q);
            });

            // If a section has at least one visible item or the description matches, keep it visible
            const descMatch = normalize(body.querySelector('p')?.textContent || '').includes(q);
            const showSection = q === '' || visibleCount > 0 || descMatch;

            section.hidden = !showSection;
            if (showSection) anyVisible = true;

            // Auto-expand matching sections
            const toggle = section.querySelector('.hb-section__toggle');
            const panel = document.getElementById(toggle.getAttribute('aria-controls'));
            if (showSection && q) {
                toggle.setAttribute('aria-expanded', 'true');
                toggle.textContent = 'Collapse';
                panel.hidden = false;
            }
        });

        emptyState.hidden = anyVisible;
        clear.hidden = input.value.length === 0;
    }

    input.addEventListener('input', runFilter);
    clear.addEventListener('click', () => { input.value = ''; input.focus(); runFilter(); });
    runFilter();
})();

/* ====== Back to top ====== */
(function () {
    const btn = document.getElementById('hbBackToTop');
    const onScroll = () => { btn.classList.toggle('is-visible', window.scrollY > 600); };
    window.addEventListener('scroll', onScroll, { passive: true });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    onScroll();
})();

/* ====== Print ====== */
document.getElementById('hbPrint').addEventListener('click', () => window.print());
</script>
</body>
</html>
