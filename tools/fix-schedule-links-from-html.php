<?php
// tools/fix-schedule-links-from-html.php
// Relink schedule to UHA-*.html by detecting teams in each HTML or using page JSON (if present).
// If a schedule pair has no matching HTML, we UNLINK it to avoid showing wrong info.
// Preview at: /sthportal/tools/fix-schedule-links-from-html.php
// Apply with changes: /sthportal/tools/fix-schedule-links-from-html.php?write=1

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root    = dirname(__DIR__);
$schedP  = $root . '/data/uploads/schedule-current.json';
$teamsP  = $root . '/data/uploads/teams.json';
$htmlDir = $root . '/data/uploads';
$jsonDir = $root . '/data/uploads/boxscores';
$report  = $root . '/data/uploads/fix-from-html-report.csv';

$write = isset($_GET['write']) && $_GET['write'] === '1';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fold($s){
  $s = trim((string)$s);
  if (class_exists('Transliterator')) { $tr = Transliterator::create('Any-Latin; Latin-ASCII'); if ($tr) $s = $tr->transliterate($s); }
  else { $c=@iconv('UTF-8','ASCII//TRANSLIT',$s); if ($c!==false) $s=$c; }
  $s = mb_strtolower($s);
  return preg_replace('/[^a-z0-9]+/',' ', $s);
}
function near_pair_bonus(string $raw, string $a, string $b): int {
  $R = strtoupper($raw);
  $A = strtoupper($a); $B = strtoupper($b);
  $best = 0;
  // adjacency
  $off = 0;
  while (($p = strpos($R, $A, $off)) !== false) {
    $q = strpos($R, $B, $p);
    if ($q !== false && ($q - $p) < 500) { $best = max($best, 10); break; }
    $off = $p + strlen($A);
  }
  // patterns "A @ B" / "A vs B" / "A at B" (and reversed)
  foreach (['@','VS','AT'] as $sep) {
    $pat = '/\b'.preg_quote($A,'/').'\b.{0,60}\b'.$sep.'\b.{0,60}\b'.preg_quote($B,'/').'\b/u';
    $pat2= '/\b'.preg_quote($B,'/').'\b.{0,60}\b'.$sep.'\b.{0,60}\b'.preg_quote($A,'/').'\b/u';
    if (preg_match($pat, $R) || preg_match($pat2, $R)) { $best = max($best, 20); break; }
  }
  return $best;
}
function readJsonFlexible(string $path){
  if (!is_file($path)) return null;
  $cands=[]; $raw=@file_get_contents($path);
  if ($raw!==false){
    $cands[]=$raw;
    if (strlen($raw)>=2 && $raw[0]==="\x1f" && $raw[1]==="\x8b"){
      $d=function_exists('gzdecode')?@gzdecode($raw):@gzinflate(substr($raw,10));
      if ($d!==false && $d!==null) $cands[]=$d;
    }
  }
  $z=@file_get_contents('compress.zlib://'.$path); if ($z!==false) $cands[]=$z;
  foreach($cands as $buf){
    if (strncmp($buf,"\xEF\xBB\xBF",3)===0) $buf=substr($buf,3);
    if (strpos(substr($buf,0,64),"\x00")!==false) $buf=@mb_convert_encoding($buf,'UTF-8','UTF-16,UTF-16LE,UTF-16BE');
    $j=json_decode((string)$buf,true); if (is_array($j)) return $j;
  }
  return null;
}

// Load teams + schedule
$teams = json_decode((string)@file_get_contents($teamsP), true);
$sched = json_decode((string)@file_get_contents($schedP), true);
if (!$teams || !$sched) { echo "Missing teams or schedule."; exit; }
$byId = []; foreach ($teams['teams'] as $t) $byId[(string)$t['id']] = $t;

// Expected pairs from schedule
$games = $sched['games'] ?? [];
$expected = []; // idx => ['pair','date','old']
foreach ($games as $i=>$g){
  $v = (string)($g['visitorTeamId'] ?? $g['visitorTeam'] ?? '');
  $h = (string)($g['homeTeamId']    ?? $g['homeTeam']    ?? '');
  $vAb = (string)($byId[$v]['abbr'] ?? '');
  $hAb = (string)($byId[$h]['abbr'] ?? '');
  $pair = ($vAb && $hAb) ? ($vAb.'@'.$hAb) : '';
  $expected[$i] = ['idx'=>$i, 'pair'=>$pair, 'date'=>(string)($g['date'] ?? ''), 'old'=>(string)($g['link'] ?? '')];
}

