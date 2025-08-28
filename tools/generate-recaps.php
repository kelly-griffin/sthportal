<?php
/**
 * tools/generate-recaps.php (v3)
 * - Prefers nested scores home/visitor.score; ignores 0–0 placeholders
 * - If still unknown, derives final from last scoreboard in HTML fallback
 * - PBP from JSON when available; otherwise from HTML but filters junk
 *   (keeps only lines like "Goal by ..." or containing "Goal!" and drops
 *    "Goalie Stats", "Goals for this period...", "End of Period", etc.)
 *
 * Preview: /tools/generate-recaps.php
 * Write:   /tools/generate-recaps.php?write=1
 * One:     /tools/generate-recaps.php?gid=UHA-123[&write=1]
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$ROOT = dirname(__DIR__);
$BOX  = $ROOT . '/data/uploads/boxscores';
$HTML = $ROOT . '/data/uploads';
$OUT  = $ROOT . '/data/recaps';

$write   = isset($_GET['write']) && (string)$_GET['write'] === '1';
$onlyGid = isset($_GET['gid']) ? preg_replace('~[^A-Za-z0-9\-]~', '', (string)$_GET['gid']) : '';

if (!is_dir($BOX)) { echo "<h1>Boxscore dir not found</h1><p>{$BOX}</p>"; exit; }
if (!is_dir($OUT)) { @mkdir($OUT, 0775, true); }

function rread($path) {
  $raw = @file_get_contents($path);
  if ($raw === false) return [false, "read_fail", null];
  // gz / BOM / UTF-16
  if (strncmp($raw, "\x1F\x8B", 2) === 0 && function_exists('gzdecode')) { $tmp = @gzdecode($raw); if ($tmp !== false && $tmp !== null) $raw = $tmp; }
  if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) $raw = substr($raw,3);
  if (strpos(substr($raw,0,64), "\x00") !== false) { $raw = @mb_convert_encoding($raw,'UTF-8','UTF-16,UTF-16LE,UTF-16BE'); }
  $j = json_decode((string)$raw, true);
  if (!is_array($j)) return [false, "json_decode", null];
  return [true, "ok", $j];
}
function text($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function pick($a, $keys){ foreach($keys as $k){ if(isset($a[$k])) return $a[$k]; } return null; }
function arr($a, $keys){ foreach($keys as $k){ if(isset($a[$k]) && is_array($a[$k])) return $a[$k]; } return []; }

/* ---- Scores & team names ---- */
function extract_scores_and_names($j, $htmlPath){
  // Prefer nested first (these are the ones schedule.php used successfully)
  $hs = null; $vs = null;
  if (isset($j['home']) && is_array($j['home'])) {
    $hs = pick($j['home'], ['score','goals','final','goalsTotal']);
    $homeName = pick($j['home'], ['name','teamName','shortName']);
  }
  if (isset($j['visitor']) && is_array($j['visitor'])) {
    $vs = pick($j['visitor'], ['score','goals','final','goalsTotal']);
    $awayName = pick($j['visitor'], ['name','teamName','shortName']);
  }
  // Fallbacks
  if ($hs === null) $hs = pick($j, ['homeScore','HomeScore','home_score']);
  if ($vs === null) $vs = pick($j, ['visitorScore','VisitorScore','visitor_score']);
  if (!$homeName) $homeName = pick($j, ['homeName','home_name','homeTeamName']);
  if (!$awayName) $awayName = pick($j, ['visitorName','visitor_name','awayTeamName']);

  // Normalize ints if numeric, otherwise leave null
  $hs = is_numeric($hs) ? (int)$hs : null;
  $vs = is_numeric($vs) ? (int)$vs : null;

  // If both are zero, treat as unknown placeholder and try HTML fallback
  if ($hs === 0 && $vs === 0) { $hs = null; $vs = null; }

  // Derive from HTML "goal by ..." scoreboard (last one is final)
  if (($hs === null || $vs === null) && is_file($htmlPath)) {
    $html = @file_get_contents($htmlPath);
    if ($html !== false) {
      $html = preg_replace('~\s+~', ' ', $html);
      // find all "... TeamA : X - TeamB : Y"
      if (preg_match_all('~:\s*(\d+)\s*-\s*[^:]+:\s*(\d+)~', $html, $m)) {
        $idx = count($m[0]) - 1;
        $vs = (int)$m[1][$idx];
        $hs = (int)$m[2][$idx];
      }
    }
  }

  return [$homeName ?: 'Home', $awayName ?: 'Visitor', $hs, $vs];
}

