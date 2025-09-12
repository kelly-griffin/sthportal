<?php
// /options-hub.php — Options landing page (matches Admin shell)
require_once __DIR__ . '/includes/bootstrap.php';
$title = 'Options';
?>
<!doctype html>
<html lang="en">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — UHA Portal</title>    
    <?php require_once __DIR__ . '/includes/head-assets.php'; ?>
</head>

<body>
    <div class="site">
        <?php include __DIR__ . '/includes/topbar.php'; ?>
        <?php include __DIR__ . '/includes/leaguebar.php'; ?>

        <div class="canvas">
            <div class="options-container">
                <div class="options-card">
                <div class="page-surface">
                    <h1><?= h($title) ?></h1>
                    <p class="muted">Tweak how the portal looks and behaves. Pick a category below. We’ll add more pages
                        as needs pop up.</p>

                    <div class="options-grid">
                        <!-- Left: primary categories -->
                        <div>
                            <div class="card">
                                <h2>Categories</h2>
                                <ul class="options-list">
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
    </div>
</body>

</html>