<?php
// /options/defaults.php — Options: Defaults (universal shell)
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Defaults';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — UHA Portal</title>

    <?php require_once __DIR__ . '/../includes/head-assets.php'; ?>
</head>

<body>
<div class="site">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

    <div class="canvas">
        <div class="defaults-container">
          <div class="defaults-card">
            <div class="page-surface">
                <h1><?= h($title) ?></h1>
                <p class="muted">Set your preferred defaults for navigation and views. These save to your browser for now; we’ll wire them into pages next.</p>

                <div class="grid2">

                    <!-- Default Landing -->
                    <section class="card" id="default-landing">
                        <h2>Default Landing Page</h2>
                        <p>Where the portal should land when you first open it.</p>
                        <div class="row">
                            <select id="landingSelect" aria-label="Default landing page">
                                <option value="home">Home</option>
                                <option value="schedule">Schedule</option>
                                <option value="standings">Standings</option>
                                <option value="leaders">Leaders</option>
                                <option value="transactions">Transactions</option>
                                <option value="statistics">Statistics</option>
                            </select>
                            <button class="btn small" type="button" id="resetLanding">Reset</button>
                        </div>
                        <p class="muted" style="margin-top:8px;">Stored as <code>portal:defaults:landing</code> (values: home|schedule|standings|leaders|transactions|statistics).</p>
                    </section>

                    <!-- Default Scope -->
                    <section class="card" id="default-scope">
                        <h2>Default Scope</h2>
                        <p>Prefer Pro / Farm / ECHL on pages that support scope switching.</p>
                        <div class="radio-row" role="radiogroup" aria-label="Default Scope">
                            <label class="radio"><input type="radio" name="scope" value="pro"> Pro</label>
                            <label class="radio"><input type="radio" name="scope" value="farm"> Farm</label>
                            <label class="radio"><input type="radio" name="scope" value="echl"> ECHL</label>
                            <label class="radio"><input type="radio" name="scope" value="remember"> Remember Last Used</label>
                        </div>
                        <div class="row" style="margin-top:8px;">
                            <button class="btn small" type="button" id="resetScope">Reset</button>
                        </div>
                        <p class="muted" style="margin-top:8px;">
                            Uses the same key as GM Settings: <code>portal:gm:default-scope</code>. Change it here or there—both stay in sync.
                        </p>
                    </section>

                    <!-- Table Rows Default -->
                    <section class="card" id="table-rows">
                        <h2>Tables — Rows Per Page</h2>
                        <p>Default number of rows to show in paged tables.</p>
                        <div class="row">
                            <select id="rowsSelect" aria-label="Rows per page">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <button class="btn small" type="button" id="resetRows">Reset</button>
                        </div>
                        <p class="muted" style="margin-top:8px;">Stored as <code>portal:defaults:rows-per-page</code>.</p>
                    </section>

                    <!-- Schedule View Default -->
                    <section class="card" id="schedule-view">
                        <h2>Schedule — Default View</h2>
                        <p>Choose the schedule layout the portal should prefer.</p>
                        <div class="radio-row" role="radiogroup" aria-label="Schedule default view">
                            <label class="radio"><input type="radio" name="schedview" value="day"> Day</label>
                            <label class="radio"><input type="radio" name="schedview" value="week"> Week</label>
                        </div>
                        <div class="row" style="margin-top:8px;">
                            <button class="btn small" type="button" id="resetSched">Reset</button>
                        </div>
                        <p class="muted" style="margin-top:8px;">Stored as <code>portal:defaults:schedule-view</code> (values: day|week).</p>
                    </section>

                    <!-- Remember last-used filters -->
                    <section class="card" id="remember-filters">
                        <h2>Remember Filters</h2>
                        <p>Let the portal remember your last-used filters/sorts on supported pages.</p>
                        <div class="row">
                            <label class="radio"><input type="checkbox" id="rememberFilters"> Enable</label>
                            <button class="btn small" type="button" id="resetRemember">Reset</button>
                        </div>
                        <p class="muted" style="margin-top:8px;">Stored as <code>portal:defaults:remember-filters</code> (true|false).</p>
                    </section>

                    <!-- Master reset -->
                    <section class="card" id="reset-all">
                        <h2>Reset All Defaults</h2>
                        <p>Clear every default on this page and return to the built-in behavior.</p>
                        <button class="btn" type="button" id="resetAll">Reset Everything</button>
                        <p class="muted" style="margin-top:8px;">This only clears local browser storage; it doesn’t change server settings.</p>
                    </section>

                </div>
            </div>
          </div>
        </div>
    </div>

