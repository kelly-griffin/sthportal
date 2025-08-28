<?php
// tools/harvest-outcomes-from-html.php
// Extract **names only** for GWG + Winning Goalie straight from UHA HTML.
// - Reads data/uploads/UHA-*.html (skips Farm).
// - For each game writes/updates data/uploads/boxscores/UHA-*.json with:
//     { "gwg": "Scorer Name[ (SO)]", "winningGoalie": "Goalie Name" }
// Run preview:  /sthportal/tools/harvest-outcomes-from-html.php
// Save:         /sthportal/tools/harvest-outcomes-from-html.php?write=1
// CLI:          php tools/harvest-outcomes-from-html.php --write

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root    = dirname(__DIR__);
$htmlDir = $root . '/data/uploads';
$jsonDir = $root . '/data/uploads/boxscores';
$teamsP  = $root . '/data/uploads/teams.json';

$WRITE = (isset($_GET['write']) && $_GET['write'] === '1')
      || (PHP_SAPI === 'cli' && in_array('--write', $argv, true));

/* ---------------- helpers ---------------- */

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function just_name(string $s): string { return trim(preg_replace('~\s*\([A-Z]{2,3}\)\s*$~','',$s)); }

function norm(string $s): string {
  $s = trim($s);
  if (class_exists('Transliterator')) {
    $tr = \Transliterator::create('Any-Latin; Latin-ASCII');
    if ($tr) $s = $tr->transliterate($s);
  } else {
    $x = @iconv('UTF-8','ASCII//TRANSLIT',$s);
    if ($x !== false) $s = $x;
  }
  $s = mb_strtolower($s);
  $s = preg_replace('/[^a-z0-9]+/',' ', $s);
  return trim($s);
}

function html_to_text(string $html): string {
  // Keep <br> as line breaks to simplify goal parsing
  $html = preg_replace('~<br\s*/?>~i', "\n", $html);
  $html = preg_replace('~<script\b[^>]*>.*?</script>~is', ' ', $html);
  $html = preg_replace('~<style\b[^>]*>.*?</style>~is', ' ', $html);
  $text = strip_tags($html);
  $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  // normalize whitespace/newlines
  $text = preg_replace('/\r\n|\r|\n/u', "\n", $text);
  $text = preg_replace('/[ \t\x0B\p{Zs}]+/u', ' ', $text);
  $text = preg_replace('/\n{2,}/u', "\n", $text);
  return trim($text);
}

function loadTeamsMap(string $teamsP): array {
  $map = ['abbr2name'=>[], 'name2abbr'=>[]];
  if (!is_file($teamsP)) return $map;
  $j = json_decode((string)@file_get_contents($teamsP), true);
  if (!is_array($j)) return $map;
  foreach (($j['teams'] ?? []) as $t) {
    $name = (string)($t['name'] ?? '');
    $abbr = (string)($t['abbr'] ?? '');
    if ($name && $abbr) {
      $map['abbr2name'][$abbr] = $name;
      $map['name2abbr'][norm($name)] = $abbr;
      if (!empty($t['shortName'])) $map['name2abbr'][norm((string)$t['shortName'])] = $abbr;
    }
  }
  return $map;
}

/* <title>UHA - Game 1 - Chicago Blackhawks vs Florida Panthers</title> */
function parse_title(string $html): array {
  if (preg_match('~<title>\s*UHA\s*-\s*Game\s*(\d+)\s*-\s*(.+?)\s+vs\s+(.+?)\s*</title>~i', $html, $m)) {
    return [(int)$m[1], trim($m[2]), trim($m[3])];
  }
  return [0, '', ''];
}

/**
 * WINNING GOALIE (names only):
 *   Look inside each <div class="STHSGame_GoalerStats"> ... </div>.
 *   The block that contains a standalone 'W' is the winner.
 *   Return [goalieNameOnly, teamABBR].
 */
function parse_winning_goalie_from_html(string $rawHtml): array {
  $nameOnly = ''; $abbr = '';
  if (preg_match_all('~<div[^>]*class=["\']STHSGame_GoalerStats["\'][^>]*>(.*?)</div>~is', $rawHtml, $mm)) {
    foreach ($mm[1] as $chunkHtml) {
      $chunkText = html_to_text($chunkHtml);
      if (!preg_match('~\bW\b~', $chunkText)) continue;
      if (preg_match('~([A-Z][A-Za-z.\'\- ]{1,60})\s*\(([A-Z]{2,3})\)~u', $chunkText, $m2)) {
        $nameOnly = trim($m2[1]);
        $abbr     = $m2[2];
        break;
      }
    }
  }
  return [$nameOnly, $abbr];
}

