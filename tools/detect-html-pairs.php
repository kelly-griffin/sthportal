<?php
// tools/detect-html-pairs.php
// Scans UHA-*.html, guesses the two teams inside, and prints a table.
// Open in browser: /sthportal/tools/detect-html-pairs.php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root   = dirname(__DIR__);
$teamsP = $root . '/assets/json/teams.json';
$htmlDir= $root . '/data/uploads';

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function fold($s){
  $s = trim((string)$s);
  if (class_exists('Transliterator')) {
    $tr = Transliterator::create('Any-Latin; Latin-ASCII');
    if ($tr) $s = $tr->transliterate($s);
  } else {
    $c = @iconv('UTF-8','ASCII//TRANSLIT',$s);
    if ($c !== false) $s = $c;
  }
  $s = mb_strtolower($s);
  return preg_replace('/[^a-z0-9]+/',' ', $s);
}

$teams = json_decode((string)@file_get_contents($teamsP), true);
if (!$teams) { echo "Missing teams.json"; exit; }
$lex = []; // id=>['abbr','name','short','syn']
foreach ($teams['teams'] as $t){
  $id   = (string)$t['id'];
  $abbr = (string)($t['abbr'] ?? '');
  $name = (string)$t['name'];
  $short= (string)($t['shortName'] ?? $name);
  $syn  = array_unique(array_filter([$abbr, $name, $short]));
  $lex[$id] = ['abbr'=>$abbr,'name'=>$name,'short'=>$short,'syn'=>$syn];
}
$allSyn = [];
foreach ($lex as $id=>$row){
  foreach ($row['syn'] as $s) $allSyn[$id][] = fold($s);
}

$rows = [];
foreach (glob($htmlDir.'/UHA-*.html') as $f){
  $base = basename($f);
  $raw  = @file_get_contents($f);
  if ($raw === false){ $rows[] = [$base,'(unreadable)','']; continue; }
  $txt  = fold($raw);

  // score teams by how often their synonyms appear
  $score = [];
  foreach ($allSyn as $id=>$syns){
    $score[$id] = 0;
    foreach ($syns as $s){
      if ($s==='') continue;
      $cnt = substr_count($txt, ' '.$s.' ');
      if ($cnt===0) $cnt = substr_count($txt, $s.' ')+substr_count($txt, ' '.$s);
      $score[$id] += $cnt;
      // give a little weight to exact abbr (all caps in original)
      if ($lex[$id]['abbr']) $score[$id] += substr_count($raw, $lex[$id]['abbr']) * 2;
    }
  }
  arsort($score);
  $cand = array_slice(array_keys($score),0,2);
  $pair = '';
  if (count($cand)===2 && $score[$cand[0]]>0 && $score[$cand[1]]>0){
    $pair = $lex[$cand[0]]['abbr'].'@'.$lex[$cand[1]]['abbr'];
  }
  $rows[] = [$base, $pair, json_encode([$score[$cand[0]]??0,$score[$cand[1]]??0])];
}

echo "<h2>HTML → detected team pairs</h2>";
echo "<table border='1' cellpadding='6' cellspacing='0'><tr><th>File</th><th>Detected Pair</th><th>scores</th></tr>";
foreach ($rows as $r){
  echo "<tr><td>".h($r[0])."</td><td>".h($r[1])."</td><td>".h($r[2])."</td></tr>";
}
echo "</table>";
