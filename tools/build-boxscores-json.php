<?php
// tools/build-boxscores-json.php — simple text/regex scraper (no DOM)
// Source of truth: boxscore HTML + fallback to PBP HTML (UHA-###-PBP.html)
// Output: data/uploads/boxscores-json/UHA-###.json

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root      = dirname(__DIR__);
$uploadDir = $root . '/data/uploads';
$pattern   = $uploadDir . '/UHA-*.html';
$outDir    = $uploadDir . '/boxscores-json';
$teamsMapP = 'assets/json/teams.json';

if (!is_dir($outDir)) mkdir($outDir, 0775, true);

/* ---------- utils ---------- */
function strip_bom(string $s): string {
  return (substr($s,0,3)==="\xEF\xBB\xBF") ? substr($s,3) : $s;
}
function tidy(string $s): string {
  $s = html_entity_decode($s, ENT_QUOTES|ENT_HTML5, 'UTF-8');
  $s = preg_replace('/\s+/u', ' ', $s);
  return trim($s);
}
function text_only(string $html): string {
  // crude but effective for searching
  $t = preg_replace('~<script\b[^>]*>.*?</script>~is', ' ', $html);
  $t = preg_replace('~<style\b[^>]*>.*?</style>~is', ' ', $t);
  $t = strip_tags($t);
  return tidy($t);
}
function load_team_maps(string $p): array {
  $abbr2name = []; $name2abbr = [];
  $raw = @file_get_contents($p);
  if ($raw !== false) {
    $j = json_decode($raw, true);
    foreach (($j['teams'] ?? []) as $t) {
      $name = (string)($t['name'] ?? '');
      $abbr = (string)($t['abbr'] ?? '');
      if ($name && $abbr) {
        $abbr2name[$abbr] = $name;
        $name2abbr[mb_strtolower($name)] = $abbr;
        if (!empty($t['shortName'])) $name2abbr[mb_strtolower((string)$t['shortName'])] = $abbr;
      }
    }
  }
  return [$abbr2name, $name2abbr];
}
function pbp_path_for(string $boxPath): ?string {
  // UHA-123.html -> UHA-123-PBP.html (same dir)
  $dir = dirname($boxPath);
  $base = basename($boxPath, '.html');
  $candidates = [
    $dir . '/' . $base . '-PBP.html',
    $dir . '/' . $base . '-PbP.html',
    $dir . '/' . $base . '-PlayByPlay.html',
  ];
  foreach ($candidates as $p) if (is_file($p)) return $p;
  return null;
}

/* ---------- parsers (regex / text search) ---------- */

// Return ['visitor'=>['name','score'], 'home'=>['name','score']]
function parse_header_scores_from_box(string $html): array {
  $out = ['visitor'=>['name'=>'','score'=>0], 'home'=>['name'=>'','score'=>0]];

  // Typical STHS header table pairs: TeamName / GoalsTotal (Visitor then Home)
  if (preg_match_all(
    '~class="[^"]*STHSGame_GoalsTeamName[^"]*"[^>]*>\s*(.*?)\s*</td>\s*<td[^>]*class="[^"]*STHSGame_GoalsTotal[^"]*"[^>]*>\s*(\d+)\s*</td>~si',
    $html, $m, PREG_SET_ORDER
  )) {
    if (isset($m[0])) { $out['visitor']['name'] = tidy($m[0][1]); $out['visitor']['score'] = (int)$m[0][2]; }
    if (isset($m[1])) { $out['home']['name']    = tidy($m[1][1]); $out['home']['score']    = (int)$m[1][2]; }
    return $out;
  }

  // Fallback: try to read from title like "CHI 1 – 7 FLA"
  if (preg_match('~<title>(.*?)</title>~si', $html, $tm)) {
    $title = tidy($tm[1]);
    if (preg_match('~([A-Z]{2,3})\s+(\d+)\s*[-–]\s*(\d+)\s+([A-Z]{2,3})~', $title, $mm)) {
      // We don't know full names here, scores only.
      $out['visitor']['score'] = (int)$mm[2];
      $out['home']['score']    = (int)$mm[3];
    }
  }
  return $out;
}

