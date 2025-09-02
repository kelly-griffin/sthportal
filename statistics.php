<?php
require_once __DIR__ . '/includes/bootstrap.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// where PHP *thinks* it is
echo "<!-- CWD: " . getcwd() . "  DIR: " . __DIR__ . " -->";

// absolute roots (works no matter the URL mount)
define('PORTAL_ROOT', str_replace('\\', '/', realpath(__DIR__)));
define('DATA_DIR', PORTAL_ROOT . '/data');
define('UPLOADS_DIR', PORTAL_ROOT . '/uploads');

// check the files the stats page typically needs
$probes = [
  'leaders' => DATA_DIR . '/derived/leaders.json',
  'skaters_summary' => DATA_DIR . '/derived/skaters_summary.json',
  'goalies_summary' => DATA_DIR . '/derived/goalies_summary.json',
  'teams_summary' => DATA_DIR . '/derived/teams_summary.json',
];

foreach ($probes as $k => $p) {
  echo "<!-- probe:$k " . (is_file($p) ? "OK $p" : "MISSING $p") . " -->";
}

// check the files the stats page typically needs
$probes = [
  'leaders' => DATA_DIR . '/derived/leaders.json',
  'skaters_summary' => DATA_DIR . '/derived/skaters_summary.json',
  'goalies_summary' => DATA_DIR . '/derived/goalies_summary.json',
  'teams_summary' => DATA_DIR . '/derived/teams_summary.json',
];

foreach ($probes as $k => $p) {
  echo "<!-- probe:$k " . (is_file($p) ? "OK $p" : "MISSING $p") . " -->";
}

/**
 * statistics.php — v1.6 (home leaders)
 * - Skaters (PTS/G/A), Defense (PTS/G/A), Goalies (GAA/SV%/SO)
 * - Feature panel with hover, headshots via CSV + fallback by name
 * - Goalie eligibility: >= 31% of team GP (or fallback 25 GP when team GP unavailable)
 * - Team colors handled client-side
 */

const ELIG_FRAC = 0.31;
const ELIG_FALLBACK = 25;

function parse_csv_assoc($file)
{
  $rows = [];
  if (!is_readable($file))
    return $rows;
  if (($fh = fopen($file, 'r')) === false)
    return $rows;
  $hdr = fgetcsv($fh);
  if (!$hdr) {
    fclose($fh);
    return $rows;
  }
  $norm = [];
  foreach ($hdr as $h) {
    $norm[] = strtolower(preg_replace('/[^a-z0-9%]+/i', '', (string) $h));
  }
  while (($cols = fgetcsv($fh)) !== false) {
    $row = [];
    foreach ($norm as $i => $k) {
      $row[$k] = isset($cols[$i]) ? $cols[$i] : null;
    }
    $rows[] = $row;
  }
  fclose($fh);
  return $rows;
}
function first_nonempty($row, $keys, $default = null)
{
  foreach ($keys as $k) {
    if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null)
      return $row[$k];
  }
  return $default;
}
function as_int($v, $d = 0)
{
  if ($v === null || $v === '')
    return $d;
  return is_numeric($v) ? (int) $v : $d;
}
function as_float($v, $d = 0.0)
{
  if ($v === null || $v === '')
    return $d;
  $v = str_replace(['%', ' '], ['', ''], (string) $v);
  $v = str_replace(',', '.', $v);
  return is_numeric($v) ? (float) $v : $d;
}
function starts_with($hay, $needle)
{
  return substr($hay, 0, strlen($needle)) === $needle;
}
function norm_name_key($s)
{
  return strtolower(preg_replace('/[^a-z]+/', '', (string) $s));
}
function truthy($v)
{
  if ($v === null)
    return false;
  $s = strtolower(trim((string) $v));
  if ($s === '')
    return false;
  if (is_numeric($s))
    return ((float) $s) > 0;
  return in_array($s, ['1', 'y', 'yes', 'true', 't', 'r', 'rook', 'rookie'], true);
}

/* ---------- locate files ---------- */
$uploads = __DIR__ . '/data/uploads/';
$playersFile = null;    // skaters
$goaliesFile = null;
$teamsStatFile = null;  // team totals for GP eligibility

foreach (['UHA-V3Players.csv', 'UHA-Players.csv'] as $cand) {
  if (is_readable($uploads . $cand)) {
    $playersFile = $uploads . $cand;
    break;
  }
}
foreach (['UHA-V3Goalies.csv', 'UHA-Goalies.csv'] as $cand) {
  if (is_readable($uploads . $cand)) {
    $goaliesFile = $uploads . $cand;
    break;
  }
}
foreach (['UHA-V3ProTeam.csv', 'UHA-Teams.csv', 'UHA-V3Teams.csv'] as $cand) {
  if (is_readable($uploads . $cand)) {
    $teamsStatFile = $uploads . $cand;
    break;
  }
}

// Optional per-year draft picks-per-round map
$draftPicksPerRoundMap = [];
$draftPicksPath = __DIR__ . '/data/uploads/draft-picks-per-round.json';
if (is_file($draftPicksPath)) {
  $tmp = json_decode((string) file_get_contents($draftPicksPath), true);
  if (is_array($tmp))
    $draftPicksPerRoundMap = $tmp;
}

// --- Derived OTG support (optional file) ---
$derivedOtgPath = __DIR__ . '/data/uploads/derived-otg.csv';
$derivedOtgMap = [];       // name|TEAM -> int
$derivedOtgByName = [];    // name -> int (team-agnostic fallback)

if (is_file($derivedOtgPath)) {
  $rows = parse_csv_assoc($derivedOtgPath);
  foreach ($rows as $row) {
    $name = trim((string) ($row['Name'] ?? $row['name'] ?? ''));
    if ($name === '')
      continue;

    $otg = (int) ($row['OTG'] ?? $row['otg'] ?? 0);
    $teamRaw = (string) ($row['Team'] ?? $row['team'] ?? '');
    $team = strtoupper(trim($teamRaw));

    $nameKey = mb_strtolower($name);

    if ($team !== '') {
      $derivedOtgMap[$nameKey . '|' . $team] = $otg;
    }

    // keep the best (or only) name-only count
    if (!isset($derivedOtgByName[$nameKey]) || $otg > $derivedOtgByName[$nameKey]) {
      $derivedOtgByName[$nameKey] = $otg;
    }
  }
}
function lookupDerivedOtg(string $name, string $teamAbbr, ?array $mapExact = null, ?array $mapNameOnly = null): ?int
{
  // Accept 2-arg or 4-arg usage; fallback to globals for maps
  global $derivedOtgMap, $derivedOtgByName;
  if ($mapExact === null) {
    $mapExact = isset($derivedOtgMap) ? (array) $derivedOtgMap : [];
  }
  if ($mapNameOnly === null) {
    $mapNameOnly = isset($derivedOtgByName) ? (array) $derivedOtgByName : [];
  }
  $nk = mb_strtolower(trim((string) $name));
  $tk = strtoupper(trim((string) $teamAbbr));
  if ($nk === '')
    return null;
  if ($tk !== '') {
    $k = $nk . '|' . $tk;
    if (array_key_exists($k, $mapExact))
      return (int) $mapExact[$k];
  }
  return array_key_exists($nk, $mapNameOnly) ? (int) $mapNameOnly[$nk] : null;
}

/* Optional team id->abbr mapping (JSON) */
$teamsJson = __DIR__ . '/data/uploads/teams.json';
$teamAbbrFromId = [];
if (is_readable($teamsJson)) {
  $json = json_decode((string) file_get_contents($teamsJson), true);
  if (!empty($json['teams'])) {
    foreach ($json['teams'] as $t) {
      $teamAbbrFromId[(string) $t['id']] = $t['abbr'];
    }
  }
}

$players = $playersFile ? parse_csv_assoc($playersFile) : [];
$goalies = $goaliesFile ? parse_csv_assoc($goaliesFile) : [];
$teamRows = $teamsStatFile ? parse_csv_assoc($teamsStatFile) : [];
$teams = $teamsStatFile ? parse_csv_assoc($teamsStatFile) : [];// NEW: Teams stats

/* ---------- header aliases ---------- */
$aliases = [
  'name' => ['name', 'player', 'playername', 'fullname', 'skater', 'goalie', 'lastnamefirstname', 'firstnameandlastname', 'fname_lname', 'lastname_firstname', 'playerfullname'],
  'team' => ['team', 'abbre', 'abbr', 'teamabbr', 'teamabbre', 'teamid', 'teamnumber', 'proteam', 'proteamid', 'proteamabbr'],
  'photo' => ['photo', 'headshot', 'headshoturl', 'image', 'img', 'urllink', 'picture', 'pic', 'portrait', 'playerphoto', 'playerpic'],
  'pos' => ['position', 'pos', 'role', 'p', 'positioncode'],
  'posd' => ['posd'],
  'rookie' => ['rookie', 'isrookie', 'rookiestatus', 'rookieflag', 'prorookie'],
  'jersey' => ['jersey', 'number', 'no', 'playernumber', 'sweater'],

  // skater stats
  'g' => ['prog', 'g', 'goals', 'goalsfor', 'goalsscored'],
  'a' => ['proa', 'a', 'assists'],
  'p' => ['propoint', 'p', 'pts', 'points', 'pointstotal'],
  // goalie stats (raw inputs we calculate from)
  'gaa' => ['progaa', 'gaa', 'goalsagainstaverage'],
  'sv' => ['prosv%', 'prosvpct', 'prosv', 'sv%', 'svpct', 'svpercent', 'svpercentage', 'savepct', 'savepercentage', 'save'],
  'so' => ['proshutout', 'so', 'shutouts'],
  'gp' => ['gp', 'gamesplayed', 'games', 'played', 'progp'],

  // NEW: inputs to calculate GAA + SV%
  'ga' => ['proga', 'ga', 'goalsagainst', 'goalsallowed'],
  'sa' => ['prosa', 'sa', 'shotsagainst', 'shotsfaced', 'shots'],
  'sec' => ['prosecplay', 'prosec', 'secondsplayed', 'proseconds', 'timeonice', 'toi'],
  'mins' => ['prominuteplay', 'prominsplay', 'minutesplayed', 'mins', 'minutes'],
  'svs' => ['prosvs', 'saves'], // optional, not required
  'w' => ['prow', 'w', 'wins'],
  'l' => ['prol', 'l', 'losses'],
  'ot' => ['prootl', 'ot', 'otl'],
  'gs' => ['prostartgoaler', 'gs', 'starts'],
];
// TEAMS aliases (all keys unique)
$aliases_teams = [
  // basic
  'team' => ['name', 'team', 'proteam'],
  'gp' => ['gp', 'gamesplayed', 'games'],
  'w' => ['w', 'wins'],
  'l' => ['l', 'losses'],
  'otw' => ['otw', 'otwins', 'ot_win'],            // overtime wins
  'otl' => ['otl', 'otlosses', 'ot_loss'],         // overtime losses (we also infer if missing)
  'p' => ['points', 'p', 'pts'],
  'sow' => ['sow', 'shootoutwins', 'shootout_win'],
  'gf' => ['gf', 'goalsfor'],
  'ga' => ['ga', 'goalsagainst'],

  // derived (allow override if a CSV already has them)
  'rw' => ['rw', 'regulationwins'],
  'row' => ['row', 'reg_ot_wins'],

  // per‑game rates (allow override)
  'gf_gp' => ['gf_gp', 'goalsforpergame'],
  'ga_gp' => ['ga_gp', 'goalsagainstpergame'],

  // special teams % (allow override)
  'pp_pct' => ['pp%', 'pppct', 'pp_percentage', 'powerplay%', 'powerplaypct'],
  'pk_pct' => ['pk%', 'pkpct', 'pk_percentage', 'penaltykill%', 'penaltykillpct'],
  'net_pp_pct' => ['netpp%', 'netpppct'],
  'net_pk_pct' => ['netpk%', 'netpkpct'],

  // shots per game (allow override)
  'shots_gp' => ['shots/gp', 'shotspergp', 'shotspergame'],
  'sa_gp' => ['shotsagainst/gp', 'shotsagainstpergp', 'shotsagainstpergame'],

  // faceoff % (allow override)
  'fow_pct' => ['fo%', 'fowpct', 'faceoff%', 'faceoffpct', 'faceoffpercentage'],

  // raw inputs for calculations (unique internal keys; include the literal headers in the value list)
  'ppattemp' => ['ppatt', 'ppattempts', 'pp_attempts', 'ppattemp'],
  'ppgoal' => ['ppg', 'ppgoals', 'pp_goals', 'ppgoal'],
  'pkattemp' => ['pkatt', 'pkattempts', 'pk_attempts', 'pkattemp'],
  'pkgoalga' => ['pkga', 'pkgoalagainst', 'pk_goals_against', 'pkgoalga'],
  'shotsfor' => ['sf', 'shots_for', 'shotsfor'],
  'shotsaga' => ['sa', 'shots_against', 'shotsaga'],

  // faceoff zone detail (wins / totals)
  'fow_d_won' => ['face off won defensif zone', 'fowdz', 'faceoffwondz'],
  'fow_d_total' => ['face off total defensif zone', 'fodz', 'faceofftotaldz'],
  'fow_o_won' => ['face off won offensif zone', 'fowoz', 'faceoffwonoz'],
  'fow_o_total' => ['face off total offensif zone', 'fooz', 'faceofftotaloz'],
  'fow_n_won' => ['face off won neutral zone', 'fownz', 'faceoffwonnz'],
  'fow_n_total' => ['face off total neutral zone', 'fonz', 'faceofftotalnz'],
];

