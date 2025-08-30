<?php
require_once __DIR__ . '/includes/bootstrap.php';

/* ---------- load data ---------- */
$weekFile  = __DIR__ . '/data/uploads/schedule-full.json';
$weekFileFallback = __DIR__ . '/data/uploads/schedule-current.json';
$teamsFile = __DIR__ . '/data/uploads/teams.json';

$weekData  = is_file($weekFile) ? json_decode((string)file_get_contents($weekFile), true) : [];
if (empty($weekData) || empty($weekData['games'])) { $weekData = is_file($weekFileFallback) ? json_decode((string)file_get_contents($weekFileFallback), true) : []; }
$weekStart = $weekData['weekStart'] ?? '';
$weekEnd   = $weekData['weekEnd']   ?? '';

$games     = $weekData['games']     ?? [];

// Determine 7-day window
$paramStart = isset($_GET['start']) ? (string)$_GET['start'] : '';
if ($paramStart !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $paramStart)) {
  $windowStart = $paramStart;
} elseif ($weekStart !== '') {
  $windowStart = $weekStart;
} else {
  // fallback: earliest game date
  $dates = array_map(fn($g) => (string)($g['date'] ?? ''), $games);
  sort($dates);
  $windowStart = $dates[0] ?? date('Y-m-d');
}
$windowEnd = date('Y-m-d', strtotime($windowStart . ' +6 days'));


$teams = [];
if (is_file($teamsFile)) {
  $td = json_decode((string)file_get_contents($teamsFile), true);
  foreach (($td['teams'] ?? []) as $t) {
    $id = (string)$t['id'];
    $teams[$id] = [
      'name'      => (string)($t['name'] ?? ''),
      'shortName' => (string)($t['shortName'] ?? $t['name'] ?? ''),
      'abbr'      => (string)($t['abbr'] ?? ''),
    ];
  }
}

/* ---------- helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function resolveTeamId(array $g, string $side): string {
  $idKey = $side.'TeamId';
  if (!empty($g[$idKey])) return (string)$g[$idKey];
  return (string)($g[$side.'Team'] ?? '');
}
function teamShort($id, $teams){ return h($teams[$id]['shortName'] ?? "Team #$id"); }
function teamAbbr($id, $teams){  return (string)($teams[$id]['abbr'] ?? ''); }

/** robust truthiness for Play flag; do NOT treat 0–0 as played */
function isGamePlayed(array $g): bool {
  if (array_key_exists('Play', $g)) {
    $v = $g['Play'];
    if (is_bool($v)) return $v;
    $s = strtoupper(trim((string)$v));
    if (in_array($s, ['TRUE','T','YES','Y','1'], true)) return true;
    if (in_array($s, ['FALSE','F','NO','N','0',''], true)) return false;
  }
  if (isset($g['visitorScore'], $g['homeScore'])) {
    return ((int)$g['visitorScore'] > 0) || ((int)$g['homeScore'] > 0);
  }
  return false;
}

/** small team logo tag with light→dark fallback, then hide */
if (!function_exists('logoTag')) {
function logoTag($abbr){
  $abbr = htmlspecialchars((string)$abbr, ENT_QUOTES, 'UTF-8');
  $light = "assets/img/logos/{$abbr}_light.svg";
  return '<img class="team-logo" src="'.$light.'" alt="" '.
         'onerror="if(!this.dataset.f){this.dataset.f=1;this.src=this.src.replace(\'_light\',\'_dark\');}else{this.style.display=\'none\';}">';
}
}

/* ---------- group games ---------- */
$selectedTeam = isset($_GET['team']) ? (string)$_GET['team'] : '';
$qTeam = ($selectedTeam !== '') ? '&team='.rawurlencode($selectedTeam) : '';
$byDate = [];$allByDate = [];
foreach ($games as $g) {
  $vid = resolveTeamId($g,'visitor');
  $hid = resolveTeamId($g,'home');
  if ($selectedTeam !== '' && $selectedTeam !== $vid && $selectedTeam !== $hid) continue;
  $allByDate[$g['date']][] = $g; if ($g['date'] >= $windowStart && $g['date'] <= $windowEnd) { $byDate[$g['date']][] = $g; }
}
ksort($byDate); ksort($allByDate);

