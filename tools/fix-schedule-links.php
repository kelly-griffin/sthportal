<?php
// tools/fix-schedule-links.php
// Fixes schedule-current.json "link" fields by matching each game to UHA-#.json.
// Uses schema-flex team extraction + gzip/UTF-16 loader + accent/punct folding.
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root      = dirname(__DIR__);
$schedPath = $root . '/data/uploads/schedule-current.json';
$teamsPath = $root . '/data/uploads/teams.json';
$boxDir    = $root . '/data/uploads/boxscores';
$reportCsv = $root . '/data/uploads/fix-schedule-links-report.csv';

$ARG_ALL    = in_array('--all', $argv ?? [], true);
$ARG_UNLINK = in_array('--unlink-mismatched', $argv ?? [], true);

/* ---- utils ---- */
function jload(string $p){ return file_exists($p) ? json_decode((string)file_get_contents($p), true) : null; }
function jdump(string $p,$d){ file_put_contents($p, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); }
function backup(string $file): string { $b=$file.'.bak.'.date('Ymd_His'); copy($file,$b); return $b; }
function norm(string $s): string { return mb_strtolower(trim($s)); }
function fold_str(string $s): string {
  $s = trim((string)$s);
  if (class_exists('Transliterator')) { $tr = Transliterator::create('Any-Latin; Latin-ASCII'); if ($tr) $s = $tr->transliterate($s); }
  else { $c = @iconv('UTF-8','ASCII//TRANSLIT',$s); if ($c !== false) $s = $c; }
  $s = mb_strtolower($s);
  return preg_replace('/[^a-z0-9]+/','',$s);
}
function readJsonFlexible(string $path) {
  if (!is_file($path)) return null;
  $cands=[]; $raw=@file_get_contents($path);
  if ($raw !== false) {
    $cands[]=$raw;
    if (strlen($raw)>=2 && $raw[0]==="\x1f" && $raw[1]==="\x8b") {
      $d=function_exists('gzdecode')?@gzdecode($raw):@gzinflate(substr($raw,10));
      if ($d!==false && $d!==null) $cands[]=$d;
    }
  }
  $z=@file_get_contents('compress.zlib://'.$path); if ($z!==false) $cands[]=$z;
  foreach ($cands as $buf) {
    if (strncmp($buf,"\xEF\xBB\xBF",3)===0) $buf=substr($buf,3);
    if (strpos(substr($buf,0,64),"\x00")!==false) $buf=@mb_convert_encoding($buf,'UTF-8','UTF-16,UTF-16LE,UTF-16BE');
    $j=json_decode((string)$buf,true); if (is_array($j)) return $j;
  }
  return null;
}
function getp($a, array $path, $def=null){ $x=$a; foreach($path as $k){ if(!is_array($x)||!array_key_exists($k,$x)) return $def; $x=$x[$k]; } return $x; }
function extractTeamsFromBox(array $j): array {
  $cand = [
    ['vName'=>getp($j,['visitor','name']), 'hName'=>getp($j,['home','name']),
     'vAbbr'=>getp($j,['visitor','abbr'], getp($j,['visitor','abbrev'])),
     'hAbbr'=>getp($j,['home','abbr'], getp($j,['home','abbrev'])),
     'date' =>$j['date'] ?? $j['gameDate'] ?? getp($j,['game','date']) ?? null],
    ['vName'=>getp($j,['teams','away','team','name'], getp($j,['teams','away','name'])),
     'hName'=>getp($j,['teams','home','team','name'], getp($j,['teams','home','name'])),
     'vAbbr'=>getp($j,['teams','away','team','abbreviation'], getp($j,['teams','away','abbr'], getp($j,['teams','away','abbrev']))),
     'hAbbr'=>getp($j,['teams','home','team','abbreviation'], getp($j,['teams','home','abbr'], getp($j,['teams','home','abbrev']))),
     'date' =>$j['gameDate'] ?? getp($j,['game','gameDate']) ?? null],
    ['vName'=>$j['awayTeam'] ?? null, 'hName'=>$j['homeTeam'] ?? null,
     'vAbbr'=>$j['awayAbbr'] ?? $j['away'] ?? null,
     'hAbbr'=>$j['homeAbbr'] ?? $j['home'] ?? null,
     'date' =>$j['date'] ?? null],
  ];
  foreach ($cand as $c) { if (!empty($c['vAbbr']) && !empty($c['hAbbr'])) return $c; if (!empty($c['vName']) && !empty($c['hName'])) return $c; }
  $vName=$hName=$vAbbr=$hAbbr=null;
  foreach ($j as $k=>$v) if (is_array($v)) {
    $lk=strtolower($k);
    if (strpos($lk,'away')!==false || strpos($lk,'vis')!==false) { $vName=$vName??($v['name']??null); $vAbbr=$vAbbr??($v['abbr']??$v['abbrev']??null); }
    if (strpos($lk,'home')!==false) { $hName=$hName??($v['name']??null); $hAbbr=$hAbbr??($v['abbr']??$v['abbrev']??null); }
  }
  return ['vName'=>$vName,'hName'=>$hName,'vAbbr'=>$vAbbr,'hAbbr'=>$hAbbr,'date'=>$j['date']??null];
}

