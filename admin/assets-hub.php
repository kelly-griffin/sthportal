<?php
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

// --- config ---
$STATUS_FILE = __DIR__ . '/../data/logs/pipeline-status.json';

// ---- TASK DEFINITIONS (restored) ----
$groups = [
  'Headshots' => [
    [
      'key'    => 'cache-headshots',
      'title'  => 'Headshots Cache',
      'path'   => '../tools/cache_headshots.php',
      'notes'  => 'refresh cache',
      'danger' => false,
      'outputs'=> ['../assets/img/mugs/.sentinel']
    ],
    [
      'key'    => 'fetch-headshots-bulk',
      'title'  => 'Fetch Headshots (Bulk)',
      'path'   => '../tools/fetch_headshots_bulk.php',
      'notes'  => 'network heavy',
      'danger' => true,
      'outputs'=> []
    ],
  ],
  'Team Mapping' => [
    [
      'key'    => 'build-team-map',
      'title'  => 'Build Team Map',
      'path'   => '../tools/build-team-map.php',
      'notes'  => 'abbr/city mapping',
      'danger' => false,
      'outputs'=> ['../data/uploads/teams.json','../data/uploads/team-map.json']
    ],
  ],
];

// --- helpers ---
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function read_statuses(string $file): array {
  if (!$file || !is_file($file)) return [];
  $raw = @file_get_contents($file);
  $data = $raw ? json_decode($raw, true) : null;
  return is_array($data) ? $data : [];
}
function write_statuses(string $file, array $data): void {
  @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/** Fallback: if no status logged, infer last-run from any output file mtime */
function probe_last_mtime(array $outputs): ?int {
  foreach ($outputs as $rel) {
    $abs = realpath(__DIR__ . '/' . $rel);
    if ($abs && is_file($abs)) {
      $t = @filemtime($abs);
      if ($t) return $t;
    }
  }
  return null;
}

// --- handle "Mark Done" ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_done'], $_POST['tool_key'])) {
  $toolKey = (string)$_POST['tool_key'];
  $note    = trim((string)($_POST['note'] ?? ''));
  $now     = date('c');
  $statuses = read_statuses($STATUS_FILE);
  $statuses[$toolKey] = ['lastRun' => $now, 'note' => $note];
  write_statuses($STATUS_FILE, $statuses);
  header('Location: '.$_SERVER['PHP_SELF'].'?ok=1'); exit;
}

$statuses = read_statuses($STATUS_FILE);

// derive flat tool list with keys for status mapping
$flat = [];
foreach ($groups as $gName => $tools) {
  if (!is_array($tools)) continue;
  foreach ($tools as $tool) {
    if (empty($tool['title']) || empty($tool['path'])) continue;
    $key = $tool['key'] ?? strtolower(preg_replace('~\s+~','-', (string)$tool['title']));
    $flat[$key] = true;
  }
}