<script>
/* ===== Defaults storage (local-only) ===== */
(function(){
  // Keys
  const KEY_LANDING = 'portal:defaults:landing';
  const KEY_SCOPE   = 'portal:gm:default-scope'; // shared with GM Settings
  const KEY_ROWS    = 'portal:defaults:rows-per-page';
  const KEY_SCHED   = 'portal:defaults:schedule-view';
  const KEY_REM     = 'portal:defaults:remember-filters';

  // DOM
  const landingSelect = document.getElementById('landingSelect');
  const rowsSelect    = document.getElementById('rowsSelect');
  const resetLanding  = document.getElementById('resetLanding');
  const resetRows     = document.getElementById('resetRows');

  const scopeRadios = Array.from(document.querySelectorAll('input[name="scope"]'));
  const resetScope  = document.getElementById('resetScope');

  const schedRadios = Array.from(document.querySelectorAll('input[name="schedview"]'));
  const resetSched  = document.getElementById('resetSched');

  const rememberFilters = document.getElementById('rememberFilters');
  const resetRemember   = document.getElementById('resetRemember');

  const resetAll = document.getElementById('resetAll');

  // Defaults
  const DEF_LANDING = 'home';
  const DEF_SCOPE   = 'remember';
  const DEF_ROWS    = '25';
  const DEF_SCHED   = 'week';
  const DEF_REM     = 'false';

  // Helpers
  function setRadio(radios, value){
    radios.forEach(r => r.checked = (r.value === value));
  }

  // Init from storage
  function init(){
    landingSelect.value = localStorage.getItem(KEY_LANDING) || DEF_LANDING;
    rowsSelect.value    = localStorage.getItem(KEY_ROWS)    || DEF_ROWS;

    const scope = localStorage.getItem(KEY_SCOPE) || DEF_SCOPE;
    setRadio(scopeRadios, scope);

    const sched = localStorage.getItem(KEY_SCHED) || DEF_SCHED;
    setRadio(schedRadios, sched);

    const rem = localStorage.getItem(KEY_REM);
    rememberFilters.checked = (rem ? rem === 'true' : DEF_REM === 'true');
  }

  // Save handlers
  landingSelect.addEventListener('change', () => {
    localStorage.setItem(KEY_LANDING, landingSelect.value);
  });
  rowsSelect.addEventListener('change', () => {
    localStorage.setItem(KEY_ROWS, rowsSelect.value);
  });
  scopeRadios.forEach(r => r.addEventListener('change', () => {
    const sel = scopeRadios.find(x => x.checked)?.value || DEF_SCOPE;
    localStorage.setItem(KEY_SCOPE, sel);
  }));
  schedRadios.forEach(r => r.addEventListener('change', () => {
    const sel = schedRadios.find(x => x.checked)?.value || DEF_SCHED;
    localStorage.setItem(KEY_SCHED, sel);
  }));
  rememberFilters.addEventListener('change', () => {
    localStorage.setItem(KEY_REM, rememberFilters.checked ? 'true' : 'false');
  });

  // Resets
  resetLanding.addEventListener('click', () => {
    localStorage.removeItem(KEY_LANDING);
    landingSelect.value = DEF_LANDING;
  });
  resetRows.addEventListener('click', () => {
    localStorage.removeItem(KEY_ROWS);
    rowsSelect.value = DEF_ROWS;
  });
  resetScope.addEventListener('click', () => {
    localStorage.setItem(KEY_SCOPE, DEF_SCOPE);
    setRadio(scopeRadios, DEF_SCOPE);
  });
  resetSched.addEventListener('click', () => {
    localStorage.setItem(KEY_SCHED, DEF_SCHED);
    setRadio(schedRadios, DEF_SCHED);
  });
  resetRemember.addEventListener('click', () => {
    localStorage.setItem(KEY_REM, DEF_REM);
    rememberFilters.checked = (DEF_REM === 'true');
  });

  // Master reset
  resetAll.addEventListener('click', () => {
    [KEY_LANDING, KEY_SCOPE, KEY_ROWS, KEY_SCHED, KEY_REM].forEach(k => localStorage.removeItem(k));
    init();
  });

  // Kickoff
  init();
})();
</script>
</body>
</html>
