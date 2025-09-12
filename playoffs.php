<?php
declare(strict_types=1);
// Playoffs page Pro/Farm
require_once __DIR__ . '/includes/bootstrap.php';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Playoffs — UHA Portal</title>
    <?php require_once __DIR__ . '/includes/head-assets.php'; ?>
</head>

<body>
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>
    <?php require_once __DIR__ . '/includes/leaguebar.php'; ?>
<div class="site">  
    <!-- SCORE TICKER (nav.js only needs #ticker-track) -->

<div class="score-ticker" aria-label="Live Scores Ticker">
    <div class="ticker-viewport">
        <div class="ticker-track" id="ticker-track"></div>
    </div>
</div>
    <section class="playoffs-container">
        <div class="playoffs-card">
            <!-- LEFT: Leaders rail -->
            <aside>
                <div class="sidebar-title">Statistics</div>
                <div id="leadersStack" class="leadersStack">

                </div>
            </aside>

            <!-- CENTER: Feature + headlines -->
            <main class="main">

            <!-- Feature story -->
            <section class="feature">
                <div class="story-head">
                    <div>
                        <strong id="feature-team-abbr">UHA</strong>
                        <span id="feature-team-name">Your League</span>
                    </div>
                    <div>IMAGE</div>
                    </div>

                    <div class="image"><img src="assets/img/logos/playoffs_logo.png" alt="Playoffs Logo" width="200" height="auto"></div>

                    <div class="overlay">
                        <h3 id="feature-headline">Welcome to the 2026 Stanley Cup Playoffs</h3>
                        <p id="feature-dek">Live data will appear as soon as uploads/DB are wired.</p>
                        <small id="feature-time">just now</small>
                        
                </div>
            </section>
            <!-- Top headlines -->
            <section class="top-headlines">
                <div class="section-title">Top Headlines</div>
                    <div class="th-grid">
                        <div class="th-item">HEADLINE CARD 1</div>
                        <div class="th-item">HEADLINE CARD 2</div>
                        <div class="th-item">HEADLINE CARD 3</div>
                    </div>
                    <div class="th-caption">
                        <div><strong>Headline 1</strong><small>xx ago</small></div>
                        <div><strong>Headline 2</strong><small>xx ago</small></div>
                        <div><strong>Headline 3</strong><small>xx ago</small></div>
                    </div> 
            </section>
<!-- Transactions — latest -->
            <section id="homeTransactions" class="transactions">
                <div class="sectionHeader">
                    <div class="sectionTitle">Latest Transactions</div>
                    <div class="tx-controls">
                        <button type="button" class="tx-pill" data-limit="25" aria-pressed="true">25</button>
                        <button type="button" class="tx-pill" data-limit="50" aria-pressed="false">50</button>
                    </div>
                </div>
                <div class="tx-mini"></div>
                <ul class="tx-mini-list"> </ul>
            </section>


            <section id="injuries-card" class="card-injuries">
                <header class="card-header">
                    <h2>Injuries</h2>
                    <div class="muted" id="inj-status">Loading…</div>
                </header>
                <ul id="inj-list" class="inj-list"></ul>
            </section>

        </main>
            <!-- RIGHT: Scores rail + standings preview -->
            <aside class="sidebar-right">

                <div class="box" id="scoresCard">
                    <div class="title">Pro League Scores</div>

                    <div class="scores-dates">
                        <button type="button" class="nav prev" aria-label="Previous day">◀</button>
                        <div id="scoresDates" class="dates-strip"></div>
                        <button type="button" class="nav next" aria-label="Next day">▶</button>
                    </div>

                    <div class="scores-controls slim">
                        <div class="fill"></div>
                        <select id="scoresScope" class="scores-scope">
                            <option value="pro">Pro</option>
                            <option value="farm">Farm</option>
                            <option value="echl">ECHL</option>
                            <option value="juniors">Juniors</option>
                        </select>
                        <select id="scoresFilter" class="scores-filter">
                            <option value="all">All</option>
                            <option value="live">Live</option>
                            <option value="final">Final</option>
                            <option value="upcoming">Upcoming</option>
                        </select>
                    </div>

                    <div id="proScores" class="scores-list"></div>
                        
                </div>    


            </aside>

        </div>
    </section>
</div>
</body>
</html>

<!-- Page scripts -->
<script src="assets/js/playoffs.js?v=<?= filemtime(__DIR__ . '/assets/js/playoffs.js') ?>"></script>
<script src="assets/js/goals.js?v=3"></script>
<script src="assets/js/scores.js"></script>
<script src="assets/js/injuries.js"></script>