function value_of($row, $aliasKey, $aliases, $default = null)
{
  $keys = isset($aliases[$aliasKey]) ? $aliases[$aliasKey] : [$aliasKey];
  return first_nonempty($row, $keys, $default);
}

function getName($r, $aliases)
{
  $name = value_of($r, 'name', $aliases, '');
  if ($name === '') {
    foreach ($r as $k => $v) {
      if (preg_match('/^[A-Za-z\-\.\']+\s+[A-Za-z\-\.\']+$/', (string) $v)) {
        return (string) $v;
      }
    }
  }
  return (string) $name;
}
function getTeam($r, $aliases, $teamAbbrFromId)
{
  $raw = (string) value_of($r, 'team', $aliases, '');
  if ($raw === '' && isset($r['tm']))
    $raw = $r['tm'];
  if (isset($teamAbbrFromId[$raw]))
    return $teamAbbrFromId[$raw];
  return $raw;
}
function getPhoto($r, $aliases)
{
  return (string) value_of($r, 'photo', $aliases, '');
}

/* Build photo fallback index from skaters CSV */
$photoIndex = [];
foreach ($players as $p) {
  $n = getName($p, $aliases);
  $ph = getPhoto($p, $aliases);
  if ($n !== '' && $ph !== '') {
    $photoIndex[norm_name_key($n)] = $ph;
  }
}
function getHeadshot($r, $aliases)
{
  $ph = getPhoto($r, $aliases);
  if ($ph !== '')
    return $ph;
  $nkey = norm_name_key(getName($r, $aliases));
  return isset($GLOBALS['photoIndex'][$nkey]) ? $GLOBALS['photoIndex'][$nkey] : '';
}

function isDefense($r, $aliases)
{
  $pos = strtolower((string) value_of($r, 'pos', $aliases, ''));
  if ($pos === 'd' || starts_with($pos, 'def'))
    return true;
  $flag = value_of($r, 'posd', $aliases, '');
  return truthy($flag);
}
function isRookie($r, $aliases)
{
  $rook = value_of($r, 'rookie', $aliases, '');
  return truthy($rook);
}
function pos_code_from_flags(array $row, array $aliases): string
{
  // D wins immediately
  if (truthy(value_of($row, 'posd', $aliases, '')))
    return 'D';

  // C/L/R flags -> "C", "L", "R", "C/L", "C/R", "L/R", or "F" if all three
  $c = truthy(value_of($row, 'posc', $aliases, ''));
  $l = truthy(value_of($row, 'poslw', $aliases, ''));
  $r = truthy(value_of($row, 'posrw', $aliases, ''));
  $parts = [];
  if ($c)
    $parts[] = 'C';
  if ($l)
    $parts[] = 'L';
  if ($r)
    $parts[] = 'R';
  if (count($parts) === 3)
    return 'F';
  if (count($parts) >= 2)
    return $parts[0] . '/' . $parts[1];
  return $parts[0] ?? '';
}
/* Team GP map for goalie eligibility */
$teamGP = [];
foreach ($teamRows as $t) {
  $abbr = getTeam($t, $aliases, $GLOBALS['teamAbbrFromId']);
  $gp = as_int(value_of($t, 'gp', $GLOBALS['aliases'], 0), 0);
  if ($abbr !== '') {
    $teamGP[$abbr] = max($gp, isset($teamGP[$abbr]) ? $teamGP[$abbr] : 0);
  }
}
function goalie_eligible($r)
{
  $gp = as_int(value_of($r, 'gp', $GLOBALS['aliases'], 0), 0);
  $team = getTeam($r, $GLOBALS['aliases'], $GLOBALS['teamAbbrFromId']);
  $teamGP = isset($GLOBALS['teamGP'][$team]) ? $GLOBALS['teamGP'][$team] : null;
  $min = $teamGP ? (int) ceil($teamGP * ELIG_FRAC) : ELIG_FALLBACK;
  return $gp >= $min;
}

function takeTop($rows, $valFn, $limit = 10, $asc = false)
{
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'name' => getName($r, $GLOBALS['aliases']),
      'team' => getTeam($r, $GLOBALS['aliases'], $GLOBALS['teamAbbrFromId']),
      'photo' => getHeadshot($r, $GLOBALS['aliases']),
      'val' => $valFn($r),
      // jersey / sweater number
      'jersey' => (string) value_of($r, 'jersey', $GLOBALS['aliases'], ''),
      // Pass-through position flags from the CSV so the JS can compose C/L/R/F/D
      // Headers like PosC/PosLW/PosRW/PosD are normalized to 'posc','poslw','posrw','posd'
      'posc' => value_of($r, 'posc', $GLOBALS['aliases'], ''),
      'poslw' => value_of($r, 'poslw', $GLOBALS['aliases'], ''),
      'posrw' => value_of($r, 'posrw', $GLOBALS['aliases'], ''),
      'posd' => value_of($r, 'posd', $GLOBALS['aliases'], ''),
      // Goalies get handled separately in the goalie list (we set data-posg="TRUE" there),
      // but include this for completeness if ever present in skater CSVs.
      'posg' => value_of($r, 'posg', $GLOBALS['aliases'], ''),
    ];

  }
  $out = array_values(array_filter($out, function ($x) {
    return $x['name'] !== '';
  }));
  usort($out, function ($a, $b) use ($asc) {
    return $asc ? ($a['val'] <=> $b['val']) : ($b['val'] <=> $a['val']);
  });
  return array_slice($out, 0, $limit);
}

/* ---------- leader sets ---------- */
$skaters = array_values(array_filter($players, function ($r) {
  return !isDefense($r, $GLOBALS['aliases']);
}));
$defense = array_values(array_filter($players, function ($r) {
  return isDefense($r, $GLOBALS['aliases']);
}));
$rookies = array_values(array_filter($players, function ($r) {
  return isRookie($r, $GLOBALS['aliases']);
}));
$goaliesElig = array_values(array_filter($goalies, function ($r) {
  return goalie_eligible($r);
}));

$valG = function ($r) {
  return as_int(value_of($r, 'g', $GLOBALS['aliases'], 0), 0);
};
$valA = function ($r) {
  return as_int(value_of($r, 'a', $GLOBALS['aliases'], 0), 0);
};
$valP = function ($r) {
  $p = value_of($r, 'p', $GLOBALS['aliases'], null);
  if ($p === null || $p === '')
    return $GLOBALS['valG']($r) + $GLOBALS['valA']($r);
  return as_int($p, 0);
};

$valGAA = function ($r) {
  // Prefer calculating from seconds; fall back to minutes.
  $ga = as_float(value_of($r, 'ga', $GLOBALS['aliases'], 0.0), 0.0);
  $sec = as_float(value_of($r, 'sec', $GLOBALS['aliases'], 0.0), 0.0);
  $mins = as_float(value_of($r, 'mins', $GLOBALS['aliases'], 0.0), 0.0);

  if ($sec > 0)
    return ($ga * 3600) / $sec;   // (GA * 60) / (sec/60) == (GA * 3600) / sec
  if ($mins > 0)
    return ($ga * 60) / $mins;    // minutes path if seconds missing
  return 0.0;
};

$valSV = function ($r) {
  // SV% = (SA - GA) / SA
  $ga = as_float(value_of($r, 'ga', $GLOBALS['aliases'], 0.0), 0.0);
  $sa = as_float(value_of($r, 'sa', $GLOBALS['aliases'], 0.0), 0.0);
  if ($sa > 0)
    return ($sa - $ga) / $sa;
  return 0.0; // no shots recorded
};
$valSO = function ($r) {
  return as_int(value_of($r, 'so', $GLOBALS['aliases'], 0), 0);
};

$leaders = [
  'skaters' => [
    'PTS' => takeTop($skaters, $valP, 10, false),
    'G' => takeTop($skaters, $valG, 10, false),
    'A' => takeTop($skaters, $valA, 10, false),
  ],
  'defense' => [
    'PTS' => takeTop($defense, $valP, 10, false),
    'G' => takeTop($defense, $valG, 10, false),
    'A' => takeTop($defense, $valA, 10, false),
  ],
  'rookies' => [
    'PTS' => takeTop($rookies, $valP, 10, false),
  ],
  'goalies' => [
    'GAA' => takeTop($goaliesElig, $valGAA, 10, true),
    'SV%' => takeTop($goaliesElig, $valSV, 10, false),
    'SO' => takeTop($goaliesElig, $valSO, 10, false),
  ],
];

$hasDefense = count($leaders['defense']['PTS']) > 0;
$hasRookies = count($leaders['rookies']['PTS']) > 0;

/* ---------- diagnostics ---------- */
$diag = [];
if (isset($_GET['debug']) && $_GET['debug']) {
  $diag['players_headers'] = array_keys(isset($players[0]) ? $players[0] : []);
  $diag['goalies_headers'] = array_keys(isset($goalies[0]) ? $goalies[0] : []);
  $diag['teams_headers'] = array_keys(isset($teamRows[0]) ? $teamRows[0] : []);
  $diag['have_team_gp'] = (bool) count($teamGP);
}

