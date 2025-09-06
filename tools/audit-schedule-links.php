<?php
// tools/audit-schedule-links.php
// Verifies each schedule row's *linked* boxscore (UHA-#.json) matches the row's teams.
// Handles gzip/UTF-16 and multiple JSON schemas (visitor/home, away/home, NHL API).
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root      = dirname(__DIR__);
$schedPath = $root . '/assets/json/schedule-current.json';
$teamsPath = $root . '/assets/json/teams.json';
$boxDir    = $root . '/data/uploads/';

/* ---- utils ---- */
function jload(string $p){ return file_exists($p) ? json_decode((string)file_get_contents($p), true) : null; }
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
  $cands = [];
  $raw = @file_get_contents($path);
  if ($raw !== false) {
    $cands[] = $raw;
    if (strlen($raw) >= 2 && $raw[0] === "\x1f" && $raw[1] === "\x8b") {
      $d = function_exists('gzdecode') ? @gzdecode($raw) : @gzinflate(substr($raw,10));
      if ($d !== false && $d !== null) $cands[] = $d;
    }
  }
  $z = @file_get_contents('compress.zlib://' . $path);
  if ($z !== false) $cands[] = $z;
  foreach ($cands as $buf) {
    if (strncmp($buf,"\xEF\xBB\xBF",3)===0) $buf = substr($buf,3);
    if (strpos(substr($buf,0,64), "\x00") !== false) $buf = @mb_convert_encoding($buf, 'UTF-8', 'UTF-16,UTF-16LE,UTF-16BE');
    $j = json_decode((string)$buf, true);
    if (is_array($j)) return $j;
  }
  return null;
}
function getp($a, array $path, $def=null) {
  $x=$a; foreach($path as $k){ if(!is_array($x) || !array_key_exists($k,$x)) return $def; $x = $x[$k]; } return $x;
}
/** Extract team names/abbrs/date from many possible schemas. */
function extractTeamsFromBox(array $j): array {
  $cand = [
    // A) our original
    ['vName'=>getp($j,['visitor','name']), 'hName'=>getp($j,['home','name']),
     'vAbbr'=>getp($j,['visitor','abbr'], getp($j,['visitor','abbrev'])),
     'hAbbr'=>getp($j,['home','abbr'], getp($j,['home','abbrev'])),
     'date' =>$j['date'] ?? $j['gameDate'] ?? getp($j,['game','date']) ?? null],
    // B) NHL-like
    ['vName'=>getp($j,['teams','away','team','name'], getp($j,['teams','away','name'])),
     'hName'=>getp($j,['teams','home','team','name'], getp($j,['teams','home','name'])),
     'vAbbr'=>getp($j,['teams','away','team','abbreviation'], getp($j,['teams','away','abbr'], getp($j,['teams','away','abbrev']))),
     'hAbbr'=>getp($j,['teams','home','team','abbreviation'], getp($j,['teams','home','abbr'], getp($j,['teams','home','abbrev']))),
     'date' =>$j['gameDate'] ?? getp($j,['game','gameDate']) ?? null],
    // C) Flat keys
    ['vName'=>$j['awayTeam'] ?? null, 'hName'=>$j['homeTeam'] ?? null,
     'vAbbr'=>$j['awayAbbr'] ?? $j['away'] ?? null,
     'hAbbr'=>$j['homeAbbr'] ?? $j['home'] ?? null,
     'date' =>$j['date'] ?? null],
  ];
  foreach ($cand as $c) {
    if (!empty($c['vAbbr']) && !empty($c['hAbbr'])) return $c;
    if (!empty($c['vName']) && !empty($c['hName'])) return $c;
  }
  // Last resort: shallow sniff
  $vName=$hName=$vAbbr=$hAbbr=null;
  foreach ($j as $k=>$v) if (is_array($v)) {
    $lk=strtolower($k);
    if (strpos($lk,'away')!==false || strpos($lk,'vis')!==false) { $vName=$vName??($v['name']??null); $vAbbr=$vAbbr??($v['abbr']??$v['abbrev']??null); }
    if (strpos($lk,'home')!==false) { $hName=$hName??($v['name']??null); $hAbbr=$hAbbr??($v['abbr']??$v['abbrev']??null); }
  }
  return ['vName'=>$vName,'hName'=>$hName,'vAbbr'=>$vAbbr,'hAbbr'=>$hAbbr,'date'=>$j['date']??null];
}

/* ---- load teams + maps ---- */
$sched = jload($schedPath);
$teams = jload($teamsPath);
if (!$sched || !$teams) { echo "Missing schedule or teams.\n"; exit(1); }

