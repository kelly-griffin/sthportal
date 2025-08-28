<?php
// /sthportal/tools/build-ticker-json.php
// Ticker = Today’s Games (from schedule CSV) + Recent Finals (from RSSFeed.xml)
// "Today":
//   1) earliest schedule date/day with ANY unplayed (blank OR zero) scores
//   2) else, next games after the max "Professional Game #N" in RSS
// Team names: if schedule has numeric team IDs, map via V3ProTeam.csv (Number → Name/Abbre).
// Output: /data/uploads/ticker-current.json  { "ticker": [ ... ] }

declare(strict_types=1);

$root       = dirname(__DIR__);
$uploadsDir = $root . '/data/uploads';
$outFile    = $uploadsDir . '/ticker-current.json';

$rssFiles   = [$uploadsDir.'/RSSFeed.xml', $uploadsDir.'/rssfeed.xml'];
$schedFiles = [
  $uploadsDir . '/UHA-V3ProSchedule.csv',  // preferred
  $uploadsDir . '/UHA-ProSchedule.csv',    // fallback
];
$teamFiles  = [
  $uploadsDir . '/UHA-V3ProTeam.csv',      // preferred
  $uploadsDir . '/UHA-ProTeam.csv',        // fallback
];

header('Content-Type: text/plain; charset=utf-8');

/* ---------- helpers ---------- */
function logln(string $s){ echo $s, "\n"; }
function clean(?string $s): string {
  if ($s === null) return '';
  $s = html_entity_decode($s, ENT_QUOTES|ENT_HTML5, 'UTF-8');
  $s = strip_tags($s);
  $s = preg_replace('/\s+/u', ' ', $s);
  return trim($s);
}
function nk(string $s): string { return strtolower(preg_replace('/[^a-z0-9]/', '', $s)); }
function getf(array $row, array $cands, $def=null){
  static $cache = [];
  $id = spl_object_id((object)$row);
  if (!isset($cache[$id])) {
    $norm=[]; foreach ($row as $k=>$v) $norm[nk((string)$k)]=$v; $cache[$id]=$norm;
  }
  $norm = $cache[$id];
  foreach ($cands as $k) if (array_key_exists($k,$row)) return $row[$k];
  foreach ($cands as $k) { $kk=nk((string)$k); if (array_key_exists($kk,$norm)) return $norm[$kk]; }
  return $def;
}
function ymd_from(string $s): ?string {
  if ($s==='') return null;
  $ts = strtotime($s);
  if ($ts) return gmdate('Y-m-d',$ts);
  foreach (['m/d/Y','Y/m/d','d/m/Y'] as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $s);
    if ($dt) return $dt->format('Y-m-d');
  }
  return null;
}
// CSV reader with delimiter sniff (; or ,)
function load_csv_assoc(string $file): array {
  if (!is_readable($file)) return [];
  $rows=[]; $fh=fopen($file,'r'); if(!$fh) return [];
  // sniff delimiter from first line
  $probe = fgets($fh, 8192);
  if ($probe===false) { fclose($fh); return []; }
  $delim = (substr_count($probe,';') > substr_count($probe,',')) ? ';' : ',';
  logln("Detected delimiter '". $delim ."' for ".basename($file));
  // rewind and parse
  rewind($fh);
  $hdr = fgetcsv($fh, 0, $delim);
  if (!$hdr){ fclose($fh); return []; }
  if (isset($hdr[0])) $hdr[0] = preg_replace('/^\xEF\xBB\xBF/u','',$hdr[0]); // BOM
  // trim headers
  foreach ($hdr as &$h) { $h = clean((string)$h); }
  while(($rec=fgetcsv($fh, 0, $delim))!==false){
    if ($rec===[null]||$rec===false) continue;
    $row=[]; foreach($hdr as $i=>$k){ $row[$k]=$rec[$i]??null; } $rows[]=$row;
  }
  fclose($fh);
  return $rows;
}

/* ---------- Team map: Number -> [name, abbr] ---------- */
$TEAM_NAME = [];  // id => Name
$TEAM_ABBR = [];  // id => Abbre

foreach ($teamFiles as $tf) {
  if (!is_readable($tf)) continue;
  $rows = load_csv_assoc($tf);
  if (!$rows) continue;
  foreach ($rows as $r) {
    // Accept many header variants
    $num  = clean((string)getf($r, ['Number','Team Number','TeamNumber','ID','Team ID','Team #']));
    $name = clean((string)getf($r, ['Name','Team Name','TeamName']));
    $abbr = clean((string)getf($r, ['Abbre','Abbrev','Abbreviation','Abbr']));
    if ($num !== '' && ctype_digit($num)) {
      $id = (int)$num;
      if ($name !== '') $TEAM_NAME[$id] = $name;
      if ($abbr !== '') $TEAM_ABBR[$id] = $abbr;
    }
  }
  logln('Loaded team map from '.basename($tf).' ('.count($TEAM_NAME).' teams)');
  if ($TEAM_NAME) break; // stop at first successful file
}

