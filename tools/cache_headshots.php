<?php
/**
 * tools/cache_headshots.php — v0.7.6
 * - Follow redirects when using fopen (Windows fallback)
 * - Defaults: dest=flat, sources=cms,mugs, relax_tls auto-on on Windows
 */

@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '768M');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level()) { @ob_end_flush(); }
ob_implicit_flush(true);
header('Content-Type: text/plain; charset=utf-8');

function out($s){ echo $s; echo str_repeat(" ", 512); echo "\n"; @flush(); @ob_flush(); }
function season_slug($now=null){
  $t = $now ?: time();
  $y = (int)date('Y', $t);
  $m = (int)date('n', $t);
  $start = ($m >= 7) ? $y : ($y - 1);
  $end = $start + 1;
  return sprintf('%d%d', $start, $end);
}
function parse_csv_assoc($file){
  $rows=[]; if(!is_readable($file)) return $rows;
  if(($fh=fopen($file,'r'))===false) return $rows;
  $hdr=fgetcsv($fh); if(!$hdr){ fclose($fh); return $rows; }
  $norm=[]; foreach($hdr as $h){ $norm[] = strtolower(preg_replace('/[^a-z0-9%]+/i','', (string)$h)); }
  while(($cols=fgetcsv($fh))!==false){
    $row=[]; foreach($norm as $i=>$k){ $row[$i] = $cols[$i] ?? null; }
    $row2=[]; foreach($norm as $i=>$k){ $row2[$k] = $row[$i]; }
    $rows[]=$row2;
  }
  fclose($fh); return $rows;
}
function first_nonempty($row, $keys, $default=null){
  foreach($keys as $k){ if(isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return $row[$k]; }
  return $default;
}
function value_of($row,$alias,$aliases,$default=null){
  $keys = $aliases[$alias] ?? [$alias];
  return first_nonempty($row,$keys,$default);
}
function extract_id($url){
  if ($url===null) return '';
  if (preg_match('/(\d{6,8})/', (string)$url, $m)) return $m[1];
  return '';
}
function save_file($path,$bytes){
  $dir = dirname($path);
  if(!is_dir($dir)) @mkdir($dir,0777,true);
  return file_put_contents($path, $bytes) !== false;
}

function http_get($url, $insecure=false, $use_fopen=false, $timeout=25, $max_redirects=5){
  if (!$use_fopen && function_exists('curl_init')){
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => $max_redirects,
      CURLOPT_USERAGENT => 'UHA-Portal/1.0',
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_SSL_VERIFYPEER => $insecure ? false : true,
      CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
      CURLOPT_HTTPHEADER => ['Accept: */*'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$code, $body, $err];
  }
  $current = $url;
  for ($i=0; $i<=$max_redirects; $i++){
    $ctx = stream_context_create([
      'http' => ['method'=>'GET', 'timeout'=>$timeout, 'header'=>"User-Agent: UHA-Portal/1.0\r\n"],
      'ssl' => ['verify_peer' => !$insecure, 'verify_peer_name' => !$insecure]
    ]);
    $body = @file_get_contents($current, false, $ctx);
    $code = 0; $loc = null;
    if (isset($http_response_header) && is_array($http_response_header)){
      foreach ($http_response_header as $h){
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) { $code = (int)$m[1]; }
        if (stripos($h, 'Location:') === 0){ $loc = trim(substr($h, 9)); }
      }
    }
    if ($code>=300 && $code<400 && $loc){
      if (parse_url($loc, PHP_URL_SCHEME) === null){
        // relative redirect
        $p = parse_url($current);
        $base = $p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');
        if (substr($loc,0,1) !== '/') { 
          $path = isset($p['path']) ? preg_replace('#/[^/]*$#','/',$p['path']) : '/';
          $loc = $base.$path.$loc;
        } else {
          $loc = $base.$loc;
        }
      }
      $current = $loc;
      continue;
    }
    return [$code, $body, $body===false ? 'fopen error' : ''];
  }
  return [0, false, 'redirect loop'];
}

