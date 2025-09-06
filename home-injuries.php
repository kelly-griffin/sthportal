<?php
// home-injuries.php — data-only payload for the Home → Injuries rail
// Emits: window.UHA.injuries = { pro:{ TEAM:[{player,detail,timeline,date}] }, farm:{} }
// No DOM. HEX-safe JSON. Dispatches UHA:injuries-ready.

declare(strict_types=1);

/* ---------------- SIM DATE ---------------- */
$simDate = '';
foreach (['SIM_DATE','simDate','sim_date'] as $k) {
  if (isset(${$k}) && is_string(${$k}) && ${$k} !== '') { $simDate = ${$k}; break; }
}
if ($simDate === '') $simDate = date('Y-m-d');

/* ---------------- TEAM MAP ---------------- */
$TEAM_ABBR = [
  'Anaheim Ducks'=>'ANA','Arizona Coyotes'=>'ARI','Boston Bruins'=>'BOS','Buffalo Sabres'=>'BUF',
  'Calgary Flames'=>'CGY','Carolina Hurricanes'=>'CAR','Chicago Blackhawks'=>'CHI','Colorado Avalanche'=>'COL',
  'Columbus Blue Jackets'=>'CBJ','Dallas Stars'=>'DAL','Detroit Red Wings'=>'DET','Edmonton Oilers'=>'EDM',
  'Florida Panthers'=>'FLA','Los Angeles Kings'=>'LAK','Minnesota Wild'=>'MIN','Montreal Canadiens'=>'MTL',
  'Nashville Predators'=>'NSH','New Jersey Devils'=>'NJD','New York Islanders'=>'NYI','New York Rangers'=>'NYR',
  'Ottawa Senators'=>'OTT','Philadelphia Flyers'=>'PHI','Pittsburgh Penguins'=>'PIT','San Jose Sharks'=>'SJS',
  'Seattle Kraken'=>'SEA','St. Louis Blues'=>'STL','Tampa Bay Lightning'=>'TBL','Toronto Maple Leafs'=>'TOR',
  'Vancouver Canucks'=>'VAN','Vegas Golden Knights'=>'VGK','Washington Capitals'=>'WSH','Winnipeg Jets'=>'WPG',
];

/* ---------------- PATH DISCOVERY ---------------- */
$root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
$candidates = [
  $root . '/data/uploads',
  dirname(__DIR__) . '/data/uploads',
  __DIR__ . '/../data/uploads',
  __DIR__ . '/data/uploads',
];
$scanDirs = [];
foreach ($candidates as $p) { $rp = realpath($p); if ($rp && is_dir($rp)) $scanDirs[$rp] = true; }
$scanDirs = array_keys($scanDirs);

/* ---------------- FILE LIST ---------------- */
$files = [];
foreach ($scanDirs as $dir) {
  foreach (['*.html','*.htm','*.HTML','*.HTM','*.txt','*.TXT','*.csv','*.CSV'] as $pat) {
    $hits = glob($dir.'/'.$pat);
    if ($hits) $files = array_merge($files, $hits);
  }
}
$files = array_values(array_unique($files));
usort($files, static fn($a,$b) => (filemtime($b) <=> filemtime($a)));

/* ---------------- NORMALIZE + REGEX ---------------- */
$normalize = static function(string $htmlOrLine): string {
  $t = strip_tags($htmlOrLine);
  $t = str_replace(["\xc2\xa0","&nbsp;"], ' ', $t); // nbsp
  $t = str_replace(["–","—"], "-", $t);             // fancy dashes
  $t = preg_replace('/\s+/u',' ', $t);
  return trim($t);
};

// Matches your samples exactly, tolerant to spacing/dash variants:
$injurySentenceRe = '/
  Game \s* \d+ \s* [\-] \s*
  (?P<player>.+?) \s+ from \s+ (?P<team>.+?) \s+ is \s+ injured
  \s* \( \s* (?P<detail>[^)]+?) \s* \) \s*
  (?:and \s+)? is \s+ out \s+ for \s+ (?P<timeline>[^.]+?) \s* \.
/ix';

/* ---------------- PARSE ALL FILES ---------------- */
$rows = [];

foreach ($files as $path) {
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  $raw = @file_get_contents($path);
  if ($raw === false || $raw === '') continue;

  // Pull candidate sentences depending on type
  $snips = [];
  if ($ext === 'csv') {
    // Scan each CSV row as a line of text
    $fh = @fopen($path, 'r');
    if ($fh) {
      while (($cols = fgetcsv($fh)) !== false) {
        $line = $normalize(implode(' ', array_map('strval', $cols)));
        if (stripos($line, 'is injured') !== false && stripos($line, 'out for') !== false) {
          $snips[] = $line;
        }
      }
      fclose($fh);
    }
  } else {
    // html/htm/txt
    $text = $normalize($raw);
    if (preg_match_all('/Game\s*\d+\s*[\-]\s*.*?is\s+injured\s*\(.*?\)\s*.*?out\s+for\s*.*?\./i', $text, $m)) {
      $snips = $m[0];
    }
  }

  // Parse each candidate sentence
  foreach ($snips as $line) {
    $line = $normalize($line);
    if (!preg_match($injurySentenceRe, $line, $m)) continue;

    $detail = trim($m['detail'] ?? '');
    if ($detail !== '' && preg_match('/\bexhaust(?:ion|ed|ing)?\b/i', $detail)) continue; // skip Exhaustion

    $teamName = trim($m['team'] ?? '');
    $abbr = $TEAM_ABBR[$teamName] ?? '';
    if ($abbr === '') {
      foreach ($TEAM_ABBR as $name => $code) { if (strcasecmp($name, $teamName) === 0) { $abbr = $code; break; } }
      if ($abbr === '') continue; // cannot place without abbr
    }

    $rows[] = [
      'abbr'     => $abbr,
      'player'   => trim($m['player'] ?? ''),
      'detail'   => $detail,                          // e.g., "Right Foot"
      'timeline' => trim($m['timeline'] ?? ''),       // e.g., "1 month"
      'date'     => $simDate,
    ];
  }
}

/* ---------------- GROUP + DEDUPE ---------------- */
$pro = [];
if ($rows) {
  $seen = [];
  foreach ($rows as $r) {
    $k = $r['abbr'].'|'.$r['player'].'|'.$r['date'].'|'.$r['detail'].'|'.$r['timeline'];
    if (isset($seen[$k])) continue;
    $seen[$k] = true;
    $pro[$r['abbr']][] = [
      'player'   => $r['player'],
      'detail'   => $r['detail'],
      'timeline' => $r['timeline'],
      'date'     => $r['date'],
    ];
  }
}

/* ---------------- EMIT ---------------- */
$payload = ['pro' => $pro ?: new stdClass(), 'farm' => new stdClass()];
$flags = JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;
?>
<script>
(function(U){U=window.UHA=window.UHA||{};U.injuries=<?=json_encode($payload,$flags)?>;
document.dispatchEvent(new CustomEvent('UHA:injuries-ready'));})(window.UHA);
</script>
