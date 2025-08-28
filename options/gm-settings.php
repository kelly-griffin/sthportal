<?php
// /options/gm-settings.php — Matches Options hub shell
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'GM Settings';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — UHA Portal</title>

    <!-- Page-local styles (copied to match options-hub.php look/feel) -->
    <style>
        :root {
            --stage: #585858ff;
            --card: #0D1117;
            --card-border: #FFFFFF1A;
            --ink: #E8EEF5;
            --ink-soft: #95A3B4;
            --site-width: 1200px;
        }

        body {
            margin: 0;
            background: #202428;
            color: var(--ink);
            font: 14px/1.5 system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        }

        .site { width: var(--site-width); margin: 0 auto; min-height: 100vh; background: transparent; }
        .canvas { padding: 0 16px 40px; }
        .wrap { max-width: 1000px; margin: 20px auto; }

        .page-surface {
            margin: 12px 0 32px;
            padding: 16px 16px 24px;
            background: var(--stage);
            border-radius: 16px;
            box-shadow: inset 0 1px 0 #ffffff0d, 0 0 0 1px #ffffff0f;
            min-height: calc(100vh - 220px);
            color: #E8EEF5;
        }

        h1 { margin: 0 0 6px; font-size: 28px; line-height: 1.15; letter-spacing: .2px; }
        .muted { color: var(--ink-soft); }

        .grid2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        @media (max-width: 920px) { .grid2 { grid-template-columns: 1fr; } }

        .card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 16px;
            color: inherit;
            box-shadow: inset 0 1px 0 #ffffff12;
        }
        .card h2 { margin: 0 0 10px; color: #DFE8F5; }
        .card p  { margin: 0 0 10px; color: #CFE1F3; }

        .btn {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 10px;
            border: 1px solid #2F3F53;
            background: #1B2431;
            color: #E6EEF8;
            text-decoration: none;
        }
        .btn:hover { background: #223349; border-color: #3D5270; }
        .btn.small { padding: 4px 8px; font-size: 12px; line-height: 1; }

        /* Simple radio row */
        .radio-row { display: flex; gap: 12px; flex-wrap: wrap; }
        label.radio { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }

        a { color: #9CC4FF; text-decoration: none; }
        a:hover { color: #C8DDFF; }
    </style>
</head>

<body>
    <div class="site">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

        <div class="canvas">
            <div class="wrap">
                <div class="page-surface">
                    <h1><?= h($title) ?></h1>
                    <p class="muted">Shortcuts and preferences for your day-to-day GM workflow.</p>

                    <div class="grid2">

                        <!-- Default Scope -->
                        <section class="card" id="default-scope">
                            <h2>Default Scope</h2>
                            <p>Choose which scope the portal should prefer when pages offer Pro/Farm/ECHL views.</p>

                            <div class="radio-row" role="radiogroup" aria-label="Default Scope">
                                <label class="radio"><input type="radio" name="scope" value="pro"> Pro</label>
                                <label class="radio"><input type="radio" name="scope" value="farm"> Farm</label>
                                <label class="radio"><input type="radio" name="scope" value="echl"> ECHL</label>
                                <label class="radio"><input type="radio" name="scope" value="remember"> Remember Last Used</label>
                            </div>

                            <p class="muted">Saved locally in your browser. We’ll wire this into page defaults later.</p>

                            <button class="btn small" type="button" id="resetScope">Reset</button>
                        </section>

                        <!-- Placeholders for future tools -->
                        <section class="card" id="lines-upload">
                            <h2>Lines Upload</h2>
                            <p>Upload your team lines file to the portal (validation and preview coming soon).</p>
                            <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Coming Soon</a>
                        </section>

                        <section class="card" id="scratch-helpers">
                            <h2>Scratch Helpers</h2>
                            <p>Quick tools to set scratches and special teams based on fatigue/injuries.</p>
                            <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Coming Soon</a>
                        </section>

                        <section class="card" id="gm-notifications">
                            <h2>Notifications</h2>
                            <p>Pick the GM alerts you want (transactions, waiver claims, injuries, suspensions).</p>
                            <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Coming Soon</a>
                        </section>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Local-only preference for Default Scope
        (function () {
            const KEY = 'portal:gm:default-scope';
            const radios = document.querySelectorAll('input[name="scope"]');
            const reset = document.getElementById('resetScope');

            function setScope(val) {
                localStorage.setItem(KEY, val);
                radios.forEach(r => r.checked = (r.value === val));
            }

            const saved = localStorage.getItem(KEY) || 'remember';
            setScope(saved);

            radios.forEach(r => r.addEventListener('change', () => setScope(r.value)));
            reset.addEventListener('click', () => setScope('remember'));
        })();
    </script>
</body>
</html>