function looks_like_silhouette($bytes){
  if (!function_exists('imagecreatefromstring')) return false;
  if ($bytes===false || $bytes===null || strlen($bytes) < 2000) return true;
  $img = @imagecreatefromstring($bytes);
  if (!$img) return true;
  $w = imagesx($img); $h = imagesy($img);
  if ($w < 64 || $h < 64) { imagedestroy($img); return true; }
  $samples = 0; $sum=0; $sum2=0;
  for ($y=0;$y<$h;$y+=max(1,(int)($h/16))){
    for ($x=0;$x<$w;$x+=max(1,(int)($w/16))){
      $rgb = imagecolorat($img,$x,$y);
      $r = ($rgb>>16) & 0xFF; $g = ($rgb>>8)&0xFF; $b=$rgb&0xFF;
      $lum = ($r*0.299 + $g*0.587 + $b*0.114);
      $sum += $lum; $sum2 += $lum*$lum; $samples++;
    }
  }
  imagedestroy($img);
  if ($samples<20) return true;
  $mean = $sum/$samples;
  $var = ($sum2/$samples) - ($mean*$mean);
  return ($var < 120 && $mean > 70 && $mean < 210);
}

$BASE = dirname(__DIR__);
$uploads = $BASE . '/data/uploads/';
$headshotsAbbr = $uploads . 'headshots/';

$destMode = isset($_GET['dest']) ? strtolower($_GET['dest']) : 'flat'; // default flat
$flatDir = isset($_GET['flat_dir']) ? trim($_GET['flat_dir']) : 'assets/img/mugs';
$flatDir = trim($flatDir, "/\\");
$flatBase = $BASE . '/' . $flatDir . '/';

$dry       = isset($_GET['dry']) ? (int)$_GET['dry'] : 0;
$limit     = isset($_GET['limit']) ? max(0,(int)$_GET['limit']) : 0;
$missLimit = isset($_GET['miss_limit']) ? max(0,(int)$_GET['miss_limit']) : 0;
$force     = isset($_GET['force']) ? (int)$_GET['force'] : 0;
$season    = isset($_GET['season']) ? preg_replace('/\D+/','',$_GET['season']) : season_slug();
$onlyP     = isset($_GET['players']) ? (int)$_GET['players'] : 0;
$onlyG     = isset($_GET['goalies']) ? (int)$_GET['goalies'] : 0;
$insecure  = isset($_GET['insecure']) ? (int)$_GET['insecure'] : 0;
$use_fopen = isset($_GET['use_fopen']) ? (int)$_GET['use_fopen'] : 0;
$selftest  = isset($_GET['selftest']) ? (int)$_GET['selftest'] : 0;
$verbose   = isset($_GET['verbose']) ? (int)$_GET['verbose'] : 0;
$report    = isset($_GET['report']) ? (int)$_GET['report'] : 0;
$prefer    = isset($_GET['prefer']) ? strtolower($_GET['prefer']) : 'cms';

// sources override + relax_tls
$sourcesQ  = isset($_GET['sources']) ? strtolower(trim($_GET['sources'])) : '';
$srcOrder = [];
if ($sourcesQ){
  foreach (explode(',', $sourcesQ) as $tok){
    $tok = trim($tok);
    if ($tok==='cms' || $tok==='mugs') $srcOrder[] = $tok;
  }
} else {
  $srcOrder = ['cms','mugs'];
}
$win = strtoupper(substr(PHP_OS,0,3)) === 'WIN';
$relaxDefault = $win ? 1 : 0;
$relaxTls = isset($_GET['relax_tls']) ? (int)$_GET['relax_tls'] : $relaxDefault;
if ($relaxTls){
  if (!$use_fopen) $use_fopen = 1;
  if (!$insecure)  $insecure  = 1;
  out("Note: relax_tls=1 → using fopen + insecure TLS (manual redirects enabled) to avoid Windows CA bundle issues");
}

