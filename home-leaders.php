<?php
// home-leaders.php — data-only partial for the left rail "Statistics" card.
// It parses uploads, builds leaders, and exposes them on window.UHA.statsData.*
// No DOM wrappers here; home.php owns <div id="leadersStack">.

// If your common helpers live here, keep; otherwise this file is self-contained.
require_once __DIR__ . '/includes/bootstrap.php';

// ---------- locate folder ----------
$json = __DIR__ . '/assets/json/';
$uploads = __DIR__ . '/data/uploads/';

// Optional team id->abbr mapping (for CSVs that use team IDs)
$teamAbbrFromId = [];
$teamsJson = $json . 'teams.json';
if (is_readable($teamsJson)) {
    $json = json_decode((string) file_get_contents($teamsJson), true);
    if (!empty($json['teams'])) {
        foreach ($json['teams'] as $t) {
            $teamAbbrFromId[(string) $t['id']] = $t['abbr'];
        }
    }
}

// Players CSV (skaters + defense + rookies)
$playersFile = null;
foreach (['UHA-V3Players.csv', 'UHA-Players.csv'] as $cand) {
    if (is_readable($uploads . $cand)) { $playersFile = $uploads . $cand; break; }
}

// Goalies CSV (for GAA/SV%/SO/W)
$goaliesFile = null;
foreach (['UHA-V3Goalies.csv', 'UHA-Goalies.csv'] as $cand) {
    if (is_readable($uploads . $cand)) { $goaliesFile = $uploads . $cand; break; }
}

// Team stats CSV (to know team GP for goalie eligibility)
$teamsStatFile = null;
foreach (['UHA-V3ProTeam.csv', 'UHA-Teams.csv', 'UHA-V3Teams.csv'] as $cand) {
    if (is_readable($uploads . $cand)) { $teamsStatFile = $uploads . $cand; break; }
}

// ---------- tiny helpers (subset mirrored from statistics.php) ----------
function first_nonempty(array $row, array $keys, $default = null) {
    foreach ($keys as $k) if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
    return $default;
}
$aliases = [
    'name'   => ['name','player','playername','fullname','skater','playerfullname'],
    'team'   => ['team','abbre','abbr','teamabbr','teamabbre','teamid','teamnumber','proteam','proteamid','proteamabbr'],
    'pos'    => ['position','pos','role','p','positioncode'],
    'posd'   => ['posd'], // defense-only flag
    'g'      => ['prog','g','goals','goalsfor','goalsscored'],
    'a'      => ['proa','a','assists'],
    'p'      => ['propoint','p','pts','points','pointstotal'],
    'gp'     => ['gp','gamesplayed','games','played','progp'],
    // Goalie metrics inputs / outputs
    'ga'     => ['proga','ga','goalsagainst','goalsallowed'],
    'sa'     => ['prosa','sa','shotsagainst','shotsfaced','shots'],
    'sec'    => ['prosecplay','prosec','secondsplayed','proseconds','timeonice','toi'],
    'mins'   => ['prominuteplay','prominsplay','minutesplayed','mins','minutes'],
    'so'     => ['proshutout','so','shutouts'],
    'w'      => ['prow','w','wins'],
    'rookie' => ['rookie','isrookie','rook','rookieflag'],
];
function value_of(array $row, string $aliasKey, array $aliases, $default = null) {
    $keys = $aliases[$aliasKey] ?? [$aliasKey];
    return first_nonempty($row, $keys, $default);
}
function as_int($v, $def = 0){ return is_numeric($v) ? (int)$v : $def; }
function as_float($v, $def = 0.0){ return is_numeric($v) ? (float)$v : $def; }
function truthy($v){
    if ($v === null) return false;
    $s = strtolower(trim((string)$v));
    if ($s === '') return false;
    if (is_numeric($s)) return ((float)$s) > 0;
    return in_array($s, ['1','y','yes','true','t'], true);
}
function isDefense(array $r): bool {
    $v = strtoupper(trim((string)($r['PosD'] ?? '')));
    return $v === 'TRUE';
}
function isRookie(array $r): bool {
    $v = strtoupper(trim((string)($r['Rookie'] ?? '')));
    return $v === 'TRUE';
}
function getName(array $r, array $aliases){
    $name = value_of($r, 'name', $aliases, '');
    if ($name === '') {
        foreach ($r as $k=>$v) if (preg_match('/^[A-Za-z\-\.\']+\s+[A-Za-z\-\.\']+$/', (string)$v)) return (string)$v;
    }
    return (string)$name;
}
function getTeam(array $r, array $aliases, array $teamAbbrFromId){
    $raw = (string) value_of($r,'team',$aliases,'');
    if ($raw === '' && isset($r['tm'])) $raw = $r['tm'];
    return $teamAbbrFromId[$raw] ?? $raw;
}
function takeTop(array $rows, callable $valFn, int $limit, bool $asc = false, array $aliases = [], array $teamAbbrFromId = []): array {
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'name' => getName($r, $aliases),
            'team' => getTeam($r, $aliases, $teamAbbrFromId),
            'val'  => $valFn($r),
        ];
    }
    usort($out, fn($a,$b) => $asc ? ($a['val'] <=> $b['val']) : ($b['val'] <=> $a['val']));
    return array_slice($out, 0, $limit);
}

