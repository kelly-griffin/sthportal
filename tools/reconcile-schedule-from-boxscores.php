<?php
// tools/reconcile-schedule-from-boxscores.php
// PURPOSE: Make schedule-current.json consistent with linked boxscore HTMLs (UHA-#.html / UHA-#.json).
// For any PLAYED game that has a `link`, this will OVERWRITE the schedule's visitor/home team names/IDs
// and scores to match the boxscore file referenced by that link. Also fills GWG and Winning Goalie.
// Safety: creates a timestamped backup and a CSV report of all changes.
//
// Usage:
//   php tools/reconcile-schedule-from-boxscores.php
// Options:
//   --dry-run       : don't write schedule, only print and CSV
//   --all           : apply to all games with links (default is played-only)
//   --prefer-json   : read teams/scores from UHA-#.json if present (default: parse HTML header for teams/scores)
//
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root        = dirname(__DIR__);
$schedPath   = $root . '/data/uploads/schedule-current.json';
$teamsPath   = $root . '/data/uploads/teams.json';
$uploadDir   = $root . '/data/uploads';
$boxJsonDir  = $uploadDir . '/boxscores';
$reportCsv   = $root . '/data/uploads/reconcile-report.csv';

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$applyAll = in_array('--all', $args, true);
$preferJson = in_array('--prefer-json', $args, true);

function jload(string $p){ return file_exists($p) ? json_decode((string) file_get_contents($p), true) : null; }
function jsave(string $p, $data){ file_put_contents($p, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); }
function norm($s){ return strtolower(trim((string)$s)); }
function clean(string $s): string {
  $s = strip_tags($s);
  $s = html_entity_decode($s, ENT_QUOTES|ENT_HTML5, 'UTF-8');
  return trim(preg_replace('/\s+/', ' ', $s));
}

function parseHeaderScores(string $html): array {
  $out = ['home'=>['name'=>'','score'=>0], 'visitor'=>['name'=>'','score'=>0]];
  if (preg_match_all('/<td[^>]*class="STHSGame_GoalsTeamName"[^>]*>(.*?)<\/td>.*?<td[^>]*class="STHSGame_GoalsTotal"[^>]*>(\d+)<\/td>/si', $html, $m, PREG_SET_ORDER)) {
    if (isset($m[0])) { $out['visitor']['name'] = clean($m[0][1]); $out['visitor']['score'] = intval($m[0][2]); }
    if (isset($m[1])) { $out['home']['name']    = clean($m[1][1]); $out['home']['score']    = intval($m[1][2]); }
  }
  return $out;
}

function abbrFromName(string $name, array $teams): string {
  $ln = norm($name);
  foreach (($teams['teams'] ?? []) as $t) {
    if (norm($t['name'] ?? '') === $ln) return (string)($t['abbr'] ?? '');
  }
  return '';
}
function idFromAbbr(string $abbr, array $teams): string {
  foreach (($teams['teams'] ?? []) as $t) {
    if ((string)($t['abbr'] ?? '') === (string)$abbr) return (string)($t['id'] ?? '');
  }
  return '';
}

$schedule = jload($schedPath);
$teams = jload($teamsPath) ?? ['teams'=>[]];
if (!$schedule) { echo "Missing schedule.\n"; exit(1); }