out("UHA Headshot Cache Builder — v0.7.6 (dest={$destMode}, flat_dir={$flatDir}, sources=".implode('+',$srcOrder).", relax_tls={$relaxTls})");
out("Portal base: $BASE");
out("Uploads: $uploads");
out("Abbr/Num headshots: $headshotsAbbr");
out("Flat headshots: $flatBase");

if (!is_dir($uploads)){ out("ERROR: uploads dir does not exist. Expected $uploads"); exit; }
if ($destMode==='flat'){
  if (!is_dir($flatBase) && !@mkdir($flatBase,0777,true)){ out("ERROR: cannot create $flatBase"); exit; }
} else {
  if (!is_dir($headshotsAbbr) && !@mkdir($headshotsAbbr,0777,true)){ out("ERROR: cannot create $headshotsAbbr"); exit; }
}

$playersFile = null;
foreach (['UHA-V3Players.csv', 'UHA-Players.csv'] as $cand) { if (is_readable($uploads.$cand)) { $playersFile = $uploads.$cand; break; } }
$goaliesFile = null;
foreach (['UHA-V3Goalies.csv', 'UHA-Goalies.csv'] as $cand) { if (is_readable($uploads.$cand)) { $goaliesFile = $uploads.$cand; break; } }

out("Players CSV: " . ($playersFile ?: '(none)'));
out("Goalies CSV: " . ($goaliesFile ?: '(none)'));

if ($selftest){
  $testId = '8478402'; $testTeam = 'EDM';
  $mugs = "https://assets.nhle.com/mugs/nhl/{$season}/{$testTeam}/{$testId}.png";
  $cms  = "https://cms.nhl.bamgrid.com/images/headshots/current/168x168/{$testId}.jpg";
  out("Selftest:");
  list($c1,$b1,) = http_get($cms,$insecure,$use_fopen,12); out("CMS: HTTP $c1 bytes=".(is_string($b1)?strlen($b1):0));
  list($c2,$b2,) = http_get($mugs,$insecure,$use_fopen,12); out("MUGS: HTTP $c2 bytes=".(is_string($b2)?strlen($b2):0));
  exit;
}

// Build unique IDs
$aliases = [
  'name'  => ['name','player','playername','fullname','skater','goalie','lastnamefirstname','firstnameandlastname','fname_lname','lastname_firstname','playerfullname'],
  'team'  => ['team','abbre','abbr','teamabbr','teamabbre','teamid','teamnumber','proteam','proteamid','proteamabbr','tm'],
  'url'   => ['urllink','url','link','photo','headshot','headshoturl','image'],
  'teamnum' => ['teamnumber','teamid','teamno','teamnum']
];
$rows = [];
if (!$onlyG && $playersFile) $rows = array_merge($rows, parse_csv_assoc($playersFile));
if (!$onlyP && $goaliesFile) $rows = array_merge($rows, parse_csv_assoc($goaliesFile));
$totalRows = count($rows);
if ($totalRows===0){ out("Nothing to do. Make sure your CSVs live under /data/uploads/"); exit; }
$unique = [];
$scanCount=0;
foreach ($rows as $r){
  if ($limit && $scanCount >= $limit) break;
  $scanCount++;
  $id = extract_id((string) value_of($r,'url',$aliases,''));
  if (!$id) continue;
  if (!isset($unique[$id])){
    $unique[$id] = [
      'team' => strtoupper((string) value_of($r,'team',$aliases,'')) ?: 'UNK',
      'teamnum' => (string) value_of($r,'teamnum',$aliases,''),
      'name' => (string) value_of($r,'name',$aliases,'')
    ];
  }
}
$totalUnique = count($unique);
out("Unique IDs: $totalUnique");