// Goalie eligibility: must have >= 31% of team GP, else fallback 25 GP
const ELIG_FRAC = 0.31;
const ELIG_FALLBACK = 25;
function build_team_gp_map(array $teamRows, array $aliases, array $teamAbbrFromId): array {
    $map = [];
    foreach ($teamRows as $t) {
        // exact team abbr if present
        $abbr = '';
        if (!empty($t['ProTeamAbbre']))      $abbr = (string)$t['ProTeamAbbre'];
        elseif (!empty($t['TeamAbbre']))     $abbr = (string)$t['TeamAbbre'];
        else                                  $abbr = getTeam($t, $aliases, $teamAbbrFromId);

        // exact GP if present
        $gp = 0;
        if (isset($t['ProGP']))              $gp = as_int($t['ProGP'], 0);
        elseif (isset($t['GP']))             $gp = as_int($t['GP'], 0);
        elseif (isset($t['GamesPlayed']))    $gp = as_int($t['GamesPlayed'], 0);

        if ($abbr !== '') $map[$abbr] = max($gp, $map[$abbr] ?? 0);
    }
    return $map;
}
function goalie_eligible(array $row, array $teamGP, array $aliases, array $teamAbbrFromId): bool {
    $gp = as_int(value_of($row,'gp',$aliases,0), 0);
    $team = getTeam($row, $aliases, $teamAbbrFromId);
    $min = isset($teamGP[$team]) ? (int)ceil(ELIG_FRAC * $teamGP[$team]) : ELIG_FALLBACK;
    return $gp >= $min;
}

// ---------- load rows ----------
function parse_csv_assoc(string $path): array {
    $out = [];
    if (!is_readable($path)) return $out;
    if (($fh = fopen($path, 'r')) === false) return $out;
    $header = fgetcsv($fh);
    if (!$header) { fclose($fh); return $out; }
    while (($row = fgetcsv($fh)) !== false) {
        $assoc = [];
        foreach ($header as $i => $k) $assoc[$k] = $row[$i] ?? '';
        $out[] = $assoc;
    }
    fclose($fh);
    return $out;
}

$players = $playersFile ? parse_csv_assoc($playersFile) : [];
$goalies = $goaliesFile ? parse_csv_assoc($goaliesFile) : [];
$teamRows = $teamsStatFile ? parse_csv_assoc($teamsStatFile) : [];
$teamGP = build_team_gp_map($teamRows, $aliases, $teamAbbrFromId);

// ---------- skaters (non-defense): PTS/G/A (EXACT: ProG/ProA/ProPoint) ----------
$skaters = array_values(array_filter($players, fn($r) => !isDefense($r)));