/* ---- load teams ---- */
$sched = jload($schedPath);
$teams = jload($teamsPath);
if (!$sched || !$teams) { fwrite(STDERR,"Missing schedule or teams.\n"); exit(1); }

$nameToId = []; $foldToId = []; $idToAbbr = [];
foreach ($teams['teams'] as $t){
  $id=(string)$t['id']; $abbr=(string)($t['abbr'] ?? '');
  $idToAbbr[$id]=$abbr;
  foreach ([(string)$t['name'], (string)($t['shortName'] ?? $t['name']), $abbr] as $k) {
    if ($k==='') continue; $nameToId[norm($k)]=$id; $foldToId[fold_str($k)]=$id;
  }
}
function resolveId(array $g, string $side, array $nameToId): string {
  $idKey=$side.'TeamId'; if (!empty($g[$idKey])) return (string)$g[$idKey];
  $nameKey=$side.'Team'; $name=isset($g[$nameKey]) ? norm((string)$g[$nameKey]) : '';
  return $nameToId[$name] ?? (string)($g[$nameKey] ?? '');
}
function isPlayed(array $g): bool {
  if (array_key_exists('Play',$g)) { $v=$g['Play']; if (is_bool($v)) return $v;
    $s=strtoupper(trim((string)$v)); if (in_array($s,['TRUE','T','YES','Y','1'],true)) return true; if (in_array($s,['FALSE','F','NO','N','0',''],true)) return false; }
  if (isset($g['visitorScore'],$g['homeScore'])) return ((int)$g['visitorScore']>0)||((int)$g['homeScore']>0);
  return false;
}

/* ---- index boxscores by pair ---- */
$pairIndex = []; // 'V@H' => [ ['html'=>..., 'date'=>...] ... ]
if (is_dir($boxDir)) {
  foreach (glob($boxDir.'/UHA-*.json') as $jf) {
    $j = readJsonFlexible($jf); if (!is_array($j)) continue;
    $ex = extractTeamsFromBox($j);
    $vAb = (string)($ex['vAbbr'] ?? '');
    $hAb = (string)($ex['hAbbr'] ?? '');
    if ($vAb==='' || $hAb==='') {
      $vId = $foldToId[fold_str((string)($ex['vName'] ?? ''))] ?? '';
      $hId = $foldToId[fold_str((string)($ex['hName'] ?? ''))] ?? '';
      $vAb = $idToAbbr[$vId] ?? '';
      $hAb = $idToAbbr[$hId] ?? '';
    }
    if ($vAb==='' || $hAb==='') continue;

    $pair = $vAb.'@'.$hAb;
    $date = (string)($ex['date'] ?? '');
    $html = basename($jf, '.json').'.html';
    $pairIndex[$pair][] = ['html'=>$html, 'date'=>$date];
  }
}

