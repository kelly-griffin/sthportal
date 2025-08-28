<?php
/**
 * Audit Schedule Links — Quick Win Wrapper (Admin)
 *
 * One-card page that:
 *  - Explains when/why to run the audit
 *  - Shows last-run time/status (shared pipeline-status.json)
 *  - Offers a Run button that opens the tool in a new tab
 *  - If an audit artifact exists (json/csv), shows a tiny summary (counts) when possible
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (isset($_SESSION['user']) && empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// ---- STATUS LOG FILE ----
$DATA_DIR    = realpath(__DIR__ . '/../data');
$LOG_DIR     = $DATA_DIR ? $DATA_DIR . DIRECTORY_SEPARATOR . 'logs' : null;
$STATUS_FILE = $LOG_DIR ? $LOG_DIR . DIRECTORY_SEPARATOR . 'pipeline-status.json' : null;
if ($LOG_DIR && !is_dir($LOG_DIR)) { @mkdir($LOG_DIR, 0775, true); }

function read_statuses(string $statusFile): array {
    if (!$statusFile || !is_file($statusFile)) return [];
    $json = @file_get_contents($statusFile);
    $data = $json ? json_decode($json, true) : null;
    return is_array($data) ? $data : [];
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ago($ts){ if(!$ts) return '—'; $d=time()-$ts; if($d<60)return $d.'s ago'; if($d<3600)return floor($d/60).'m ago'; if($d<86400)return floor($d/3600).'h ago'; return date('Y-m-d H:i',$ts);} 

$statuses   = read_statuses($STATUS_FILE ?? '');
$taskId     = 'audit-schedule-links';
$taskStatus = $statuses[$taskId] ?? null;
$lastRunTs  = $taskStatus['last_run'] ?? null;
$lastNote   = $taskStatus['note'] ?? '';

// Known outputs for audit tool
$artifactJson = $LOG_DIR ? $LOG_DIR . DIRECTORY_SEPARATOR . 'audit-schedule-links.json' : null;
$artifactCsv  = $LOG_DIR ? $LOG_DIR . DIRECTORY_SEPARATOR . 'audit-schedule-links.csv'  : null;

// Try to glean a tiny summary from JSON if it looks like {errors:[...], warnings:[...]} or similar.
$summary = null;
if ($artifactJson && is_file($artifactJson)) {
    $raw = @file_get_contents($artifactJson);
    $obj = $raw ? json_decode($raw, true) : null;
    if (is_array($obj)) {
        $err = isset($obj['errors'])   && is_array($obj['errors'])   ? count($obj['errors'])   : null;
        $war = isset($obj['warnings']) && is_array($obj['warnings']) ? count($obj['warnings']) : null;
        $sum = isset($obj['summary']) && is_array($obj['summary']) ? $obj['summary'] : null;
        $summary = [
            'errors'   => $err,
            'warnings' => $war,
            'extra'    => $sum,
        ];
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Audit Schedule Links</title>
  <style>
    .wrap{max-width:1000px;margin:20px auto;padding:0 12px}
    .card{border:1px solid #3333;border-radius:12px;padding:14px;background:#0b0b0bcc;margin-bottom:16px}
    h1{margin:0 0 6px}
    .muted{color:#aaa}
    .row{display:grid;grid-template-columns:1fr;gap:14px}
    @media(min-width:900px){.row{grid-template-columns:2fr 1fr}}
    .btn{display:inline-block;padding:8px 12px;border:1px solid #555;border-radius:8px;background:#1a1a1a;color:#fff;text-decoration:none}
    .btn:hover{background:#222}
    .list{margin:0;padding-left:18px}
    .kv{display:grid;grid-template-columns:160px 1fr;gap:6px}
    .kv div{padding:2px 0}
    .badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #444;background:#181818;color:#ddd;font-size:.85em}
    .badge.good{border-color:#2a6;}
    .badge.warn{border-color:#c84;}
    .badge.bad{border-color:#c55;}
    table{width:100%;border-collapse:collapse;margin-top:10px}
    th,td{padding:6px;border-bottom:1px solid #3335;text-align:left;font-size:.95em}
  </style>
</head>
<body>
<?php include __DIR__ . '/../topbar.php'; ?>
<?php include __DIR__ . '/../leaguebar.php'; ?>

<div class="wrap">
  <h1>Audit Schedule Links</h1>
  <p class="muted">Quick health check for game link integrity across the current schedule. Use this to find missing/incorrect boxscore links after a sim, or before publishing public pages.</p>

  <div class="card">
    <div class="row">
      <div>
        <h2 style="margin:0 0 8px;font-size:1.1rem">When to Run</h2>
        <ul class="list">
          <li><strong>Required:</strong> After running <em>Reconcile Schedule</em> or any job that edits links.</li>
          <li><strong>Recommended:</strong> After each sim day import to catch stale/missing links.</li>
          <li><strong>Repair-only:</strong> If audit shows issues, follow up with <em>Fix Schedule Links</em>.</li>
        </ul>
        <p class="muted">Tip: The <em>Data Pipeline Hub</em> centralizes these steps if you prefer one screen.</p>
      </div>
      <div>
        <div class="kv">
          <div>Last run:</div>
          <div><strong><?= h(ago($lastRunTs ?? (is_file($artifactJson)?filemtime($artifactJson):null))) ?></strong></div>
          <div>Status:</div>
          <div>
            <?php $st = $taskStatus['status'] ?? (is_file($artifactJson)||is_file($artifactCsv) ? 'ok' : '—'); ?>
            <span class="badge <?= $st==='ok'?'good':($st==='—'?'':($st==='warn'?'warn':'bad')) ?>"><?= h($st) ?></span>
          </div>
          <?php if(!empty($lastNote)): ?>
            <div>Note:</div><div><?= h($lastNote) ?></div>
          <?php endif; ?>
          <?php if(is_file($artifactJson)): ?>
            <div>Artifact:</div><div><code><?= h(basename($artifactJson)) ?></code> (<?= date('Y-m-d H:i', filemtime($artifactJson)) ?>)</div>
          <?php elseif(is_file($artifactCsv)): ?>
            <div>Artifact:</div><div><code><?= h(basename($artifactCsv)) ?></code> (<?= date('Y-m-d H:i', filemtime($artifactCsv)) ?>)</div>
          <?php endif; ?>
        </div>
        <div style="margin-top:10px">
          <a class="btn" href="../tools/audit-schedule-links.php" target="_blank" rel="noopener">Run Audit</a>
          <a class="btn" href="../tools/fix-schedule-links.php" target="_blank" rel="noopener">Open Fix Tool</a>
          <a class="btn" href="data-pipeline.php">Open Data Pipeline Hub</a>
        </div>
      </div>
    </div>
  </div>

  <?php if ($summary): ?>
  <div class="card">
    <h2 style="margin:0 0 8px;font-size:1.1rem">Latest Audit Summary</h2>
    <table>
      <tbody>
        <?php if ($summary['errors'] !== null): ?>
        <tr><th>Errors</th><td><span class="badge bad"><?= (int)$summary['errors'] ?></span></td></tr>
        <?php endif; ?>
        <?php if ($summary['warnings'] !== null): ?>
        <tr><th>Warnings</th><td><span class="badge warn"><?= (int)$summary['warnings'] ?></span></td></tr>
        <?php endif; ?>
        <?php if (is_array($summary['extra'])): foreach ($summary['extra'] as $k=>$v): ?>
          <tr><th><?= h($k) ?></th><td><?= h(is_scalar($v)? (string)$v : json_encode($v)) ?></td></tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-top:8px">JSON shape not guaranteed — we only surface very basic counts if present. Full detail remains in the artifact file.</p>
  </div>
  <?php endif; ?>

</div>

</body>
</html>