/* ---- PBP ---- */
function collect_goals_from_json($j){
  // Accept multiple layouts, and period-nested structures
  $pbp = arr($j, ['pbp','plays','playByPlay','events','PBP','play_by_play']);
  if (!$pbp && isset($j['pbp']) && isset($j['pbp']['periods']) && is_array($j['pbp']['periods'])) {
    $tmp = [];
    foreach ($j['pbp']['periods'] as $p) {
      if (isset($p['events']) && is_array($p['events'])) $tmp = array_merge($tmp, $p['events']);
    }
    $pbp = $tmp;
  }
  $goals = [];
  foreach ($pbp as $ev) {
    $type = strtolower((string)pick($ev, ['type','event','code']));
    $desc = strtolower((string)pick($ev, ['desc','description','text','details']));
    $isGoal = (strpos($type,'goal') !== false) || (strpos($desc,'goal') !== false) || (pick($ev,['code']) === 'G');
    if (!$isGoal) continue;

    $p = (string)pick($ev, ['period','prd','p']);
    $t = (string)pick($ev, ['time','clock','timeInPeriod']);
    $tm= (string)pick($ev, ['team','byTeam','for','teamAbbr','abbr']);
    $sc= (string)pick($ev, ['scorer','player','by','playerName','primaryPlayer']);
    $a1= (string)pick($ev, ['assist1','a1','assist_1','secondaryPlayer']);
    $a2= (string)pick($ev, ['assist2','a2','assist_2','tertiaryPlayer']);
    $str= strtoupper((string)pick($ev, ['strength','str','situation']));

    $assistTxt = '';
    $alist = array_values(array_filter([$a1,$a2]));
    if ($alist) $assistTxt = ' (' . implode(', ', $alist) . ')';

    $perTxt = $p ? (in_array($p,['1','2','3']) ? ($p.'P') : ('P'.$p)) : '';
    $note = ($str && $str !== 'EVEN' && $str !== 'EV') ? " [{$str}]" : '';

    $left = trim(implode(' ', array_filter([$perTxt, $t, $tm ? strtoupper($tm) : ''])));
    $line = trim($left . ' — ' . $sc . $assistTxt . $note);
    if ($line !== '—') $goals[] = $line;
  }
  return $goals;
}

function collect_goals_from_html_filtered($htmlPath){
  if (!is_file($htmlPath)) return [];
  $html = @file_get_contents($htmlPath);
  if ($html === false) return [];
  $html = preg_replace('~\s+~', ' ', $html);

  $lines = [];
  // Pull list items and block text
  if (preg_match_all('~<li[^>]*>(.*?)</li>~i', $html, $m)) {
    foreach ($m[1] as $li) {
      $plain = trim(strip_tags($li));
      // Keep only "Goal by ..." or "... Goal!" style, skip junk
      if (preg_match('~^\s*Goal by\b~i', $plain) || preg_match('~\bGoal!\b~i', $plain)) {
        $lines[] = $plain;
      }
    }
  }
  if (empty($lines) && preg_match_all('~>([^<]*Goal by [^<]*)<~i', $html, $m2)) {
    foreach ($m2[1] as $txt) {
      $plain = trim($txt);
      if ($plain !== '') $lines[] = $plain;
    }
  }

  // De-duplicate and return
  $lines = array_values(array_unique($lines));
  return $lines;
}

