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
    <?php require_once __DIR__ . '/../includes/head-assets.php'; ?>
</head>

<body>
    <div class="site">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

        <div class="canvas">
            <div class="gmset-container">
                <div class="gmset-card">
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
                                    <label class="radio"><input type="radio" name="scope" value="remember"> Remember
                                        Last Used</label>
                                </div>

                                <p class="muted">Saved locally in your browser. We’ll wire this into page defaults
                                    later.</p>

                                <button class="btn small" type="button" id="resetScope">Reset</button>
                            </section>

                            <!-- Placeholders for future tools -->
                            <section class="card" id="lines-upload">
                                <h2>Lines Upload</h2>
                                <p>Upload your team lines file to the portal (validation and preview coming soon).</p>
                                <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Coming
                                    Soon</a>
                            </section>

                            <section class="card" id="scratch-helpers">
                                <h2>Scratch Helpers</h2>
                                <p>Quick tools to set scratches and special teams based on fatigue/injuries.</p>
                                <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Coming
                                    Soon</a>
                            </section>

                            <section class="card" id="gm-notifications">
                                <h2>Notifications</h2>
                                <p>Pick the GM alerts you want (transactions, waiver claims, injuries, suspensions).</p>
                                <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Coming
                                    Soon</a>
                            </section>

                        </div>
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