$title = 'Assets Hub';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> — UHA Portal</title>

  <style>
    :root{
      --stage:#585858ff;      /* translucent light gray */
      --card:#0D1117;        /* dark card */
      --card-border:#FFFFFF1A;
      --ink:#E8EEF5;
      --ink-muted:#A9BACB;
      --ink-soft:#95A3B4;
      --accent:#6AA1FF;
      --site-width:1200px;
    }
    body{ margin:0; background:#202428; color:var(--ink); font:14px/1.5 system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; }
    .site{ width:var(--site-width); margin:0 auto; min-height:100vh; background:transparent; }
    .canvas{ padding:0 16px 40px; }
    .wrap{ max-width:1000px; margin:20px auto; }

    /* page surface (center stage like Front Office) */
    .page-surface{
      margin:12px 0 32px;
      padding:16px 16px 24px;
      background:var(--stage);
      border-radius:16px;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.05), 0 0 0 1px rgba(255,255,255,.06);
      min-height: calc(100vh - 220px);
    }

    h1{ font-size:38px; margin:8px 0 10px; letter-spacing:.2px; color:#F2F6FF; }
    h2{ font-size:18px; margin:0 0 10px; color:#DFE8F5; }
    .muted{ color:var(--ink-soft); }

    .card{ background:var(--card); border:1px solid var(--card-border); border-radius:14px; padding:14px; margin:18px 0; }
    table{ width:100%; border-collapse:collapse; color:inherit; }
    th,td{ padding:10px 12px; border-bottom:1px solid #FFFFFF14; vertical-align:middle; }
    th{ text-align:left; font-weight:700; color:#DFE8F5; }

    .tool-title{ font-weight:700; color:#E6EEF8; }
    .tool-sub{ font-size:12px; color:var(--ink-muted); }

    .actions{ display:flex; gap:10px; align-items:center; }
    .btn{ display:inline-block; padding:6px 10px; border-radius:10px; border:1px solid #2F3F53; background:#1B2431; color:#E6EEF8; text-decoration:none; }
    .btn:hover{ background:#223349; border-color:#3D5270; }
    .btn.run{ border-color:#2EA043; }
    .btn.danger{ border-color:#D14; }
    .btn.mark{ border-color:#3D5270; }

    .note-input{ width:220px; padding:6px 8px; border-radius:8px; border:1px solid #2F3F53; background:#0F1621; color:#E6EEF8; }
    .note-input::placeholder{ color:#9FB0C2; }
    .note-input:focus{ outline:none; border-color:var(--accent); box-shadow:0 0 0 2px #6AA1FF33; }
  </style>
</head>
<body>
  <div class="site">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="wrap">
        <div class="page-surface">
          <h1><?= h($title) ?></h1>
          <p class="muted">Open a tool in a new tab to run it; when complete, return here and click <em>Mark Done</em> to log a timestamp. If no log exists yet, we try to infer from the output artifact's modified time.</p>

          <!-- NEW: Uploads card (first card on page) -->
         <div class="card" id="uploads-card">
  <h2>Uploads</h2>
  <div class="actions">
<button type="button" class="btn" id="btnUploadLeague">Upload League File</button>
<a class="btn" href="<?= h(u('admin/upload-portal-files.php')) ?>">Upload Portal Files</a>

<form id="leagueUploadForm" action="<?= h(u('upload-league-file.php')) ?>" method="post" ...>
  </div>

  <!-- Hidden direct-upload form: opens file picker and auto-submits -->
  <form id="leagueUploadForm"
        action="/upload-league-file.php"
        method="post"
        enctype="multipart/form-data"
        style="display:none">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
    <input type="file" id="leagueFileInput" name="league_file" accept=".stc">
  </form>

  <p class="muted">League file must be <code>.stc</code> format.</p>
  <p class="muted">Tip: after uploading, run parsers in the
    <a href="<?= h(u('admin/data-pipeline.php')) ?>">Data Pipeline Hub</a>.
  </p>

  <script>
    (function () {
      const btn   = document.getElementById('btnUploadLeague');
      const input = document.getElementById('leagueFileInput');
      const form  = document.getElementById('leagueUploadForm');

      if (btn && input && form) {
        // click opens the OS chooser
        btn.addEventListener('click', () => input.click());

        // when a file is chosen, submit the hidden form
        input.addEventListener('change', () => {
          if (input.files && input.files.length) form.submit();
        });
      }

      // allow deep-linking: /admin/assets-hub.php?do=upload-league
      try {
        const p = new URLSearchParams(location.search);
        if (p.get('do') === 'upload-league' && btn) btn.click();
      } catch (_) {}
    })();
  </script>
</div>

          <?php if (!empty($groups)): ?>
            <?php foreach ($groups as $groupName => $tasks): ?>
              <div class="card">
                <h2><?= h($groupName) ?></h2>
                <table>
                  <thead>
                    <tr>
                      <th>Tool</th>
                      <th style="width:160px">Last run</th>
                      <th style="width:110px">Status</th>
                      <th style="width:360px">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (is_array($tasks) && !empty($tasks)): ?>
                      <?php foreach ($tasks as $task): ?>
                        <?php
                          $title = (string)($task['title'] ?? '');
                          $path  = (string)($task['path']  ?? '');
                          if ($title === '' || $path === '') continue;
                          $key   = $task['key'] ?? strtolower(preg_replace('~\s+~','-', $title));
                          $stat  = $statuses[$key] ?? null;
                          $last  = $stat['lastRun'] ?? '—';
                          $note  = $stat['note'] ?? '';
                        ?>
                        <tr>
                          <td>
                            <div class="tool-title"><?= h($title) ?></div>
                            <div class="tool-sub"><?= h($path) ?></div>
                          </td>
                          <td><?= h($last) ?></td>
                          <td><?= h($task['status'] ?? '—') ?></td>
                          <td>
                            <div class="actions">
                              <a class="btn run" target="_blank" href="<?= h($path) ?>">Run</a>
                              <form method="post" style="display:flex; gap:10px; align-items:center; margin:0">
                                <input type="hidden" name="tool_key" value="<?= h($key) ?>">
                                <input type="hidden" name="mark_done" value="1">
                                <input class="note-input" type="text" name="note" placeholder="Optional note..." value="<?= h($note) ?>">
                                <button class="btn mark" type="submit">Mark<br>Done</button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="4" class="muted">No tools in this group yet.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="card">
              <h2>Tools</h2>
              <p class="muted">No tool groups are configured in this environment.</p>
            </div>
          <?php endif; ?>

          <p class="muted" style="margin-top:14px">Status file: <code><?= h($STATUS_FILE ?? '(unavailable)') ?></code></p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
