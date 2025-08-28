<?php
/**
 * Data Pipeline Quickstart (Admin)
 *
 * Goal: One-page guidance on what to run, when, and why.
 * Labels: REQUIRED, RECOMMENDED, OPTIONAL, REPAIR-ONLY.
 * Links out to Hubs and wrappers so the commish doesn't have to hunt.
 */
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

function h($s)
{
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Data Pipeline Quickstart</title>
  <style>
    .grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 14px
    }

    @media(min-width:1000px) {
      .grid {
        grid-template-columns: 1.2fr .8fr
      }
    }

    h1 {
      margin: 0 0 10px
    }

    h2 {
      margin: 0 0 6px;
      font-size: 1.15rem
    }

    .section {
      margin-bottom: 14px
    }

    .list {
      margin: 0;
      padding-left: 18px
    }

    .kb {
      display: grid;
      grid-template-columns: 160px 1fr;
      gap: 6px
    }

    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      border: 1px solid #444;
      background: #181818;
      color: #ddd;
      font-size: .65em;
      margin-right: 6px
    }

    .req {
      border-color: #2a6
    }

    .rec {
      border-color: rgba(192, 167, 28, 1)
    }

    .opt {
      border-color: #bd710fff
    }

    .fix {
      border-color: #c55
    }

    .steps li {
      margin: 6px 0
    }

    .shortcut {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 8px
    }

    .callout {
      border: 1px dashed #444;
      border-radius: 10px;
      padding: 10px;
      background: #121212
    }

    .tight ul {
      margin: 6px 0 0 18px
    }
  </style>
  <!-- R2: Admin Surface (local-only) -->
  <style>
    :root {
      --stage: #585858ff; 
      /* translucent light gray center */
      --card: #0D1117;
      /* dark card */
      --card-border: #FFFFFF1A;
      --ink: #E8EEF5;
      --ink-muted: #A9BACB;
      --ink-soft: #95A3B4;
      --accent: #6AA1FF;
      --site-width: 1200px;
    }

    /* stage shell (matches Front Office/Assets Hub) */
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

    /* light-gray page surface */
    .page-surface {
      margin: 12px 0 32px;
      padding: 16px 16px 24px;
      background: var(--stage);
      border-radius: 16px;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, .05), 0 0 0 1px rgba(255, 255, 255, .06);
      min-height: calc(100vh - 220px);
      font-size: 0.68em!important;
    }

    /* common cards/typography (safe to reuse) */
    h1 {
      font-size: 38px;
      margin: 8px 0 10px;
      letter-spacing: .2px;
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
      border-radius: 14px;
      padding: 14px;
      margin: 18px 0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      color: inherit;
    }

    th,
    td {
      padding: 10px 12px;
      border-bottom: 1px solid #FFFFFF14;
      vertical-align: middle;
    }

    th {
      text-align: left;
      font-weight: 700;
      color: #DFE8F5;
    }

    .tool-title {
      font-weight: 700;
      color: #E6EEF8;
    }

    .tool-sub {
      font-size: 12px;
      color: #9FB0C2;
    }

    .actions {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .btn {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 10px;
      border: 1px solid #2F3F53;
      background: #1B2431;
      color: #E6EEF8;
      text-decoration: none;
      font-size: 0.65em;
    }

    .btn:hover {
      background: #223349;
      border-color: #3D5270;
    }

    .btn.run {
      border-color: #2EA043;
    }

    .btn.run.danger {
      border-color: #D14;
    }

    .btn.mark {
      border-color: #3D5270;
    }

    .note-input {
      width: 220px;
      padding: 6px 8px;
      border-radius: 8px;
      border: 1px solid #2F3F53;
      background: #0F1621;
      color: #E6EEF8;
    }

    .note-input::placeholder {
      color: #9FB0C2;
    }

    .note-input:focus {
      outline: none;
      border-color: #6AA1FF;
      box-shadow: 0 0 0 2px #6AA1FF33;
    }

    /* R2: Pipeline Quickstart — contrast pass (local only) */
.page-surface { color:#E8EEF5; }                      /* base text */
.page-surface h1, .page-surface h2, .page-surface h3 { color:#F2F6FF; }
.page-surface p, .page-surface li, .page-surface dt,
.page-surface dd { color:#D7E3F2; }
.page-surface .muted, .page-surface .small, .page-surface small { color:#A9BACB; }

/* dark panels/cards */
.page-surface .card,
.page-surface .panel,
.page-surface .block,
.page-surface .section {
  background:#0D1117;
  border:1px solid #FFFFFF1A;
  color:inherit;               /* inherit the light text */
}
.page-surface .card p,
.page-surface .panel p,
.page-surface .block p,
.page-surface .section p { color:inherit; }

/* buttons */
.page-surface .btn {
  color:#E6EEF8;
  background:#1B2431;
  border:1px solid #2F3F53;
}
.page-surface .btn:hover { background:#223349; border-color:#3D5270; }

/* pills/badges (REQUIRED / RECOMMENDED / etc.) */
.page-surface .badge {
    color:#EAF2FD;
  background:#263B52;
}
.page-surface .pill,
.page-surface .tag,
.page-surface .label {
  color:#EAF2FD;
  background:#263B52;
  border:1px solid #3D5270;
}

/* links */
.page-surface a { color:#9CC4FF; text-decoration:none; }
.page-surface a:hover { color:#C8DDFF; }

/* optional: code/inline monospace */
.page-surface code, .page-surface kbd {
  color:#EADFB2;
  background:#0B121A;
  border:1px solid #2A3B50;
  padding:0 .25em; border-radius:6px;
  font-size: 0.65em;
}
/* headings + spacing */
.page-surface h1 { margin-bottom: 8px; }
.page-surface .subhead { color:#C9D6E7; margin:0 0 14px; }

/* two-column panels: consistent padding + gap */
.page-surface .panel { padding:16px; border-radius:14px; }
.page-surface .grid { display:grid; grid-template-columns: 2fr 1fr; gap:16px; }

/* tiny buttons used inside steps */
.page-surface .btn.small { padding:4px 8px; font-size:12px; line-height:1; }

/* code tokens: readable + wrap long filenames */
.page-surface code, .page-surface kbd {
  color:#FFE1A6; background:#0B121A; border:1px solid #2A3B50;
  padding:0 .35em; border-radius:6px;
}
.page-surface code { word-break: break-word; overflow-wrap: anywhere; }
/* make numbered steps line up neatly */
.page-surface .steps { list-style: none; padding:0; margin:0; }
.page-surface .steps li { display:flex; gap:10px; align-items:flex-start; margin:8px 0; }
.page-surface .steps .num {
  flex:0 0 1.6em; text-align:right; opacity:.9; color:#D7E3F2;
}
.page-surface .steps .text { flex:1; }
/* spacing + rhythm */
.page-surface { line-height: 1.45; }
.page-surface .grid { gap: 20px; }                /* a little more breathing room */
.page-surface h1 { margin-bottom: 6px; }
.page-surface .subhead { color:#C9D6E7; margin:0 0 14px; }

/* R2: widen right column a bit */
.page-surface .grid {
  /* was: 2fr 1fr */
  grid-template-columns: minmax(0, 59%) minmax(0, 41%);
}

/* on very wide screens, give the right even more room */
@media (min-width: 1440px){
  .page-surface .grid {
    grid-template-columns: minmax(0, 60%) minmax(0, 40%);
  }
}

/* keep the mobile stack as-is */
@media (max-width: 920px){
  .page-surface .grid { grid-template-columns: 1fr; }
}

/* safety: prevent right-panel content from forcing overflow */
.page-surface code { word-break: break-word; overflow-wrap: anywhere; }

.site .body {
  font: 0.68em / 1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
}

  </style>

</head>
<div class="site">

  <body>
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
    <div class="canvas">
      <div class="wrap">
        <div class="page-surface">
          <h1>Data Pipeline Quickstart</h1>
          <p class="muted">Exact order-of-operations with clear labels: <span class="badge req">REQUIRED</span> <span
              class="badge rec">RECOMMENDED</span> <span class="badge opt">OPTIONAL</span> <span
              class="badge fix">REPAIR‑ONLY</span>.</p>

          <div class="grid">
            <div>
              <div class="card section">
                <h2>Daily Flow (after each sim)</h2>
                <ol class="steps">
                  <li><span class="badge req">REQUIRED</span> <strong>STHS Importer</strong> — ingest the latest
                    outputs. <a class="btn" href="sths-importer.php">Open</a></li>
                  <li><span class="badge req">REQUIRED</span> <strong>Update Schedule</strong> — refresh
                    <code>schedule-current.json</code>. (via <em>Data Pipeline Hub</em>) <a class="btn"
                      href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="badge rec">RECOMMENDED</span> <strong>Enrich Schedule</strong> — add links/flags for
                    games. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="badge rec">RECOMMENDED</span> <strong>Build Boxscores JSON</strong> — consolidate box
                    data. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="badge rec">RECOMMENDED</span> <strong>Audit Schedule Links</strong> — quick integrity
                    check. <a class="btn" href="audit-schedule-links.php">Open Wrapper</a></li>
                  <li><span class="badge opt">OPTIONAL</span> <strong>Build PBP Stats</strong> — derived metrics from
                    PBP. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="badge rec">RECOMMENDED</span> <strong>Build Home/Ticker JSON</strong> — update
                    homepage widgets. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                </ol>
                <div class="callout tight">
                  <strong>Quick sanity checks:</strong>
                  <ul>
                    <li>Homepage leaders & ticker reflect today’s sim.</li>
                    <li>Schedule page shows correct scores/times.</li>
                    <li>Random boxscore opens from today’s slate.</li>
                  </ul>
                </div>
              </div>

              <div class="card section">
                <h2>Weekly Touch‑ups</h2>
                <ul class="steps">
                  <li><span class="badge rec">RECOMMENDED</span> <strong>Reconcile Schedule</strong> — align schedule
                    vs. boxscores. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="badge rec">RECOMMENDED</span> <strong>Headshots Cache</strong> — refresh recent
                    movers/call‑ups. <a class="btn" href="assets-hub.php">Assets Hub</a></li>
                  <li><span class="badge opt">OPTIONAL</span> <strong>Generate Recaps</strong> — draft stories for
                    publication. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="badge rec">RECOMMENDED</span> <strong>Backup Now</strong> — snapshot DB/files. <a
                      class="btn" href="system-hub.php">System Hub</a></li>
                </ul>
              </div>

              <div class="card section">
                <h2>Repair Playbook</h2>
                <table>
                  <thead>
                    <tr>
                      <th>Symptom</th>
                      <th>Action</th>
                      <th>Label</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Schedule shows wrong/missing links</td>
                      <td>
                        <div>1) <a href="audit-schedule-links.php">Run Audit</a></div>
                        <div>2) <a href="../tools/fix-schedule-links.php" target="_blank" rel="noopener">Fix Links
                            Tool</a></div>
                      </td>
                      <td><span class="badge fix">REPAIR‑ONLY</span></td>
                    </tr>
                    <tr>
                      <td>Teams out of sync in boxscores</td>
                      <td><a href="data-pipeline.php">Patch Boxscores Teams</a> (via Hub)</td>
                      <td><span class="badge fix">REPAIR‑ONLY</span></td>
                    </tr>
                    <tr>
                      <td>Box JSON stale or corrupted</td>
                      <td><a href="data-pipeline.php">Rebuild Box JSON (from HTML)</a> (slow)</td>
                      <td><span class="badge fix">REPAIR‑ONLY</span></td>
                    </tr>
                    <tr>
                      <td>Homepage widgets outdated</td>
                      <td><a href="data-pipeline.php">Build Home JSON</a> and <a href="data-pipeline.php">Build Ticker
                          JSON</a></td>
                      <td><span class="badge rec">RECOMMENDED</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div>
              <div class="card section">
                <h2>Shortcuts</h2>
                <div class="shortcut">
                  <a class="btn" href="data-pipeline.php">Open Data Pipeline Hub</a>
                  <a class="btn" href="assets-hub.php">Open Assets Hub</a>
                  <a class="btn" href="system-hub.php">Open System Hub</a>
                  <a class="btn" href="audit-links-wrapper.php">Audit Links Wrapper</a>
                </div>
              </div>

              <div class="card section">
                <h2>What writes what?</h2>
                <ul class="list">
                  <li><strong>Update Schedule</strong> → <code>data/uploads/schedule-current.json</code></li>
                  <li><strong>Build Boxscores JSON</strong> → <code>data/uploads/boxscores/index.json</code></li>
                  <li><strong>Build Home/Ticker JSON</strong> → <code>data/uploads/home.json</code>,
                    <code>data/uploads/ticker.json</code></li>
                  <li><strong>Headshots Cache</strong> → <code>assets/img/mugs/</code> (+ <code>.sentinel</code>)</li>
                  <li><strong>Audit Links</strong> → <code>data/logs/audit-schedule-links.json</code> /
                    <code>.csv</code></li>
                </ul>
              </div>

              <div class="card section">
                <h2>Labels</h2>
                <p class="muted">How we categorize steps in this quickstart.</p>
                <ul class="list">
                  <li><span class="badge req">REQUIRED</span> Must run for the portal to reflect the latest sim.</li>
                  <li><span class="badge rec">RECOMMENDED</span> Improves quality and UX, but not strictly mandatory.
                  </li>
                  <li><span class="badge opt">OPTIONAL</span> Nice to have; can be deferred.</li>
                  <li><span class="badge fix">REPAIR‑ONLY</span> Use only to correct specific issues, not daily.</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        </div>
    </div>
</div>        
  </body>

</html>
