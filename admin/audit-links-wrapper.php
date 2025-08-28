<?php
/**
 * Audit Links Wrapper (Admin)
 * Matches the visual shell used by Assets Hub / Pipeline Quickstart / System Hub.
 * Now wired to real tools from your repo.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// --- gate: admin only ---
if (isset($_SESSION['user']) && empty($_SESSION['user']['is_admin'])) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

$title = 'Audit Links Wrapper';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> — UHA Portal</title>

  <!-- R2: Admin Surface (page-local) -->
  <style>
    :root{
      --stage:#585858ff; --card:#0D1117; --card-border:#FFFFFF1A;
      --ink:#E8EEF5; --ink-soft:#95A3B4; --accent:#6AA1FF; --site-width:1200px;
    }
    body{ margin:0; background:#202428; color:var(--ink); font:14px/1.5 system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; }

    .site{ width:var(--site-width); margin:0 auto; min-height:100vh; background:transparent; }
    .canvas{ padding:0 16px 40px; }
    .wrap{ max-width:1000px; margin:20px auto; }

    .page-surface{
      margin:12px 0 32px; padding:16px 16px 24px; background:var(--stage);
      border-radius:16px; box-shadow: inset 0 1px 0 #ffffff0d, 0 0 0 1px #ffffff0f;
      min-height:calc(100vh - 220px);
      color:#E8EEF5;
    }
    h1{ font-size:38px; margin:8px 0 10px; color:#F2F6FF; }
    h2{ font-size:18px; margin:0 0 10px; color:#DFE8F5; }
    .muted{ color:var(--ink-soft); }

    .grid{ display:grid; grid-template-columns:minmax(0,58%) minmax(0,42%); gap:20px; }
    @media (min-width:1440px){ .grid{ grid-template-columns:minmax(0,55%) minmax(0,45%);} }
    @media (max-width:920px){ .grid{ grid-template-columns:1fr; gap:14px; } }

    .card{ background:var(--card); border:1px solid var(--card-border);
      border-radius:16px; padding:16px; margin:18px 0; color:inherit; box-shadow: inset 0 1px 0 #ffffff12; }

    .btn{ display:inline-block; padding:6px 10px; border-radius:10px; border:1px solid #2F3F53; background:#1B2431; color:#E6EEF8; text-decoration:none; }
    .btn:hover{ background:#223349; border-color:#3D5270; }
    .btn.primary{ border-color:#3D6EA8; }
    .btn.danger{ border-color:#D14; }
    .btn.small{ padding:4px 8px; font-size:12px; line-height:1; }

    .flex{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

    code{ color:#FFE1A6; background:#0B121A; border:1px solid #2A3B50; padding:0 .35em; border-radius:6px; }
    ul.clean{ margin:0; padding-left:1.2em; }
    ul.clean li{ margin:6px 0; }

    table{ width:100%; border-collapse:collapse; color:inherit; margin-top:8px; }
    th,td{ padding:10px 12px; border-bottom:1px solid #FFFFFF24; vertical-align:middle; }
    th{ text-align:left; font-weight:700; color:#DFE8F5; }
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
          <p class="muted">Scan and repair schedule/game links. Use this wrapper to run a quick audit, open the detailed report, or perform targeted fixes.</p>

          <div class="grid">
            <!-- Left: audit + repair -->
            <div class="card">
              <h2>Audit & Repair</h2>

              <div class="flex" style="margin-bottom:10px;">
                <a class="btn primary" href="/tools/audit-schedule-links.php">Run Audit</a>
                <a class="btn" href="/admin/audit-schedule-links.php">Open Report</a>
                <a class="btn" href="/admin/audit-export.php">Export</a>
                <a class="btn danger" href="/tools/fix-schedule-links.php">Auto-Repair</a>
              </div>

              <table>
                <thead>
                  <tr>
                    <th>Check</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Missing links in <code>data/uploads/schedule-current.json</code></td>
                    <td>—</td>
                    <td>
                      <a class="btn small" href="/tools/audit-schedule-links.php">Scan</a>
                      <a class="btn small danger" href="/tools/fix-schedule-links.php">Repair</a>
                    </td>
                  </tr>
                  <tr>
                    <td>Broken boxscore links</td>
                    <td>—</td>
                    <td>
                      <a class="btn small" href="/tools/peek-boxlinks.php">Scan</a>
                      <a class="btn small danger" href="/tools/repair-boxlinks.php">Repair</a>
                    </td>
                  </tr>
                  <tr>
                    <td>Mismatched home/away flags</td>
                    <td>—</td>
                    <td>
                      <a class="btn small" href="/tools/reconcile-schedule-from-boxscores.php">Scan</a>
                      <a class="btn small danger" href="/tools/patch-boxscores-teams-from-schedule.php">Repair</a>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Right: shortcuts + notes -->
            <div>
              <div class="card">
                <h2>Shortcuts</h2>
                <div class="flex">
                  <a class="btn" href="/admin/data-pipeline.php">Open Data Pipeline Hub</a>
                  <a class="btn" href="/admin/assets-hub.php">Open Assets Hub</a>
                  <a class="btn" href="/admin/system-hub.php">Open System Hub</a>
                </div>
              </div>

              <div class="card">
                <h2>Notes</h2>
                <ul class="clean">
                  <li><strong>Audit:</strong> reads <code>data/uploads/schedule-current.json</code> and generated link maps.</li>
                  <li><strong>Auto-Repair:</strong> writes a patched schedule JSON and keeps a backup as <code>*.bak</code>.</li>
                  <li><strong>Reports:</strong> view the on-page report or use Export for CSV/JSON under <code>data/logs/</code>.</li>
                </ul>
              </div>
            </div>
          </div>

        </div><!-- /.page-surface -->
      </div><!-- /.wrap -->
    </div><!-- /.canvas -->
  </div><!-- /.site -->
</body>
</html>
