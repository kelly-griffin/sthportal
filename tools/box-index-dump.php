<?php
// tools/box-index-dump.php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root   = dirname(__DIR__);
$boxDir = $root . '/data/uploads/json-boxscores';
$teams  = json_decode((string)@file_get_contents($root.'/assets/json/teams.json'), true);

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
    if (strncmp($buf, "\xEF\xBB\xBF", 3) === 0) $buf = substr($buf,3);
    if (strpos(substr($buf,0,64), "\x00") !== false) {
      $buf = @mb_convert_encoding($buf, 'UTF-8', 'UTF-16,UTF-16LE,UTF-16BE');
    }
    $j = json_decode((string)$buf, true);
    if (is_array($j)) return $j;
  }
  return null;
}
function fold(string $s): string {
  $s = trim($s);
  if (class_exists('Transliterator')) {
    $tr = Transliterator::create('Any-Latin; Latin-ASCII');
    if ($tr) $s = $tr->transliterate($s);
  } else {
    $c = @iconv('UTF-8','ASCII//TRANSLIT',$s);
    if ($c !== false) $s = $c;
  }
  $s = mb_strtolower($s);
  return preg_replace('/[^a-z0-9]+/','',$s);
}
$nameToId = [];
$idToAbbr = [];
if (is_array($teams) && isset($teams['teams'])) {
  foreach ($teams['teams'] as $t) {
    $id = (string)$t['id'];
    $abbr = (string)($t['abbr'] ?? '');
    $idToAbbr[$id] = $abbr;
    $keys = [
      (string)$t['name'],
      (string)($t['shortName'] ?? $t['name']),
      $abbr
    ];
    foreach ($keys as $k) {
      if ($k === '') continue;
      $nameToId[fold($k)] = $id;
    }
  }
}

$pairs = [];
if (is_dir($boxDir)) {
  foreach (glob($boxDir.'/UHA-*.json') as $jf) {
    $base = basename($jf, '.json').'.html';
    $j = readJsonFlexible($jf);
    if (!is_array($j)) continue;

    // prefer abbr from JSON if present
    $vAb = (string)($j['visitor']['abbr'] ?? '');
    $hAb = (string)($j['home']['abbr'] ?? '');
    if ($vAb === '' || $hAb === '') {
      $vName = fold((string)($j['visitor']['name'] ?? ''));
      $hName = fold((string)($j['home']['name'] ?? ''));
      $vId = $nameToId[$vName] ?? null;
      $hId = $nameToId[$hName] ?? null;
      $vAb = ($vId && isset($idToAbbr[$vId])) ? $idToAbbr[$vId] : '';
      $hAb = ($hId && isset($idToAbbr[$hId])) ? $idToAbbr[$hId] : '';
    }
    if ($vAb === '' || $hAb === '') continue;

    $key = $vAb.'@'.$hAb;
    $pairs[$key][] = $base;
  }
}

echo "<h2>Boxscore index pairs</h2>";
if (empty($pairs)) {
  echo "<p><b>No pairs found.</b> That means team-name mapping failed (likely accents/naming) or no JSONs built.</p>";
} else {
  echo "<table border='1' cellpadding='6' cellspacing='0'><tr><th>Pair</th><th>Files</th></tr>";
  foreach ($pairs as $pair => $files) {
    echo "<tr><td>".htmlspecialchars($pair)."</td><td>".htmlspecialchars(implode(', ',$files))."</td></tr>";
  }
  echo "</table>";
}
