<?php
/**
 * Data Pipeline Hub (Admin)
 *
 * Purpose: one screen with run-buttons + simple status for the league's data pipeline.
 * Scope: non-destructive by default. Some tasks marked as "danger" will show a guard-rail modal.
 *
 * Notes:
 * - This page does NOT change any existing tool. It only links to them.
 * - "Last run" is tracked in /data/logs/pipeline-status.json when you click "Mark Done" after a run.
 *   (Open tool in a new tab → verify → come back → click Mark Done.)
 * - You can later wire tools to write to the same status file via a tiny helper (see bottom comment).
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

// ---- STATUS LOG FILE ----
$DATA_DIR = realpath(__DIR__ . '/../data');
$LOG_DIR = $DATA_DIR ? $DATA_DIR . DIRECTORY_SEPARATOR . 'logs' : null;
$STATUS_FILE = $LOG_DIR ? $LOG_DIR . DIRECTORY_SEPARATOR . 'pipeline-status.json' : null;
if ($LOG_DIR && !is_dir($LOG_DIR)) {
  @mkdir($LOG_DIR, 0775, true);
}

function read_statuses(string $statusFile): array
{
  if (!$statusFile || !is_file($statusFile))
    return [];
  $json = @file_get_contents($statusFile);
  $data = $json ? json_decode($json, true) : null;
  return is_array($data) ? $data : [];
}

function write_statuses(string $statusFile, array $data): void
{
  @file_put_contents($statusFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$statuses = read_statuses($STATUS_FILE ?? '');

// ---- TASKS (buttons) ----
$groups = [
  'Schedule & Data' => [
    ['id' => 'build-schedule-json', 'path' => '../tools/build-schedule-json.php', 'label' => 'Update Schedule', 'danger' => false, 'notes' => '+ builds schedule-current.json', 'outputs' => ['../data/uploads/schedule-current.json']],
    ['id' => 'build-schedule-full', 'path' => '../tools/build-schedule-full.php', 'label' => 'Build Schedule (Full)', 'danger' => false, 'notes' => 'full season artifact', 'outputs' => ['../data/uploads/schedule-full.json', '../data/uploads/schedule.json']],
    ['id' => 'enrich-schedule', 'path' => '../tools/enrich-schedule.php', 'label' => 'Enrich Schedule', 'danger' => false, 'notes' => 'adds links/flags', 'outputs' => []],
    ['id' => 'reconcile-schedule', 'path' => '../tools/reconcile-schedule-from-boxscores.php', 'label' => 'Reconcile Schedule', 'danger' => true, 'notes' => 'align from boxscores', 'outputs' => []],
    ['id' => 'audit-schedule-links', 'path' => '../tools/audit-schedule-links.php', 'label' => 'Audit Schedule Links', 'danger' => false, 'notes' => 'report only', 'outputs' => ['../data/logs/audit-schedule-links.json', '../data/logs/audit-schedule-links.csv']],
    ['id' => 'fix-schedule-links', 'path' => '../tools/fix-schedule-links.php', 'label' => 'Fix Schedule Links', 'danger' => true, 'notes' => 'edits schedule links', 'outputs' => []],
    ['id' => 'patch-box-teams', 'path' => '../tools/patch-boxscores-teams-from-schedule.php', 'label' => 'Patch Boxscores Teams', 'danger' => true, 'notes' => 'team name sync', 'outputs' => []],
    ['id' => 'build-box-json', 'path' => '../tools/build-boxscores-json.php', 'label' => 'Build Boxscores JSON', 'danger' => false, 'notes' => 'fast build from sources', 'outputs' => ['../data/uploads/boxscores/index.json']],
    ['id' => 'rebuild-boxjson-html', 'path' => '../tools/rebuild-boxjson-from-html.php', 'label' => 'Rebuild Box JSON (from HTML)', 'danger' => true, 'notes' => 'heavy/slow reparse', 'outputs' => ['../data/uploads/boxscores/index.json']],
    ['id' => 'build-pbp-stats', 'path' => '../tools/build-pbp-stats.php', 'label' => 'Build PBP Stats', 'danger' => false, 'notes' => 'derived stats', 'outputs' => ['../data/uploads/pbp/derived.json']],
    ['id' => 'sths-importer', 'path' => './sths-importer.php', 'label' => 'STHS Importer', 'danger' => false, 'notes' => 'league ingest', 'outputs' => []],
  ],
  'Content' => [
    ['id' => 'generate-recaps', 'path' => '../tools/generate-recaps.php', 'label' => 'Generate Recaps', 'danger' => false, 'notes' => 'draft stories', 'outputs' => ['../data/uploads/recaps/last-run.json']],
    ['id' => 'build-home-json', 'path' => '../tools/build-home-json.php', 'label' => 'Build Home JSON', 'danger' => false, 'notes' => 'home widgets', 'outputs' => ['../data/uploads/home.json']],
    ['id' => 'build-ticker-json', 'path' => '../tools/build-ticker-json.php', 'label' => 'Build Ticker JSON', 'danger' => false, 'notes' => 'header ticker', 'outputs' => ['../data/uploads/ticker.json']],
  ],
  'Assets' => [
    ['id' => 'cache-headshots', 'path' => '../tools/cache_headshots.php', 'label' => 'Headshots Cache', 'danger' => false, 'notes' => 'refresh cache', 'outputs' => ['../assets/img/mugs/.sentinel']],
    ['id' => 'fetch-headshots-bulk', 'path' => '../tools/fetch_headshots_bulk.php', 'label' => 'Fetch Headshots (Bulk)', 'danger' => true, 'notes' => 'network heavy', 'outputs' => []],
    ['id' => 'build-team-map', 'path' => '../tools/build-team-map.php', 'label' => 'Build Team Map', 'danger' => false, 'notes' => 'abbr/city mapping', 'outputs' => ['../data/uploads/teams.json', '../data/uploads/team-map.json']],
  ],
];

function probe_last_mtime(array $outputs): ?int
{
  foreach ($outputs as $rel) {
    $abs = realpath(__DIR__ . '/' . $rel);
    if ($abs && is_file($abs)) {
      $t = @filemtime($abs);
      if ($t)
        return $t;
    }
  }
  return null;
}

if (($_POST['action'] ?? '') === 'mark' && !empty($_POST['task'])) {
  $task = preg_replace('~[^a-z0-9\-]~i', '', $_POST['task']);
  $note = trim((string) ($_POST['note'] ?? ''));
  $statuses[$task] = [
    'last_run' => time(),
    'status' => 'ok',
    'note' => $note,
    'user' => $_SESSION['user']['username'] ?? 'admin',
  ];
  if ($STATUS_FILE)
    write_statuses($STATUS_FILE, $statuses);
  header('Location: ' . $_SERVER['REQUEST_URI']);
  exit;
}

function h($s)
{
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function ago($ts)
{
  if (!$ts)
    return '—';
  $diff = time() - $ts;
  if ($diff < 60)
    return $diff . 's ago';
  if ($diff < 3600)
    return floor($diff / 60) . 'm ago';
  if ($diff < 86400)
    return floor($diff / 3600) . 'h ago';
  return date('Y-m-d H:i', $ts);
}

?><!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Data Pipeline Hub</title>
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

    body {
      margin: 0;
      background: #202428;
      color: var(--ink);
      font: 14px/1.5 system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
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
  </style>

</head>

<body>
  <div class="site">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
    <div class="canvas">
      <div class="wrap">
        <div class="page-surface">
          <h1>Data Pipeline Hub</h1>
          <p class="muted">Open a task in a new tab to run it. When it finishes and looks good, return here and click
            <em>Mark Done</em> so we log the timestamp. Tasks marked <strong class="note">danger</strong> will show a
            confirmation modal.
          </p>

          <div class="grid">
            <?php foreach ($groups as $groupName => $tasks): ?>
              <div class="card">
                <h2><?= h($groupName) ?></h2>
                <table>
                  <thead>
                    <tr>
                      <th style="width:38%">Tool</th>
                      <th style="width:20%">Last run</th>
                      <th style="width:15%">Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($tasks as $t):
                      $sid = $t['id'];
                      $s = $statuses[$sid] ?? null;
                      $ts = $s['last_run'] ?? null;
                      if (!$ts) {
                        $probe = probe_last_mtime($t['outputs'] ?? []);
                        if ($probe)
                          $ts = $probe;
                      }
                      $statusText = $s['status'] ?? ($ts ? 'ok' : '—');
                      $note = $s['note'] ?? ($t['notes'] ?? '');
                      ?>
                      <tr>
                        <td>
                          <div><strong><?= h($t['label']) ?></strong></div>
                          <?php if (!empty($note)): ?>
                            <div class="note"><?= h($note) ?></div><?php endif; ?>
                          <div class="muted" style="font-size:.85em"><?= h($t['path']) ?></div>
                        </td>
                        <td class="last"><?= h(ago($ts)) ?></td>
                        <td><?= h($statusText) ?></td>
                        <td>
                          <div class="actions">
                            <a class="btn run<?= !empty($t['danger']) ? ' danger' : '' ?>" href="<?= h($t['path']) ?>"
                              target="_blank" data-danger="<?= !empty($t['danger']) ? '1' : '0' ?>">Run</a>
                            <form method="post" class="mark-form" onsubmit="return true">
                              <input type="hidden" name="action" value="mark">
                              <input type="hidden" name="task" value="<?= h($sid) ?>">
                              <input type="text" name="note" placeholder="Optional note…" value="">
                              <button class="btn" type="submit" title="Record timestamp">Mark Done</button>
                            </form>
                            </div>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>

            <?php endforeach; ?>
          


      <p class="muted" style="margin-top:14px">Status file: <code><?= h($STATUS_FILE ?? '(unavailable)') ?></code></p>

  <div class="modal" id="dangerModal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="box">
      <h3>Confirm heavy/destructive run</h3>
      <p>This task may be slow or modify existing artifacts. Make sure you have a fresh backup before proceeding.</p>
      <p class="muted">Tip: run it in a separate tab; verify results; then come back and click <em>Mark Done</em>.</p>
      <div class="actions" style="margin-top:10px">
        <a href="#" class="btn" id="dangerCancel">Cancel</a>
        <a href="#" class="btn danger" id="dangerProceed" target="_blank" rel="noopener">Proceed</a>
              </div>              
      </div>
    </div>
    </div>
  </div>
  </div>
  <script>
    (function () {
      const modal = document.getElementById('dangerModal');
      const cancel = document.getElementById('dangerCancel');
      const proceed = document.getElementById('dangerProceed');
      let pendingHref = null;

      function openModal(href) {
        pendingHref = href;
        proceed.setAttribute('href', href);
        modal.style.display = 'flex';
      }
      function closeModal() {
        modal.style.display = 'none';
        pendingHref = null;
      }
      cancel.addEventListener('click', function (e) { e.preventDefault(); closeModal(); });
      proceed.addEventListener('click', function () { closeModal(); });

      document.querySelectorAll('a.btn.run').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          const danger = btn.getAttribute('data-danger') === '1';
          const href = btn.getAttribute('href');
          if (danger) {
            e.preventDefault();
            openModal(href);
          }
        });
      });
    })();
  </script>
</body>

</html>