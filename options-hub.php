<?php
// /options-hub.php — Options landing page (matches Admin shell)
require_once __DIR__ . '/includes/bootstrap.php';
$title = 'Options';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — UHA Portal</title>

    <!-- Page‑local styles only (no globals touched) -->
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

        .site {
            width: var(--site-width);
            margin: 0 auto;
            min-height: 100vh;
            background: transparent;
        }

        .canvas {
            padding: 0 16px 40px;
        }

        .wrap {
            max-width: 1000px;
            margin: 20px auto;
        }

        .page-surface {
            margin: 12px 0 32px;
            padding: 16px 16px 24px;
            background: var(--stage);
            border-radius: 16px;
            box-shadow: inset 0 1px 0 #ffffff0d, 0 0 0 1px #ffffff0f;
            min-height: calc(100vh - 220px);
            color: #E8EEF5;
        }

        h1 {
            font-size: 38px;
            margin: 8px 0 10px;
            color: #F2F6FF;
        }

        .muted {
            color: var(--ink-soft);
        }

        .grid {
            display: grid;
            grid-template-columns: minmax(0, 58%) minmax(0, 42%);
            gap: 20px;
        }

        @media (max-width: 920px) {
            .grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }
        }

        .card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 16px;
            margin: 18px 0;
            color: inherit;
            box-shadow: inset 0 1px 0 #ffffff12;
        }

        .card h2 {
            margin: 0 0 10px;
            color: #DFE8F5;
        }

        .list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .list li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #FFFFFF14;
        }

        .list li:last-child {
            border-bottom: none;
        }

        .btn {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 10px;
            border: 1px solid #2F3F53;
            background: #1B2431;
            color: #E6EEF8;
            text-decoration: none;
        }

        .btn:hover {
            background: #223349;
            border-color: #3D5270;
        }

        .btn.small {
            padding: 4px 8px;
            font-size: 12px;
            line-height: 1;
        }

        a {
            color: #9CC4FF;
            text-decoration: none;
        }

        a:hover {
            color: #C8DDFF;
        }
    </style>
</head>

<body>
    <div class="site">
        <?php include __DIR__ . '/includes/topbar.php'; ?>
        <?php include __DIR__ . '/includes/leaguebar.php'; ?>

        <div class="canvas">
            <div class="wrap">
                <div class="page-surface">
                    <h1><?= h($title) ?></h1>
                    <p class="muted">Tweak how the portal looks and behaves. Pick a category below. We’ll add more pages
                        as needs pop up.</p>

                    <div class="grid">
                        <!-- Left: primary categories -->
                        <div>
                            <div class="card">
                                <h2>Categories</h2>
                                <ul class="list">
                                    <li>
                                        <div>
                                            <div><strong>Appearance</strong></div>
                                            <div class="muted">Theme accents, font size, table density.</div>
                                        </div>
                                        <a class="btn" href="<?= h(u('options/appearance.php')) ?>">Open</a>
                                    </li>
                                    <li>
                                        <div>
                                            <div><strong>Defaults</strong></div>
                                            <div class="muted">Your team, timezone, date formats.</div>
                                        </div>
                                        <a class="btn" href="<?= h(u('options/defaults.php')) ?>">Open</a>
                                    </li>
                                    <li>
                                        <div>
                                            <div><strong>Notifications</strong></div>
                                            <div class="muted">Email/popup alerts for sims, injuries, trades.</div>
                                        </div>
                                        <a class="btn" href="<?= h(u('options/notifications.php')) ?>">Open</a>
                                    </li>
                                    <li>
                                        <div>
                                            <div><strong>Data & Privacy</strong></div>
                                            <div class="muted">Retention windows, export, anonymization.</div>
                                        </div>
                                        <a class="btn" href="<?= h(u('options/privacy.php')) ?>">Open</a>
                                    </li>
                                    <li>
                                        <div>
                                            <div><strong>Profile & Account</strong></div>
                                            <div class="muted">Name, email, password, avatar.</div>
                                        </div>
                                        <a class="btn" href="<?= h(u('options/profile.php')) ?>">Open</a>
                                    </li>
                                </ul>
                            </div>

                            <div class="card">
                                <h2>Shortcuts</h2>
                                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                                    <a class="btn" href="<?= h(u('admin/assets-hub.php')) ?>">Assets Hub</a>
                                    <a class="btn" href="<?= h(u('admin/data-pipeline.php')) ?>">Data Pipeline Hub</a>
                                    <a class="btn" href="<?= h(u('admin/system-hub.php')) ?>">System Hub</a>
                                </div>
                            </div>
                        </div>

                        <!-- Right: notes/help -->
                        <div>
                            <div class="card">
                                <h2>Notes</h2>
                                <p class="muted">Looking for file uploads? Use <a
                                        href="<?= h(u('admin/upload-portal-files.php')) ?>">Upload Portal Files</a> in
                                    Admin, or the inline League File picker on the <a
                                        href="<?= h(u('admin/assets-hub.php')) ?>">Assets Hub</a>.</p>
                                <p class="muted">We can add more categories here—just tell me what you want (e.g.,
                                    Integrations, User Roles, Advanced).</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</body>

</html>