// Index existing files (abbr/num and flat) — guarded
$existingById = []; // id => true
if (is_dir($headshotsAbbr)){
  $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($headshotsAbbr, FilesystemIterator::SKIP_DOTS));
  foreach ($rii as $file){
    if (!$file->isFile()) continue;
    $rel = str_replace('\\','/', substr($file->getPathname(), strlen($BASE)+1));
    if (preg_match('#data/uploads/headshots/([A-Za-z0-9]+)/(\d{6,8})\.png$#', $rel, $m)){
      $existingById[$m[2]] = true;
    }
  }
}
if (is_dir($flatBase)){
  $rii2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($flatBase, FilesystemIterator::SKIP_DOTS));
  foreach ($rii2 as $file){
    if (!$file->isFile()) continue;
    $rel = str_replace('\\','/', substr($file->getPathname(), strlen($BASE)+1));
    if (preg_match('#'.preg_quote($flatDir,'#').'/(\d{6,8})\.png$#', $rel, $m)){
      $existingById[$m[1]] = true;
    }
  }
}

$done=0;$skipped=0;$errors=0; $missProcessed=0; $processed=0;
foreach ($unique as $id=>$info){
  $abbr = $info['team']; $num=$info['teamnum']; $name=$info['name'];
  $destRel = ($destMode==='flat')
    ? "{$flatDir}/{$id}.png"
    : ("data/uploads/headshots/".(($destMode==='num' && $num!=='')?$num:$abbr)."/{$id}.png");
  $destAbs = $BASE . '/' . $destRel;
  $exists = is_file($destAbs) || isset($existingById[$id]); // skip if we already have this id anywhere unless force

  if ($exists && !$force){ $skipped++; out(sprintf("[%-5d/%-5d] skip  exist  | %s", $processed+1, $totalUnique, $destRel)); $processed++; continue; }
  if ($missLimit && !$exists && $missProcessed >= $missLimit){
    out(sprintf("[%-5d/%-5d] stop  miss_limit reached (%d)", $processed+1, $totalUnique, $missLimit));
    break;
  }

  $mugs = "https://assets.nhle.com/mugs/nhl/{$season}/{$abbr}/{$id}.png";
  $cms  = "https://cms.nhl.bamgrid.com/images/headshots/current/168x168/{$id}.jpg";

  if ($dry){
    $done++; if(!$exists) $missProcessed++;
    out(sprintf("[%-5d/%-5d] GET   DRY   | %s -> %s", $processed+1, $totalUnique, $id, $destRel));
    $processed++; continue;
  }

  $bytes = null; $final=''; $http=0;

  foreach ($srcOrder as $src){
    $url = ($src==='cms') ? $cms : $mugs;
    list($code,$body,$err) = http_get($url, $insecure, $use_fopen, 18, 5);
    if ($code===200 && $body){
      if ($skipSil && $src==='mugs' && looks_like_silhouette($body)){
        if ($verbose) out(sprintf("[%-5d/%-5d] note  MUGS silhouette -> trying next | %s", $processed+1, $totalUnique, $id));
        continue;
      }
      $bytes = $body; $final=$src; $http=$code; break;
    } else if ($verbose){
      out(sprintf("[%-5d/%-5d] note  %s %s | %s", $processed+1, $totalUnique, strtoupper($src), $code, $id));
    }
  }

  if ($bytes){
    if (save_file($destAbs, $bytes)){
      $done++; if(!$exists) $missProcessed++;
      out(sprintf("[%-5d/%-5d] SAVE  %-4s | %s -> %s", $processed+1, $totalUnique, strtoupper($final), $id, $destRel));
    } else {
      $errors++; out(sprintf("[%-5d/%-5d] ERR   write | %s -> %s", $processed+1, $totalUnique, $id, $destRel));
    }
  } else {
    $errors++; out(sprintf("[%-5d/%-5d] ERR   fetch | %s -> %s", $processed+1, $totalUnique, $id, $destRel));
  }

  $processed++;
}

out(str_repeat("-", 64));
out("Summary: downloaded=$done, skipped=$skipped, errors=$errors");
out("Tip: Use ?dest=flat&flat_dir=assets/img/mugs. If you see MUGS 302, v0.7.6 will now follow redirects in fopen mode.");
