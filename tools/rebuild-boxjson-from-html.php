<?php
// tools/rebuild-boxjson-from-html.php
// For every UHA-*.html, derive visitor/home teams from the page and extract the Winning Goalie.
// Overwrites boxscores/UHA-*.json so JSON always matches the HTML.
// Run: php tools/rebuild-boxjson-from-html.php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root   = dirname(__DIR__);
$teamsP = $root . '/assets/json/teams.json';
$htmlDir= $root . '/data/uploads';
$jsonDir= $root . '/assets/json';
$report = $root . '/data/rebuild-boxjson-report.csv';

function jdump($p,$d){ file_put_contents($p, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); }
function h2t(string $html): string {
  $html = preg_replace('~<script\b[^>]*>.*?</script>~is',' ', $html);
  $html = preg_replace('~<style\b[^>]*>.*?</style>~is',' ', $html);
  $t = strip_tags($html);
  $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $t = preg_replace('/[ \t\x0B\p{Zs}]+/u', ' ', $t);
  $t = preg_replace('/\R+/u', ' ', $t);
  return trim($t);
}
function fold($s){
  $s = trim((string)$s);
  if (class_exists('Transliterator')) { $tr = Transliterator::create('Any-Latin; Latin-ASCII'); if ($tr) $s = $tr->transliterate($s); }
  else { $c=@iconv('UTF-8','ASCII//TRANSLIT',$s); if ($c!==false) $s=$c; }
  $s = mb_strtolower($s);
  return preg_replace('/[^a-z0-9]+/',' ', $s);
}

$teams = json_decode((string)@file_get_contents($teamsP), true);
if (!$teams) { echo "Missing teams.json"; exit; }
$byName = []; $byShort = []; $byAbbr = [];
foreach ($teams['teams'] as $t){
  $byName[fold($t['name'])] = $t;
  $byShort[fold($t['shortName'] ?? $t['name'])] = $t;
  $byAbbr[$t['abbr']] = $t;
}

function pick_pair_from_html(string $raw, array $byName, array $byShort, array $byAbbr): array {
  $text = h2t($raw);
  // First try the common title: "... TeamA vs TeamB"
  if (preg_match('~vs\s+([A-Za-z .\'\-]+)~u', $text, $m2) &&
      preg_match('~-\s*([A-Za-z .\'\-]+)\s+vs\s+~u', $text, $m1)) {
    $A = fold($m1[1]); $B = fold($m2[1]);
    $ta = $byName[$A] ?? $byShort[$A] ?? null;
    $tb = $byName[$B] ?? $byShort[$B] ?? null;
    if ($ta && $tb) return [$ta,$tb];
  }
  // Fallback: count abbr hits
  $counts=[];
  $R = $raw;
  foreach ($byAbbr as $abbr=>$t) {
    $counts[$abbr] = substr_count($R, $abbr);
  }
  arsort($counts);
  $top = array_keys($counts);
  if (count($top)>=2 && $counts[$top[0]]>0 && $counts[$top[1]]>0) {
    return [$byAbbr[$top[0]], $byAbbr[$top[1]]];
  }
  return [null,null];
}

function extract_winner_goalie(string $text): string {
  // Look in Goalie Stats for ", W," or " W, " patterns
  // e.g. "Sergei Bobrovsky (FLA), 29 saves ... W, 1-0-0"
  if (preg_match('~([A-Za-z.\'\- ]{2,60})\s*\([A-Z]{2,3}\)[^\\n,]*,\s*[^\\n]*\bW\b~u', $text, $m)) {
    return trim($m[1]);
  }
  if (preg_match('~\bWinning\s*Goalie\s*[:\-]\s*([A-Za-z.\'\- ]{2,60})~u', $text, $m)) {
    return trim($m[1]);
  }
  if (preg_match('~\bW:\s*([A-Za-z.\'\- ]{2,60})\b~u', $text, $m)) {
    return trim($m[1]);
  }
  return '';
}

$rows=[]; $rebuilt=0; $skipped=0;
foreach (glob($htmlDir.'/UHA-*.html') as $htmlPath){
  if (stripos($htmlPath,'Farm') !== false) continue;
  $base = basename($htmlPath, '.html');
  $raw  = @file_get_contents($htmlPath); if ($raw===false) { $rows[] = [$base,'HTML_READ_FAIL','','']; continue; }
  [$ta,$tb] = pick_pair_from_html($raw, $byName, $byShort, $byAbbr);
  if (!$ta || !$tb) { $rows[] = [$base,'PAIR_FAIL','','']; continue; }

  $text = h2t($raw);
  $winner = extract_winner_goalie($text);

  $json = [
    'gwg' => '',               // we’ll fill this later if needed
    'winningGoalie' => $winner,
    'firstStar' => '',
    'secondStar' => '',
    'thirdStar' => '',
    'gameNumber' => (int)preg_replace('~\D~','', $base),
    'visitor' => ['name'=>$ta['name'], 'score'=>0, 'abbr'=>$ta['abbr']],
    'home'    => ['name'=>$tb['name'], 'score'=>0, 'abbr'=>$tb['abbr']],
    'date'    => '',
  ];
  $outPath = $jsonDir . '/'.$base.'.json';
  @mkdir(dirname($outPath), 0777, true);
  jdump($outPath, $json);
  $rebuilt++; $rows[] = [$base,'REBILT',$json['visitor']['abbr'].'@'.$json['home']['abbr'],$winner];
}

$fp = fopen($report,'w');
fputcsv($fp,['file','status','pair','winningGoalie']);
foreach ($rows as $r) fputcsv($fp,$r);
fclose($fp);

echo "Rebuilt: $rebuilt, Skipped: $skipped\n";
echo "Report: $report\n";