// Resolve any of: explicit name, abbr, or numeric id -> pretty name (fallback to abbr)
function resolve_team($val, $abbr, $TEAM_NAME, $TEAM_ABBR): string {
  $val = clean((string)$val);
  $abbr= clean((string)$abbr);
  if ($val !== '') {
    if (ctype_digit($val)) {
      $id = (int)$val;
      if (isset($TEAM_NAME[$id])) return $TEAM_NAME[$id];
      if (isset($TEAM_ABBR[$id])) return $TEAM_ABBR[$id];
      return $val; // give up
    }
    return $val; // non-numeric, assume it's a name already
  }
  if ($abbr !== '') return $abbr;
  return 'Unknown';
}

/* ---------- FINALS: from RSS <description> + track max Game# ---------- */
$finals = [];
$maxPlayedGameNo = 0;
$rssXml = null;
foreach ($rssFiles as $rf) {
  if (is_readable($rf)) {
    libxml_use_internal_errors(true);
    $rssXml = @simplexml_load_file($rf,'SimpleXMLElement',LIBXML_NOERROR|LIBXML_NOWARNING);
    if ($rssXml) { logln("Using RSS: ".basename($rf)); break; }
  }
}
if ($rssXml) {
  $items = isset($rssXml->channel->item) ? $rssXml->channel->item : (isset($rssXml->item) ? $rssXml->item : []);
  foreach ($items as $it) {
    $title = clean((string)($it->title ?? ''));
    $desc  = clean((string)($it->description ?? ''));
    $pub   = (string)($it->pubDate ?? '');
    $dtag  = '';
    if ($pub!=='') { $ts=strtotime($pub)?:0; if($ts) $dtag=' ['.gmdate('Y-m-d',$ts).']'; }

    foreach ([$title,$desc] as $txt) {
      if ($txt && preg_match('/Game\s*#\s*(\d+)/i', $txt, $m)) {
        $maxPlayedGameNo = max($maxPlayedGameNo, (int)$m[1]);
      }
    }

    $line = $desc ?: $title;
    if ($line==='') continue;
    $line = preg_replace('/^\s*(?:Professional|Pro)\s+Game\s+#?\d+\s*-\s*/i','',$line);
    if (preg_match('/^(.*?)\(\s*(\d+)\s*\)\s+vs\s+(.*?)\(\s*(\d+)\s*\)\s*$/i',$line,$m)) {
      $aTeam = clean($m[1]); $aScore=(int)$m[2];
      $bTeam = clean($m[3]); $bScore=(int)$m[4];
      $finals[] = "NHL: $aTeam $aScore — $bTeam $bScore$dtag";
    } else {
      $finals[] = "NHL: $line$dtag";
    }
  }
}
$finals = array_values(array_unique($finals));
logln("Max played game #: ".$maxPlayedGameNo);

/* ---------- TODAY’S GAMES: choose earliest unplayed or next after Game# ---------- */
$scheduled = [];
$todayLabel = null; // YYYY-MM-DD or "Day N"
$builtBy = '';      // "date" or "gamenumber"

