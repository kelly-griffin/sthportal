<?php
// tools/patch-boxscore-teams-from-schedule.php
// Fill missing visitor/home names + abbr in UHA-*.json using schedule-current.json and teams.json.
// Safe: only writes when fields are empty. Leaves gwg/winningGoalie as-is.
//
// Run: php tools/patch-boxscore-teams-from-schedule.php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root       = dirname(__DIR__);
$schedPath  = $root . '/assets/json/schedule-current.json';
$teamsPath  = $root . '/assets/json/teams.json';
$boxDir     = $root . '/data/uploads/';
$reportCsv  = $root . '/data/patch-boxscore-teams-report.csv';

function jload(string $p){ return is_file($p) ? json_decode((string)file_get_contents($p), true) : null; }
function jdump(string $p,$d){ file_put_contents($p, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); }
function norm(string $s): string { return mb_strtolower(trim($s)); }

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
    if (strpos(substr($buf,0,64),"\x00")!==false) {
      $buf=@mb_convert_encoding($buf,'UTF-8','UTF-16,UTF-16LE,UTF-16BE');
    }
    $j=json_decode((string)$buf,true);
    if (is_array($j)) return $j;
  }
  return null;
}

$sched = jload($schedPath);
$teams = jload($teamsPath);
if (!$sched || !$teams) { fwrite(STDERR,"Missing schedule or teams.\n"); exit(1); }

$idTo = []; // id => ['name','abbr']
foreach ($teams['teams'] as $t) {
  $id = (string)$t['id'];
  $idTo[$id] = [
    'name' => (string)($t['name'] ?? ''),
    'abbr' => (string)($t['abbr'] ?? '')
  ];
}

function resolveId(array $g, string $side): string {
  $idKey = $side.'TeamId';
  if (!empty($g[$idKey])) return (string)$g[$idKey];
  // fallback to name if really needed
  $nameKey = $side.'Team';
  return (string)($g[$nameKey] ?? '');
}

// Build map: link base => schedule-derived teams/date
$linkMap = []; // 'UHA-42' => ['vId'=>..,'hId'=>..,'date'=>...]
foreach (($sched['games'] ?? []) as $g) {
  $link = (string)($g['link'] ?? '');
  if ($link === '') continue;
  $base = pathinfo($link, PATHINFO_FILENAME); // UHA-#
  if ($base === '') continue;
  $vid = resolveId($g,'visitor');
  $hid = resolveId($g,'home');
  $date = (string)($g['date'] ?? '');
  $linkMap[$base] = ['vId'=>$vid, 'hId'=>$hid, 'date'=>$date];
}

$rows = [];
$updated = 0; $skipped = 0; $missing = 0;

foreach (glob($boxDir.'/UHA-*.json') as $jf) {
  $base = basename($jf, '.json'); // UHA-#
  $map  = $linkMap[$base] ?? null;
  if ($map === null) { $rows[] = [$base.'.json','NO_SCHED_MAP','','']; $missing++; continue; }

  $j = readJsonFlexible($jf);
  if (!is_array($j)) { $rows[] = [$base.'.json','DECODE_FAIL','','']; $missing++; continue; }

  $changed = false;

  // build target values from schedule/teams
  $vId = (string)$map['vId']; $hId = (string)$map['hId'];
  $vName = $idTo[$vId]['name'] ?? '';
  $hName = $idTo[$hId]['name'] ?? '';
  $vAbbr = $idTo[$vId]['abbr'] ?? '';
  $hAbbr = $idTo[$hId]['abbr'] ?? '';

  // ensure structure exists
  if (!isset($j['visitor']) || !is_array($j['visitor'])) $j['visitor'] = [];
  if (!isset($j['home'])    || !is_array($j['home']))    $j['home']    = [];

  // Fill only when empty/absent
  if (!isset($j['visitor']['name']) || trim((string)$j['visitor']['name']) === '') { $j['visitor']['name'] = $vName; $changed = true; }
  if (!isset($j['home']['name'])    || trim((string)$j['home']['name'])    === '') { $j['home']['name']    = $hName; $changed = true; }
  if (!isset($j['visitor']['abbr']) || $j['visitor']['abbr'] === null || $j['visitor']['abbr'] === '') { $j['visitor']['abbr'] = $vAbbr; $changed = true; }
  if (!isset($j['home']['abbr'])    || $j['home']['abbr'] === null    || $j['home']['abbr'] === '')    { $j['home']['abbr']    = $hAbbr; $changed = true; }

  // add date if schedule has it and JSON doesn't
  if (!isset($j['date']) || trim((string)$j['date']) === '') {
    if (!empty($map['date'])) { $j['date'] = $map['date']; $changed = true; }
  }

  if ($changed) {
    jdump($jf, $j);
    $updated++;
    $rows[] = [$base.'.json','UPDATED',$vAbbr.'@'.$hAbbr,$map['date']];
  } else {
    $skipped++;
    $rows[] = [$base.'.json','UNCHANGED',$j['visitor']['abbr'].'@'.$j['home']['abbr'] ?? '', $j['date'] ?? ''];
  }
}

// write small report
$fp = fopen($reportCsv,'w');
fputcsv($fp, ['file','status','pair','date']);
foreach ($rows as $r) fputcsv($fp,$r);
fclose($fp);

echo "Patched: $updated, Unchanged: $skipped, Missing/Decode: $missing\n";
echo "Report: $reportCsv\n";