// From a scoring summary block in either boxscore or PBP text, find explicit "(GWG)"
function find_gwg_explicit(string $text): string {
  // Look for "Name (GWG" or "Name – GWG"
  if (preg_match('~([A-Za-z\.\'\- ]{3,40})\s*\((?=[^)]*\bGWG\b)~u', $text, $m)) return tidy($m[1]);
  if (preg_match('~\bGWG\b[^A-Za-z]*([A-Za-z\.\'\- ]{3,40})~u', $text, $m)) return tidy($m[1]);
  return '';
}

// Build a chronological list of goals: [['team'=>..., 'scorer'=>...], ...]
function parse_goal_events_simple(string $text): array {
  $events = [];
  // Lines like: "1. Florida Panthers , Sam Reinhart 1 (A. Tkachuk) at 1:15"
  foreach (preg_split('~[\r\n]+~', $text) as $ln) {
    $ln = tidy($ln);
    if ($ln === '') continue;
    if (preg_match('~^\d+\.\s*([^,]+)\s*,\s*([A-Za-z\.\'\- ]+?)(?:\s+\d+)?\s+(?:\(|at\b)~u', $ln, $m)) {
      $events[] = ['team'=>tidy($m[1]), 'scorer'=>tidy($m[2])];
    }
  }
  return $events;
}

// Stars from either "1 - Name" lines or a small table dumped to text
function parse_stars_simple(string $text): array {
  $first=''; $second=''; $third='';
  // Prefer explicit lines starting with "1 -", "2 -", "3 -"
  foreach (preg_split('~[\r\n]+~', $text) as $ln) {
    $ln = tidy($ln);
    if ($first===''  && preg_match('~^\s*1\s*[-–]\s*(.+)$~',  $ln, $m)) $first  = tidy($m[1]);
    if ($second==='' && preg_match('~^\s*2\s*[-–]\s*(.+)$~',  $ln, $m)) $second = tidy($m[1]);
    if ($third===''  && preg_match('~^\s*3\s*[-–]\s*(.+)$~',  $ln, $m)) $third  = tidy($m[1]);
  }
  // Fallback: look for "3 Stars" block then grab next three capitalized names
  if ($first==='' || $second==='' || $third==='') {
    if (preg_match('~3\s*Stars.*?$((?:.*\n){0,8})~ims', $text, $m)) {
      $blk = $m[1];
      preg_match_all('~\b([A-Z][a-z]{1,}(?:\s+[A-Z][a-z\'\-]{1,}){0,3})\b~', $blk, $nms);
      $uniq = array_values(array_unique(array_map('trim', $nms[1] ?? [])));
      if (isset($uniq[0])) $first  = $first  ?: $uniq[0];
      if (isset($uniq[1])) $second = $second ?: $uniq[1];
      if (isset($uniq[2])) $third  = $third  ?: $uniq[2];
    }
  }
  return [$first,$second,$third];
}

// Very loose human date grab
function parse_date_simple(string $text): string {
  // Look for a line starting with "Date:" first
  if (preg_match('~\bDate\s*:\s*(.+?)\s*(?:\||$)~i', $text, $m)) return tidy($m[1]);
  // Month day, year
  if (preg_match('~\b(January|February|March|April|May|June|July|August|September|October|November|December)\b[^0-9]{0,5}(\d{1,2}),\s*(\d{4})~', $text, $m)) {
    return "{$m[1]} {$m[2]}, {$m[3]}";
  }
  // Weekday Month Day, Year
  if (preg_match('~\b(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\w*\b[^A-Za-z]+([A-Za-z]+)\s+(\d{1,2}),\s*(\d{4})~i', $text, $m)) {
    return "{$m[2]} {$m[3]}, {$m[4]}";
  }
  return '';
}

/* ---------- main ---------- */

[$abbr2name, $name2abbr] = load_team_maps($teamsMapP);

$files = glob($pattern);
natsort($files);