/* collect goals chronologically: [ ['team'=>'Florida Panthers', 'scorer'=>'Sam Reinhart'], ... ] */
function parse_goals_names_only(string $text): array {
  $goals = [];
  // Lines like: "2. Florida Panthers , Sam Reinhart 2 (Carter Verhaeghe 2, Brad Marchand 2) at 15:02"
  $lines = preg_split('/\n/u', $text);
  foreach ($lines as $ln) {
    if (!preg_match('~^\s*\d+\.\s*([^,]+?)\s*,\s*([^\n]+?)\s+at\s+~u', $ln, $m)) continue;
    $team  = trim($m[1]);
    $who   = trim($m[2]);
    // 1) remove assists tail " ( ... )"
    $who = preg_replace('~\s*\(.*$~', '', $who);
    // 2) strip trailing digits after name (e.g., "Sam Reinhart 2" -> "Sam Reinhart")
    $who = preg_replace('~\s+\d+\b~', '', $who);
    // 3) remove any trailing "(ABR)" just in case
    $who = just_name($who);
    $goals[] = ['team'=>$team, 'scorer'=>trim($who)];
  }
  return $goals;
}

/**
 * SHOOTOUT DECIDER (names only):
 *   We use the simple rule: **the last "Goal!" in the shootout list is the winner**.
 *   Returns "Player Name" or "".
 */
function parse_shootout_decisive_name_only(string $rawHtml): string {
  $text = preg_replace('~<br\s*/?>~i', "\n", $rawHtml);
  $text = preg_replace('~<script\b[^>]*>.*?</script>~is',' ', $text);
  $text = preg_replace('~<style\b[^>]*>.*?</style>~is',' ', $text);
  $text = strip_tags($text);
  $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

  $lastGoal = null;
  foreach (preg_split('/\r\n|\r|\n/u', $text) as $ln) {
    if (preg_match('~^\s*([A-Za-z .\'\-]+?),\s*([A-Za-z.\'\- ]{2,60})\s*-\s*(Goal!)~u', $ln, $m)) {
      $lastGoal = ['team'=>trim($m[1]), 'player'=>trim($m[2])];
    }
  }
  return $lastGoal ? $lastGoal['player'] : '';
}