$firstUnplayedDate = '';
foreach ($allByDate as $d => $list) {
  foreach ($list as $gchk) { if (!isGamePlayed($gchk)) { $firstUnplayedDate = $d; break 2; } }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>League Schedule & Results</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <style>
  /* day-ribbon inline guard */
  .day-ribbon{display:flex;gap:12px;overflow-x:auto;padding:8px 0 14px;margin:0 0 8px}
  .day-card{min-width:120px;height:96px;border:1px solid #cfd6e4;border-radius:12px;background:#fff;color:#0b1220;text-decoration:none;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:0 1px 0 #e6ecf6}
  .day-card:hover,.day-card:focus{border-color:#8fb3ff;box-shadow:0 0 0 3px rgba(60,120,255,.15);outline:none}
  .day-card .dc-top{font-weight:900;font-size:16px;letter-spacing:.2px}
  .day-card .dc-dow{font-size:12px;color:#667085;margin-top:2px}
  .day-card .dc-count{font-size:12px;color:#3a475a;margin-top:10px}
  .day-card.active{outline:2px solid #2f6fed; outline-offset:-2px}

  /* inline guard for day cards */
  .day-ribbon{display:flex;gap:12px;overflow-x:auto;padding:8px 0 14px;margin:0 0 8px}
  .day-card{min-width:120px;height:96px;border:1px solid #cfd6e4;border-radius:12px;background:#fff;color:#0b1220;text-decoration:none;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:0 1px 0 #e6ecf6}
  .day-card:hover,.day-card:focus{border-color:#8fb3ff;box-shadow:0 0 0 3px rgba(60,120,255,.15);outline:none}
  .day-card .dc-top{font-weight:900;font-size:16px;letter-spacing:.2px}
  .day-card .dc-dow{font-size:12px;color:#667085;margin-top:2px}
  .day-card .dc-count{font-size:12px;color:#3a475a;margin-top:10px}
  .day-card.active{outline:2px solid #2f6fed; outline-offset:-2px}

    .badge{display:inline-block;margin-left:.4rem;padding:.12rem .5rem;font-size:.78rem;border-radius:.6rem;color:#fff;background:#7f8c8d;vertical-align:middle}
    .badge-warn{background:#c0392b}
    .subnote{display:block;font-size:1rem;color:#333;line-height:1.25;margin-top:2px;white-space:normal;word-break:break-word}
    .loser{color:#777}
    .at{color:#999}
    .actions .btn-link{margin-right:.4rem}
    .actions{text-align:right;white-space:nowrap}
    .schedule-table th:nth-child(3),.schedule-table td:nth-child(3){text-align:left;vertical-align:middle}
    /* no inline .team-logo sizing here; controlled in schedule-polish.css */
  
  /* === Stable two-row week controls === */
  .week-controls{margin:6px 0 8px}
  .week-controls .week-links a{color:#2f6fed;text-decoration:none;font-weight:600;margin-right:12px}
  .week-controls .week-links a:hover{text-decoration:underline}
  .week-controls .week-range{margin-top:6px}
  .week-controls .week-picker summary{
    list-style:none;cursor:pointer;border:1px solid #cfd6e4;border-radius:8px;padding:.35rem .6rem;background:#fff;
    display:inline-flex;align-items:center;white-space:nowrap
  }
  .week-controls .week-pop{position:absolute;z-index:20;background:#fff;border:1px solid #cfd6e4;border-radius:10px;
    box-shadow:0 6px 24px rgba(0,0,0,.15);padding:12px;margin-top:6px;min-width:240px}
  .week-controls .week-pop .row{display:flex;gap:10px;align-items:center;margin-top:8px}
</style>
  <script src="assets/js/schedule.js" defer></script>
</head>
<body>
  <div class="site">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="schedule-container">
        <div class="schedule-card">
          <h1>League Schedule & Results</h1>

          
                    
          <div <div class="week-controls">
            <div class="week-links">
              <a href="?start=<?= h(date('Y-m-d', strtotime($windowStart.' -7 days'))) ?><?= $qTeam ?>">⟨ Prev</a>
              <a href="?start=<?= h($firstUnplayedDate ?: date('Y-m-d')) ?><?= $qTeam ?>">Today</a>
              <a href="?start=<?= h(date('Y-m-d', strtotime($windowStart.' +7 days'))) ?><?= $qTeam ?>">Next ⟩</a>
            </div>
            <div class="week-range">
              <details class="week-picker">
                <summary id="week-label"><?= date('M j', strtotime($windowStart)) ?> – <?= date('M j', strtotime($windowEnd)) ?></summary>
                <div class="week-pop">
                  <form method="get">
                    <input type="hidden" name="team" value="<?= h($selectedTeam) ?>">
                    <label for="start">Pick a date:</label>
                    <input type="date" id="start" name="start" value="<?= h($windowStart) ?>">
                    <div class="row">
                      <button type="submit">Go</button>
                      <a class="btn-link" href="?start=<?= h(date('Y-m-d')) ?><?= $qTeam ?>">Today</a>
                    </div>
                  </form>
                </div>
              </details>
            </div>
          </div>

<?php
          // Build exact 7-day window starting at $windowStart
          $days = [];
          for ($i=0; $i<7; $i++) {
            $d = date('Y-m-d', strtotime($windowStart . " +$i days"));
            $days[] = $d;
          }
        ?>
          <div class="day-ribbon" role="tablist" aria-label="Days this week">
            <?php foreach ($days as $d): 
              $ts = strtotime($d);
              $md = date('n/j', $ts);
              $dow = strtoupper(date('D', $ts));
              $cnt = isset($allByDate[$d]) ? count($allByDate[$d]) : 0;
              $active = ($d === $windowStart) ? ' active' : '';
            ?>
              <a class="day-card<?= $active ?>" href="?start=<?= h($d) ?><?= $qTeam ?>" role="tab" aria-controls="day-<?= h($d) ?>">
                <div class="dc-top"><?= h($md) ?></div>
                <div class="dc-dow"><?= h($dow) ?></div>
                <div class="dc-count"><?= $cnt ?> <?= $cnt === 1 ? 'Game' : 'Games' ?></div>
              </a>
            <?php endforeach; ?>
          </div>
<form method="get" style="margin-bottom:12px">
            <label for="team">Team:</label>
            <select name="team" id="team" onchange="this.form.submit()">
              <option value="">All Teams</option>
              <?php foreach ($teams as $tid => $t): ?>
                <option value="<?= h($tid) ?>" <?= ($selectedTeam === (string)$tid ? 'selected' : '') ?>><?= h($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>

<?php
if (empty($byDate)) {
  echo "<p>No games scheduled this week.</p>";
} else {
  foreach ($byDate as $date => $list) {

    // Decide column 3 header dynamically for this date
    $allPre = true; $allPlayed = true;
    foreach ($list as $gchk) {
      if (isGamePlayed($gchk)) { $allPre = false; } else { $allPlayed = false; }
    }
    $col3Label = ($allPlayed ? 'Game-Winning Goal / Goalie' : ($allPre ? 'Broadcasters' : 'GWG / Goalie • Broadcasters'));

    echo '<h3 id="day-' . h($date) . '" class="schedule-date">' . date('l, F j', strtotime($date)) . '</h3>';
    echo '<table class="schedule-table">';
    echo '<colgroup><col><col><col><col></colgroup>';
    echo '<tr><th>Matchup</th><th>Time / Result</th><th>' . $col3Label . '</th><th></th></tr>';

    foreach ($list as $g) {
      $vid = resolveTeamId($g,'visitor');
      $hid = resolveTeamId($g,'home');
      $vShort = teamShort($vid, $teams);
      $hShort = teamShort($hid, $teams);
      $vAbbr  = teamAbbr($vid,  $teams);
      $hAbbr  = teamAbbr($hid,  $teams);

      $vLogo = $vAbbr !== '' ? logoTag($vAbbr) : '';
      $hLogo = $hAbbr !== '' ? logoTag($hAbbr) : '';

      echo "<tr>";
      echo "<td class='matchup-cell'><b>{$vShort}</b>{$vLogo}<span class='at'>@</span>{$hLogo}<b>{$hShort}</b></td>";

      // Column 2: Time / Result
      $badge = '';
      $broadcastersCell = '';

      if (isGamePlayed($g)) {
        // Pull scores from linked box when available
        $box = (function(array $g){
          $link = (string)($g['link'] ?? '');
          if ($link === '') return ['ok'=>false,'status'=>'no_link','gwg'=>'','winner'=>'','vScore'=>null,'hScore'=>null];
          $base = pathinfo($link, PATHINFO_FILENAME);
          $jsonPath = __DIR__ . "/data/uploads/boxscores/{$base}.json";
          if (!is_file($jsonPath)) return ['ok'=>false,'status'=>'missing','gwg'=>'','winner'=>'','vScore'=>null,'hScore'=>null];
          $raw = @file_get_contents($jsonPath);
          if ($raw === false) return ['ok'=>false,'status'=>'decode','gwg'=>'','winner'=>'','vScore'=>null,'hScore'=>null];
          if (strncmp($raw, "\x1F\x8B", 2) === 0 && function_exists('gzdecode')) { $tmp = @gzdecode($raw); if ($tmp !== false && $tmp !== null) $raw = $tmp; }
          if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) $raw = substr($raw,3);
          if (strpos(substr($raw,0,64), "\x00") !== false) { $raw = @mb_convert_encoding($raw,'UTF-8','UTF-16,UTF-16LE,UTF-16BE'); }
          $j = json_decode((string)$raw, true);
          if (!is_array($j)) return ['ok'=>false,'status'=>'decode','gwg'=>'','winner'=>'','vScore'=>null,'hScore'=>null];
          $vScore = null; $hScore = null;
          if (isset($j['visitor']['score'])) $vScore = (int)$j['visitor']['score'];
          if (isset($j['home']['score']))    $hScore = (int)$j['home']['score'];
          if ($vScore === null && isset($j['visitorScore'])) $vScore = (int)$j['visitorScore'];
          if ($hScore === null && isset($j['homeScore']))    $hScore = (int)$j['homeScore'];
          if (($vScore === 0 && $hScore === 0) || ($vScore === null && $hScore === null)) { $vScore = null; $hScore = null; }
          return [
            'ok'=>true,'status'=>'ok',
            'gwg'=>trim((string)($j['gwg'] ?? '')),
            'winner'=>trim((string)($j['winningGoalie'] ?? '')),
            'vScore'=>$vScore,'hScore'=>$hScore,
          ];
        })($g);

        $vs_sched = (int)($g['visitorScore'] ?? 0);
        $hs_sched = (int)($g['homeScore']   ?? 0);

        $vs = $box['ok'] && $box['vScore'] !== null ? (int)$box['vScore'] : $vs_sched;
        $hs = $box['ok'] && $box['hScore'] !== null ? (int)$box['hScore'] : $hs_sched;

        if ($vs > $hs) {
          echo "<td><b>".h($vAbbr)." {$vs}</b>,&nbsp;<span class='loser'>".h($hAbbr)." {$hs}</span></td>";
        } else {
          echo "<td><b>".h($hAbbr)." {$hs}</b>,&nbsp;<span class='loser'>".h($vAbbr)." {$vs}</span></td>";
        }

        if ($box['ok']) {
          if ($box['vScore'] !== null && $box['hScore'] !== null &&
              ($box['vScore'] != $vs_sched || $box['hScore'] != $hs_sched)) {
            $badge = "<span class='badge badge-warn' title='Schedule score differs from box JSON'>scr mismatch</span>";
          }
          $parts = [];
          if ($box['gwg']   !== '') $parts[] = 'GWG: ' . h($box['gwg']);
          if ($box['winner']!== '') $parts[] = 'W: '   . h($box['winner']);
          $broadcastersCell = $parts ? '<div class="subnote">'.implode(' • ', $parts).'</div>' : '';
        } else {
          if ($box['status'] === 'missing')
            $badge = "<span class='badge badge-warn' title='Boxscore JSON not found'>no box json</span>";
          elseif ($box['status'] === 'decode')
            $badge = "<span class='badge badge-warn' title='Boxscore JSON unreadable'>bad json</span>";
          elseif ($box['status'] === 'no_link')
            $badge = "<span class='badge badge-warn' title='No boxscore link'>no link</span>";
        }
      } else {
        $time = '';
        if (isset($g['time'])) $time = is_array($g['time']) ? ($g['time'][0] ?? '') : (string)$g['time'];
        echo "<td>".h($time)."</td>";
        if (!empty($g['broadcasters'])) {
          $arr = is_array($g['broadcasters']) ? $g['broadcasters'] : array_map('trim', explode(',', (string)$g['broadcasters']));
          $broadcastersCell = h(implode(', ', $arr));
        }
      }

      echo "<td>{$broadcastersCell}</td>";

      // Column 4: Actions -> use viewers
      echo "<td class='actions'>";
      $hrefRaw = (string)($g['link'] ?? '');
      $gid = $hrefRaw ? pathinfo($hrefRaw, PATHINFO_FILENAME) : '';

      $recapRel = $gid ? ("data/recaps/{$gid}.html") : '';
      $recapAbs = $recapRel ? (__DIR__ . '/' . $recapRel) : '';
      if ($recapAbs && is_file($recapAbs)) {
        echo "<a class='btn-link' href='recap.php?gid=".h($gid)."' target='_blank' rel='noopener'>Recap</a> ";
      }

      if ($gid !== '') {
        echo "<a class='btn-link' href='box.php?gid=".h($gid)."'>Boxscore</a>";
      } else {
        echo "<a class='btn-link' href='#'>Boxscore</a>";
      }

      if (!isGamePlayed($g)) {
        echo " <a class='btn-link secondary' href='#'>Tickets</a>";
      }
      if ($badge) echo " ".$badge;
      echo "</td>";

      echo "</tr>";
    }
    echo "</table>";
  }
}
?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>