$nameToId = []; $foldToId = []; $idToAbbr = [];
foreach ($teams['teams'] as $t) {
  $id=(string)$t['id']; $abbr=(string)($t['abbr'] ?? '');
  $idToAbbr[$id] = $abbr;
  foreach ([(string)$t['name'], (string)($t['shortName'] ?? $t['name']), $abbr] as $k) {
    if ($k==='') continue;
    $nameToId[norm($k)] = $id;
    $foldToId[fold_str($k)] = $id;
  }
}
function resolveId(array $g, string $side, array $nameToId): string {
  $idKey = $side.'TeamId'; if (!empty($g[$idKey])) return (string)$g[$idKey];
  $nameKey = $side.'Team'; $name = isset($g[$nameKey]) ? norm((string)$g[$nameKey]) : '';
  return $nameToId[$name] ?? (string)($g[$nameKey] ?? '');
}
function isPlayed(array $g): bool {
  if (array_key_exists('Play',$g)) { $v=$g['Play']; if (is_bool($v)) return $v;
    $s=strtoupper(trim((string)$v)); if (in_array($s,['TRUE','T','YES','Y','1'],true)) return true; if (in_array($s,['FALSE','F','NO','N','0',''],true)) return false; }
  if (isset($g['visitorScore'],$g['homeScore'])) return ((int)$g['visitorScore']>0)||((int)$g['homeScore']>0);
  return false;
}

/* ---- audit ---- */
$rows=[]; $ok=0; $no=0; $missing=0;
foreach ($sched['games'] as $g) {
  $date=(string)($g['date'] ?? ''); $link=(string)($g['link'] ?? '');
  $vid=resolveId($g,'visitor',$nameToId); $hid=resolveId($g,'home',$nameToId);
  $vAb=$idToAbbr[$vid] ?? ''; $hAb=$idToAbbr[$hid] ?? ''; $pair=$vAb.'@'.$hAb;

  $status='UNPLAYED'; $gwg=''; $w='';
  if (isPlayed($g)) {
    if ($link==='') { $status='NO_LINK'; }
    else {
      $base = pathinfo($link, PATHINFO_FILENAME);
      $json = $boxDir . '/' . $base . '.json';
      if (!is_file($json)) { $status='MISSING_JSON'; $missing++; }
      else {
        $box = readJsonFlexible($json);
        if (!is_array($box)) { $status='DECODE_FAILED'; $missing++; }
        else {
          $ex = extractTeamsFromBox($box);
          $bvAb = (string)($ex['vAbbr'] ?? '');
          $bhAb = (string)($ex['hAbbr'] ?? '');
          if ($bvAb==='' || $bhAb==='') {
            // map names → ABBR via folded lookup
            $bvName = fold_str((string)($ex['vName'] ?? ''));
            $bhName = fold_str((string)($ex['hName'] ?? ''));
            $bvId = $foldToId[$bvName] ?? '';
            $bhId = $foldToId[$bhName] ?? '';
            $bvAb = $idToAbbr[$bvId] ?? '';
            $bhAb = $idToAbbr[$bhId] ?? '';
          }
          $boxPair = $bvAb.'@'.$bhAb;
          if ($bvAb!=='' && $bhAb!=='' && $boxPair===$pair) {
            $status='OK'; $ok++;
            $gwg = (string)($box['gwg'] ?? '');
            $w   = (string)($box['winningGoalie'] ?? '');
          } else {
            $status='NO_MATCH'; $no++;
          }
        }
      }
    }
  }
  $rows[] = ['date'=>$date,'pair'=>$pair,'link'=>basename($link),'status'=>$status,'gwg'=>$gwg,'w'=>$w];
}

/* ---- output ---- */
if (php_sapi_name()==='cli') {
  echo "OK: $ok, NO_MATCH: $no, MISSING/DECODE: $missing\n";
  foreach ($rows as $r) echo "{$r['date']}  {$r['pair']}  {$r['link']}  {$r['status']}  GWG={$r['gwg']}  W={$r['w']}\n";
} else {
  echo "<h2>Schedule ↔ Linked Boxscores Audit</h2>";
  echo "<p>OK: <b>$ok</b> &nbsp; NO_MATCH: <b>$no</b> &nbsp; MISSING/DECODE: <b>$missing</b></p>";
  echo "<table border='1' cellpadding='6' cellspacing='0'><tr><th>Date</th><th>Matchup</th><th>Link</th><th>Status</th><th>GWG</th><th>W</th></tr>";
  foreach ($rows as $r) {
    $color = ($r['status']==='OK') ? '#e7ffe7' : (($r['status']==='NO_MATCH') ? '#fff2d6' : '#ffe7e7');
    echo "<tr style='background:$color'><td>".htmlspecialchars($r['date'])."</td><td>".htmlspecialchars($r['pair'])."</td><td>".htmlspecialchars($r['link'])."</td><td>".htmlspecialchars($r['status'])."</td><td>".htmlspecialchars($r['gwg'])."</td><td>".htmlspecialchars($r['w'])."</td></tr>";
  }
  echo "</table>";
}
