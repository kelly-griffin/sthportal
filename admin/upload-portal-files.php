<?php
// /admin/upload-portal-files.php — New UI (no league-file upload here)
// Visual only: two cards (Current Status, Upload Portal Files). Posts to existing
// backend /upload-portal-files.php. We are NOT changing any global styles.
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
@include_once __DIR__ . '/../includes/admin-helpers.php';

// Standard gates (match legacy pages)
require_login();
require_admin();

// Ensure CSRF is present (if bootstrap didn’t already set it)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$csrf = $_SESSION['csrf'];

$title = 'Upload Portal Files';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> — UHA Portal</title>

    <!-- Admin Surface (page-local) -->
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
        }

        h1 {
            font-size: 38px;
            margin: 8px 0 10px;
            color: #F2F6FF;
        }

        h2 {
            font-size: 18px;
            margin: 0 0 10px;
            color: #DFE8F5;
        }

        .muted {
            color: var(--ink-soft);
        }

        .card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 16px;
            margin: 18px 0;
        }

        .row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 10px;
            border: 1px solid #2F3F53;
            background: #1B2431;
            color: #E6EEF5;
            text-decoration: none;
        }

        .btn:hover {
            background: #223349;
            border-color: #3D5270;
        }

        input[type=file],
        input[type=text] {
            background: #0F1621;
            border: 1px solid #2F3F53;
            color: #E6EEF5;
            border-radius: 8px;
            padding: 6px 8px;
        }

        input[type=file]::file-selector-button {
            margin-right: 8px;
            border: 1px solid #2F3F53;
            border-radius: 8px;
            background: #1B2431;
            color: #E6EEF5;
            padding: 6px 10px;
        }

        input[type=text]::placeholder {
            color: #9FB0C2;
        }

        input[type=file]:focus,
        input[type=text]:focus {
            outline: none;
            border-color: #6AA1FF;
            box-shadow: 0 0 0 2px #6AA1FF33;
        }

        ul.clean {
            margin: 0;
            padding-left: 1.2em;
        }

        ul.clean li {
            margin: 6px 0;
        }

        /* Contrast pass (local to this page) */
        .page-surface {
            color: #E8EEF5;
        }

        /* base text */
        .page-surface h1,
        .page-surface h2,
        .page-surface h3 {
            color: #F2F6FF;
        }

        .page-surface p,
        .page-surface li {
            color: #D7E3F2;
        }

        .page-surface .muted {
            color: #A9BACB;
        }

        /* ensure dark cards inherit the light text */
        .page-surface .card {
            color: inherit;
        }

        .page-surface .card p,
        .page-surface .card li,
        .page-surface .card label,
        .page-surface .card strong {
            color: inherit;
        }

        /* links */
        .page-surface a {
            color: #9CC4FF;
            text-decoration: none;
        }

        .page-surface a:hover {
            color: #C8DDFF;
        }

        /* code tokens (paths/extensions) */
        .page-surface code {
            color: #FFE1A6;
            background: #0B121A;
            border: 1px solid #2A3B50;
            padding: 0 .35em;
            border-radius: 6px;
        }

        /* Align the three file pickers into neat columns */
        .page-surface .card form .row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* fixed label column so all rows start at the same x */
        .page-surface .card form .row label[for="csv_files"],
        .page-surface .card form .row label[for="xml_files"],
        .page-surface .card form .row label[for="html_files"] {
            flex: 0 0 180px;
            /* tweak 170–200px to taste */
        }

        /* make the input fill the remaining space evenly */
        .page-surface .card form .row input[type="file"] {
            flex: 1;
            min-width: 320px;
            /* prevents short wrapping on narrow screens */
        }

        /* normalize the “Choose Files” button width */
        .page-surface .card form .row input[type="file"]::file-selector-button {
            width: 120px;
            /* same width across rows */
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="site">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

        <div class="canvas">
            <div class="wrap">
                <div class="page-surface">
                    <h1><?= htmlspecialchars($title) ?></h1>
                    <p class="muted">Current Status is shown below. Use the <strong>All‑in‑One</strong> form to upload
                        CSV, XML, and Boxscore (HTML) exports after each sim. League file upload lives elsewhere.</p>

                    <!-- Card: Current Status -->
                    <div class="card">
                        <h2>Current Status</h2>
                        <ul class="clean">
                            <li><code>.stc</code> File: <em>No files uploaded yet.</em></li>
                            <li><code>.csv</code> Files: <em>No files uploaded yet.</em></li>
                            <li><code>.xml</code> Files: <em>No files uploaded yet.</em></li>
                            <li><code>.html</code> Files: <em>No files uploaded yet.</em></li>
                        </ul>
                    </div>

                    <!-- Card: Upload Portal Files (All‑in‑One) -->
                    <div class="card">
                        <h2>Upload Portal Files (All‑in‑One)</h2>
                        <p class="muted">Drop the files exported after a simulation. The system will validate and
                            publish them.</p>

                        <p><strong>What do I need?</strong></p>
                        <ul class="clean">
                            <li><strong>All Exported CSV Files</strong> (i.e. <code>YourLeague‑V3Proteam.csv</code>)
                            </li>
                            <li><strong>All Exported XML Files</strong> (i.e. <code>YourLeague‑Waivers.xml</code>)</li>
                            <li><strong>All Boxscore (HTML) Files</strong> (i.e. <code>YourLeague‑1.html</code>,
                                <code>YourLeague‑Farm‑1.html</code>)
                            </li>
                        </ul>

                        <form action="/upload-portal-files.php" method="post" enctype="multipart/form-data"
                            style="margin-top:10px;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

                            <div class="row" style="margin-top:6px;">
                                <label for="csv_files"><strong>CSV Files</strong> (required)</label>
                                <input id="csv_files" name="csv_files[]" type="file" multiple required
                                    accept=".csv,text/csv">
                            </div>

                            <div class="row" style="margin-top:10px;">
                                <label for="xml_files"><strong>XML Files</strong> (required)</label>
                                <input id="xml_files" name="xml_files[]" type="file" multiple required
                                    accept=".xml,application/xml,text/xml">
                            </div>

                            <div class="row" style="margin-top:10px;">
                                <label for="html_files"><strong>Boxscores</strong> (html; required)</label>
                                <input id="html_files" name="html_files[]" type="file" multiple required
                                    accept=".html,.htm,text/html">
                            </div>

                            <div class="row" style="margin-top:12px;">
                                <button type="submit" class="btn"><strong>Upload &amp; Publish</strong></button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</body>

</html>