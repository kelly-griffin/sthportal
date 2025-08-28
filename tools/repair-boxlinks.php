<?php
// tools/repair-boxlinks.php
// Auto-fix schedule `link` fields by scanning UHA-*.html and matching by team ABBR pairs.
// Safe: writes a timestamped backup of schedule-current.json before modifying.
// Usage: php tools/repair-boxlinks.php
// Output: summary + a CSV at data/uploads/repair-boxlinks-report.csv

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root         = dirname(__DIR__);
$schedPath    = $root . '/data/uploads/schedule-current.json';
$teamsPath    = $root . '/data/uploads/teams.json';
$uploadsDir   = $root . '/data/uploads';
$reportCsv    = $root . '/data/uploads/repair-boxlinks-report.csv';

function jload(string $p){ return file_exists($p) ? json_decode((string) file_get_contents($p), true) : null; }
function jsave(string $p, $data){ file_put_contents($p, json_encode($data, JSON_PRETTY_JSON|JSON_UNESCAPED_SLASHES)); }
function clean(string $s): string {
  $s = strip_tags($s);
  $s = html_entity_decode($s, ENT_QUOTES|ENT_HTML5, 'UTF-8');
  return trim(preg_replace('/\s+/', ' ', $s));
}
function abbrOfName(string $name, array $teams): string {
  $n = mb_strtolower(trim($name));
  foreach (($teams['teams'] ?? []) as $t) {
    if (mb_strtolower(trim((string)($t['name'] ?? ''))) === $n) return (string)($t['abbr'] ?? '');
  }
  return '';
}
function abbrOfId(string $id, array $teams): string {
  foreach (($teams['teams'] ?? []) as $t) {
    if ((string)($t['id'] ?? '') === (string)$id) return (string)($t['abbr'] ?? '');
  }
  return '';
}
function parseHeader(string $html): array {
  $out = ['visitor'=>['name'=>'','abbr'=>''], 'home'=>['name'=>'','abbr'=>'']];
  if (preg_match_all('/<td[^>]*class="STHSGame_GoalsTeamName"[^>]*>(.*?)<\/td>.*?<td[^>]*class="STHSGame_GoalsTotal"[^>]*>(\d+)<\/td>/si', $html, $m, PREG_SET_ORDER)) {
    $out['visitor']['name'] = clean($m[0][1] ?? '');
    $out['home']['name']    = clean($m[1][1] ?? '');
  }
  return $out;
}

$schedule = jload($schedPath);
$teams = jload($teamsPath) ?? ['teams'=>[]];
if (!$schedule) { echo "Missing schedule.\n"; exit(1); }

// Build index of boxscore HTML by ABBR pair "V@H"
$index = []; $dupes = [];
foreach (glob($uploadsDir . '/UHA-*.html') as $path) {
  $base = basename($path);
  $html = (string) file_get_contents($path);
  $hdr  = parseHeader($html);
  $v = $hdr['visitor']['name'] ?? '';
  $h = $hdr['home']['name'] ?? '';
  if ($v === '' || $h === '') continue;
  $va = abbrOfName($v, $teams);
  $ha = abbrOfName($h, $teams);
  if ($va === '' || $ha === '') continue;
  $key = "{$va}@{$ha}";
  if (!isset($index[$key])) $index[$key] = [];
  $index[$key][] = $base;
  if (count($index[$key]) > 1) $dupes[$key] = $index[$key];
}

$changed = 0; $mismatch = 0; $ambiguous = 0; $missing = 0;
$rows = [];
foreach ($schedule['games'] as &$g) {
  $vabbr = ($g['visitorTeamId'] ?? '') !== '' ? abbrOfId((string)$g['visitorTeamId'], $teams) : abbrOfName((string)($g['visitorTeam'] ?? ''), $teams);
  $habbr = ($g['homeTeamId'] ?? '') !== ''    ? abbrOfId((string)$g['homeTeamId'], $teams)    : abbrOfName((string)($g['homeTeam'] ?? ''), $teams);
  $key = "{$vabbr}@{$habbr}";
  $want = $index[$key] ?? [];
  $link = basename((string)($g['link'] ?? ''));

  $status = 'ok';
  $suggest = '';
  if (!$want) {
    $status = 'missing_box_for_matchup'; $missing++;
  } elseif (count($want) > 1) {
    $status = 'ambiguous'; $suggest = implode(';', $want); $ambiguous++;
  } else {
    $suggest = $want[0];
    if ($link !== $suggest) {
      $status = 'fixed';
      $g['link'] = $suggest;
      $changed++;
    }
  }

  // Also log obvious mismatch
  if (($status === 'ok' || $status === 'fixed') && $link !== '' && $suggest !== '' && $link !== $suggest) {
    $mismatch++;
  }

  $rows[] = [
    'date'     => (string)($g['date'] ?? ''),
    'sched'    => $key,
    'link_old' => $link,
    'link_new' => $g['link'] ?? '',
    'status'   => $status,
    'candidates' => $suggest
  ];
}
unset($g);

// Backup + save schedule
$backup = $schedPath . '.bak.' . date('Ymd_His');
copy($schedPath, $backup);
file_put_contents($schedPath, json_encode($schedule, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

// Write CSV report
$fp = fopen($reportCsv, 'w');
fputcsv($fp, array_keys($rows[0] ?? ['date','sched','link_old','link_new','status','candidates']));
foreach ($rows as $r) fputcsv($fp, $r);
fclose($fp);

echo "Repaired links.\n";
echo "Changed: {$changed}, Missing matchup: {$missing}, Ambiguous: {$ambiguous}, Mismatch leftovers: {$mismatch}\n";
echo "Backup: {$backup}\n";
echo "Report: {$reportCsv}\n";
