<?php
/**
 * System Hub (Admin)
 *
 * Purpose: Central place for system-related tools with simple last-run/status logging.
 * Uses the shared /data/logs/pipeline-status.json (same as Data Pipeline Hub).
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
@include_once __DIR__ . '/../includes/admin-helpers.php';

// Standard gates (match legacy pages)
require_login();
require_admin();

// Ensure CSRF is present (if bootstrap didn’t already set it)
if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}
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

</head>
<div class="site">

  <body>
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
    <div class="canvas">
      <div class="system-container">
        <div class="system-card">
          <h1>System Hub</h1>
          <div class="system-lead">
            <p class="system-muted">Open a tool in a new tab to run it; when complete, return here and click <em>Mark
                Done</em>
              to
              log a timestamp. If no log exists yet, we try to infer from the output artifact's modified time.</p>

            <?php foreach ($groups as $groupName => $tasks): ?>
              <div class="system-audit-card">
                <h2><?= h($groupName) ?></h2>
                <table class="system-audit-table">
                  <thead>
                    <tr>
                      <th style="width:20%">Tool</th>
                      <th style="width:20%">Last run</th>
                      <th style="width:20%">Status</th>
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
                          <div class="system-label"><strong><?= h($t['label']) ?></strong></div>
                          <?php if (!empty($note)): ?>
                            <div class="system-note"><?= h($note) ?></div><?php endif; ?>
                          <div class="system-muted" style="font-size:.85em"><?= h($t['path']) ?></div>
                        </td>
                        <td class="last"><?= h(ago($ts)) ?></td>
                        <td><?= h($statusText) ?></td>
                        <td>
                          <div class="actions">
                            <a class="btn run<?= !empty($t['danger']) ? ' danger' : '' ?>" href="<?= h($t['path']) ?>"
                              target="_blank" rel="noopener">Run</a>
                            <form method="post" class="system-markdone" onsubmit="return true">
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

            <p class="system-muted" style="margin-top:14px">Status file: <code><?= h($STATUS_FILE ?? '(unavailable)') ?></code>
            </p>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>