/* write JSON with backup */
function write_json_safe(string $path, array $data): bool {
  @mkdir(dirname($path), 0777, true);
  if (is_file($path)) @copy($path, $path.'.bak.'.date('Ymd_His'));
  return (bool)file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

/* ---------------- main ---------------- */

$teamsMap  = loadTeamsMap($teamsP);
$abbr2name = $teamsMap['abbr2name'];
$name2abbr = $teamsMap['name2abbr'];

$files = glob($htmlDir.'/UHA-*.html');
natsort($files);

$rows = [];
$updated = 0; $skipped = 0;

foreach ($files as $htmlPath) {
  $base = basename($htmlPath, '.html');
  if (stripos($base, 'Farm') !== false) { $rows[] = [$base, 'SKIP_FARM', '', '']; continue; }

  $raw  = @file_get_contents($htmlPath);
  if ($raw === false) { $rows[] = [$base, 'HTML_READ_FAIL', '', '']; $skipped++; continue; }

  [$gameNo, $leftName, $rightName] = parse_title($raw);
  $text = html_to_text($raw);

  // Winning goalie (names only)
  [$winnerGoalieName, $winAbbr] = parse_winning_goalie_from_html($raw);
  if ($winnerGoalieName === '' || $winAbbr === '') {
    $rows[] = [$base, 'NO_WINNER_FOUND', '', ''];
    $skipped++; continue;
  }
  $winTeam = $abbr2name[$winAbbr] ?? '';
  if ($winTeam === '') {
    if (isset($name2abbr[norm($leftName)]) && $name2abbr[norm($leftName)] === $winAbbr) $winTeam = $leftName;
    if (isset($name2abbr[norm($rightName)]) && $name2abbr[norm($rightName)] === $winAbbr) $winTeam = $rightName;
  }

  // Losing team abbr (grab the 'L' goalie block, if present) — optional
  $loseAbbr = '';
  if (preg_match_all('~<div[^>]*class=["\']STHSGame_GoalerStats["\'][^>]*>(.*?)</div>~is', $raw, $blocks)) {
    foreach ($blocks[1] as $chunkHtml) {
      $chunkText = html_to_text($chunkHtml);
      if (preg_match('~\bL\b~', $chunkText) && preg_match('~\(([A-Z]{2,3})\)~', $chunkText, $mL)) {
        $loseAbbr = $mL[1]; break;
      }
    }
  }
  $loseTeam = $abbr2name[$loseAbbr] ?? '';

  // Parse goals (reg/OT) -> names only
  $goals = parse_goals_names_only($text);
  if (empty($goals) || $winTeam === '') {
    $rows[] = [$base, 'INSUFFICIENT_DATA', '', $winnerGoalieName];
    $skipped++; continue;
  }

  // If we couldn't identify losing team from L-block, infer by not-the-winner in title
  if ($loseTeam === '') {
    $tL = norm($leftName); $tR = norm($rightName);
    if ($winTeam && norm($winTeam) === $tL) $loseTeam = $rightName;
    elseif ($winTeam && norm($winTeam) === $tR) $loseTeam = $leftName;
  }

  // Count opponent final goals (reg/OT)
  $loseFinal = 0;
  foreach ($goals as $g) if (norm($g['team']) === norm($loseTeam)) $loseFinal++;

  // GWG = (loseFinal + 1)-th goal by the winning team (reg/OT)
  $need = $loseFinal + 1;
  $countWin = 0;
  $gwgScorer = '';
  foreach ($goals as $g) {
    if (norm($g['team']) === norm($winTeam)) {
      $countWin++;
      if ($countWin === $need) { $gwgScorer = $g['scorer']; break; }
    }
  }

  // SHOOTOUT fallback: if reg/OT GWG not found, take last shootout "Goal!" scorer (name only)
  $wasSO = false;
  if ($gwgScorer === '') {
    $so = parse_shootout_decisive_name_only($raw);
    if ($so !== '') { $gwgScorer = $so; $wasSO = true; }
  }

  if ($gwgScorer === '') {
    $rows[] = [$base, 'GWG_NOT_FOUND', '', $winnerGoalieName];
    $skipped++; continue;
  }

  // Save string for GWG (append (SO) if it came from the shootout)
  $gwgToSave = $gwgScorer . ($wasSO ? ' (SO)' : '');

  // Update JSON (write **names only**)
  $jsonPath = $jsonDir . '/' . $base . '.json';
  $j = [];
  if (is_file($jsonPath)) {
    $buf = @file_get_contents($jsonPath);
    $dec = $buf !== false ? json_decode((string)$buf, true) : null;
    if (is_array($dec)) $j = $dec;
  }
  $beforeG = trim((string)($j['gwg'] ?? ''));
  $beforeW = trim((string)($j['winningGoalie'] ?? ''));

  $j['gwg'] = $gwgToSave;                 // e.g., "Sam Reinhart" or "Tomas Hertl (SO)"
  $j['winningGoalie'] = $winnerGoalieName; // e.g., "Sergei Bobrovsky"
  if (!isset($j['visitor'])) $j['visitor'] = ['name'=>'','score'=>0];
  if (!isset($j['home']))    $j['home']    = ['name'=>'','score'=>0];
  if (!isset($j['gameNumber'])) $j['gameNumber'] = $gameNo ?: (int)preg_replace('~\D~','', $base);

  if (!$WRITE) {
    $rows[] = [$base, 'PREVIEW', $j['gwg'], $j['winningGoalie']];
    continue;
  }

  if ($j['gwg'] !== $beforeG || $j['winningGoalie'] !== $beforeW) {
    if (write_json_safe($jsonPath, $j)) {
      $rows[] = [$base, 'UPDATED', $j['gwg'], $j['winningGoalie']];
      $updated++;
    } else {
      $rows[] = [$base, 'WRITE_FAIL', $j['gwg'], $j['winningGoalie']];
      $skipped++;
    }
  } else {
    $rows[] = [$base, 'UNCHANGED', $j['gwg'], $j['winningGoalie']];
    $skipped++;
  }
}

/* --------------- output --------------- */
if (PHP_SAPI === 'cli') {
  echo "Updated: {$updated}, Skipped: {$skipped}\n";
  foreach ($rows as $r) echo implode("\t", $r)."\n";
} else {
  echo "<h2>Harvest Outcomes from HTML (".($WRITE?'WRITE':'PREVIEW').") — names only</h2>";
  echo "<p>Updated: {$updated}, Skipped: {$skipped}</p>";
  echo "<p>".($WRITE ? "Backup written next to each JSON." : "Add <code>?write=1</code> to save.")."</p>";
  echo "<table border='1' cellpadding='6' cellspacing='0'><tr><th>File</th><th>Status</th><th>GWG</th><th>Winning Goalie</th></tr>";
  foreach ($rows as $r) {
    echo "<tr><td>".h($r[0])."</td><td>".h($r[1])."</td><td>".h($r[2])."</td><td>".h($r[3])."</td></tr>";
  }
  echo "</table>";
}