// Detect pair per HTML (prefer page JSON if present)
$detected = []; // file => 'V@H'
foreach (glob($htmlDir.'/UHA-*.html') as $htmlPath){
  $bn = basename($htmlPath);
  if (stripos($bn,'farm') !== false) continue; // ignore farm
  $base = pathinfo($bn, PATHINFO_FILENAME);
  $jsonPath = $jsonDir.'/'.$base.'.json';

  $pair = '';
  if (is_file($jsonPath)) {
    $j = readJsonFlexible($jsonPath);
    if (is_array($j)) {
      $vAb = (string)($j['visitor']['abbr'] ?? '');
      $hAb = (string)($j['home']['abbr'] ?? '');
      if ($vAb !== '' && $hAb !== '') $pair = $vAb.'@'.$hAb;
    }
  }
  if ($pair === '') {
    $raw = @file_get_contents($htmlPath);
    if ($raw !== false) {
      $txt = fold($raw);
      // build scores
      $lex = []; $syn=[]; $foldSyn=[];
      foreach ($teams['teams'] as $t){
        $id=(string)$t['id']; $abbr=(string)($t['abbr'] ?? '');
        $name=(string)$t['name']; $short=(string)($t['shortName'] ?? $name);
        $lex[$id] = ['abbr'=>$abbr,'name'=>$name,'short'=>$short];
        $syn[$id] = array_unique(array_filter([$abbr,$name,$short]));
      }
      foreach ($syn as $id=>$arr) foreach ($arr as $s) $foldSyn[$id][] = fold($s);
      $score = [];
      foreach ($foldSyn as $id=>$arr){
        $score[$id]=0;
        foreach ($arr as $s){
          if ($s==='') continue;
          $cnt = substr_count(' '.$txt.' ', ' '.$s.' ');
          $score[$id] += $cnt;
          if (!empty($lex[$id]['abbr'])) {
            $score[$id] += substr_count($raw, $lex[$id]['abbr']) * 2;
          }
        }
      }
      arsort($score);
      $ids = array_slice(array_keys($score),0,4);
      $bestPair=''; $bestScore=0;
      for ($i=0; $i<count($ids); $i++){
        for ($jnd=$i+1; $jnd<count($ids); $jnd++){
          $idA=$ids[$i]; $idB=$ids[$jnd];
          $abbrA=$lex[$idA]['abbr'] ?? ''; $abbrB=$lex[$idB]['abbr'] ?? '';
          if ($abbrA==='' || $abbrB==='') continue;
          $baseScore = ($score[$idA] ?? 0) + ($score[$idB] ?? 0);
          $bonus = near_pair_bonus($raw, $abbrA, $abbrB);
          $total = $baseScore + $bonus;
          if ($total > $bestScore){ $bestScore=$total; $bestPair=$abbrA.'@'.$abbrB; }
        }
      }
      $pair = $bestPair;
    }
  }
  $detected[$bn] = $pair; // can be ''
}

// Map schedule -> candidates. If none, UNLINK to prevent wrong goalies.
$rows=[]; $changed=0; $kept=0; $unlinked=0;
foreach ($expected as $ex){
  $pair = $ex['pair'];
  $cands = [];
  foreach ($detected as $file=>$p) if ($p === $pair) $cands[] = $file;

  if (count($cands)===1){
    $new = $cands[0];
    if ($ex['old'] !== $new){
      if ($write) $sched['games'][$ex['idx']]['link'] = $new;
      $rows[] = [$ex['date'],$pair,$new,'CHANGED',$ex['old']]; $changed++;
    } else {
      $rows[] = [$ex['date'],$pair,$ex['old'],'OK',$ex['old']]; $kept++;
    }
  } else {
    if ($write) unset($sched['games'][$ex['idx']]['link']); // UNLINK
    $rows[] = [$ex['date'],$pair,'', 'UNLINKED_NO_MATCH', $ex['old']]; $unlinked++;
  }
}

// Write CSV + save
$fp = fopen($report,'w');
fputcsv($fp,['date','pair','new_link','status','old_link']);
foreach ($rows as $r) fputcsv($fp,$r);
fclose($fp);

if ($write) {
  $bak = $schedP.'.bak.'.date('Ymd_His');
  @copy($schedP, $bak);
  file_put_contents($schedP, json_encode($sched, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

echo "<h2>Fix schedule links from HTML (strict)</h2>";
echo "<p>Changed: $changed, Kept: $kept, Unlinked (no match): $unlinked</p>";
echo "<p>Report: ".h($report)."</p>";
echo $write
  ? "<p><b>Saved changes.</b> Backup written next to schedule.</p>"
  : "<p><b>Preview only.</b> Add <code>?write=1</code> to save.</p>";
echo "<table border='1' cellpadding='6' cellspacing='0'><tr><th>Date</th><th>Pair</th><th>New</th><th>Status</th><th>Old</th></tr>";
foreach ($rows as $r){
  echo "<tr><td>".h($r[0])."</td><td>".h($r[1])."</td><td>".h($r[2])."</td><td>".h($r[3])."</td><td>".h($r[4])."</td></tr>";
}
echo "</table>";