// --- Face-off helpers (added) ---
if (!function_exists('normalize_name_key')) {
  function normalize_name_key(string $name): string
  {
    if (function_exists('iconv')) {
      $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT', $name);
      if ($tmp !== false)
        $name = $tmp;
    }
    $n = strtolower($name);
    $n = html_entity_decode($n, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $n = preg_replace('/[^a-z\s]/', ' ', $n);
    $n = preg_replace('/\s+/', ' ', $n);
    return trim($n);
  }
}
if (!function_exists('make_roster_map_from_rows')) {
  function make_roster_map_from_rows(array $rows, array $aliases, ?array $teamAbbrFromId = null): array
  {
    $map = [];
    foreach ($rows as $r) {
      $name = function_exists('getName') ? getName($r, $aliases) : (string) ($r['Name'] ?? '');
      if ($name === '')
        continue;
      $team = function_exists('getTeam') ? getTeam($r, $aliases, $teamAbbrFromId ?? null) : strtoupper((string) ($r['Team'] ?? ''));
      if ($team === '')
        continue;
      $map[normalize_name_key($name)] = strtoupper($team);
    }
    return $map;
  }
}
if (!function_exists('load_team_full_to_code')) {
  function load_team_full_to_code(string $teamsJsonPath): array
  {
    $out = [];
    if (is_readable($teamsJsonPath)) {
      $json = json_decode((string) file_get_contents($teamsJsonPath), true);
      if (isset($json['teams']) && is_array($json['teams'])) {
        foreach ($json['teams'] as $t) {
          $full = $t['fullName'] ?? ($t['name'] ?? '');
          $abbr = $t['abbr'] ?? ($t['triCode'] ?? '');
          if ($full && $abbr)
            $out[$full] = strtoupper($abbr);
        }
      }
    }
    return $out;
  }
}
if (!function_exists('pct')) {
  function pct(int $won, int $taken): float
  {
    return $taken > 0 ? round(($won * 100.0) / $taken, 1) : 0.0;
  }
}
// SIMPLE-PBP PARSER (matches your examples, strips HTML, recursive-safe)
if (!function_exists('parse_faceoffs_from_pbp')) {
  function parse_faceoffs_from_pbp(array $files, array $rosterMap, array $teamFullToCode): array
  {
    // Disabled: we now load derived JSON via tools/build-pbp-stats.php.
    return [];
  }

}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=1280, initial-scale=1" />
  <title>Statistics</title>
  <link rel="stylesheet" href="assets/css/nav.css" />
</head>

<body>
  <div class="site">

    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="statistics-container">
        <div class="statistics-card">
          <div class="stats-tab-title" id="statsTabTitle">Statistics Home</div>
          <nav class="stats-subnav" aria-label="Statistics Sections">
            <a href="#home" class="tab-link active" data-tab="home">Home</a>
            <a href="#skaters" class="tab-link" data-tab="skaters">Skaters</a>
            <a href="#goalies" class="tab-link" data-tab="goalies">Goalies</a>
            <a href="#teams" class="tab-link" data-tab="teams">Teams</a>
          </nav>

          <?php if (isset($_GET['debug']) && $_GET['debug']): ?>
            <div class="diag">
              <strong>Players headers</strong>
              <code><?= htmlspecialchars(json_encode($diag['players_headers'])) ?></code>
              <strong>Goalies headers</strong>
              <code><?= htmlspecialchars(json_encode($diag['goalies_headers'])) ?></code>
              <strong>Teams headers</strong>
              <code><?= htmlspecialchars(json_encode($diag['teams_headers'])) ?></code>
              <strong>Team GP detected?</strong>
              <code><?= $diag['have_team_gp'] ? 'yes' : 'no (fallback ' . ELIG_FALLBACK . ' GP)' ?></code>
            </div>
          <?php endif; ?>

          <section id="tab-home" class="tab-panel active" aria-labelledby="home">
            <div class="leaders-grid">

              <!-- Skaters -->
              <div class="leaders-card" data-card="skaters">
                <header class="card-head">
                  <h2>Skaters</h2>
                  <div class="metric-tabs" role="tablist" aria-label="Skater metrics">
                    <button class="metric-tab active" data-metric="PTS" role="tab" aria-selected="true">Points</button>
                    <button class="metric-tab" data-metric="G" role="tab" aria-selected="false">Goals</button>
                    <button class="metric-tab" data-metric="A" role="tab" aria-selected="false">Assists</button>
                  </div>
                </header>
                <div class="metric-panels">
                  <div class="feature-panel" data-card-feature>
                    <div class="avatar" data-avatar><img data-avatar-img alt="" /></div>
                    <div class="feat-lines">
                      <div class="feat-name" data-name>—</div>
                      <div class="feat-team" data-team>—</div>

                      <div class="feat-info">
                        <img class="feat-logo" data-team-logo alt="">
                        <span class="feat-code" data-team-code>—</span>
                        <span class="dot">•</span>
                        <span class="feat-num" data-number></span>
                        <span class="dot">•</span>
                        <span class="feat-pos" data-pos></span>
                      </div>
                      <div class="feat-metric"><span data-metric-label>PTS</span><span data-metric-val>—</span></div>
                    </div>
                  </div>
                  <?php foreach (['PTS', 'G', 'A'] as $metric): ?>
                    <ol class="mini-board metric-panel <?= $metric === 'PTS' ? 'active' : '' ?>"
                      data-metric="<?= $metric ?>">
                      <?php foreach (($leaders['skaters'][$metric] ?? []) as $i => $r): ?>
                        <?php $photo = htmlspecialchars($r['photo']); ?>
                        <li data-name="<?= htmlspecialchars($r['name']) ?>" data-team="<?= htmlspecialchars($r['team']) ?>"
                          data-jersey="<?= htmlspecialchars($r['jersey'] ?? '') ?>" data-val="<?= (int) $r['val'] ?>"
                          data-valtext="<?= (int) $r['val'] ?>" data-metric="<?= $metric ?>" data-photo="<?= $photo ?>"
                          data-posc="<?= htmlspecialchars($r['posc'] ?? '') ?>"
                          data-poslw="<?= htmlspecialchars($r['poslw'] ?? '') ?>"
                          data-posrw="<?= htmlspecialchars($r['posrw'] ?? '') ?>"
                          data-posd="<?= htmlspecialchars($r['posd'] ?? '') ?>"
                          data-posg="<?= htmlspecialchars($r['posg'] ?? '') ?>">
                          <span class="rank"><?= $i + 1 ?></span><span
                            class="player"><?= htmlspecialchars($r['name']) ?></span><span
                            class="team"><?= htmlspecialchars($r['team']) ?></span><span
                            class="val"><?= (int) $r['val'] ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ol>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Goalies -->
              <div class="leaders-card" data-card="goalies">
                <header class="card-head">
                  <h2>Goalies</h2>
                  <div class="metric-tabs" role="tablist" aria-label="Goalie metrics">
                    <button class="metric-tab active" data-metric="GAA" role="tab" aria-selected="true">GAA</button>
                    <button class="metric-tab" data-metric="SV%" role="tab" aria-selected="false">SV%</button>
                    <button class="metric-tab" data-metric="SO" role="tab" aria-selected="false">SO</button>
                  </div>
                </header>
                <div class="metric-panels">
                  <div class="feature-panel" data-card-feature>
                    <div class="avatar" data-avatar><img data-avatar-img alt="" /></div>
                    <div class="feat-lines">
                      <div class="feat-name" data-name>—</div>
                      <div class="feat-team" data-team>—</div>
                      <div class="feat-info">
                        <img class="feat-logo" data-team-logo alt="">
                        <span class="feat-code" data-team-code>—</span>
                        <span class="dot">•</span>
                        <span class="feat-num" data-number></span>
                        <span class="dot">•</span>
                        <span class="feat-pos" data-pos></span>
                      </div>
                      <div class="feat-metric"><span data-metric-label>GAA</span><span data-metric-val>—</span></div>
                    </div>
                  </div>
                  <?php foreach (['GAA', 'SV%', 'SO'] as $metric): ?>
                    <ol class="mini-board metric-panel <?= $metric === 'GAA' ? 'active' : '' ?>"
                      data-metric="<?= $metric ?>">
                      <?php foreach (($leaders['goalies'][$metric] ?? []) as $i => $r): ?>
                        <?php $photo = htmlspecialchars($r['photo']); ?>
                        <li data-name="<?= htmlspecialchars($r['name']) ?>" data-team="<?= htmlspecialchars($r['team']) ?>"
                          data-val="<?= $metric === 'GAA'
                            ? number_format((float) $r['val'], 2, '.', '')
                            : ($metric === 'SV%'
                              ? number_format((float) $r['val'], 3, '.', '')
                              : (int) $r['val']) ?>" data-valtext="<?= $metric === 'GAA'
                               ? number_format((float) $r['val'], 2, '.', '')
                               : ($metric === 'SV%'
                                 ? ltrim(number_format((float) $r['val'], 3, '.', ''), '0')  // ".938"
                                 : (int) $r['val']) ?>" data-metric="<?= $metric ?>" data-photo="<?= $photo ?>"
                          data-posg="TRUE">
                          <span class="rank"><?= $i + 1 ?></span><span
                            class="player"><?= htmlspecialchars($r['name']) ?></span><span
                            class="team"><?= htmlspecialchars($r['team']) ?></span><span class="val">
                            <?php
                            if ($metric === 'GAA') {
                              echo number_format((float) $r['val'], 2, '.', '');
                            } elseif ($metric === 'SV%') {
                              $sv = number_format((float) $r['val'], 3, '.', ''); // "0.938"
                              echo ltrim($sv, '0');                              // ".938"
                            } else {
                              echo (int) $r['val'];
                            }
                            ?>
                          </span>
                        </li>
                      <?php endforeach; ?>
                    </ol>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Defensemen -->
              <?php if ($hasDefense): ?>
                <div class="leaders-card" data-card="defense">
                  <header class="card-head">
                    <h2>Defensemen</h2>
                    <div class="metric-tabs" role="tablist" aria-label="Defense metrics">
                      <button class="metric-tab active" data-metric="PTS" role="tab" aria-selected="true">Points</button>
                      <button class="metric-tab" data-metric="G" role="tab" aria-selected="false">Goals</button>
                      <button class="metric-tab" data-metric="A" role="tab" aria-selected="false">Assists</button>
                    </div>
                  </header>
                  <div class="metric-panels">
                    <div class="feature-panel" data-card-feature>
                      <div class="avatar" data-avatar><img data-avatar-img alt="" /></div>
                      <div class="feat-lines">
                        <div class="feat-name" data-name>—</div>
                        <div class="feat-team" data-team>—</div>
                        <div class="feat-info">
                          <img class="feat-logo" data-team-logo alt="">
                          <span class="feat-code" data-team-code>—</span>
                          <span class="dot">•</span>
                          <span class="feat-num" data-number></span>
                          <span class="dot">•</span>
                          <span class="feat-pos" data-pos></span>
                        </div>
                        <div class="feat-metric"><span data-metric-label>POINTS</span><span data-metric-val>—</span></div>
                      </div>
                    </div>
                    <?php foreach (['PTS', 'G', 'A'] as $metric): ?>
                      <ol class="mini-board metric-panel <?= $metric === 'PTS' ? 'active' : '' ?>"
                        data-metric="<?= $metric ?>">
                        <?php foreach (($leaders['defense'][$metric] ?? []) as $i => $r): ?>
                          <?php $photo = htmlspecialchars($r['photo']); ?>
                          <li data-name="<?= htmlspecialchars($r['name']) ?>" data-team="<?= htmlspecialchars($r['team']) ?>"
                            data-jersey="<?= htmlspecialchars($r['jersey'] ?? '') ?>" data-val="<?= (int) $r['val'] ?>"
                            data-valtext="<?= (int) $r['val'] ?>" data-metric="<?= $metric ?>" data-photo="<?= $photo ?>"
                            data-posc="<?= htmlspecialchars($r['posc'] ?? '') ?>"
                            data-poslw="<?= htmlspecialchars($r['poslw'] ?? '') ?>"
                            data-posrw="<?= htmlspecialchars($r['posrw'] ?? '') ?>"
                            data-posd="<?= htmlspecialchars($r['posd'] ?? '') ?>"
                            data-posg="<?= htmlspecialchars($r['posg'] ?? '') ?>">
                            <span class="rank"><?= $i + 1 ?></span><span
                              class="player"><?= htmlspecialchars($r['name']) ?></span><span
                              class="team"><?= htmlspecialchars($r['team']) ?></span><span
                              class="val"><?= (int) $r['val'] ?></span>
                          </li>
                        <?php endforeach; ?>
                      </ol>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

              <!-- Rookies -->
              <?php if ($hasRookies): ?>
                <div class="leaders-card" data-card="rookies">
                  <header class="card-head">
                    <h2>Rookies</h2>
                    <div class="metric-tabs solo" role="tablist" aria-label="Rookie metrics">
                      <button class="metric-tab active" data-metric="PTS" role="tab" aria-selected="true">Points</button>
                    </div>
                  </header>
                  <div class="metric-panels">
                    <div class="feature-panel" data-card-feature>
                      <div class="avatar" data-avatar><img data-avatar-img alt="" /></div>
                      <div class="feat-lines">
                        <div class="feat-name" data-name>—</div>
                        <div class="feat-team" data-team>—</div>
                        <div class="feat-info">
                          <img class="feat-logo" data-team-logo alt="">
                          <span class="feat-code" data-team-code>—</span>
                          <span class="dot">•</span>
                          <span class="feat-num" data-number></span>
                          <span class="dot">•</span>
                          <span class="feat-pos" data-pos></span>
                        </div>
                        <div class="feat-metric"><span data-metric-label>POINTS</span><span data-metric-val>—</span></div>
                      </div>
                    </div>
                    <?php $metric = 'PTS'; ?>
                    <ol class="mini-board metric-panel active" data-metric="PTS">
                      <?php foreach (($leaders['rookies']['PTS'] ?? []) as $i => $r): ?>
                        <?php $photo = htmlspecialchars($r['photo']); ?>
                        <li data-name="<?= htmlspecialchars($r['name']) ?>" data-team="<?= htmlspecialchars($r['team']) ?>"
                          data-jersey="<?= htmlspecialchars($r['jersey'] ?? '') ?>" data-val="<?= (int) $r['val'] ?>"
                          data-valtext="<?= (int) $r['val'] ?>" data-metric="<?= $metric ?>" data-photo="<?= $photo ?>"
                          data-posc="<?= htmlspecialchars($r['posc'] ?? '') ?>"
                          data-poslw="<?= htmlspecialchars($r['poslw'] ?? '') ?>"
                          data-posrw="<?= htmlspecialchars($r['posrw'] ?? '') ?>"
                          data-posd="<?= htmlspecialchars($r['posd'] ?? '') ?>"
                          data-posg="<?= htmlspecialchars($r['posg'] ?? '') ?>">
                          <span class="rank"><?= $i + 1 ?></span><span
                            class="player"><?= htmlspecialchars($r['name']) ?></span><span
                            class="team"><?= htmlspecialchars($r['team']) ?></span><span
                            class="val"><?= (int) $r['val'] ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ol>
                  </div>
                </div>
              <?php endif; ?>

            </div>
          </section>

          <!--Skaters main tab-->

          <section id="tab-skaters" class="tab-panel" aria-labelledby="skaters">
            <div class="skaters-wrap">
              <div class="skaters-toolbar">
                <span class="label">Summary</span>
              </div>
              <div class="skaters-filters">
                <label>Report
                  <select id="skaters-subtab">
                    <option value="summary" selected>Summary</option>
                    <option value="bio">Bio Info</option>
                    <option value="faceoffs">Face-off Percentages</option>
                    <option value="faceoffs-wl">Face-off Wins &amp; Losses</option>
                  </select>
                </label>

                <label>Rows
                  <select id="skaters-rows">
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                    <option value="300">300</option>
                    <option value="400">400</option>
                    <option value="500">500</option>
                    <option value="all">All</option>
                  </select>
                </label>
              </div>
            </div>
            <div class="skaters-subpanel" data-subtab="summary">
              <div class="table-scroll">
                <table class="data skaters-table" aria-label="Skaters Summary">
                  <thead>
                    <tr>
                      <th class="col-rank">#</th>
                      <th class="col-name" data-sort="name">Player</th>
                      <th class="col-team" data-sort="team">Team</th>
                      <th class="col-pos" data-sort="pos">Pos.</th>
                      <th class="col-gp" data-sort="gp">GP</th>
                      <th class="col-g" data-sort="g">G</th>
                      <th class="col-a" data-sort="a">A</th>
                      <th class="col-p" data-sort="p" aria-sort="descending">P</th>
                      <th class="col-plus" data-sort="plusminus">+/-</th>
                      <th class="col-pim" data-sort="pim">PIM</th>
                      <th class="col-ppg" data-sort="p_per_gp">P/GP</th>
                      <th class="col-evg" data-sort="evg">EVG</th>
                      <th class="col-ppg2" data-sort="ppg">PPG</th>
                      <th class="col-ppp" data-sort="ppp">PPP</th>
                      <th class="col-shg" data-sort="shg">SHG</th>
                      <th class="col-shp" data-sort="shp">SHP</th>
                      <th class="col-otg" data-sort="otg">OTG</th>
                      <th class="col-gwg" data-sort="gwg">GWG</th>
                      <th class="col-s" data-sort="s">S</th>
                      <th class="col-spct" data-sort="s_pct">S%</th>
                      <th class="col-toi" data-sort="toi_gp">TOI/GP</th>
                      <th class="col-fow" data-sort="fow_pct">FOW%</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    // Helper for Pos once (safe if already defined)
                    if (!function_exists('pos_code_from_flags')) {
                      function pos_code_from_flags(array $row, array $aliases): string
                      {
                        if (truthy(value_of($row, 'posd', $aliases, '')))
                          return 'D';
                        $c = truthy(value_of($row, 'posc', $aliases, ''));
                        $l = truthy(value_of($row, 'poslw', $aliases, ''));
                        $r = truthy(value_of($row, 'posrw', $aliases, ''));
                        $parts = [];
                        if ($c)
                          $parts[] = 'C';
                        if ($l)
                          $parts[] = 'L';
                        if ($r)
                          $parts[] = 'R';
                        if (count($parts) === 3)
                          return 'F';
                        if (count($parts) >= 2)
                          return $parts[0] . '/' . $parts[1];
                        return $parts[0] ?? '';
                      }
                    }

                    $rows = [];
                    foreach ($players as $r) {
                      $name = getName($r, $aliases);
                      if ($name === '')
                        continue;

                      $team = getTeam($r, $aliases, $teamAbbrFromId);
                      $pos = pos_code_from_flags($r, $aliases);

                      // raw counts
                      $gp = (int) value_of($r, 'progp', $aliases, 0);
                      $g = (int) value_of($r, 'prog', $aliases, 0);
                      $a = (int) value_of($r, 'proa', $aliases, 0);
                      $p0 = value_of($r, 'propoint', $aliases, null);
                      $p = (int) ($p0 !== null ? $p0 : ($g + $a));

                      $plus = (int) value_of($r, 'proplusminus', $aliases, 0);
                      $pim = (int) value_of($r, 'propim', $aliases, 0);

                      // special teams & derived
                      $ppg = (int) value_of($r, 'proppg', $aliases, 0);
                      $ppa = (int) value_of($r, 'proppa', $aliases, 0);
                      $ppp = $ppg + $ppa;

                      $shg = (int) value_of($r, 'propkg', $aliases, 0);
                      $sha = (int) value_of($r, 'propka', $aliases, 0);
                      $shp = $shg + $sha;

                      $evg = max(0, $g - $ppg - $shg); // Even-strength goals
                    
                      $otgCsv = value_of($r, 'prootg', $aliases, value_of($r, 'otg', $aliases, null));
                      // prefer derived number if we have it
                      $otgDerived = lookupDerivedOtg($name, $team, $derivedOtgMap);
                      $otg = ($otgDerived !== null) ? $otgDerived : (int) ($otgCsv ?? 0);
                      $gwg = (int) value_of($r, 'progw', $aliases, 0);

                      $s = (int) value_of($r, 'proshots', $aliases, 0);
                      $s_pct = $s > 0 ? ($g / $s) * 100.0 : 0.0; // Shooting%
                    
                      $toi_sec_total = (int) value_of($r, 'prosecondplay', $aliases, 0);
                      $toi_gp_sec = $gp > 0 ? (int) round($toi_sec_total / $gp) : 0;
                      $toi_gp_str = sprintf('%d:%02d', intdiv($toi_gp_sec, 60), $toi_gp_sec % 60);

                      $fow = (int) value_of($r, 'profaceoffwon', $aliases, 0);
                      $fotot = (int) value_of($r, 'profaceofftotal', $aliases, 0);
                      $fow_pct = $fotot > 0 ? ($fow / $fotot) * 100.0 : 0.0;

                      $p_per_gp = $gp > 0 ? ($p / $gp) : 0.0;

                      $rows[] = [
                        'name' => $name,
                        'team' => $team,
                        'pos' => $pos,
                        'gp' => $gp,
                        'g' => $g,
                        'a' => $a,
                        'p' => $p,
                        'plusminus' => $plus,
                        'pim' => $pim,
                        'p_per_gp' => $p_per_gp,
                        'evg' => $evg,
                        'ppg' => $ppg,
                        'ppp' => $ppp,
                        'shg' => $shg,
                        'shp' => $shp,
                        'otg' => $otg,
                        'gwg' => $gwg,
                        's' => $s,
                        's_pct' => $s_pct,
                        'toi_gp' => $toi_gp_sec,
                        'toi_disp' => $toi_gp_str,
                        'fow_pct' => $fow_pct
                      ];
                    }

                    // Sort default: Points desc, then Goals desc, Assists desc, Name asc
                    usort($rows, fn($A, $B) => [$B['p'], $B['g'], $B['a'], $A['name']] <=> [$A['p'], $A['g'], $A['a'], $B['name']]);

                    $rank = 1;
                    foreach ($rows as $row): ?>
                      <tr data-name="<?= htmlspecialchars($row['name']) ?>"
                        data-team="<?= htmlspecialchars($row['team']) ?>" data-pos="<?= htmlspecialchars($row['pos']) ?>"
                        data-gp="<?= $row['gp'] ?>" data-g="<?= $row['g'] ?>" data-a="<?= $row['a'] ?>"
                        data-p="<?= $row['p'] ?>" data-plusminus="<?= $row['plusminus'] ?>" data-pim="<?= $row['pim'] ?>"
                        data-p_per_gp="<?= number_format($row['p_per_gp'], 3, '.', '') ?>" data-evg="<?= $row['evg'] ?>"
                        data-ppg="<?= $row['ppg'] ?>" data-ppp="<?= $row['ppp'] ?>" data-shg="<?= $row['shg'] ?>"
                        data-shp="<?= $row['shp'] ?>" data-otg="<?= $row['otg'] ?>" data-gwg="<?= $row['gwg'] ?>"
                        data-s="<?= $row['s'] ?>" data-s_pct="<?= number_format($row['s_pct'], 3, '.', '') ?>"
                        data-toi_gp="<?= $row['toi_gp'] ?>"
                        data-fow_pct="<?= number_format($row['fow_pct'], 3, '.', '') ?>">
                        <td class="col-rank"><?= $rank++ ?></td>
                        <td class="col-name"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="col-team"><?= htmlspecialchars($row['team']) ?></td>
                        <td class="col-pos"><?= htmlspecialchars($row['pos']) ?></td>
                        <td class="col-gp"><?= $row['gp'] ?></td>
                        <td class="col-g"><?= $row['g'] ?></td>
                        <td class="col-a"><?= $row['a'] ?></td>
                        <td class="col-p"><?= $row['p'] ?></td>
                        <td class="col-plus"><?= $row['plusminus'] ?></td>
                        <td class="col-pim"><?= $row['pim'] ?></td>
                        <td class="col-ppg"><?= number_format($row['p_per_gp'], 2) ?></td>
                        <td class="col-evg"><?= $row['evg'] ?></td>
                        <td class="col-ppg2"><?= $row['ppg'] ?></td>
                        <td class="col-ppp"><?= $row['ppp'] ?></td>
                        <td class="col-shg"><?= $row['shg'] ?></td>
                        <td class="col-shp"><?= $row['shp'] ?></td>
                        <td class="col-otg"><?= $row['otg'] ?></td>
                        <td class="col-gwg"><?= $row['gwg'] ?></td>
                        <td class="col-s"><?= $row['s'] ?></td>
                        <td class="col-spct"><?= number_format($row['s_pct'], 1) ?>%</td>
                        <td class="col-toi"><?= htmlspecialchars($row['toi_disp']) ?></td>
                        <td class="col-fow"><?= number_format($row['fow_pct'], 1) ?>%</td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="skaters-subpanel" data-subtab="bio" hidden>
              <div class="table-scroll">
                <table class="bio-table" aria-label="Skaters Bio">
                  <thead>
                    <tr>
                      <th class="col-rank">#</th>
                      <th class="col-name" data-sort="name">Player</th>
                      <th class="col-team" data-sort="team">Team</th>
                      <th class="col-pos" data-sort="pos">Pos</th>
                      <th class="col-dob" data-sort="dob">DOB</th>
                      <th class="col-city" data-sort="city">Birth City</th>
                      <th class="col-sp" data-sort="region">S/P</th>
                      <th class="col-ctry" data-sort="country">Ctry</th>
                      <th class="col-ht" data-sort="height">Ht</th>
                      <th class="col-wt" data-sort="weight">Wt</th>
                      <th class="col-dyr" data-sort="draft_year">Draft Yr</th>
                      <th class="col-drnd" data-sort="draft_round">Round</th>
                      <th class="col-dovr" data-sort="draft_overall">Overall</th>
                      <th class="col-gp" data-sort="gp">GP</th>
                      <th class="col-g" data-sort="g">G</th>
                      <th class="col-a" data-sort="a">A</th>
                      <th class="col-p" data-sort="p">P</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $rank = 1;
                    foreach ($players as $row):
                      // IDs / display via your helpers + alias map
                      $uid = function_exists('value_of') ? value_of($row, 'uniqueid', $aliases ?? [], '') : ($row['UniqueID'] ?? '');
                      $name = function_exists('getName') ? getName($row, $aliases ?? []) : ($row['Name'] ?? '—');
                      $team = function_exists('getTeam') ? getTeam($row, $aliases ?? [], $teamAbbrFromId ?? null)
                        : strtoupper(preg_replace('/[^A-Z]/i', '', $row['Team'] ?? ''));

                      // Position — reuse your existing function if available
                      if (function_exists('pos_code_from_flags')) {
                        $pos = pos_code_from_flags($row, $aliases ?? []);
                      } elseif (function_exists('getSkaterPos')) {
                        $pos = getSkaterPos($row);
                      } else {
                        // very small fallback if neither exists (won’t run in your build)
                        $truthy = fn($v) => preg_match('/^(1|true|y|t)$/i', (string) ($v ?? ''));
                        $C = $truthy($row['PosC'] ?? '');
                        $L = $truthy($row['PosLW'] ?? '');
                        $R = $truthy($row['PosRW'] ?? '');
                        $D = $truthy($row['PosD'] ?? '');
                        $pos = ($D && !($C || $L || $R)) ? 'D' : ((($C ? 1 : 0) + ($L ? 1 : 0) + ($R ? 1 : 0) === 3) ? 'F'
                          : trim(implode('/', array_keys(array_filter(['C' => $C, 'LW' => $L, 'RW' => $R]))), '/'));
                      }

                      // Bio fields — USE value_of so we hit normalized keys/aliases
                    
                      $dobRaw = function_exists('value_of') ? value_of($row, 'agedate', $aliases ?? [], '') : ($row['AgeDate'] ?? '');
                      $country = function_exists('value_of') ? value_of($row, 'country', $aliases ?? [], '') : ($row['Country'] ?? '');
                      $height = function_exists('value_of') ? value_of($row, 'height', $aliases ?? [], '') : ($row['Height'] ?? '');
                      $weight = function_exists('value_of') ? value_of($row, 'weight', $aliases ?? [], '') : ($row['Weight'] ?? '');
                      // Draft info (no Round in CSV — compute from per-year map)
                      $draftYear = function_exists('value_of') ? value_of($row, 'draftyear', $aliases ?? [], '') : ($row['DraftYear'] ?? '');
                      $draftOverall = function_exists('value_of') ? value_of($row, 'draftoverallpick', $aliases ?? [], '') : ($row['DraftOverallPick'] ?? '');

                      // Normalize overall to an int
                      $overallInt = (int) preg_replace('/\D+/', '', (string) $draftOverall);

                      // picks per round for that year (JSON override → else team count → else 32)
                      $perRound = 0;
                      if ($draftYear !== '') {
                        $perRound = (int) ($draftPicksPerRoundMap[(string) $draftYear] ?? 0);
                      }
                      if ($perRound <= 0) {
                        $perRound = is_array($teamAbbrFromId ?? null) ? (int) count($teamAbbrFromId) : 32;
                      }

                      // Compute round (blank if we can't)
                      $draftRound = ($overallInt > 0 && $perRound > 0)
                        ? (int) floor(($overallInt - 1) / $perRound) + 1
                        : '0';


                      // DOB normalize for sort
                      $dobSort = '';
                      if ($dobRaw !== '') {
                        $t = strtotime($dobRaw);
                        $dobSort = $t ? date('Y-m-d', $t) : $dobRaw;
                      }
                      $dobDisp = $dobSort ? date('Y-m-d', strtotime($dobSort)) : '';

                      // Optional birthplace file: data/uploads/bio-locations.csv (UniqueID,BirthCity,Region)
                      $city = (isset($bioLocations[$uid]['city'])) ? $bioLocations[$uid]['city'] : '';
                      $region = (isset($bioLocations[$uid]['region'])) ? $bioLocations[$uid]['region'] : '';

                      // Pro stats via value_of (keeps parity with Summary)
                      $gp = (int) (function_exists('value_of') ? value_of($row, 'progp', $aliases ?? [], 0) : ($row['ProGP'] ?? 0));
                      $g = (int) (function_exists('value_of') ? value_of($row, 'prog', $aliases ?? [], 0) : ($row['ProG'] ?? 0));
                      $a = (int) (function_exists('value_of') ? value_of($row, 'proa', $aliases ?? [], 0) : ($row['ProA'] ?? 0));
                      $p0 = (function_exists('value_of') ? value_of($row, 'propoint', $aliases ?? [], null) : ($row['ProPoint'] ?? null));
                      $p = (int) ($p0 !== null ? $p0 : ($g + $a));
                      ?>
                      <tr data-name="<?= htmlspecialchars($name) ?>" data-team="<?= htmlspecialchars($team) ?>"
                        data-pos="<?= htmlspecialchars($pos) ?>" data-dob="<?= htmlspecialchars($dobSort) ?>"
                        data-city="<?= htmlspecialchars($city) ?>" data-region="<?= htmlspecialchars($region) ?>"
                        data-country="<?= htmlspecialchars(strtoupper($country)) ?>"
                        data-height="<?= htmlspecialchars($height) ?>" data-weight="<?= htmlspecialchars($weight) ?>"
                        data-draft_year="<?= htmlspecialchars($draftYear) ?>" data-draft_round="<?= (int) $draftRound ?>"
                        data-draft_overall="<?= htmlspecialchars($draftOverall) ?>" data-gp="<?= (int) $gp ?>"
                        data-g="<?= (int) $g ?>" data-a="<?= (int) $a ?>" data-p="<?= (int) $p ?>">
                        <td class="col-rank"><?= $rank++ ?></td>
                        <td class="col-name"><?= htmlspecialchars($name) ?></td>
                        <td class="col-team"><?= htmlspecialchars($team) ?></td>
                        <td class="col-pos"><?= htmlspecialchars($pos) ?></td>
                        <td class="col-dob"><?= htmlspecialchars($dobDisp) ?></td>
                        <td class="col-city"><?= htmlspecialchars($city) ?></td>
                        <td class="col-sp"><?= htmlspecialchars($region) ?></td>
                        <td class="col-ctry"><?= htmlspecialchars(strtoupper($country)) ?></td>
                        <td class="col-ht"><?= htmlspecialchars($height) ?></td>
                        <td class="col-wt"><?= htmlspecialchars($weight) ?></td>
                        <td class="col-dyr"><?= htmlspecialchars($draftYear) ?></td>
                        <td class="col-drnd"><?= htmlspecialchars($draftRound) ?></td>
                        <td class="col-dovr"><?= htmlspecialchars($draftOverall) ?></td>
                        <td class="col-gp" style="text-align:center;"><?= (int) $gp ?></td>
                        <td class="col-g" style="text-align:center;"><?= (int) $g ?></td>
                        <td class="col-a" style="text-align:center;"><?= (int) $a ?></td>
                        <td class="col-p" style="text-align:center; font-weight:800;"><?= (int) $p ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>

                </table>
              </div>
            </div>

            <?php /* Faceoff data build */ ?>
            <?php
            // === Build maps + parse PBP (after $players/$aliases exist) ===
            if (!isset($FO_PBP)) {
              $players_arr = (isset($players) && is_array($players)) ? $players : [];
              $aliases_arr = (isset($aliases) && is_array($aliases)) ? $aliases : [];

              if (!function_exists('load_team_full_to_code')) {
                function load_team_full_to_code(string $teamsJsonPath): array
                {
                  $out = [];
                  if (is_readable($teamsJsonPath)) {
                    $json = json_decode((string) file_get_contents($teamsJsonPath), true);
                    if (isset($json['teams']) && is_array($json['teams'])) {
                      foreach ($json['teams'] as $t) {
                        $full = $t['fullName'] ?? ($t['name'] ?? '');
                        $abbr = $t['abbr'] ?? ($t['triCode'] ?? '');
                        if ($full && $abbr)
                          $out[$full] = strtoupper($abbr);
                      }
                    }
                  }
                  return $out;
                }
              }

              // recursive finder so season subfolders work
              if (!function_exists('find_pbp_files')) {
                function find_pbp_files(array $roots): array
                {
                  $out = [];
                  foreach ($roots as $root) {
                    if (!is_dir($root))
                      continue;
                    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
                    foreach ($it as $f) {
                      $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
                      if ($ext === 'txt' || $ext === 'html')
                        $out[] = $f->getPathname();
                    }
                  }
                  return $out;
                }
              }

              $TEAM_FULL_TO_CODE = load_team_full_to_code(__DIR__ . '/data/uploads/teams.json'); // optional for OZ/DZ
              $ROSTER_MAP = make_roster_map_from_rows($players_arr, $aliases_arr, $teamAbbrFromId ?? null);
              $__pbp_files = find_pbp_files([
                __DIR__ . '/data/uploads',
                __DIR__ . '/data/uploads/boxscores',
                __DIR__ . '/uploads/pbp',
                __DIR__ . '/uploads/boxscores',
              ]);
              $DERIVED_PBP = __DIR__ . '/data/derived/pbp-faceoffs-players.json';
              $FO_PBP = [];
              if (is_readable($DERIVED_PBP)) {
                $FO_PBP = json_decode((string) file_get_contents($DERIVED_PBP), true) ?: [];
              } else {
                $FO_PBP = [];
              }
              // debug once if needed:
              // echo "<!-- PBP files: ".count($__pbp_files)." ; Bedard: ".(isset($FO_PBP[normalize_name_key('Connor Bedard')])?'yes':'no')." -->";
            }
            ?>

            <?php if (!empty($_GET['debug'])): ?>
              <?php
              // Pick any player you see in the table (using your example)
              $__probe_name = 'Alex Newhook';
              $__probe_key = normalize_name_key($__probe_name);
              $__probe_roster_team = isset($ROSTER_MAP) ? ($ROSTER_MAP[$__probe_key] ?? 'MISSING') : 'ROSTER_MAP missing';
              $__probe_stats = isset($FO_PBP[$__probe_key]) ? json_encode($FO_PBP[$__probe_key]) : 'MISSING';

              echo "<!-- PBP files: " . (isset($__pbp_files) ? count($__pbp_files) : 0) . " -->\n";
              echo "<!-- Roster map for {$__probe_name} [{$__probe_key}]: {$__probe_roster_team} -->\n";
              echo "<!-- FO_PBP entry for {$__probe_name}: {$__probe_stats} -->\n";

              // Optional: show a few matched keys to prove parsing worked at all
              if (!empty($FO_PBP) && is_array($FO_PBP)) {
                $keys = array_slice(array_keys($FO_PBP), 0, 5);
                echo "<!-- FO_PBP keys sample: " . implode(', ', $keys) . " -->\n";
              }
              ?>
            <?php endif; ?>

            <!--Skaters - Face-off Percentages Sub-Tab -->
            <?php /* build FO_PBP data lives just above this panel */ ?>
            <div class="skaters-subpanel" data-subtab="faceoffs" hidden>
              <div class="table-scroll">
                <table class="bio-table" aria-label="Skaters Face-off Percentages">
                  <thead>
                    <tr>
                      <th class="col-rank">#</th>
                      <th class="col-name" data-sort="name">Player</th>
                      <th class="col-team" data-sort="team">Team</th>
                      <th class="col-pos" data-sort="pos">Pos</th>
                      <th class="col-gp" data-sort="gp">GP</th>
                      <th class="col-toi" data-sort="toi_gp">TOI/GP</th>

                      <th class="col-fo" data-sort="fo">FO</th>
                      <th class="col-evfo" data-sort="ev_fo">EV FO</th>
                      <th class="col-ppfo" data-sort="pp_fo">PP FO</th>
                      <th class="col-shfo" data-sort="sh_fo">SH FO</th>
                      <th class="col-ozfo" data-sort="oz_fo">OZ FO</th>
                      <th class="col-nzfo" data-sort="nz_fo">NZ FO</th>
                      <th class="col-dzfo" data-sort="dz_fo">DZ FO</th>

                      <th class="col-fowp" data-sort="fow_pct">FOW%</th>
                      <th class="col-evfowp" data-sort="ev_fow_pct">EV FOW%</th>
                      <th class="col-ppfowp" data-sort="pp_fow_pct">PP FOW%</th>
                      <th class="col-shfowp" data-sort="sh_fow_pct">SH FOW%</th>
                      <th class="col-ozfowp" data-sort="oz_fow_pct">OZ FOW%</th>
                      <th class="col-nzfowp" data-sort="nz_fow_pct">NZ FOW%</th>
                      <th class="col-dzfowp" data-sort="dz_fow_pct">DZ FOW%</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $rank = 1;
                    foreach ($players as $r):
                      $name = getName($r, $aliases);
                      if ($name === '')
                        continue;
                      $team = getTeam($r, $aliases, $teamAbbrFromId ?? null);
                      $pos = pos_code_from_flags($r, $aliases);
                      $gp = (int) value_of($r, 'gp', $aliases, 0);
                      $toi_sec_total = (int) value_of($r, 'prosecondplay', $aliases, 0);
                      $toi_gp_sec = $gp > 0 ? (int) round($toi_sec_total / $gp) : 0;
                      $toi_gp_str = sprintf('%d:%02d', intdiv($toi_gp_sec, 60), $toi_gp_sec % 60);

                      $csvWon = (int) value_of($r, 'profaceoffwon', $aliases, 0);
                      $csvTot = (int) value_of($r, 'profaceofftotal', $aliases, 0);

                      $k = normalize_name_key($name);
                      $pbp = isset($FO_PBP[$k]) ? $FO_PBP[$k] : [
                        'total_won' => 0,
                        'total_taken' => 0,
                        'ev_won' => 0,
                        'ev_taken' => 0,
                        'pp_won' => 0,
                        'pp_taken' => 0,
                        'sh_won' => 0,
                        'sh_taken' => 0,
                        'oz_won' => 0,
                        'oz_taken' => 0,
                        'nz_won' => 0,
                        'nz_taken' => 0,
                        'dz_won' => 0,
                        'dz_taken' => 0,
                      ];
                      if ($gp === 0) {
                        $pbp = [
                          'total_won' => 0,
                          'total_taken' => 0,
                          'ev_won' => 0,
                          'ev_taken' => 0,
                          'pp_won' => 0,
                          'pp_taken' => 0,
                          'sh_won' => 0,
                          'sh_taken' => 0,
                          'oz_won' => 0,
                          'oz_taken' => 0,
                          'nz_won' => 0,
                          'nz_taken' => 0,
                          'dz_won' => 0,
                          'dz_taken' => 0,
                        ];
                      }


                      $totalTaken = $csvTot ?: (int) $pbp['total_taken'];
                      $totalWon = $csvWon ?: (int) $pbp['total_won'];
                      $ppTaken = (int) $pbp['pp_taken'];
                      $ppWon = (int) $pbp['pp_won'];
                      $shTaken = (int) $pbp['sh_taken'];
                      $shWon = (int) $pbp['sh_won'];

                      // Derive EV from totals so any PP/SH we detect is subtracted out
                      $evTaken = max(0, $totalTaken - $ppTaken - $shTaken);
                      $evWon = max(0, $totalWon - $ppWon - $shWon);
                      $ozTaken = (int) $pbp['oz_taken'];
                      $ozWon = (int) $pbp['oz_won'];
                      $nzTaken = (int) $pbp['nz_taken'];
                      $nzWon = (int) $pbp['nz_won'];
                      $dzTaken = (int) $pbp['dz_taken'];
                      $dzWon = (int) $pbp['dz_won'];

                      $fowPct = pct($totalWon, $totalTaken);
                      $evPct = pct($evWon, $evTaken);
                      $ppPct = pct($ppWon, $ppTaken);
                      $shPct = pct($shWon, $shTaken);
                      $ozPct = pct($ozWon, $ozTaken);
                      $nzPct = pct($nzWon, $nzTaken);
                      $dzPct = pct($dzWon, $dzTaken);
                      ?>
                      <tr data-name="<?= htmlspecialchars($name) ?>" data-team="<?= htmlspecialchars($team) ?>"
                        data-pos="<?= htmlspecialchars($pos) ?>" data-gp="<?= (int) $gp ?>"
                        data-toi_gp="<?= htmlspecialchars($toi_gp_str) ?>" data-fo="<?= (int) $totalTaken ?>"
                        data-ev_fo="<?= (int) $evTaken ?>" data-pp_fo="<?= (int) $ppTaken ?>"
                        data-sh_fo="<?= (int) $shTaken ?>" data-oz_fo="<?= (int) $ozTaken ?>"
                        data-nz_fo="<?= (int) $nzTaken ?>" data-dz_fo="<?= (int) $dzTaken ?>" data-fow_pct="<?= $fowPct ?>"
                        data-ev_fow_pct="<?= $evPct ?>" data-pp_fow_pct="<?= $ppPct ?>" data-sh_fow_pct="<?= $shPct ?>"
                        data-oz_fow_pct="<?= $ozPct ?>" data-nz_fow_pct="<?= $nzPct ?>" data-dz_fow_pct="<?= $dzPct ?>">
                        <td class="col-rank"><?= $rank++ ?></td>
                        <td class="col-name"><?= htmlspecialchars($name) ?></td>
                        <td class="col-team" style="text-align:center;"><?= htmlspecialchars($team) ?></td>
                        <td class="col-pos" style="text-align:center;"><?= htmlspecialchars($pos) ?></td>
                        <td class="col-gp" style="text-align:center;"><?= (int) $gp ?></td>
                        <td class="col-toi" style="text-align:center;"><?= htmlspecialchars($toi_gp_str) ?></td>

                        <td class="col-fo" style="text-align:center;"><?= (int) $totalTaken ?></td>
                        <td class="col-evfo" style="text-align:center;"><?= (int) $evTaken ?></td>
                        <td class="col-ppfo" style="text-align:center;"><?= (int) $ppTaken ?></td>
                        <td class="col-shfo" style="text-align:center;"><?= (int) $shTaken ?></td>
                        <td class="col-ozfo" style="text-align:center;"><?= (int) $ozTaken ?></td>
                        <td class="col-nzfo" style="text-align:center;"><?= (int) $nzTaken ?></td>
                        <td class="col-dzfo" style="text-align:center;"><?= (int) $dzTaken ?></td>

                        <td class="col-fowp" style="text-align:center;"><?= $fowPct ?></td>
                        <td class="col-evfowp" style="text-align:center;"><?= $evPct ?></td>
                        <td class="col-ppfowp" style="text-align:center;"><?= $ppPct ?></td>
                        <td class="col-shfowp" style="text-align:center;"><?= $shPct ?></td>
                        <td class="col-ozfowp" style="text-align:center;"><?= $ozPct ?></td>
                        <td class="col-nzfowp" style="text-align:center;"><?= $nzPct ?></td>
                        <td class="col-dzfowp" style="text-align:center;"><?= $dzPct ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (empty($players)): ?>
                      <tr>
                        <td colspan="19" style="text-align:center; opacity:.7; padding:12px 6px;">No skater rows available
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <!--Skaters - Face-off Wins & Losses Sub-Tab -->
            <div class="skaters-subpanel" data-subtab="faceoffs-wl" hidden>
              <div class="table-scroll">
                <table class="bio-table" aria-label="Skaters Face-off Wins & Losses">

                  <thead>
                    <tr>
                      <th class="col-rank">#</th>
                      <th class="col-name" data-sort="name">Player</th>
                      <th class="col-team" data-sort="team">Team</th>
                      <th class="col-pos" data-sort="pos">Pos.</th>
                      <th class="col-gp" data-sort="gp">GP</th>
                      <th class="col-fo" data-sort="fo">FO</th>
                      <th class="col-fow" data-sort="fow">FOW</th>
                      <th class="col-fol" data-sort="fol">FOL</th>
                      <th class="col-fowp" data-sort="fow_pct">FOW%</th>
                      <th class="col-evfo" data-sort="ev_fo">EV FO</th>
                      <th class="col-evfow" data-sort="ev_fow">EV FOW</th>
                      <th class="col-evfol" data-sort="ev_fol">EV FOL</th>
                      <th class="col-ppfo" data-sort="pp_fo">PP FO</th>
                      <th class="col-ppfow" data-sort="pp_fow">PP FOW</th>
                      <th class="col-ppfol" data-sort="pp_fol">PP FOL</th>
                      <th class="col-shfo" data-sort="sh_fo">SH FO</th>
                      <th class="col-shfow" data-sort="sh_fow">SH FOW</th>
                      <th class="col-shfol" data-sort="sh_fol">SH FOL</th>
                      <th class="col-ozfo" data-sort="oz_fo">OZ FO</th>
                      <th class="col-ozfow" data-sort="oz_fow">OZ FOW</th>
                      <th class="col-ozfol" data-sort="oz_fol">OZ FOL</th>
                      <th class="col-nzfo" data-sort="nz_fo">NZ FO</th>
                      <th class="col-nzfow" data-sort="nz_fow">NZ FOW</th>
                      <th class="col-nzfol" data-sort="nz_fol">NZ FOL</th>
                      <th class="col-dzfo" data-sort="dz_fo">DZ FO</th>
                      <th class="col-dzfow" data-sort="dz_fow">DZ FOW</th>
                      <th class="col-dzfol" data-sort="dz_fol">DZ FOL</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php
                    $rank = 1;
                    foreach ($players as $row):
                      $uid = function_exists('value_of') ? value_of($row, 'uniqueid', $aliases ?? [], '') : ($row['UniqueID'] ?? '');
                      $name = function_exists('getName') ? getName($row, $aliases ?? []) : ($row['Name'] ?? '—');
                      $team = function_exists('getTeam') ? getTeam($row, $aliases ?? [], $teamAbbrFromId ?? null) : ($row['Team'] ?? '');
                      $pos = function_exists('pos_code_from_flags') ? pos_code_from_flags($row, $aliases ?? []) : strtoupper((string) (function_exists('value_of') ? value_of($row, 'pos', $aliases ?? [], '') : ($row['Pos'] ?? '')));
                      $gp = (int) (function_exists('value_of') ? (int) value_of($row, 'gp', $aliases ?? [], 0) : (int) ($row['GP'] ?? 0));
                      $toi_gp_str = function_exists('toi_per_game') ? toi_per_game($row) : '';

                      $k = normalize_name_key($name);
                      $pbp = isset($FO_PBP[$k]) ? $FO_PBP[$k] : [
                        'total_won' => 0,
                        'total_taken' => 0,
                        'ev_won' => 0,
                        'ev_taken' => 0,
                        'pp_won' => 0,
                        'pp_taken' => 0,
                        'sh_won' => 0,
                        'sh_taken' => 0,
                        'oz_won' => 0,
                        'oz_taken' => 0,
                        'nz_won' => 0,
                        'nz_taken' => 0,
                        'dz_won' => 0,
                        'dz_taken' => 0,
                      ];
                      if ($gp === 0) {
                        $pbp = [
                          'total_won' => 0,
                          'total_taken' => 0,
                          'ev_won' => 0,
                          'ev_taken' => 0,
                          'pp_won' => 0,
                          'pp_taken' => 0,
                          'sh_won' => 0,
                          'sh_taken' => 0,
                          'oz_won' => 0,
                          'oz_taken' => 0,
                          'nz_won' => 0,
                          'nz_taken' => 0,
                          'dz_won' => 0,
                          'dz_taken' => 0,
                        ];
                      }

                      $csvTot = (int) ($row['FO'] ?? 0);
                      $csvWon = (int) ($row['FOW'] ?? 0);

                      $totalTaken = $csvTot ?: (int) $pbp['total_taken'];
                      $totalWon = $csvWon ?: (int) $pbp['total_won'];
                      $totalLost = max(0, $totalTaken - $totalWon);
                      $fowPct = pct($totalWon, $totalTaken);

                      $ppTaken = (int) $pbp['pp_taken'];
                      $ppWon = (int) $pbp['pp_won'];
                      $shTaken = (int) $pbp['sh_taken'];
                      $shWon = (int) $pbp['sh_won'];
                      $evTaken = max(0, $totalTaken - $ppTaken - $shTaken);
                      $evWon = max(0, $totalWon - $ppWon - $shWon);
                      $evLost = max(0, $evTaken - $evWon);
                      $ppLost = max(0, $ppTaken - $ppWon);
                      $shLost = max(0, $shTaken - $shWon);

                      $ozTaken = (int) $pbp['oz_taken'];
                      $ozWon = (int) $pbp['oz_won'];
                      $ozLost = max(0, $ozTaken - $ozWon);
                      $nzTaken = (int) $pbp['nz_taken'];
                      $nzWon = (int) $pbp['nz_won'];
                      $nzLost = max(0, $nzTaken - $nzWon);
                      $dzTaken = (int) $pbp['dz_taken'];
                      $dzWon = (int) $pbp['dz_won'];
                      $dzLost = max(0, $dzTaken - $dzWon);
                      ?>
                      <tr data-name="<?= htmlspecialchars($name) ?>" data-team="<?= htmlspecialchars($team) ?>"
                        data-pos="<?= htmlspecialchars($pos) ?>" data-gp="<?= (int) $gp ?>"
                        data-fo="<?= (int) $totalTaken ?>" data-fow="<?= (int) $totalWon ?>"
                        data-fol="<?= (int) $totalLost ?>" data-fow_pct="<?= $fowPct ?>" data-ev_fo="<?= (int) $evTaken ?>"
                        data-ev_fow="<?= (int) $evWon ?>" data-ev_fol="<?= (int) $evLost ?>"
                        data-pp_fo="<?= (int) $ppTaken ?>" data-pp_fow="<?= (int) $ppWon ?>"
                        data-pp_fol="<?= (int) $ppLost ?>" data-sh_fo="<?= (int) $shTaken ?>"
                        data-sh_fow="<?= (int) $shWon ?>" data-sh_fol="<?= (int) $shLost ?>"
                        data-oz_fo="<?= (int) $ozTaken ?>" data-oz_fow="<?= (int) $ozWon ?>"
                        data-oz_fol="<?= (int) $ozLost ?>" data-nz_fo="<?= (int) $nzTaken ?>"
                        data-nz_fow="<?= (int) $nzWon ?>" data-nz_fol="<?= (int) $nzLost ?>"
                        data-dz_fo="<?= (int) $dzTaken ?>" data-dz_fow="<?= (int) $dzWon ?>"
                        data-dz_fol="<?= (int) $dzLost ?>">
                        <td class="col-rank"><?= $rank++ ?></td>
                        <td class="col-name"><?= htmlspecialchars($name) ?></td>
                        <td class="col-team" style="text-align:center;"><?= htmlspecialchars($team) ?></td>
                        <td class="col-pos" style="text-align:center;"><?= htmlspecialchars($pos) ?></td>
                        <td class="col-gp" style="text-align:center;"><?= (int) $gp ?></td>

                        <td class="col-fo" style="text-align:center;"><?= (int) $totalTaken ?></td>
                        <td class="col-fow" style="text-align:center;"><?= (int) $totalWon ?></td>
                        <td class="col-fol" style="text-align:center;"><?= (int) $totalLost ?></td>
                        <td class="col-fowp" style="text-align:center;"><?= $fowPct ?></td>

                        <td class="col-evfo" style="text-align:center;"><?= (int) $evTaken ?></td>
                        <td class="col-evfow" style="text-align:center;"><?= (int) $evWon ?></td>
                        <td class="col-evfol" style="text-align:center;"><?= (int) $evLost ?></td>

                        <td class="col-ppfo" style="text-align:center;"><?= (int) $ppTaken ?></td>
                        <td class="col-ppfow" style="text-align:center;"><?= (int) $ppWon ?></td>
                        <td class="col-ppfol" style="text-align:center;"><?= (int) $ppLost ?></td>

                        <td class="col-shfo" style="text-align:center;"><?= (int) $shTaken ?></td>
                        <td class="col-shfow" style="text-align:center;"><?= (int) $shWon ?></td>
                        <td class="col-shfol" style="text-align:center;"><?= (int) $shLost ?></td>

                        <td class="col-ozfo" style="text-align:center;"><?= (int) $ozTaken ?></td>
                        <td class="col-ozfow" style="text-align:center;"><?= (int) $ozWon ?></td>
                        <td class="col-ozfol" style="text-align:center;"><?= (int) $ozLost ?></td>

                        <td class="col-nzfo" style="text-align:center;"><?= (int) $nzTaken ?></td>
                        <td class="col-nzfow" style="text-align:center;"><?= (int) $nzWon ?></td>
                        <td class="col-nzfol" style="text-align:center;"><?= (int) $nzLost ?></td>

                        <td class="col-dzfo" style="text-align:center;"><?= (int) $dzTaken ?></td>
                        <td class="col-dzfow" style="text-align:center;"><?= (int) $dzWon ?></td>
                        <td class="col-dzfol" style="text-align:center;"><?= (int) $dzLost ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (empty($players)): ?>
                      <tr data-name="<?= htmlspecialchars($name) ?>" data-team="<?= htmlspecialchars($team) ?>"
                        data-pos="<?= htmlspecialchars($pos) ?>" data-gp="<?= (int) $gp ?>"
                        data-fo="<?= (int) $totalTaken ?>" data-fow="<?= (int) $totalWon ?>"
                        data-fol="<?= (int) $totalLost ?>" data-fow_pct="<?= $fowPct ?>" data-ev_fo="<?= (int) $evTaken ?>"
                        data-ev_fow="<?= (int) $evWon ?>" data-ev_fol="<?= (int) $evLost ?>"
                        data-pp_fo="<?= (int) $ppTaken ?>" data-pp_fow="<?= (int) $ppWon ?>"
                        data-pp_fol="<?= (int) $ppLost ?>" data-sh_fo="<?= (int) $shTaken ?>"
                        data-sh_fow="<?= (int) $shWon ?>" data-sh_fol="<?= (int) $shLost ?>"
                        data-oz_fo="<?= (int) $ozTaken ?>" data-oz_fow="<?= (int) $ozWon ?>"
                        data-oz_fol="<?= (int) $ozLost ?>" data-nz_fo="<?= (int) $nzTaken ?>"
                        data-nz_fow="<?= (int) $nzWon ?>" data-nz_fol="<?= (int) $nzLost ?>"
                        data-dz_fo="<?= (int) $dzTaken ?>" data-dz_fow="<?= (int) $dzWon ?>"
                        data-dz_fol="<?= (int) $dzLost ?>">
                        <td colspan="27" style="text-align:center; opacity:.7; padding:12px 6px;">No skater rows available
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>

              </div>
            </div>

          </section>

          <section id="tab-goalies" class="tab-panel" aria-labelledby="goalies" hidden>
            <!-- Goalies – Summary -->
            <div class="goalies-wrap">
              <!-- Goalies toolbar -->
              <div class="goalies-toolbar">
                <span class="label">Summary</span>
              </div>
              <div class="goalies-filters">
                <label>
                  Report
                  <select id="goalies-subtab">
                    <option value="summary" selected>Summary</option>
                    <!-- future: <option value="bio">Bio Info</option> -->
                    <!-- future: <option value="advanced">Advanced</option> -->
                  </select>
                </label>
                <label>
                  Rows
                  <select id="goalies-rows">
                    <option value="All" selected>All</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                  </select>
                </label>

              </div>
            </div>
            <div class="goalies-subpanel" data-subtab="summary">
              <div class="table-scroll">
                <table class="bio-table" aria-label="Goalies Summary">
                  <thead>
                    <tr>
                      <th class="col-rank">#</th>
                      <th class="col-name" data-sort="name">Player</th>
                      <th class="col-team" data-sort="team">Team</th>
                      <th class="col-gp" data-sort="gp">GP</th>
                      <th class="col-gs" data-sort="gs">GS</th>
                      <th class="col-w" data-sort="w">W</th>
                      <th class="col-l" data-sort="l">L</th>
                      <th class="col-ot" data-sort="ot">OT</th>
                      <th class="col-sa" data-sort="sa">SA</th>
                      <th class="col-svs" data-sort="svs">Svs</th>
                      <th class="col-ga" data-sort="ga">GA</th>
                      <th class="col-svpct" data-sort="sv_pct">Sv%</th>
                      <th class="col-gaa" data-sort="gaa">GAA</th>
                      <th class="col-toi" data-sort="toi_sec">TOI</th>
                      <th class="col-so" data-sort="so">SO</th>
                      <th class="col-a" data-sort="a">A</th>
                      <th class="col-pim" data-sort="pim">PIM</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    // helpers (fallbacks if your globals aren’t present)
                    if (!function_exists('pct1')) {
                      function pct1($num, $den)
                      {
                        return $den > 0 ? number_format($num * 100 / $den, 1) : '0.0';
                      }
                    }
                    if (!function_exists('fmt_hms')) {
                      function fmt_hms($s)
                      {
                        $h = intval($s / 3600);
                        $m = intval(($s % 3600) / 60);
                        $ss = intval($s % 60);
                        return sprintf('%d:%02d:%02d', $h, $m, $ss);
                      }
                    }

                    $rank = 1;
                    foreach (($goalies ?? []) as $row):
                      $name = function_exists('getName') ? getName($row, $aliases ?? []) : ($row['Name'] ?? '—');
                      $team = function_exists('getTeam') ? getTeam($row, $aliases ?? [], $teamAbbrFromId ?? null) : ($row['Team'] ?? '');

                      // ✅ use alias keys that map to normalized headers
                      $gp = (int) value_of($row, 'gp', $aliases, 0);   // progp / gp
                      $gs = (int) value_of($row, 'gs', $aliases, 0);   // prostartgoaler / gs
                      $w = (int) value_of($row, 'w', $aliases, 0);   // prow / w
                      $l = (int) value_of($row, 'l', $aliases, 0);   // prol / l
                      $ot = (int) value_of($row, 'ot', $aliases, 0);   // prootl / ot
                      $sa = (int) value_of($row, 'sa', $aliases, 0);   // prosa / sa
                      $ga = (int) value_of($row, 'ga', $aliases, 0);   // proga / ga
                      $sec = (int) value_of($row, 'sec', $aliases, 0);   // prosecplay / toi (sec)
                      $mins = (int) value_of($row, 'mins', $aliases, 0);   // (optional minutes if present)
                      $svs = (int) value_of($row, 'svs', $aliases, max(0, $sa - $ga)); // fallback to SA-GA
                      $so = (int) value_of($row, 'so', $aliases, 0);
                      $a = (int) value_of($row, 'proa', $aliases, 0);  // you already had 'proa' alias
                      $pim = (int) value_of($row, 'pim', $aliases, 0);

                      // GP==0 guard
                      if ($gp === 0) {
                        $gs = $w = $l = $ot = $sa = $ga = $sec = $so = $a = $pim = 0;
                      }

                      // Derived
                      $sv_pct = ($sa > 0) ? number_format($svs * 100 / $sa, 1) : '0.0';
                      $gaa = ($sec > 0) ? number_format(($ga * 3600) / $sec, 2) : '0.00';

                      // TOI string
                      if (!function_exists('fmt_minsec')) {
                        function fmt_minsec($sec)
                        {
                          if ($sec <= 0)
                            return '0:00';
                          $minutes = intdiv($sec, 60);
                          $seconds = $sec % 60;
                          return sprintf('%d:%02d', $minutes, $seconds);
                        }
                      }
                      $toiStr = fmt_minsec($sec);
                      ?>
                      <tr data-name="<?= htmlspecialchars($name) ?>" data-team="<?= htmlspecialchars($team) ?>"
                        data-gp="<?= $gp ?>" data-gs="<?= $gs ?>" data-w="<?= $w ?>" data-l="<?= $l ?>"
                        data-ot="<?= $ot ?>" data-sa="<?= $sa ?>" data-svs="<?= $svs ?>" data-ga="<?= $ga ?>"
                        data-sv_pct="<?= $sv_pct ?>" data-gaa="<?= $gaa ?>" data-toi_sec="<?= $sec ?>"
                        data-so="<?= $so ?>" data-a="<?= $a ?>" data-pim="<?= $pim ?>">
                        <td class="col-rank"><?= $rank ?></td>
                        <td class="col-name"><?= htmlspecialchars($name) ?></td>
                        <td class="col-team"><?= htmlspecialchars($team) ?></td>
                        <td class="col-gp"><?= $gp ?></td>
                        <td class="col-gs"><?= $gs ?></td>
                        <td class="col-w"><?= $w ?></td>
                        <td class="col-l"><?= $l ?></td>
                        <td class="col-ot"><?= $ot ?></td>
                        <td class="col-sa"><?= $sa ?></td>
                        <td class="col-svs"><?= $svs ?></td>
                        <td class="col-ga"><?= $ga ?></td>
                        <td class="col-svpct"><?= $sv_pct ?></td>
                        <td class="col-gaa"><?= $gaa ?></td>
                        <td class="col-toi"><?= $toiStr ?></td>
                        <td class="col-so"><?= $so ?></td>
                        <td class="col-a"><?= $a ?></td>
                        <td class="col-pim"><?= $pim ?></td>
                      </tr>

                      <?php
                      $rank++;
                    endforeach;
                    ?>
                    <?php if (empty($goalies ?? [])): ?>
                      <tr>
                        <td colspan="17" style="text-align:center; opacity:.7; padding:12px 6px;">No goalie rows available
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </section>

          <!-- TEAMS TAB -->

          <section id="tab-teams" class="tab-panel " aria-labelledby="teams" hidden>
            <div class="teams-wrap">
              <!-- Teams toolbar -->
              <div class="teams-toolbar">
                <span class="label">Summary</span>
              </div>
              <div class="teams-filters">
                <label>
                  Report
                  <select id="teams-subtab">
                    <option value="summary" selected>Summary</option>

                  </select>
                </label>
                <label>
                  Rows
                  <select id="teams-rows">
                    <option value="All" selected>All</option>
                    <option value="20">20</option>
                    <option value="15">15</option>
                    <option value="10">10</option>
                    <option value="5">5</option>
                  </select>
                </label>
              </div>
            </div>
            <div class="teams-subpanel" data-subtab="summary">
              <div class="table-scroll">
                <table class="teams-table" aria-label="Teams Summary">
                  <thead>
                    <tr>
                      <th class="col-rank">#</th>
                      <th class="col-team" data-sort="team">Team</th>
                      <th class="col-gp" data-sort="gp">GP</th>
                      <th class="col-w" data-sort="w">W</th>
                      <th class="col-l" data-sort="l">L</th>
                      <th class="col-ot" data-sort="ot">OT</th>
                      <th class="col-p" data-sort="p">P</th>
                      <th class="col-p_pct" data-sort="p_pct">P%</th>
                      <th class="col-rw" data-sort="rw">RW</th>
                      <th class="col-row" data-sort="row">ROW</th>
                      <th class="col-sow" data-sort="sow">SOW</th>
                      <th class="col-gf" data-sort="gf">GF</th>
                      <th class="col-ga" data-sort="ga">GA</th>
                      <th class="col-gf_gp" data-sort="gf_gp">GF/GP</th>
                      <th class="col-ga_gp" data-sort="ga_gp">GA/GP</th>
                      <th class="col-pp_pct" data-sort="pp_pct">PP%</th>
                      <th class="col-pk_pct" data-sort="pk_pct">PK%</th>
                      <th class="col-net_pp_pct" data-sort="net_pp_pct">Net PP%</th>
                      <th class="col-net_pk_pct" data-sort="net_pk_pct">Net PK%</th>
                      <th class="col-shots_gp" data-sort="shots_gp">Shots/GP</th>
                      <th class="col-sa_gp" data-sort="sa_gp">SA/GP</th>
                      <th class="col-fow_pct" data-sort="fow_pct">FOW%</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    /* ===== DEBUG SWITCH (set to true to see why it’s empty) ===== */
                    $__DEBUG_TEAMS_KEYS = false;

                    /* ===== Helpers (only if not already defined elsewhere) ===== */
                    if (!function_exists('safe_div')) {
                      function safe_div($n, $d)
                      {
                        return $d > 0 ? ($n / $d) : 0.0;
                      }
                    }
                    if (!function_exists('fmt1')) {
                      function fmt1($v)
                      {
                        return number_format((float) $v, 1);
                      }
                    }
                    if (!function_exists('fmt2')) {
                      function fmt2($v)
                      {
                        return number_format((float) $v, 2);
                      }
                    }
                    if (!function_exists('fmt3')) {
                      function fmt3($v)
                      {
                        return number_format((float) $v, 3);
                      }
                    }
                    if (!function_exists('value_of')) {
                      function value_of($row, $key, $aliases, $default = '')
                      {
                        if (isset($row[$key]) && $row[$key] !== '')
                          return $row[$key];
                        if (isset($aliases[$key])) {
                          foreach ($aliases[$key] as $alt) {
                            if (isset($row[$alt]) && $row[$alt] !== '')
                              return $row[$alt];
                          }
                        }
                        return $default;
                      }
                    }

                    // === TEAMS aliases (matches your exact CSV headers) ===
                    $aliases_teams = [
                      'team' => ['name', 'team', 'proteam'],   // full team name
                      'team_code' => ['abbre', 'abbreviation'],    // 3-letter code (optional)
                      'gp' => ['gp', 'gamesplayed', 'games'],
                      'w' => ['w', 'wins'],
                      'l' => ['l', 'losses'],
                      't' => ['t', 'ties'],
                      'otw' => ['otw', 'otwins', 'ot_win'],
                      'otl' => ['otl', 'otlosses', 'ot_loss'],
                      'sow' => ['sow', 'shootoutwins', 'shootout_win'],
                      'sol' => ['sol', 'shootoutlosses', 'shootout_loss'],
                      'p' => ['points', 'p', 'pts'],
                      'gf' => ['gf', 'goalsfor'],
                      'ga' => ['ga', 'goalsagainst'],

                      'rw' => ['rw', 'regulationwins'],
                      'row' => ['row', 'reg_ot_wins'],
                      'p_pct' => ['p%', 'points%', 'pointspct', 'points_pct'],

                      'gf_gp' => ['gf_gp', 'goalsforpergame'],
                      'ga_gp' => ['ga_gp', 'goalsagainstpergame'],
                      'pp_pct' => ['pp%', 'pppct', 'pp_percentage', 'powerplay%', 'powerplaypct'],
                      'pk_pct' => ['pk%', 'pkpct', 'pk_percentage', 'penaltykill%', 'penaltykillpct'],
                      'net_pp_pct' => ['netpp%', 'netpppct'],
                      'net_pk_pct' => ['netpk%', 'netpkpct'],
                      'shots_gp' => ['shots/gp', 'shotspergp', 'shotspergame'],
                      'sa_gp' => ['shotsagainst/gp', 'shotsagainstpergp', 'shotsagainstpergame'],
                      'fow_pct' => ['fo%', 'fowpct', 'faceoff%', 'faceoffpct', 'faceoffpercentage'],

                      'ppattemp' => ['ppattemp', 'ppatt', 'ppattempts', 'pp_attempts'],
                      'ppgoal' => ['ppgoal', 'ppg', 'ppgoals', 'pp_goals'],
                      'pkattemp' => ['pkattemp', 'pkatt', 'pkattempts', 'pk_attempts'],
                      'pkgoalga' => ['pkgoalga', 'pkga', 'pkgoalagainst', 'pk_goals_against'],
                      'shotsfor' => ['shotsfor', 'sf', 'shots_for'],
                      'shotsaga' => ['shotsaga', 'sa', 'shots_against'],

                      'fow_d_won' => ['faceoffwondefensifzone', 'fowdz', 'faceoffwondz'],
                      'fow_d_total' => ['faceofftotaldefensifzone', 'fodz', 'faceofftotaldz'],
                      'fow_o_won' => ['faceoffwonoffensifzone', 'fowoz', 'faceoffwonoz'],
                      'fow_o_total' => ['faceofftotaloffensifzone', 'fooz', 'faceofftotaloz'],
                      'fow_n_won' => ['faceoffwonneutralzone', 'fownz', 'faceoffwonnz'],
                      'fow_n_total' => ['faceofftotalneutralzone', 'fonz', 'faceofftotalnz'],
                    ];


                    /* ===== RENDER or DEBUG ===== */
                    if (empty($teams) || !is_array($teams)) {
                      if ($__DEBUG_TEAMS_KEYS) {
                        echo '<tr><td colspan="22" style="padding:10px">';
                        echo '<div class="diag"><strong>Teams is empty.</strong><br>Check where $teams is populated (CSV load).';
                        echo '</div></td></tr>';
                      }
                    } else {
                      if ($__DEBUG_TEAMS_KEYS) {
                        $first = $teams[0];
                        echo '<tr><td colspan="22" style="padding:10px">';
                        echo '<div class="diag"><strong>First Teams row keys:</strong><br><code>';
                        echo htmlspecialchars(implode(' | ', array_keys($first)));
                        echo '</code></div></td></tr>';
                      }

                      $rank = 1;
                      foreach ($teams as $t) {
                        $team = value_of($t, 'team', $aliases_teams, '');

                        $gp = (int) value_of($t, 'gp', $aliases_teams, 0);
                        $w = (int) value_of($t, 'w', $aliases_teams, 0);
                        $l = (int) value_of($t, 'l', $aliases_teams, 0);
                        $otw = (int) value_of($t, 'otw', $aliases_teams, 0);
                        $pts = (int) value_of($t, 'p', $aliases_teams, 0);
                        $sow = (int) value_of($t, 'sow', $aliases_teams, 0);
                        $gf = (int) value_of($t, 'gf', $aliases_teams, 0);
                        $ga = (int) value_of($t, 'ga', $aliases_teams, 0);

                        $otl = (int) value_of($t, 'otl', $aliases_teams, max(0, $gp - $w - $l));
                        $rw = (int) value_of($t, 'rw', $aliases_teams, max(0, $w - ($otw + $sow)));
                        $row = (int) value_of($t, 'row', $aliases_teams, max(0, $w - $sow));

                        $p_pct_val = safe_div($pts, 2 * max($gp, 1));  // 0..1
                        $p_pct = fmt3($p_pct_val);

                        $gf_gp_val = safe_div($gf, max($gp, 1));
                        $ga_gp_val = safe_div($ga, max($gp, 1));
                        $gf_gp = fmt2($gf_gp_val);
                        $ga_gp = fmt2($ga_gp_val);

                        $pp_att = (int) value_of($t, 'ppattemp', $aliases_teams, 0);
                        $pp_g = (int) value_of($t, 'ppgoal', $aliases_teams, 0);
                        $pk_att = (int) value_of($t, 'pkattemp', $aliases_teams, 0);
                        $pk_ga = (int) value_of($t, 'pkgoalga', $aliases_teams, 0);

                        $pp_pct_val = safe_div($pp_g, $pp_att) * 100;
                        $pk_pct_val = safe_div(($pk_att - $pk_ga), max($pk_att, 1)) * 100;
                        $pp_pct = fmt1($pp_pct_val);
                        $pk_pct = fmt1($pk_pct_val);
                        $net_pp_pct_val = $pp_pct_val;   // no SHG data yet
                        $net_pk_pct_val = $pk_pct_val;
                        $net_pp_pct = fmt1($net_pp_pct_val);
                        $net_pk_pct = fmt1($net_pk_pct_val);

                        $sf = (int) value_of($t, 'shotsfor', $aliases_teams, 0);
                        $sa = (int) value_of($t, 'shotsaga', $aliases_teams, 0);
                        $shots_gp_val = safe_div($sf, max($gp, 1));
                        $sa_gp_val = safe_div($sa, max($gp, 1));
                        $shots_gp = fmt2($shots_gp_val);
                        $sa_gp = fmt2($sa_gp_val);

                        $fow_w_d = (int) value_of($t, 'fow_d_won', $aliases_teams, 0);
                        $fow_t_d = (int) value_of($t, 'fow_d_total', $aliases_teams, 0);
                        $fow_w_o = (int) value_of($t, 'fow_o_won', $aliases_teams, 0);
                        $fow_t_o = (int) value_of($t, 'fow_o_total', $aliases_teams, 0);
                        $fow_w_n = (int) value_of($t, 'fow_n_won', $aliases_teams, 0);
                        $fow_t_n = (int) value_of($t, 'fow_n_total', $aliases_teams, 0);

                        $fo_wins = $fow_w_d + $fow_w_o + $fow_w_n;
                        $fo_total = $fow_t_d + $fow_t_o + $fow_t_n;
                        $fow_pct_val = safe_div($fo_wins, max($fo_total, 1)) * 100;
                        $fow_pct = fmt1($fow_pct_val);

                        echo '<tr
      data-team="' . htmlspecialchars($team) . '"
      data-gp="' . $gp . '" data-w="' . $w . '" data-l="' . $l . '" data-ot="' . $otl . '"
      data-p="' . $pts . '" data-p_pct="' . $p_pct_val . '"
      data-rw="' . $rw . '" data-row="' . $row . '" data-sow="' . $sow . '"
      data-gf="' . $gf . '" data-ga="' . $ga . '"
      data-gf_gp="' . $gf_gp_val . '" data-ga_gp="' . $ga_gp_val . '"
      data-pp_pct="' . $pp_pct_val . '" data-pk_pct="' . $pk_pct_val . '"
      data-net_pp_pct="' . $net_pp_pct_val . '" data-net_pk_pct="' . $net_pk_pct_val . '"
      data-shots_gp="' . $shots_gp_val . '" data-sa_gp="' . $sa_gp_val . '"
      data-fow_pct="' . $fow_pct_val . '"
    >';
                        echo '<td class="col-rank">' . $rank . '</td>';
                        echo '<td class="col-team">' . htmlspecialchars($team) . '</td>';
                        echo '<td class="col-gp">' . $gp . '</td>';
                        echo '<td class="col-w">' . $w . '</td>';
                        echo '<td class="col-l">' . $l . '</td>';
                        echo '<td class="col-ot">' . $otl . '</td>';
                        echo '<td class="col-p">' . $pts . '</td>';
                        echo '<td class="col-p_pct">' . $p_pct . '</td>';
                        echo '<td class="col-rw">' . $rw . '</td>';
                        echo '<td class="col-row">' . $row . '</td>';
                        echo '<td class="col-sow">' . $sow . '</td>';
                        echo '<td class="col-gf">' . $gf . '</td>';
                        echo '<td class="col-ga">' . $ga . '</td>';
                        echo '<td class="col-gf_gp">' . $gf_gp . '</td>';
                        echo '<td class="col-ga_gp">' . $ga_gp . '</td>';
                        echo '<td class="col-pp_pct">' . $pp_pct . '</td>';
                        echo '<td class="col-pk_pct">' . $pk_pct . '</td>';
                        echo '<td class="col-net_pp_pct">' . $net_pp_pct . '</td>';
                        echo '<td class="col-net_pk_pct">' . $net_pk_pct . '</td>';
                        echo '<td class="col-shots_gp">' . $shots_gp . '</td>';
                        echo '<td class="col-sa_gp">' . $sa_gp . '</td>';
                        echo '<td class="col-fow_pct">' . $fow_pct . '</td>';
                        echo '</tr>';
                        $rank++;
                      }
                    }
                    ?>
                  </tbody>
                </table>

              </div>
            </div>
        </div>
      </div>
      </section>
      <script src="assets/js/statistics.js" defer></script>
</body>

</html>