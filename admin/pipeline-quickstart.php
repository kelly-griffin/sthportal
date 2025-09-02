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
>
<div class="site">
</head>
  <body>
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
    <div class="canvas">
      <div class="pipeline-container">
        <div class="pipeline-card">
          <h1>Data Pipeline Quickstart</h1>
          <p class="dp-lead">Exact order-of-operations with clear labels: <span class="dp-label required">REQUIRED</span> <span
              class="dp-label recommended">RECOMMENDED</span> <span class="dp-label optional">OPTIONAL</span> <span
              class="dp-label repair">REPAIR‑ONLY</span>.</p>
    <!-- Row 1: Daily Flow (L) + Shortcuts (R) -->
          <div class="dp-grid">
            <div>
              <div class="dp-section">
                <h2>Daily Flow (after each sim)</h2>
                <ol class="dp-steps">
                  <li><span class="dp-label required">REQUIRED</span> <strong>STHS Importer</strong> — ingest the latest
                    outputs. <a class="btn" href="sths-importer.php">Open</a></li>
                  <li><span class="dp-label required">REQUIRED</span> <strong>Update Schedule</strong> — refresh
                    <code>schedule-current.json</code>. (via <em>Data Pipeline Hub</em>) <a class="btn"
                      href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="dp-label recommended">RECOMMENDED</span> <strong>Enrich Schedule</strong> — add links/flags for
                    games. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="dp-label recommended">RECOMMENDED</span> <strong>Build Boxscores JSON</strong> — consolidate box
                    data. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="dp-label recommended">RECOMMENDED</span> <strong>Audit Schedule Links</strong> — quick integrity
                    check. <a class="btn" href="audit-schedule-links.php">Open Wrapper</a></li>
                  <li><span class="dp-label optional">OPTIONAL</span> <strong>Build PBP Stats</strong> — derived metrics from
                    PBP. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="dp-label recommended">RECOMMENDED</span> <strong>Build Home/Ticker JSON</strong> — update
                    homepage widgets. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                </ol>
                <div class="dp-note">
                  <strong>Quick sanity checks:</strong>
                  <ul>
                    <li>Homepage leaders & ticker reflect today’s sim.</li>
                    <li>Schedule page shows correct scores/times.</li>
                    <li>Random boxscore opens from today’s slate.</li>
                  </ul>
                </div>
              </div>

              <div class="dp-section">
                <h2>Weekly Touch‑ups</h2>
                <ul class="dp-steps">
                  <li><span class="dp-label recommended">RECOMMENDED</span> <strong>Reconcile Schedule</strong> — align schedule
                    vs. boxscores. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="dp-label recommended">RECOMMENDED</span> <strong>Headshots Cache</strong> — refresh recent
                    movers/call‑ups. <a class="btn" href="assets-hub.php">Assets Hub</a></li>
                  <li><span class="dp-label optional">OPTIONAL</span> <strong>Generate Recaps</strong> — draft stories for
                    publication. <a class="btn" href="data-pipeline.php">Open Hub</a></li>
                  <li><span class="dp-label recommended">RECOMMENDED</span> <strong>Backup Now</strong> — snapshot DB/files. <a
                      class="btn" href="system-hub.php">System Hub</a></li>
                </ul>
              </div>

              <div class="dp-section">
                <h2>Repair Playbook</h2>
                <table class="dp-table">
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
                      <td><span class="dp-label repair">REPAIR‑ONLY</span></td>
                    </tr>
                    <tr>
                      <td>Teams out of sync in boxscores</td>
                      <td><a href="data-pipeline.php">Patch Boxscores Teams</a> (via Hub)</td>
                      <td><span class="dp-label repair">REPAIR‑ONLY</span></td>
                    </tr>
                    <tr>
                      <td>Box JSON stale or corrupted</td>
                      <td><a href="data-pipeline.php">Rebuild Box JSON (from HTML)</a> (slow)</td>
                      <td><span class="dp-label repair">REPAIR‑ONLY</span></td>
                    </tr>
                    <tr>
                      <td>Homepage widgets outdated</td>
                      <td><a href="data-pipeline.php">Build Home JSON</a> and <a href="data-pipeline.php">Build Ticker
                          JSON</a></td>
                      <td><span class="dp-label recommended">RECOMMENDED</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div>
              <div class="dp-section">
                <h2>Shortcuts</h2>
                <div class="dp-shortcut">
                  <a class="btn" href="data-pipeline.php">Open Data Pipeline Hub</a>
                  <a class="btn" href="assets-hub.php">Open Assets Hub</a>
                  <a class="btn" href="system-hub.php">Open System Hub</a>
                  <a class="btn" href="audit-links-wrapper.php">Audit Links Wrapper</a>
                </div>
              </div>

              <div class="dp-section">
                <h2>What writes what?</h2>
                <ul class="dp-list">
                  <li><strong>Update Schedule</strong> → <code>data/uploads/schedule-current.json</code></li>
                  <li><strong>Build Boxscores JSON</strong> → <code>data/uploads/boxscores/index.json</code></li>
                  <li><strong>Build Home/Ticker JSON</strong> → <code>data/uploads/home.json</code>,
                    <code>data/uploads/ticker.json</code></li>
                  <li><strong>Headshots Cache</strong> → <code>assets/img/mugs/</code> (+ <code>.sentinel</code>)</li>
                  <li><strong>Audit Links</strong> → <code>data/logs/audit-schedule-links.json</code> /
                    <code>.csv</code></li>
                </ul>
              </div>

              <div class="dp-section">
                <h2>Labels</h2>
                <p class="dp-lead">How we categorize steps in this quickstart.</p>
                <ul class="dp-list">
                  <li><span class="dp-label required">REQUIRED</span> Must run for the portal to reflect the latest sim.</li>
                  <li><span class="dp-label recommended">RECOMMENDED</span> Improves quality and UX, but not strictly mandatory.
                  </li>
                  <li><span class="dp-label optional">OPTIONAL</span> Nice to have; can be deferred.</li>
                  <li><span class="dp-label repair">REPAIR‑ONLY</span> Use only to correct specific issues, not daily.</li>
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