$rows = [];
$changes = 0; $skipped = 0; $missing = 0;
foreach ($schedule['games'] as &$g) {
  $link = basename((string)($g['link'] ?? ''));
  if ($link === '') { $skipped++; continue; }

  $isPlayed = false;
  if (array_key_exists('Play', $g)) {
    $v = $g['Play']; $isPlayed = is_bool($v) ? $v : in_array(strtoupper(trim((string)$v)), ['TRUE','T','YES','Y','1'], true);
  } elseif (isset($g['visitorScore'], $g['homeScore'])) {
    $isPlayed = ((int)$g['visitorScore'] > 0) || ((int)$g['homeScore'] > 0);
  }
  if (!$applyAll && !$isPlayed) { $skipped++; continue; }

  $htmlPath = $uploadDir . '/' . $link;
  if (!is_file($htmlPath)) { $missing++; continue; }

  $boxBase = basename($link, '.html');
  $boxJson = $boxJsonDir . '/' . $boxBase . '.json';
  $box = file_exists($boxJson) ? json_decode((string) file_get_contents($boxJson), true) : null;
  $hdr = [];
  if ($preferJson && $box && isset($box['visitor']['name'], $box['home']['name'])) {
    $hdr = ['visitor'=>['name'=>$box['visitor']['name'], 'score'=>intval($box['visitor']['score'] ?? 0)],
            'home'   =>['name'=>$box['home']['name'],    'score'=>intval($box['home']['score'] ?? 0)]];
  } else {
    $html = (string) file_get_contents($htmlPath);
    $hdr = parseHeaderScores($html);
  }

  $vName = $hdr['visitor']['name'] ?? '';
  $hName = $hdr['home']['name'] ?? '';
  $vAbbr = abbrFromName($vName, $teams);
  $hAbbr = abbrFromName($hName, $teams);
  $vId   = idFromAbbr($vAbbr, $teams);
  $hId   = idFromAbbr($hAbbr, $teams);

  $old = [
    'visitorTeam' => (string)($g['visitorTeam'] ?? ''),
    'homeTeam'    => (string)($g['homeTeam'] ?? ''),
    'visitorTeamId' => (string)($g['visitorTeamId'] ?? ''),
    'homeTeamId'    => (string)($g['homeTeamId'] ?? ''),
    'visitorScore'  => (string)($g['visitorScore'] ?? ''),
    'homeScore'     => (string)($g['homeScore'] ?? ''),
  ];

  $new = $old; // start with old, then replace
  if ($vName !== '') $new['visitorTeam'] = $vName;
  if ($hName !== '') $new['homeTeam']    = $hName;
  if ($vId   !== '') $new['visitorTeamId']= $vId;
  if ($hId   !== '') $new['homeTeamId']   = $hId;
  if (isset($hdr['visitor']['score'])) $new['visitorScore'] = (string) $hdr['visitor']['score'];
  if (isset($hdr['home']['score']))    $new['homeScore']    = (string) $hdr['home']['score'];

  // GWG / Winning Goalie
  if ($box) {
    if (!empty($box['gwg'])) $g['gwg'] = (string)$box['gwg'];
    if (!empty($box['winningGoalie'])) $g['winningGoalie'] = (string)$box['winningGoalie'];
  }

  $changedThis = ($new !== $old);
  if ($changedThis) {
    $g['visitorTeam']   = $new['visitorTeam'];
    $g['homeTeam']      = $new['homeTeam'];
    $g['visitorTeamId'] = $new['visitorTeamId'];
    $g['homeTeamId']    = $new['homeTeamId'];
    $g['visitorScore']  = $new['visitorScore'];
    $g['homeScore']     = $new['homeScore'];
    $changes++;
  }

  $rows[] = [
    'date' => (string)($g['date'] ?? ''),
    'link' => $link,
    'old'  => "{$old['visitorTeam']}({$old['visitorTeamId']})@{$old['homeTeam']}({$old['homeTeamId']}) {$old['visitorScore']}-{$old['homeScore']}",
    'new'  => "{$new['visitorTeam']}({$new['visitorTeamId']})@{$new['homeTeam']}({$new['homeTeamId']}) {$new['visitorScore']}-{$new['homeScore']}",
    'gwg'  => (string)($g['gwg'] ?? ''),
    'wgoalie' => (string)($g['winningGoalie'] ?? ''),
    'changed' => $changedThis ? 'YES' : 'no'
  ];
}
unset($g);

// Backup + write
$backup = $schedPath . '.bak.' . date('Ymd_His');
if (!$dryRun) {
  copy($schedPath, $backup);
  jsave($schedPath, $schedule);
}

// CSV
$fp = fopen($reportCsv, 'w');
fputcsv($fp, ['date','link','old','new','gwg','wgoalie','changed']);
foreach ($rows as $r) fputcsv($fp, $r);
fclose($fp);

echo "Reconcile complete.\n";
echo "Changes: {$changes}, Skipped: {$skipped}, Missing HTML: {$missing}\n";
echo "Backup: {$backup}\n";
echo "Report: {$reportCsv}\n";
echo $dryRun ? "(dry run)\n" : "";