function build_recap_html($base, $j, $htmlFallbackPath) {
  [$homeName, $awayName, $hs, $vs] = extract_scores_and_names($j, $htmlFallbackPath);
  $gwg = trim((string)($j['gwg'] ?? ''));
  $wG  = trim((string)($j['winningGoalie'] ?? ''));

  $goals = collect_goals_from_json($j);
  if (!$goals) {
    $goals = collect_goals_from_html_filtered($htmlFallbackPath);
  }

  $titleScore = ($vs !== null && $hs !== null) ? " — {$awayName} {$vs} @ {$homeName} {$hs}" : '';

  ob_start(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Recap <?= text($base) . text($titleScore) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font:16px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0e1114;color:#e8eaed;margin:0;padding:24px}
  .card{max-width:900px;margin:0 auto;background:#11161c;border:1px solid #1f2630;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.25)}
  header{padding:16px 20px;border-bottom:1px solid #1f2630}
  h1{font-size:20px;margin:0 0 6px}
  .meta{color:#aab2bd;font-size:14px}
  .section{padding:16px 20px;border-top:1px solid #1f2630}
  .section h2{font-size:16px;margin:0 0 10px;color:#cfd8e3}
  ul{margin:0;padding-left:18px}
  .pill{display:inline-block;background:#1e2a36;color:#cfe6ff;border:1px solid #2a3a4b;border-radius:999px;padding:2px 10px;margin-right:8px;font-size:12px}
  .mono{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace}
</style>
</head>
<body>
  <div class="card">
    <header>
      <h1>Game Recap<?= text($titleScore) ?></h1>
      <div class="meta">
        <?php if ($gwg): ?><span class="pill">GWG: <?= text($gwg) ?></span><?php endif; ?>
        <?php if ($wG): ?><span class="pill">W: <?= text($wG) ?></span><?php endif; ?>
        <span class="pill mono"><?= text($base) ?></span>
      </div>
    </header>
    <div class="section">
      <h2>Summary</h2>
      <p>
        <?php if ($vs !== null && $hs !== null): ?>
          <?= text($awayName) ?> <?= $vs ?> @ <?= text($homeName) ?> <?= $hs ?>.
        <?php else: ?>
          Final score not available in JSON.
        <?php endif; ?>
        <?php if ($gwg): ?> The game-winner was <?= text($gwg) ?>.<?php endif; ?>
        <?php if ($wG): ?> Winning goalie: <?= text($wG) ?>.<?php endif; ?>
      </p>
    </div>
    <div class="section">
      <h2>Scoring Summary</h2>
      <?php if ($goals): ?>
        <ul>
          <?php foreach ($goals as $line): ?><li><?= text($line) ?></li><?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p>No play-by-play goal events found.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
<?php
  return ob_get_clean();
}

$files = ($onlyGid !== '') ? glob($BOX . '/' . $onlyGid . '.json') : glob($BOX . '/UHA-*.json');
sort($files);

echo "<!doctype html><meta charset='utf-8'><title>Generate Recaps</title>";
echo "<style>body{font:14px/1.35 system-ui,sans-serif;background:#0f1216;color:#e8eaed;padding:16px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #2a3340;padding:8px}th{background:#1a2330}tr:nth-child(even){background:#0f151c}</style>";
echo "<h1>Generate Recaps</h1>";
echo "<p>Source JSON: <code>{$BOX}</code><br>Source HTML: <code>{$HTML}</code><br>Output: <code>{$OUT}</code></p>";
echo $write ? "<p><b>WRITE MODE</b>: files were saved. Backups created if overwriting.</p>" : "<p><b>PREVIEW MODE</b>: add <code>?write=1</code> to save files.</p>";
echo "<p>One game: <code>?gid=UHA-123</code> (add <code>&write=1</code> to save)</p>";
echo "<table><tr><th>Game</th><th>Box JSON</th><th>HTML Fallback</th><th>Status</th><th>Recap File</th></tr>";

foreach ($files as $path) {
  $base = pathinfo($path, PATHINFO_FILENAME);
  [$ok, $status, $j] = rread($path);
  $htmlFallback = $HTML . '/' . $base . '.html';

  if (!$ok) {
    echo "<tr><td>{$base}</td><td>{$path}</td><td>{$htmlFallback}</td><td>error:{$status}</td><td>—</td></tr>";
    continue;
  }

  $outPath = $OUT . '/' . $base . '.html';
  $html = build_recap_html($base, $j, $htmlFallback);

  if ($write) {
    if (is_file($outPath)) @copy($outPath, $outPath . '.bak.' . date('Ymd_His'));
    file_put_contents($outPath, $html);
    $done = "written";
  } else {
    $done = "preview";
  }

  echo "<tr><td>{$base}</td><td>{$path}</td><td>{$htmlFallback}</td><td>{$done}</td><td>".text(basename($outPath))."</td></tr>";
}
echo "</table>";