/* ---- walk schedule ---- */
$games=$sched['games'] ?? [];
$rows=[]; $changed=0; $unlinked=0; $ambig=0; $missed=0; $keptOk=0;

foreach ($games as &$g){
  $date=(string)($g['date'] ?? '');
  $vid=resolveId($g,'visitor',$nameToId);
  $hid=resolveId($g,'home',$nameToId);
  $vAb=$idToAbbr[$vid] ?? '';
  $hAb=$idToAbbr[$hid] ?? '';
  $pair=$vAb.'@'.$hAb;
  $had=(string)($g['link'] ?? '');
  $played=isPlayed($g);

  if (!$ARG_ALL && !$played) { $rows[] = [$date,$pair,$had,'SKIP_UNPLAYED','']; continue; }

  // Keep existing link if it matches
  if ($had!=='') {
    $base=pathinfo($had, PATHINFO_FILENAME);
    $jf=$boxDir.'/'.$base.'.json';
    if (is_file($jf)) {
      $j=readJsonFlexible($jf);
      if (is_array($j)) {
        $ex=extractTeamsFromBox($j);
        $bv=$ex['vAbbr'] ?? ''; $bh=$ex['hAbbr'] ?? '';
        if ($bv==='' || $bh==='') {
          $bvId=$foldToId[fold_str((string)($ex['vName']??''))] ?? '';
          $bhId=$foldToId[fold_str((string)($ex['hName']??''))] ?? '';
          $bv=$idToAbbr[$bvId] ?? ''; $bh=$idToAbbr[$bhId] ?? '';
        }
        if ($bv!=='' && $bh!=='' && ($bv.'@'.$bh)===$pair) { $rows[] = [$date,$pair,$had,'KEEP_OK',$had]; $keptOk++; continue; }
      }
    }
  }

  $cands = $pairIndex[$pair] ?? [];
  if (count($cands)===0) {
    if ($ARG_UNLINK && $had!=='') { $g['link']=''; $unlinked++; $rows[] = [$date,$pair,'','UNLINKED_NO_MATCH',$had]; }
    else { $rows[] = [$date,$pair,$had,'NO_MATCH_FOUND',$had]; $missed++; }
    continue;
  }
  if (count($cands)===1) {
    $new=$cands[0]['html'];
    if ($had !== $new) { $g['link']=$new; $changed++; $rows[] = [$date,$pair,$new,'LINK_FIXED',$had]; }
    else { $rows[] = [$date,$pair,$had,'OK',$had]; $keptOk++; }
    continue;
  }
  // prefer same date if present
  $byDate = array_values(array_filter($cands, fn($c)=> (string)$c['date']===$date && $c['date']!==''));
  if (count($byDate)===1) {
    $new=$byDate[0]['html'];
    if ($had !== $new) { $g['link']=$new; $changed++; $rows[] = [$date,$pair,$new,'LINK_FIXED_DATE',$had]; }
    else { $rows[] = [$date,$pair,$had,'OK_DATE',$had]; $keptOk++; }
  } else {
    if ($ARG_UNLINK && $had!=='') { $g['link']=''; $unlinked++; $rows[] = [$date,$pair,'','UNLINKED_AMBIG',$had]; }
    else { $rows[] = [$date,$pair,$had,'AMBIGUOUS',$had]; $ambig++; }
  }
}
unset($g);

/* ---- write + report ---- */
$bak = backup($schedPath);
$sched['games']=$games;
jdump($schedPath,$sched);

$fp=fopen($reportCsv,'w');
fputcsv($fp,['date','matchup','new_link','status','old_link']);
foreach ($rows as $r) fputcsv($fp,$r);
fclose($fp);

echo "Fix complete. Changed: $changed, Kept: $keptOk, Unlinked: $unlinked, Ambiguous: $ambig, No-match: $missed\n";
echo "Backup: $bak\n";
echo "Report: $reportCsv\n";
