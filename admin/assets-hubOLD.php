<?php
/**
 * Assets Hub (Admin)
 *
 * Purpose: Central place for asset-related tools with simple last-run/status logging.
 * Uses the shared /data/logs/pipeline-status.json (same as Data Pipeline Hub).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (isset($_SESSION['user']) && empty($_SESSION['user']['is_admin'])) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
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

// ---- TASK DEFINITIONS ----
$groups = [
  'Headshots' => [
    ['id' => 'cache-headshots', 'path' => '../tools/cache_headshots.php', 'label' => 'Headshots Cache', 'danger' => false, 'notes' => 'refresh cache', 'outputs' => ['../assets/img/mugs/.sentinel']],
    ['id' => 'fetch-headshots-bulk', 'path' => '../tools/fetch_headshots_bulk.php', 'label' => 'Fetch Headshots (Bulk)', 'danger' => true, 'notes' => 'network heavy', 'outputs' => []],
  ],
  'Team Mapping' => [
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

// Compute base path for CSS and asset linking so /admin pages don't prepend /admin/
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$base = preg_replace('~/(admin|tools)(/.*)?$~', '', $scriptPath);
if (!$base || $base === $scriptPath) {
  $base = rtrim(dirname($scriptPath), '/');
}
$navCss = rtrim($base, '/') . '/assets/css/nav.css';
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assets Hub</title>
  <link rel="stylesheet" href="<?= h($navCss) ?>">
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
      text-align: left
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
  <!-- R2: Assets Hub contrast pass (local only) -->
  <style>
    /* Base text + headings inside this page */
    .wrap {
      color: #E8EEF5;
    }

    .wrap h1,
    .wrap h2 {
      color: #F2F6FF;
      font-weight: 700;
      letter-spacing: .2px;
    }

    /* Cards & dividers */
    .card {
      background: #0D1117;
      border-color: #FFFFFF1A;
    }

    table {
      color: inherit;
    }

    th,
    td {
      border-bottom: 1px solid #FFFFFF14;
    }

    th {
      color: #DFE8F5;
      font-weight: 700;
    }

    /* Subtext */
    .note {
      color: #A9BACB;
    }

    /* was #bbb */
    .muted {
      color: #95A3B4;
    }

    /* was #aaa */
    .last {
      color: #D4DEE8;
    }

    /* Buttons */
    .btn {
      color: #E6EEF8;
      background: #1B2431;
      border: 1px solid #2F3F53;
    }

    .btn:hover {
      background: #223349;
      border-color: #3D5270;
    }

    .btn.run {
      border-color: #2EA043;
    }

    /* subtle green cue */
    .btn.danger {
      border-color: #D14;
    }

    /* red cue */

    /* Inputs */
    input[type=text] {
      background: #0F1621;
      border: 1px solid #2F3F53;
      color: #E6EEF8;
    }

    input[type=text]::placeholder {
      color: #9FB0C2;
    }

    .mark-form input[type=text]:focus {
      outline: none;
      border-color: #6AA1FF;
      box-shadow: 0 0 0 2px #6AA1FF33;
    }
  </style>
<!-- R2: Assets Hub page surface -->
<style>
  /* “Light gray on dark” surface under the header, inside .wrap width */
  .page-surface{
    margin: 12px 0 32px;
    padding: 16px 16px 24px;
    background: #bec3c91a;            /* subtle light-gray film */
    border-radius: 16px;
    box-shadow:
      inset 0 1px 0 rgba(255,255,255,0.05),
      0 0 0 1px rgba(255,255,255,0.06);  
      min-height: calc(100vh - 220px);
  }

  /* Keep cards crisp on the surface */
  .page-surface .card{
    background:#0D1117;
    border-color:#FFFFFF1A;
  }

  /* Slight contrast bump for headings/text within the surface */
  .page-surface h1, .page-surface h2 { color:#F2F6FF; }
  .page-surface .note  { color:#A9BACB; }
  .page-surface .muted { color:#95A3B4; }
  .page-surface .last  { color:#D4DEE8; }
</style>
<!-- R2: Assets Hub — page background to light gray -->
<style>

  /* In case any site wrapper paints its own bg, neutralize it here */
  .page, .main, .content, .container, .wrap { background: transparent !important; }
</style>
</head>

<body>
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

  <div class="wrap">

    <div class="page-surface">   
       <h1>Assets Hub</h1>
    <p class="muted">Open a tool in a new tab to run it; when complete, return here and click <em>Mark Done</em> to log
      a timestamp. If no log exists yet, we try to infer from the output artifact's modified time.</p>
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
    </div>
    <p class="muted" style="margin-top:14px">Status file: <code><?= h($STATUS_FILE ?? '(unavailable)') ?></code></p>
  </div>

</body>

</html>