$count = 0;
foreach ($files as $boxPath) {
  // Skip non-game pages like Farm if they’re mixed in
  if (stripos($boxPath, 'Farm') !== false) continue;

  $html = @file_get_contents($boxPath);
  if ($html === false) continue;
  $html = strip_bom($html);
  $text = text_only($html);

  // Box header scores & names
  $hdr = parse_header_scores_from_box($html);

  // PBP fallback text if needed
  $pbpText = '';
  $pbpPath = pbp_path_for($boxPath);
  if ($pbpPath) {
    $pbpHtml = strip_bom((string)@file_get_contents($pbpPath));
    if ($pbpHtml !== '') $pbpText = text_only($pbpHtml);
  }

  // Date: box first, then PBP
  $date = parse_date_simple($text);
  if ($date === '' && $pbpText !== '') $date = parse_date_simple($pbpText);

  // Stars: box first, then PBP
  [$firstStar,$secondStar,$thirdStar] = parse_stars_simple($text);
  if (($firstStar==='' || $secondStar==='' || $thirdStar==='') && $pbpText!=='') {
    [$f2,$s2,$t2] = parse_stars_simple($pbpText);
    $firstStar  = $firstStar  ?: $f2;
    $secondStar = $secondStar ?: $s2;
    $thirdStar  = $thirdStar  ?: $t2;
  }

  // GWG:
  $gwg = find_gwg_explicit($text);
  if ($gwg === '' && $pbpText !== '') $gwg = find_gwg_explicit($pbpText);

  // If still blank, compute from winner’s Nth goal logic
  $homeScore = (int)($hdr['home']['score'] ?? 0);
  $awayScore = (int)($hdr['visitor']['score'] ?? 0);
  $winnerTeam = null; $loserGoals = 0;
  if ($homeScore !== $awayScore) {
    if ($homeScore > $awayScore) { $winnerTeam = $hdr['home']['name'];    $loserGoals = $awayScore; }
    else                         { $winnerTeam = $hdr['visitor']['name']; $loserGoals = $homeScore; }
  }
  if ($gwg === '' && $winnerTeam) {
    $events = parse_goal_events_simple($pbpText ?: $text);
    $n = 0;
    foreach ($events as $e) {
      if (strcasecmp($e['team'], $winnerTeam) === 0) {
        $n++;
        if ($n === $loserGoals + 1) { $gwg = $e['scorer']; break; }
      }
    }
  }

  // Map team abbrs
  $vName = (string)($hdr['visitor']['name'] ?? '');
  $hName = (string)($hdr['home']['name'] ?? '');
  $vAbbr = $name2abbr[mb_strtolower($vName)] ?? '';
  $hAbbr = $name2abbr[mb_strtolower($hName)] ?? '';
  if ($gwg !== '' && $winnerTeam) {
    $wAbbr = $name2abbr[mb_strtolower($winnerTeam)] ?? '';
    if ($wAbbr !== '' && stripos($gwg, "($wAbbr)") === false) $gwg .= " ($wAbbr)";
  }

  // Game number from filename
  $gameNumber = 0;
  if (preg_match('~UHA-(\d+)\.html$~', $boxPath, $mm)) $gameNumber = (int)$mm[1];

  $out = [
    'gwg' => $gwg,
    'winningGoalie' => '', // unchanged here; filled by goalie parser if you want (or keep your existing tool)
    'firstStar' => $firstStar,
    'secondStar' => $secondStar,
    'thirdStar' => $thirdStar,
    'gameNumber' => $gameNumber,
    'visitor' => ['name' => $vName, 'score' => (int)($hdr['visitor']['score'] ?? 0), 'abbr' => $vAbbr],
    'home'    => ['name' => $hName, 'score' => (int)($hdr['home']['score']    ?? 0), 'abbr' => $hAbbr],
    'date'    => $date,
  ];

  // If you want winning goalie here too, uncomment the simple W-scan below:
  // $w = '';
  // if ($pbpText) {
  //   if (preg_match('~\b([A-Z][a-z\.\'\- ]{2,})\s*\(([A-Z]{2,3})\)\s*,\s*W,~', $pbpText, $gm)) {
  //     $w = tidy($gm[1]).' ('.$gm[2].')';
  //   }
  // }
  // $out['winningGoalie'] = $w;

  $base = basename($boxPath, '.html');
  $jsonPath = $outDir . '/' . $base . '.json';
  file_put_contents($jsonPath, json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  $count++;
}

echo "Built {$count} boxscore JSON files in {$outDir}\n";
