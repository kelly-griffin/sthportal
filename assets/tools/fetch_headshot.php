<?php
/**
 * tools/fetch_headshot.php
 * Fetch exactly ONE player image and save it to the local cache.
 * Example:
 *   /tools/fetch_headshot.php?id=8471214&team=WSH&teamnum=2&prefer=mugs
 */
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '256M');
header('Content-Type: text/plain; charset=utf-8');

function season_slug($now=null){
  $t = $now ?: time();
  $y = (int)date('Y', $t);
  $m = (int)date('n', $t);
  $start = ($m >= 7) ? $y : ($y - 1);
  $end = $start + 1;
  return sprintf('%d%d', $start, $end);
}
function http_get($url, $insecure=false, $use_fopen=false, $timeout=25){
  if (!$use_fopen && function_exists('curl_init')){
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_USERAGENT => 'UHA-Portal/1.0',
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_SSL_VERIFYPEER => $insecure ? false : true,
      CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
      CURLOPT_HTTPHEADER => ['Accept: */*'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
  }
  $ctx = stream_context_create([
    'http' => ['method'=>'GET', 'timeout'=>$timeout, 'header'=>"User-Agent: UHA-Portal/1.0\r\n"],
    'ssl' => ['verify_peer' => !$insecure, 'verify_peer_name' => !$insecure]
  ]);
  $body = @file_get_contents($url, false, $ctx);
  $code = 0;
  if (isset($http_response_header) && is_array($http_response_header)){
    foreach ($http_response_header as $h){
      if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) { $code = (int)$m[1]; break; }
    }
  }
  return [$code, $body];
}
function save_file($path,$bytes){
  $dir = dirname($path);
  if(!is_dir($dir)) @mkdir($dir,0777,true);
  return file_put_contents($path, $bytes) !== false;
}

$BASE = dirname(__DIR__);
$uploads = $BASE . '/data/uploads/';
$outBase = $uploads . 'headshots/';

$id = isset($_GET['id']) ? preg_replace('/\D+/','',$_GET['id']) : '';
$team = isset($_GET['team']) ? strtoupper(preg_replace('/[^A-Z]/','',$_GET['team'])) : '';
$teamnum = isset($_GET['teamnum']) ? preg_replace('/\D+/','',$_GET['teamnum']) : '';
$season = isset($_GET['season']) ? preg_replace('/\D+/','',$_GET['season']) : season_slug();
$prefer = isset($_GET['prefer']) ? strtolower($_GET['prefer']) : 'mugs'; // default to mugs for one-offs
$insecure = isset($_GET['insecure']) ? (int)$_GET['insecure'] : 0;
$use_fopen = isset($_GET['use_fopen']) ? (int)$_GET['use_fopen'] : 0;

if (!$id){ echo "ERR: id is required\n"; exit; }
if (!$team && !$teamnum){ echo "ERR: team or teamnum is required\n"; exit; }

$destRel = "headshots/".(($teamnum!=='')?$teamnum:$team)."/{$id}.png";
$destAbs = $uploads . $destRel;

$mugs = "https://assets.nhle.com/mugs/nhl/{$season}/{$team}/{$id}.png";
$cms  = "https://cms.nhl.bamgrid.com/images/headshots/current/168x168/{$id}.jpg";

$order = ($prefer==='cms') ? ['cms','mugs'] : ['mugs','cms'];
$code=0; $bytes=''; $src='';

foreach ($order as $s){
  $url = ($s==='cms') ? $cms : $mugs;
  list($c,$b) = http_get($url, $insecure, $use_fopen, 18);
  if ($c===200 && $b){ $code=$c; $bytes=$b; $src=$s; break; }
}

if (!$bytes){ echo "ERR: both sources failed. Try &use_fopen=1&insecure=1\n"; exit; }

if (save_file($destAbs,$bytes)){
  echo "OK: saved from " . strtoupper($src) . " to /data/uploads/{$destRel}\n";
} else {
  echo "ERR: could not write to /data/uploads/{$destRel}\n";
}