$valG = fn($r) => as_int($r['ProG']      ?? 0, 0);
$valA = fn($r) => as_int($r['ProA']      ?? 0, 0);
$valP = fn($r) => as_int($r['ProPoint']  ?? (($r['ProG'] ?? 0) + ($r['ProA'] ?? 0)), 0);

$leadersSkaters = [
    'PTS' => takeTop($skaters, $valP, 10, false, $aliases, $teamAbbrFromId),
    'G'   => takeTop($skaters, $valG, 10, false, $aliases, $teamAbbrFromId),
    'A'   => takeTop($skaters, $valA, 10, false, $aliases, $teamAbbrFromId),
];


// ---------- defense-only: PTS/G/A (EXACT: ProG/ProA/ProPoint; PosD === TRUE) ----------
$defenders = array_values(array_filter($players, fn($r) => isDefense($r)));

$valGd = fn($r) => as_int($r['ProG']     ?? 0, 0);
$valAd = fn($r) => as_int($r['ProA']     ?? 0, 0);
$valPd = fn($r) => as_int($r['ProPoint'] ?? (($r['ProG'] ?? 0) + ($r['ProA'] ?? 0)), 0);

$leadersDefense = [
    'PTS' => takeTop($defenders, $valPd, 10, false, $aliases, $teamAbbrFromId),
    'G'   => takeTop($defenders, $valGd, 10, false, $aliases, $teamAbbrFromId),
    'A'   => takeTop($defenders, $valAd, 10, false, $aliases, $teamAbbrFromId),
];
// Pretty aliases (if your UI looks for the words):
$leadersDefensePretty = [
    'Points'  => $leadersDefense['PTS'],
    'Goals'   => $leadersDefense['G'],
    'Assists' => $leadersDefense['A'],
];


// ---------- rookies only: PTS/G/A (EXACT: ProG/ProA/ProPoint; Rookie === TRUE) ----------
$rookies = array_values(array_filter($players, fn($r) => isRookie($r)));

$valGr = fn($r) => as_int($r['ProG']     ?? 0, 0);
$valAr = fn($r) => as_int($r['ProA']     ?? 0, 0);
$valPr = fn($r) => as_int($r['ProPoint'] ?? (($r['ProG'] ?? 0) + ($r['ProA'] ?? 0)), 0);

$leadersRookies = [
    'PTS' => takeTop($rookies, $valPr, 10, false, $aliases, $teamAbbrFromId),
    'G'   => takeTop($rookies, $valGr, 10, false, $aliases, $teamAbbrFromId),
    'A'   => takeTop($rookies, $valAr, 10, false, $aliases, $teamAbbrFromId),
];
$leadersRookiesPretty = [
    'Points'  => $leadersRookies['PTS'],
    'Goals'   => $leadersRookies['G'],
    'Assists' => $leadersRookies['A'],
];


// ---------- goalies: compute GAA & SV% from raw stats (EXACT cols) ----------
// Inputs expected in the goalie CSV:
//   ProGA (goals against), ProSA (shots against),
//   ProSecPlay (seconds) or ProMinutePlay (minutes),
//   ProSO (shutouts), ProW (wins), ProGP (games), ProTeamAbbre (team abbr)

// helpers
$valSO = fn($r) => as_int($r['ProSO'] ?? 0, 0);
$valW  = fn($r) => as_int($r['ProW']  ?? 0, 0);

$minutesFromRow = function(array $r): float {
  $sec  = as_float($r['ProSecPlay']    ?? 0.0, 0.0);
  $mins = as_float($r['ProMinutePlay'] ?? 0.0, 0.0);
  return $sec > 0 ? ($sec / 60.0) : $mins;
};

$valSV = function(array $r): float {
  $sa = as_int($r['ProSA'] ?? 0, 0);
  $ga = as_int($r['ProGA'] ?? 0, 0);
  if ($sa <= 0) return 0.000;
  return round(1.0 - ($ga / $sa), 3); // e.g., 0.923
};

