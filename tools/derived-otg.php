<?php
// tools/derived-otg.php
// Usage: php tools/derived-otg.php [boxscores_dir] [output_csv]
// Defaults: boxscores_dir = ../data/uploads , output = ../data/uploads/derived-otg.csv

error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$root   = dirname(__DIR__);
$boxDir = isset($argv[1]) ? $argv[1] : ($root . '/data/uploads');
$outCsv = isset($argv[2]) ? $argv[2] : ($root . '/data/uploads/derived-otg.csv');
$mapPath = $root . '/team-map.json'; // optional; supports several shapes

$abbrMap = [];
if (is_file($mapPath)) {
  $raw = @file_get_contents($mapPath);
  $js  = json_decode($raw, true);
  if (is_array($js)) {
    foreach ($js as $k => $v) {
      // Accept {"Boston Bruins":"BOS"} OR {"BOS":"Boston Bruins"} OR [{"name":"Boston Bruins","abbr":"BOS"},...]
      if (is_array($v) && isset($v['name'],$v['abbr'])) { $abbrMap[$v['name']] = strtoupper($v['abbr']); continue; }
      if (is_string($k) && is_string($v)) {
        if (strlen($k) === 3 && strlen($v) > 3) { $abbrMap[$v] = strtoupper($k); }
        elseif (strlen($v) === 3 && strlen($k) > 3) { $abbrMap[$k] = strtoupper($v); }
      }
    }
  }
}
function map_abbr($teamFull) {
  global $abbrMap;
  $t = trim((string)$teamFull);
  if ($t === '') return '';
  if (isset($abbrMap[$t])) return strtoupper($abbrMap[$t]);
  // leave blank; statistics.php falls back to name-only
  return '';
}
function name_key($s) { return strtolower(preg_replace('/[^a-z]+/i','',(string)$s)); }

$totals  = []; // player -> count
$perTeam = []; // nameKey|ABBR -> count

$flags = FilesystemIterator::SKIP_DOTS;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($boxDir, $flags));

foreach ($it as $f) {
  if (!$f->isFile()) continue;
  $ext = strtolower($f->getExtension());
  if (!in_array($ext, ['html','htm'])) continue;

  $html = @file_get_contents($f->getPathname());
  if ($html === false || $html === '') continue;

  $seenThisFile = [];

  // --- Strategy A: Scoring Summary Overtime block (preferred)
  if (preg_match('/<h3[^>]*STHSGame_Overtime[^>]*>.*?<div[^>]*STHSGame_GoalPeriod4[^>]*>(.*?)<\/div>/is', $html, $m)) {
    $block = $m[1];
    if (preg_match_all('/\d+\.\s*([^,<>]+)\s*,\s*([^\d<]+?)\s+\d+.*?at\s*[0-9:]+/is', $block, $mm, PREG_SET_ORDER)) {
      foreach ($mm as $g) {
        $teamFull = trim(html_entity_decode(strip_tags($g[1])));
        $player   = trim(html_entity_decode(strip_tags($g[2])));
        if ($player === '') continue;

        $nk = name_key($player);
        if (isset($seenThisFile[$nk])) continue;   // avoid duplicate from PBP later
        $seenThisFile[$nk] = true;

        $totals[$player] = ($totals[$player] ?? 0) + 1;

        $abbr = map_abbr($teamFull);
        if ($abbr !== '') {
          $perTeam[$nk.'|'.$abbr] = ($perTeam[$nk.'|'.$abbr] ?? 0) + 1;
        }
      }
    }
  }

  // --- Strategy B: Overtime Play-by-Play "Goal by ..."
  if (empty($seenThisFile) && preg_match('/<h4[^>]*FullPlayByPlayOvertime[^>]*>.*?<\/h4>(.*)$/is', $html, $m2)) {
    $otpbp = $m2[1];
    if (preg_match_all('/Goal by\s+([^\-<]+?)\s+-/i', $otpbp, $mm2)) {
      foreach ($mm2[1] as $p) {
        $player = trim(html_entity_decode(strip_tags($p)));
        if ($player === '') continue;

        $nk = name_key($player);
        if (isset($seenThisFile[$nk])) continue;
        $seenThisFile[$nk] = true;

        $totals[$player] = ($totals[$player] ?? 0) + 1;
      }
    }
  }
}

// --- Write CSV (Name,Team,OTG) ---
@mkdir(dirname($outCsv), 0777, true);
$fh = fopen($outCsv, 'w');
fputcsv($fh, ['Name','Team','OTG']);
foreach ($totals as $player => $count) {
  $nk = name_key($player);
  $abbr = '';
  // if we learned a team abbr for this name, use one
  foreach ($perTeam as $k => $v) {
    if (strpos($k, $nk.'|') === 0) { $abbr = strtoupper(substr($k, strlen($nk)+1)); break; }
  }
  fputcsv($fh, [$player, $abbr, (int)$count]);
}
fclose($fh);

echo "Wrote ".count($totals)." rows to $outCsv\n";
