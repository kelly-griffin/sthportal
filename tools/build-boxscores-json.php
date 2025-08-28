<?php
// tools/build-boxscores-json.php
// Parse STHS HTML boxscores (UHA-*.html) and write compact JSON per game
// Expected keys in output JSON:
// { "gameKey": "YYYY-MM-DD_VIS_at_HOME", "gwg": "Name (TEAM)", "winningGoalie": "Name (TEAM)", "firstStar": "...", "secondStar": "...", "thirdStar": "..." }

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root = dirname(__DIR__);
$uploadDir = $root . '/data/uploads';
$pattern = $uploadDir . '/UHA-*.html';
$outDir = $uploadDir . '/boxscores';

if (!is_dir($outDir)) mkdir($outDir, 0775, true);

function textBetween($html, $startMarker, $endMarker) {
    $start = strpos($html, $startMarker);
    if ($start === false) return '';
    $start += strlen($startMarker);
    $end = strpos($html, $endMarker, $start);
    if ($end === false) $end = $start + 2000;
    return substr($html, $start, $end - $start);
}

function clean($s) {
    $s = strip_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES|ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $s));
}

// Extract team names and scores from the top "Goals" table header
function parseHeaderScores($html) {
    $out = ['home'=>['name'=>'','score'=>0], 'visitor'=>['name'=>'','score'=>0]];
    if (!preg_match_all('/<td[^>]*class="STHSGame_GoalsTeamName"[^>]*>(.*?)<\/td>.*?<td[^>]*class="STHSGame_GoalsTotal"[^>]*>(\d+)<\/td>/si', $html, $m, PREG_SET_ORDER)) {
        return $out;
    }
    // First row is visitor, second is home in STHS output
    if (isset($m[0])) { $out['visitor']['name'] = clean($m[0][1]); $out['visitor']['score'] = intval($m[0][2]); }
    if (isset($m[1])) { $out['home']['name']    = clean($m[1][1]); $out['home']['score']    = intval($m[1][2]); }
    return $out;
}

// Extract chronological list of goals (team name + scorer)
function parseGoalEvents($html) {
    $events = [];
    // Each period div has class STHSGame_GoalPeriod1/2/3/OT etc.
    if (preg_match_all('/<div class="STHSGame_GoalPeriod[^"]*">(.*?)<\/div>/si', $html, $pm)) {
        foreach ($pm[1] as $block) {
            // Lines like: "1. Florida Panthers , Sam Reinhart 1 (Assist) at 1:15"
            if (preg_match_all('/\d+\.\s*([^,]+)\s*,\s*([^<\(]+)\s*(?:\([^\)]*\))?\s*at\s*[^<]+<br\s*\/?>/si', $block, $gm, PREG_SET_ORDER)) {
                foreach ($gm as $g) {
                    $team = clean($g[1]);
                    $scorer = clean($g[2]);
                    $events[] = ['team'=>$team, 'scorer'=>$scorer];
                }
            }
        }
    }
    return $events;
}

// Extract goalie stats lines and find the one with ", W," marker
function parseWinningGoalie($html) {
    if (preg_match_all('/<div class="STHSGame_GoalerStats">(.*?)<\/div>/si', $html, $m)) {
        foreach ($m[1] as $line) {
            $t = clean($line);
            // Example: "Sergei Bobrovsky (FLA), 29 saves ... , W, 1-0-0, 60:00 minutes"
            if (preg_match('/^([A-Za-z\.\'\-\s]+)\s*\(([A-Z]{2,3})\).*?,\s*W,/', $t, $mm)) {
                return $mm[1] . ' (' . $mm[2] . ')';
            }
        }
    }
    return '';
}

// Extract 3 Stars
function parseStars($html) {
    $stars = ['firstStar'=>'','secondStar'=>'','thirdStar'=>''];
    if (preg_match('/<h3 class="STHSGame_3StarTitle">3 Stars<\/h3>\s*<div class="STHSGame_3Star">\s*1\s*-\s*(.*?)<br\s*\/?>\s*2\s*-\s*(.*?)<br\s*\/?>\s*3\s*-\s*(.*?)<br\s*\/?>\s*<\/div>/si', $html, $m)) {
        $stars['firstStar']  = clean($m[1]);
        $stars['secondStar'] = clean($m[2]);
        $stars['thirdStar']  = clean($m[3]);
    }
    return $stars;
}

function abbrMap() {
    $mapPath = dirname(__DIR__) . '/data/uploads/teams.json';
    $raw = @file_get_contents($mapPath);
    if ($raw === false) return [];
    $json = json_decode($raw, true);
    $map = [];
    foreach (($json['teams'] ?? []) as $t) {
        $map[$t['name']] = $t['abbr'];
    }
    return $map;
}

$teamAbbr = abbrMap();

$files = glob($pattern);
$count = 0;
foreach ($files as $file) {
    $html = file_get_contents($file);
    if ($html === false) continue;

    $hdr = parseHeaderScores($html);
    $events = parseGoalEvents($html);
    $wGoalie = parseWinningGoalie($html);
    $stars = parseStars($html);

    $winnerTeam = ($hdr['home']['score'] > $hdr['visitor']['score']) ? $hdr['home']['name'] : $hdr['visitor']['name'];
    $loserGoals = ($hdr['home']['score'] > $hdr['visitor']['score']) ? $hdr['visitor']['score'] : $hdr['home']['score'];

    // Find (loserGoals+1)th goal by the winner
    $n = 0; $gwg = '';
    foreach ($events as $e) {
        if ($e['team'] === $winnerTeam) {
            $n++;
            if ($n === $loserGoals + 1) { $gwg = $e['scorer']; break; }
        }
    }
    // Attach team abbr if we know it
    $abbr = $teamAbbr[$winnerTeam] ?? '';
    if ($abbr !== '' && $gwg !== '') $gwg = $gwg . ' (' . $abbr . ')';

    // Derive date + teams for gameKey from file name and header teams
    if (preg_match('/UHA-(\d+)\.html$/', $file, $mm)) {
        $gameNumber = intval($mm[1]);
    } else {
        $gameNumber = 0;
    }
    // We cannot derive date from the HTML reliably; leave empty and let enrich-schedule match by link (UHA-#.html)
    $out = [
        'gwg' => $gwg,
        'winningGoalie' => $wGoalie,
        'firstStar' => $stars['firstStar'],
        'secondStar' => $stars['secondStar'],
        'thirdStar' => $stars['thirdStar'],
        'gameNumber' => $gameNumber,
        'visitor' => ['name'=>$hdr['visitor']['name'], 'score'=>$hdr['visitor']['score']],
        'home'    => ['name'=>$hdr['home']['name'],    'score'=>$hdr['home']['score']]
    ];

    // Save per-file JSON next to outDir named UHA-#.json
    $base = basename($file, '.html');
    $jsonPath = $outDir . '/' . $base . '.json';
    file_put_contents($jsonPath, json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    $count++;
}
echo "Built {$count} boxscore JSON files in {$outDir}\n";