$valGAA = function(array $r) use ($minutesFromRow): float {
  $ga = as_float($r['ProGA'] ?? 0.0, 0.0);
  $min = $minutesFromRow($r);
  if ($min <= 0) return 99.99;        // push to bottom if no time
  return round(($ga * 60.0) / $min, 2); // e.g., 2.37
};

// eligibility: >= 31% of team GP if known; else 31% of league max GP; fallback >= 1
$leagueMaxGP = 0;
foreach ($goalies as $r) {
  $leagueMaxGP = max($leagueMaxGP, as_int($r['ProGP'] ?? 0, 0));
}

$goalieEligible = function(array $row) use ($teamGP, $leagueMaxGP, $teamAbbrFromId, $aliases): bool {
  $gp   = as_int($row['ProGP'] ?? 0, 0);
  $team = !empty($row['ProTeamAbbre']) ? (string)$row['ProTeamAbbre']
       : (!empty($row['TeamAbbre'])    ? (string)$row['TeamAbbre']
       :  getTeam($row, $aliases, $teamAbbrFromId));
  $min = isset($teamGP[$team])
    ? (int)ceil(0.31 * $teamGP[$team])
    : max(1, (int)ceil(0.31 * max(1, $leagueMaxGP)));
  return $gp >= $min;
};

$goaliesElig = array_values(array_filter($goalies, $goalieEligible));

// build leaders (note: GAA asc, others desc)
$leadersGoalies = [
  'GAA' => takeTop($goaliesElig, $valGAA, 10, /*asc*/ true,  $aliases, $teamAbbrFromId),
  'SV%' => takeTop($goaliesElig, $valSV,  10, /*asc*/ false, $aliases, $teamAbbrFromId),
  'SO'  => takeTop($goaliesElig, $valSO,  10, /*asc*/ false, $aliases, $teamAbbrFromId),
  'W'   => takeTop($goaliesElig, $valW,   10, /*asc*/ false, $aliases, $teamAbbrFromId),
];

// ---------- emit to window.UHA (consumed by assets/js/home.js) ----------
?>
<script>
(function (U) {
  U = window.UHA = window.UHA || {};
  U.statsData = U.statsData || {};

  U.statsData.skaters = <?= json_encode($leadersSkaters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  // Defensemen under multiple section names (backup expected this)
  (function(){
    var D  = <?= json_encode($leadersDefense, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var DP = <?= json_encode($leadersDefensePretty, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    function mix(a,b){var o={},k; for(k in a)o[k]=a[k]; for(k in b)o[k]=b[k]; return o;}
    var merged = mix(D, DP);
    U.statsData.defense    = U.statsData.defense    ? mix(U.statsData.defense, merged)         : merged;
    U.statsData.defensemen = U.statsData.defensemen ? mix(U.statsData.defensemen, merged)      : merged;
    U.statsData.defenders  = U.statsData.defenders  ? mix(U.statsData.defenders, merged)       : merged;
  })();

  (function(){
    var R  = <?= json_encode($leadersRookies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var RP = <?= json_encode($leadersRookiesPretty, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    function mix(a,b){var o={},k; for(k in a)o[k]=a[k]; for(k in b)o[k]=b[k]; return o;}
    U.statsData.rookies = U.statsData.rookies ? mix(U.statsData.rookies, mix(R, RP)) : mix(R, RP);
  })();

 (function(){
    var G = <?= json_encode($leadersGoalies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    // duplicate the save% bucket under safer keys
    if (G && G['SV%']) {
      G['SV']    = G['SV%'];
      G['SVPCT'] = G['SV%'];
      G['SavePct'] = G['SV%'];
    }
    U.statsData.goalies = G;
  })();

  // Don’t stomp UI’s chosen length. Only default if missing.
  if (typeof U.leadersCompactN !== 'number') U.leadersCompactN = 5;
})(window.UHA);
</script>
