<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root   = dirname(__DIR__);
$boxDir = $root . '/data/uploads/boxscores';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function listUhaFiles(string $dir): array {
  $out = [];
  foreach (glob($dir . '/UHA-*.json') ?: [] as $p) {
    $out[] = basename($p);
  }
  sort($out, SORT_NATURAL);
  return $out;
}

// Strictly allow only UHA-#.json
function sanitizeFile(?string $f): ?string {
  if ($f === null) return null;
  $f = basename($f);
  if (preg_match('/^UHA-[0-9]+\.json$/i', $f)) return $f;
  return null;
}

// ---------- header ----------
echo "<h2>Peek Boxscore JSON</h2>";
echo "<p>Looking in: <b>".h($boxDir)."</b></p>";

$files = listUhaFiles($boxDir);
if (!$files) {
  echo "<p><b>No UHA-*.json files found in that folder.</b></p>";
  exit;
}

// links
echo "<details open><summary>Files found (click to inspect)</summary>";
echo "<ul>";
foreach ($files as $bn) {
  $href = 'peek-box.php?file=' . rawurlencode($bn);
  echo "<li><a href='".h($href)."'>".h($bn)."</a> (".filesize($boxDir.'/'.$bn)." bytes)</li>";
}
echo "</ul></details>";

// fallback form (works even if query strings are stripped)
echo "<form method='post' style='margin:10px 0; padding:8px; background:#f6f6f6; border:1px solid #ddd; display:inline-block'>";
echo "<label>Inspect: <select name='file'>";
foreach ($files as $bn) echo "<option value='".h($bn)."'>".h($bn)."</option>";
echo "</select></label> <button type='submit'>Open</button>";
echo "</form>";

// accept GET or POST
$param = $_GET['file'] ?? $_POST['file'] ?? null;
$file  = sanitizeFile($param);

if ($param !== null && $file === null) {
  echo "<p style='color:#b00'><b>Refused:</b> Only filenames like <code>UHA-123.json</code> are allowed.</p>";
}
if ($file === null) {
  echo "<p>Select a file above to inspect.</p>";
  exit;
}

$path = $boxDir . '/' . $file;

// ---------- decode with deep debug ----------
function readJsonFlexibleDebug(string $path, array &$debug) {
  $debug['exists']   = file_exists($path) ? 'yes' : 'no';
  $debug['is_file']  = is_file($path) ? 'yes' : 'no';
  $debug['realpath'] = realpath($path) ?: '(none)';
  if (!is_file($path)) return [null, 'missing'];

  $raw = @file_get_contents($path);
  $debug['filesize'] = $raw === false ? -1 : strlen((string)$raw);
  $debug['first_bytes_hex'] = '(unread)';
  if ($raw !== false) {
    $debug['first_bytes_hex'] = strtoupper(
      implode(' ', array_map(fn($c)=>str_pad(dechex(ord($c)),2,'0',STR_PAD_LEFT), str_split(substr($raw,0,16))))
    );
  }

  $candidates = [];
  if ($raw !== false) {
    $candidates[] = ['how'=>'plain','buf'=>$raw];
    if (strlen($raw) >= 2 && $raw[0] === "\x1f" && $raw[1] === "\x8b") {
      $d = function_exists('gzdecode') ? @gzdecode($raw) : @gzinflate(substr($raw,10));
      if ($d !== false && $d !== null) $candidates[] = ['how'=>'gzdecode','buf'=>$d];
    }
  }
  $z = @file_get_contents('compress.zlib://' . $path);
  if ($z !== false) $candidates[] = ['how'=>'compress.zlib','buf'=>$z];

  foreach ($candidates as $cand) {
    $buf = $cand['buf'];
    if (strncmp($buf, "\xEF\xBB\xBF", 3) === 0) $buf = substr($buf,3);
    if (strpos(substr($buf,0,64), "\x00") !== false) {
      $buf = @mb_convert_encoding($buf, 'UTF-8', 'UTF-16,UTF-16LE,UTF-16BE');
    }
    $j = json_decode((string)$buf, true);
    if (is_array($j)) return [$j, 'ok:'.$cand['how']];
  }
  $debug['raw_preview'] = is_string($raw) ? substr($raw, 0, 400) : '(no raw)';
  return [null, 'decode_failed'];
}

function getp($a, array $path, $def=null) {
  $x=$a; foreach($path as $k){ if(!is_array($x)||!array_key_exists($k,$x)) return $def; $x=$x[$k]; } return $x;
}
function extractTeamsProbe(array $j): array {
  $paths = [
    'visitor.name' => ['visitor','name'],
    'visitor.abbr' => ['visitor','abbr'],
    'home.name'    => ['home','name'],
    'home.abbr'    => ['home','abbr'],
    'teams.away.team.name'  => ['teams','away','team','name'],
    'teams.away.team.abbreviation' => ['teams','away','team','abbreviation'],
    'teams.home.team.name'  => ['teams','home','team','name'],
    'teams.home.team.abbreviation' => ['teams','home','team','abbreviation'],
    'awayTeam' => ['awayTeam'],
    'homeTeam' => ['homeTeam'],
    'awayAbbr' => ['awayAbbr'],
    'homeAbbr' => ['homeAbbr'],
    'date'     => ['date'],
    'gameDate' => ['gameDate'],
  ];
  $out = [];
  foreach ($paths as $label=>$p) $out[$label] = getp($j,$p);
  return $out;
}

$debug = ['path'=>$path];
[$j,$how] = readJsonFlexibleDebug($path, $debug);

// ---------- render ----------
echo "<hr>";
echo "<h3>File: ".h($file)."</h3>";
echo "<table border='1' cellpadding='6' cellspacing='0'>";
foreach ($debug as $k=>$v) echo "<tr><th align='left'>".h($k)."</th><td><code>".h(is_scalar($v)?$v:json_encode($v))."</code></td></tr>";
echo "<tr><th align='left'>status</th><td><b>".h($how)."</b></td></tr>";
echo "</table>";

if (!is_array($j)) {
  echo "<p><b>Could not decode JSON.</b></p>";
  if (!empty($debug['raw_preview'])) {
    echo "<h4>Raw preview (first 400 chars)</h4><pre>".h($debug['raw_preview'])."</pre>";
  }
  exit;
}

echo "<h4>Top-level keys</h4><pre>".h(implode(', ', array_keys($j)))."</pre>";

$probe = extractTeamsProbe($j);
echo "<h4>Common fields probe</h4><table border='1' cellpadding='6' cellspacing='0'><tr><th>Field</th><th>Value</th></tr>";
foreach ($probe as $label=>$val) {
  $disp = is_scalar($val) ? $val : json_encode($val);
  echo "<tr><td>".h($label)."</td><td>".h($disp)."</td></tr>";
}
echo "</table>";

echo "<h4>Raw JSON (pretty)</h4><pre>".h(json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))."</pre>";