foreach ($schedFiles as $sf) {
  if (!is_readable($sf)) continue;
  $rows = load_csv_assoc($sf);
  if (!$rows) continue;

  // group by date if present, else by Day
  $byBucket = [];
  $bucketIsDate = false;
  $hasDateCol = false;
  foreach (['Date','GameDate','ScheduleDate'] as $dc) {
    if (array_key_exists($dc, $rows[0])) { $hasDateCol = true; break; }
  }

  foreach ($rows as $r) {
    if ($hasDateCol) {
      $dateRaw = (string)getf($r,['Date','GameDate','ScheduleDate'],''); $d = ymd_from(clean($dateRaw));
      if (!$d) continue; $bucket = $d; $bucketIsDate = true;
    } else {
      $day = clean((string)getf($r,['Day'],'')); if ($day==='') continue; $bucket = 'Day '.$day;
    }
    $byBucket[$bucket][] = $r;
  }
  if (!$byBucket) continue;

  // sort earliest first
  uksort($byBucket, function($a,$b) use($bucketIsDate){
    if ($bucketIsDate) return strcmp($a,$b);
    $na = (int)preg_replace('/\D+/','',$a); $nb = (int)preg_replace('/\D+/','',$b);
    return $na <=> $nb;
  });

  // 1) earliest bucket with any unplayed (blank or zero)
  foreach ($byBucket as $key=>$games) {
    $hasUnplayed=false;
    foreach ($games as $r) {
      $v = clean((string)getf($r,['Visitor Score','VisitorScore','Visitor Team Score','V Score'],''));
      $h = clean((string)getf($r,['Home Score','HomeScore','Home Team Score','H Score'],''));
      if ($v==='' || $h==='' || $v==='0' || $h==='0') { $hasUnplayed=true; break; }
    }
    if ($hasUnplayed) {
      $todayLabel = $key; $builtBy='date';
      foreach ($games as $r) {
        $v = clean((string)getf($r,['Visitor Score','VisitorScore','Visitor Team Score','V Score'],''));
        $h = clean((string)getf($r,['Home Score','HomeScore','Home Team Score','H Score'],''));
        if (!($v==='' || $h==='' || $v==='0' || $h==='0')) continue;

        $awayName = getf($r,['Visitor Team Name','VisitorTeamName','Visitor Name','Visitor Team','Visitor'],''); // may be id
        $homeName = getf($r,['Home Team Name','HomeTeamName','Home Name','Home Team','Home'],'');               // may be id
        $awayAbbr = getf($r,['Visitor Team Abbre','VisitorTeamAbbre','VisitorTeamAbbrev','AwayAbbre'],''); 
        $homeAbbr = getf($r,['Home Team Abbre','HomeTeamAbbre','HomeTeamAbbrev','HomeAbbre'],''); 
        $A = resolve_team($awayName, $awayAbbr, $TEAM_NAME, $TEAM_ABBR);
        $H = resolve_team($homeName, $homeAbbr, $TEAM_NAME, $TEAM_ABBR);
        if ($A==='Unknown' || $H==='Unknown') continue;

        $time     = clean((string)getf($r,['Time','Game Time','GameTime','StartTime'],''));
        $line = "NHL: $A at $H"; if ($time!=='') $line .= " ($time)";
        $scheduled[] = $line;
      }
      break;
    }
  }

  // 2) else next day after max Game#
  if (!$scheduled && $maxPlayedGameNo > 0) {
    $nextBucket = null;
    foreach ($byBucket as $key=>$games) {
      foreach ($games as $r) {
        $gn = (int)clean((string)getf($r,['Game Number','GameNumber','Game #','Game'],'0'));
        if ($gn > $maxPlayedGameNo) { $nextBucket = $key; break 2; }
      }
    }
    if ($nextBucket !== null) {
      $todayLabel = $nextBucket; $builtBy='gamenumber';
      foreach ($byBucket[$nextBucket] as $r) {
        $gn = (int)clean((string)getf($r,['Game Number','GameNumber','Game #','Game'],'0'));
        if ($gn <= $maxPlayedGameNo) continue;
        $awayName = getf($r,['Visitor Team Name','VisitorTeamName','Visitor Name','Visitor Team','Visitor'],'');
        $homeName = getf($r,['Home Team Name','HomeTeamName','Home Name','Home Team','Home'],'');
        $awayAbbr = getf($r,['Visitor Team Abbre','VisitorTeamAbbre','VisitorTeamAbbrev','AwayAbbre'],'');
        $homeAbbr = getf($r,['Home Team Abbre','HomeTeamAbbre','HomeTeamAbbrev','HomeAbbre'],'');
        $A = resolve_team($awayName, $awayAbbr, $TEAM_NAME, $TEAM_ABBR);
        $H = resolve_team($homeName, $homeAbbr, $TEAM_NAME, $TEAM_ABBR);
        if ($A==='Unknown' || $H==='Unknown') continue;
        $time     = clean((string)getf($r,['Time','Game Time','GameTime','StartTime'],''));
        $line = "NHL: $A at $H"; if ($time!=='') $line .= " ($time)";
        $scheduled[] = $line;
      }
    }
  }

  if ($todayLabel !== null) {
    logln("Schedule source: ".basename($sf)."  Today = ".$todayLabel." (built by ".$builtBy.")");
    break;
  }
}

$scheduled = array_values(array_unique($scheduled));

/* ---------- cap + compose ---------- */
$scheduled = array_slice($scheduled, 0, 16);
$finals    = array_slice($finals,    0, 16);

$ticker = [];
$hdr = "NHL — Today’s Games:";
if ($todayLabel !== null) $hdr .= " [$todayLabel]";
$ticker[] = $hdr;
if ($scheduled) foreach ($scheduled as $t) $ticker[] = $t;
else            $ticker[] = "NHL: No games scheduled";

if ($finals) {
  $ticker[] = "NHL — Recent Finals:";
  foreach ($finals as $t) $ticker[] = $t;
}

if (!is_dir($uploadsDir)) @mkdir($uploadsDir,0775,true);
$json = json_encode(['ticker'=>$ticker], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
if ($json === false) { http_response_code(500); exit("ERROR: JSON encode failed: ".json_last_error_msg()."\n"); }
$ok = @file_put_contents($outFile,$json)!==false;
if (!$ok) { http_response_code(500); exit("ERROR: Could not write $outFile\n"); }

echo "Wrote: $outFile\n";
echo "Lines: ".count($ticker)."\n";
foreach (array_slice($ticker,0,10) as $i=>$t) echo sprintf(" %02d) %s\n",$i+1,$t);
