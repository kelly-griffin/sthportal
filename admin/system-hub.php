<?php
/**
 * System Hub (Admin)
 *
 * Purpose: Central place for system-related tools with simple last-run/status logging.
 * Uses the shared /data/logs/pipeline-status.json (same as Data Pipeline Hub).
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

// ---- TASK DEFINITIONS (id must be unique across the file) ----
$groups = [
  'Auditing & Logs' => [
    ['id' => 'audit-log', 'path' => 'audit-log.php', 'label' => 'Audit Log', 'danger' => false, 'notes' => 'view events', 'outputs' => []],
    ['id' => 'audit-export', 'path' => 'audit-export.php', 'label' => 'Export Audit Log', 'danger' => false, 'notes' => 'CSV/JSON', 'outputs' => ['../data/logs/audit-export.csv']],
  ],
  'Maintenance & Backup' => [
    ['id' => 'backup-now', 'path' => 'backup-now.php', 'label' => 'Backup Now', 'danger' => true, 'notes' => 'snapshot DB/files', 'outputs' => ['../data/backups/.sentinel']],
    ['id' => 'schema-check', 'path' => 'schema-check.php', 'label' => 'Schema Check', 'danger' => false, 'notes' => 'verify tables', 'outputs' => ['../data/logs/schema-check.json']],
    ['id' => 'maintenance', 'path' => 'maintenance.php', 'label' => 'Maintenance', 'danger' => false, 'notes' => 'utility tools', 'outputs' => []],
    ['id' => 'system-health', 'path' => '../tools/health.php', 'label' => 'System Health', 'danger' => false, 'notes' => 'basic diagnostics', 'outputs' => ['../data/logs/health.json']],
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

// ---- HANDLE POST: mark-done ----
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
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>System Hub</title>
  <style>
    .wrap {
      max-width: 1000px;
      margin: 20px auto;
      padding: 0 12px
    }

    .card {
      border: 1px solid #3333;
      border-radius: 12px;
      padding: 14px;
      background: #0b0b0bcc;
      margin-bottom: 16px
    }

    .card h2 {
      margin: 0 0 8px;
      font-size: 1.1rem
    }

    table {
      width: 100%;
      border-collapse: collapse
    }

    th,
    td {
      padding: 8px;
      border-bottom: 1px solid #3335;
      text-align: left;
      color: var(--ink-soft);
    }

    th {
      font-weight: 600
    }

    .actions {
      display: flex;
      gap: 6px;
      flex-wrap: wrap
    }

    .btn {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 8px;
      border: 1px solid #555;
      background: #1a1a1a;
      color: #fff;
      text-decoration: none
    }

    .btn:hover {
      background: #222
    }

    .btn.run {
      border-color: #3a7;
    }

    .btn.danger {
      border-color: #c55
    }

    .last {
      white-space: nowrap
    }

    .note {
      color: #bbb;
      font-size: .9em
    }

    .muted {
      color: #aaa
    }

    .mark-form {
      display: flex;
      gap: 6px;
      margin-top: 6px
    }

    input[type=text] {
      background: #111;
      border: 1px solid #444;
      border-radius: 6px;
      padding: 6px;
      color: #fff
    }
  </style>
  <!-- R2: Admin Surface (page-local) -->
  <style>
    :root {
      --stage: #585858ff; 
      --card: #0D1117;
      --card-border: #FFFFFF1A;
      --ink: #E8EEF5;
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

    .muted {
      color: var(--ink-soft);
    }

    .card,
    .panel,
    .section {
      background: var(--card);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 16px;
      margin: 18px 0;
      color: inherit;
      box-shadow: inset 0 1px 0 #ffffff1f;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      color: inherit;
    }

    th,
    td {
      padding: 10px 12px;
      border-bottom: 1px solid #ffffff24;
      vertical-align: middle;
    }

    th {
      text-align: left;
      font-weight: 700;
      color: #DFE8F5;
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

    /* optional two-column grid like Quickstart */
    .grid {
      display: grid;
      grid-template-columns: minmax(0, 58%) minmax(0, 42%);
      gap: 20px;
    }

    @media (min-width:1440px) {
      .grid {
        grid-template-columns: minmax(0, 55%) minmax(0, 45%);
      }
    }

    @media (max-width:920px) {
      .grid {
        grid-template-columns: 1fr;
        gap: 14px;
      }
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
          <h1>System Hub</h1>
          <p class="muted">Open a tool in a new tab to run it; when complete, return here and click <em>Mark Done</em>
            to
            log a timestamp. If no log exists yet, we try to infer from the output artifact's modified time.</p>

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
                            target="_blank" rel="noopener">Run</a>
                          <form method="post" class="mark-form" onsubmit="return true">
                            <input type="hidden" name="action" value="mark">
                            <input type="hidden" name="task" value="<?= h($sid) ?>">
                            <input type="text" name="note" placeholder="Optional note…" value="">
                            <button class="btn" type="submit" title="Record timestamp">Mark Done</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; ?>

          <p class="muted" style="margin-top:14px">Status file: <code><?= h($STATUS_FILE ?? '(unavailable)') ?></code>
          </p>
        </div>
      </div>
    </div>
</div>
</body>

</html>