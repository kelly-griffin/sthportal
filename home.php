<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/tx_helpers.php';
require_once __DIR__ . '/includes/sim_clock.php';

$SIM_DATE = sim_clock_now();

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
/* ---------- Sources (match transactions.php) ---------- */
$TEAMS_JSON = __DIR__ . '/data/uploads/teams.json';
$LEAGUE_LOG = __DIR__ . '/data/uploads/UHA-V3LeagueLog.csv';
$TRADE_INDEX = __DIR__ . '/data/state/trade_dates.json';
$SIGNING_INDEX = __DIR__ . '/data/state/signing_dates.json';
$MOVE_INDEX = __DIR__ . '/data/state/move_dates.json';

$CODE2NAME = load_team_names_by_code($TEAMS_JSON);
$CODE2ABBR = load_team_codes($TEAMS_JSON);

/* ---------- Parse (same helpers used by transactions.php) ---------- */
$BASE_DRAFT_YEAR = 2025; // keep in sync with transactions.php
$trades = parse_trades_with_index($LEAGUE_LOG, $TEAMS_JSON, $TRADE_INDEX, $SIM_DATE);
$signings = parse_signings_with_index($LEAGUE_LOG, $TEAMS_JSON, $SIGNING_INDEX, $SIM_DATE);
$moves = parse_roster_moves_with_index($LEAGUE_LOG, $TEAMS_JSON, $MOVE_INDEX, $SIM_DATE);

/* ---------- Merge and take latest 10 ---------- */
$merged = [];
foreach ($trades as $t) {
    $t['__type'] = 'trade';
    $merged[] = $t;
}
foreach ($signings as $s) {
    $s['__type'] = 'signing';
    $merged[] = $s;
}
foreach ($moves as $m) {
    $m['__type'] = 'move';
    $merged[] = $m;
}

usort($merged, function ($a, $b) {
    $da = strtotime($a['date'] ?? '');
    $db = strtotime($b['date'] ?? '');
    if ($da === $db)
        return 0;
    return $db <=> $da; // newest first
});
$latestTxTop = array_slice($merged, 0, 50);

/* ---------- Tiny helpers for the one-line summaries ---------- */
function _asset_count($s)
{
    $s = str_replace(' + ', '+', (string) $s);
    $arr = array_filter(array_map('trim', explode('+', $s)));
    return count($arr);
}

function tx_norm_asset(string $s): string
{
    // Trim noise and shorten common phrases
    $s = preg_replace('~\s*\(.*?\)~', '', $s);                // drop parentheticals
    $s = preg_replace('~^Rights to\s+~i', '', $s);            // "Rights to X" → "X"
    $s = preg_replace('~\bConditional\b~i', 'cond.', $s);     // conditional → cond.
    $s = preg_replace('~\bRound\b~i', 'Rd', $s);              // Round → Rd
    $map = ['First' => '1st', 'Second' => '2nd', 'Third' => '3rd', 'Fourth' => '4th', 'Fifth' => '5th', 'Sixth' => '6th', 'Seventh' => '7th'];
    $s = str_replace(array_keys($map), array_values($map), $s);
    return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8');
}

function tx_top_assets(string $assets, int $max = 2): array
{
    $parts = array_values(array_filter(array_map('trim', preg_split('~\s*\+\s*~', (string) $assets))));
    $out = [];
    foreach ($parts as $p) {
        $out[] = tx_norm_asset($p);
        if (count($out) >= $max)
            break;
    }
    return $out ?: ['(details)'];
}

function tx_home_summary(array $x, array $abbr, int $baseDraftYear): string
{
    switch ($x['__type']) {
        case 'trade':
            $fromA = htmlspecialchars($abbr[$x['from']] ?? $x['from'], ENT_QUOTES, 'UTF-8');
            $toA = htmlspecialchars($abbr[$x['to']] ?? $x['to'], ENT_QUOTES, 'UTF-8');

            $leftList = implode('; ', tx_top_assets($x['assets_out'] ?? '', 2));
            $rightList = implode('; ', tx_top_assets($x['assets_in'] ?? '', 2));

            // Home already shows team logos/abbr in the "who" column,
            // so the desc just lists each side's top pieces:
            return "{$fromA}: {$leftList} | {$toA}: {$rightList}";

        case 'signing':
            // Team already shown in the "who" column → focus the line on player/terms
            $yrs = (int) ($x['years'] ?? 0);
            $aav = htmlspecialchars($x['aav'] ?? '', ENT_QUOTES, 'UTF-8');
            $ply = htmlspecialchars($x['player'] ?? '', ENT_QUOTES, 'UTF-8');
            return "{$ply} • {$yrs}yr • AAV {$aav}";

        default: // move
            // Team already shown in the "who" column → focus on player/action
            $ply = htmlspecialchars($x['player'] ?? '', ENT_QUOTES, 'UTF-8');
            $act = htmlspecialchars($x['action'] ?? '', ENT_QUOTES, 'UTF-8');
            $to = !empty($x['to']) ? ' • to ' . htmlspecialchars($x['to'], ENT_QUOTES, 'UTF-8') : '';
            $from = !empty($x['from']) ? ' • from ' . htmlspecialchars($x['from'], ENT_QUOTES, 'UTF-8') : '';
            return "{$ply} • {$act}{$to}{$from}";
    }
}


function tx_trade_headline(array $t, array $abbr, int $max = 2): string
{
    $fromA = htmlspecialchars($abbr[$t['from']] ?? $t['from'], ENT_QUOTES, 'UTF-8');
    $toA = htmlspecialchars($abbr[$t['to']] ?? $t['to'], ENT_QUOTES, 'UTF-8');

    $leftList = implode('; ', tx_top_assets($t['assets_out'] ?? '', $max));
    $rightList = implode('; ', tx_top_assets($t['assets_in'] ?? '', $max));

    // TOR ↔ VGK — TOR: X; Y | VGK: A; B
    return "{$fromA} ↔ {$toA} — {$fromA}: {$leftList} | {$toA}: {$rightList}";
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=1280, initial-scale=1" />
    <title>United Hockey Association — Home</title>
</head>

<body>
    <div class="site">

        <?php
        // Home — wire Skaters (Forwards) leaders (PTS/G/A) using the same sources as statistics.php
        $uploads = __DIR__ . '/data/uploads/';

        // locate files (same candidates as statistics.php)
        $playersFile = null;
        foreach (['UHA-V3Players.csv', 'UHA-Players.csv'] as $cand) {
            if (is_readable($uploads . $cand)) {
                $playersFile = $uploads . $cand;
                break;
            }
        }

        // Optional team id->abbr mapping (JSON) — used when CSV gives team IDs
        $teamAbbrFromId = [];
        $teamsJson = $uploads . 'teams.json';
        if (is_readable($teamsJson)) {
            $json = json_decode((string) file_get_contents($teamsJson), true);
            if (!empty($json['teams'])) {
                foreach ($json['teams'] as $t) {
                    $teamAbbrFromId[(string) $t['id']] = $t['abbr'];
                }
            }
        }

        // helper: parse CSV to assoc rows with normalized keys
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
                    $row[$k] = $cols[$i] ?? null;
                }
                $rows[] = $row;
            }
            fclose($fh);
            return $rows;
        }

        // small helpers (subset lifted from statistics.php)
        function first_nonempty($row, $keys, $default = null)
        {
            foreach ($keys as $k) {
                if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null)
                    return $row[$k];
            }
            return $default;
        }
        $aliases = [
            'name' => ['name', 'player', 'playername', 'fullname', 'skater', 'playerfullname'],
            'team' => ['team', 'abbre', 'abbr', 'teamabbr', 'teamabbre', 'teamid', 'teamnumber', 'proteam', 'proteamid', 'proteamabbr'],
            'pos' => ['position', 'pos', 'role', 'p', 'positioncode'],
            'posd' => ['posd'],
            'g' => ['prog', 'g', 'goals', 'goalsfor', 'goalsscored'],
            'a' => ['proa', 'a', 'assists'],
            'p' => ['propoint', 'p', 'pts', 'points', 'pointstotal'],
            'gp' => ['gp', 'gamesplayed', 'games', 'played', 'progp'],
            // inputs to calculate advanced goalie metrics
            'ga' => ['proga', 'ga', 'goalsagainst', 'goalsallowed'],
            'sa' => ['prosa', 'sa', 'shotsagainst', 'shotsfaced', 'shots'],
            'sec' => ['prosecplay', 'prosec', 'secondsplayed', 'proseconds', 'timeonice', 'toi'],
            'mins' => ['prominuteplay', 'prominsplay', 'minutesplayed', 'mins', 'minutes'],
            // leaderboard metrics (direct)
            'so' => ['proshutout', 'so', 'shutouts'],
            'w' => ['prow', 'w', 'wins'],
            'rookie' => ['rookie', 'isrookie', 'rook', 'rookieflag'],
        ];
        function value_of($row, $aliasKey, $aliases, $default = null)
        {
            $keys = isset($aliases[$aliasKey]) ? $aliases[$aliasKey] : [$aliasKey];
            return first_nonempty($row, $keys, $default);
        }
        function as_int($v, $def = 0)
        {
            return is_numeric($v) ? (int) $v : $def;
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
            return in_array($s, ['1', 'y', 'yes', 'true', 't'], true);
        }

        // same helpers used on statistics.php
        function starts_with($hay, $needle)
        {
            return substr($hay, 0, strlen($needle)) === $needle;
        }
        // PosD-only detection (matches the simulator export exactly)
        function isDefense($r, $aliases)
        {
            return truthy(value_of($r, 'posd', $aliases, ''));
        }
        function getName($r, $aliases)
        {
            $name = value_of($r, 'name', $aliases, '');
            if ($name === '') {
                foreach ($r as $k => $v) {
                    if (preg_match('/^[A-Za-z\-\.\']+\s+[A-Za-z\-\.\']+$/', (string) $v))
                        return (string) $v;
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
        // --- Goalie helpers ---
        const ELIG_FRAC = 0.31; // must have >=31% of team GP
        const ELIG_FALLBACK = 25;   // if team GP unknown, require 25 GP
        
        function as_float($v, $def = 0.0)
        {
            return is_numeric($v) ? (float) $v : $def;
        }

        function build_team_gp_map(array $teamRows, array $aliases, array $teamAbbrFromId): array
        {
            $map = [];
            foreach ($teamRows as $t) {
                $abbr = getTeam($t, $aliases, $teamAbbrFromId);
                $gp = as_int(value_of($t, 'gp', $aliases, 0), 0);
                if ($abbr !== '')
                    $map[$abbr] = max($gp, $map[$abbr] ?? 0);
            }
            return $map;
        }

        function goalie_eligible(array $row, array $teamGP, array $aliases, array $teamAbbrFromId): bool
        {
            $gp = as_int(value_of($row, 'gp', $aliases, 0), 0);
            $team = getTeam($row, $aliases, $teamAbbrFromId);
            $min = isset($teamGP[$team]) ? (int) ceil($teamGP[$team] * ELIG_FRAC) : ELIG_FALLBACK;
            return $gp >= $min;
        }

        // metric accessors
        $valGAA = function (array $r) use ($aliases) {
            $ga = as_float(value_of($r, 'ga', $aliases, 0.0), 0.0);
            $sec = as_float(value_of($r, 'sec', $aliases, 0.0), 0.0);
            $min = as_float(value_of($r, 'mins', $aliases, 0.0), 0.0);
            if ($sec > 0)
                return ($ga * 3600) / $sec; // GA per 60 using seconds
            if ($min > 0)
                return ($ga * 60) / $min; // fallback if only minutes exist
            return 0.0;
        };
        $valSV = function (array $r) use ($aliases) {
            $ga = as_float(value_of($r, 'ga', $aliases, 0.0), 0.0);
            $sa = as_float(value_of($r, 'sa', $aliases, 0.0), 0.0);
            return $sa > 0 ? ($sa - $ga) / $sa : 0.0;
        };
        $valSO = function (array $r) use ($aliases) {
            return as_int(value_of($r, 'so', $aliases, 0), 0);
        };
        $valW = function (array $r) use ($aliases) {
            return as_int(value_of($r, 'w', $aliases, 0), 0);
        };

        function takeTop(array $rows, callable $valFn, int $limit = 10, bool $asc = false): array
        {
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'name' => getName($r, $GLOBALS['aliases']),
                    'team' => getTeam($r, $GLOBALS['aliases'], $GLOBALS['teamAbbrFromId']),
                    'val' => $valFn($r),
                ];
            }
            $out = array_values(array_filter($out, fn($x) => $x['name'] !== ''));
            usort($out, fn($a, $b) => $asc ? ($a['val'] <=> $b['val']) : ($b['val'] <=> $a['val']));
            return array_slice($out, 0, $limit);
        }

        // load data + filter to skaters (non-defense)
        $players = $playersFile ? parse_csv_assoc($playersFile) : [];
        $skaters = array_values(array_filter($players, fn($r) => !isDefense($r, $GLOBALS['aliases'])));

        // metric accessors
        $valG = function ($r) use ($aliases) {
            return as_int(value_of($r, 'g', $aliases, 0), 0);
        };
        $valA = function ($r) use ($aliases) {
            return as_int(value_of($r, 'a', $aliases, 0), 0);
        };
        $valP = function ($r) use ($aliases, $valG, $valA) {
            $p = value_of($r, 'p', $aliases, null);
            if ($p === null || $p === '')
                return $valG($r) + $valA($r);
            return as_int($p, 0);
        };


        // leaders: PTS/G/A top 10
        $leadersSkaters = [
            'PTS' => takeTop($skaters, $valP, 10),
            'G' => takeTop($skaters, $valG, 10),
            'A' => takeTop($skaters, $valA, 10),
        ];

        // emit into window.UHA for home.js
        ?>
        <script>
            window.UHA = window.UHA || {};
            (function (U) {
                U.statsData = U.statsData || {};
                U.statsData.skaters = <?= json_encode($leadersSkaters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            })(window.UHA);
        </script>
        <?php
        // Home — wire Defensemen leaders (PTS/G/A)
        
        // only defensemen
        $defenders = array_values(array_filter($players, fn($r) => isDefense($r, $GLOBALS['aliases'])));

        // PTS/G/A top 10 for defense
        $leadersDefense = [
            'PTS' => takeTop($defenders, $valP, 10),
            'G' => takeTop($defenders, $valG, 10),
            'A' => takeTop($defenders, $valA, 10),
        ];

        // Home — wire Defensemen leaders (PTS/G/A) + alias to pretty keys
        $defenders = array_values(array_filter($players, fn($r) => isDefense($r, $aliases)));

        $leadersDefense = [
            'PTS' => takeTop($defenders, $valP, 10),
            'G' => takeTop($defenders, $valG, 10),
            'A' => takeTop($defenders, $valA, 10),
        ];

        // also provide pretty-key versions (what the tab labels use)
        $leadersDefensePretty = [
            'Points' => $leadersDefense['PTS'],
            'Goals' => $leadersDefense['G'],
            'Assists' => $leadersDefense['A'],
        ];

        // === Home — Rookies leaders (Rookie == TRUE), PTS/G/A ===
// Uses the same $players parsed earlier
        
        // filter rookies by boolean column (Rookie/ProRookie/etc.)
        $rookies = array_values(array_filter($players, fn($r) => truthy(value_of($r, 'rookie', $aliases, ''))));
        $__rook_count = count($rookies);

        // Build leaders (raw keys)
        $leadersRookies = [
            'PTS' => takeTop($rookies, $valP, 10),
            'G' => takeTop($rookies, $valG, 10),
            'A' => takeTop($rookies, $valA, 10),
        ];

        // Pretty-key aliases so the tabs match exactly
        $leadersRookiesPretty = [
            'Points' => $leadersRookies['PTS'],
            'Goals' => $leadersRookies['G'],
            'Assists' => $leadersRookies['A'],
        ];

        // Emit for home.js
        ?>
        <!-- rookies matched: <?= $__rook_count ?> -->
        <script>
            (function (U) {
                U = window.UHA = window.UHA || {};
                U.statsData = U.statsData || {};
                function mix(a, b) { var o = {}, k; for (k in a) o[k] = a[k]; for (k in b) o[k] = b[k]; return o; }
                var R = <?= json_encode($leadersRookies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                var RP = <?= json_encode($leadersRookiesPretty, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                U.statsData.rookies = U.statsData.rookies ? mix(U.statsData.rookies, mix(R, RP)) : mix(R, RP);
            })();
        </script>

        <script>
            window.UHA = window.UHA || {};
            (function (U) {
                U.statsData = U.statsData || {};
                function mix(a, b) { var o = {}; for (var k in a) o[k] = a[k]; for (var k in b) o[k] = b[k]; return o; }
                var R = <?= json_encode($leadersRookies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                var RP = <?= json_encode($leadersRookiesPretty, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                U.statsData.rookies = U.statsData.rookies ? mix(U.statsData.rookies, mix(R, RP)) : mix(R, RP);
            })(window.UHA);
        </script>

        <script>
            // expose under all expected section names with both key styles
            window.UHA = window.UHA || {};
            (function (U) {
                U.statsData = U.statsData || {};
                var D = <?= json_encode($leadersDefense, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                var DP = <?= json_encode($leadersDefensePretty, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                function mix(a, b) { var o = {}; for (var k in a) o[k] = a[k]; for (var k in b) o[k] = b[k]; return o; }

                var merged = mix(D, DP);
                U.statsData.defense = U.statsData.defense ? mix(U.statsData.defense, merged) : merged;
                U.statsData.defensemen = U.statsData.defensemen ? mix(U.statsData.defensemen, merged) : merged;
                U.statsData.defenders = U.statsData.defenders ? mix(U.statsData.defenders, merged) : merged;
            })(window.UHA);
        </script>

        <?php





        // --- Inject goalie leaders exactly like statistics.php ---
// Prefer the prebuilt derived leaders file to avoid recomputing.
        $__leaders_path = __DIR__ . '/data/derived/leaders.json';
        if (is_readable($__leaders_path)) {
            $leadersJson = json_decode((string) file_get_contents($__leaders_path), true);
            if (is_array($leadersJson) && isset($leadersJson['goalies']) && is_array($leadersJson['goalies'])) {
                // Ensure stats container exists and copy over goalies block (GAA/SV%/SO)
                $__uha_data['statsData'] = $__uha_data['statsData'] ?? [];
                $__uha_data['statsData']['goalies'] = $leadersJson['goalies'];
            }
        }

        include __DIR__ . '/includes/topbar.php';
        include __DIR__ . '/includes/leaguebar.php'; ?>

        <!-- SCORE TICKER (nav.js only needs #ticker-track) -->
        <div class="score-ticker" aria-label="Live Scores Ticker">
            <div class="ticker-viewport">
                <div class="ticker-track" id="ticker-track"></div>
            </div>
        </div>

        <!-- MAIN CANVAS -->
        <section class="page-container">
            <div class="home-canvas">

                <!-- LEFT: Leaders rail -->
                <aside>
                    <div class="sidebar-title">Statistics</div>
                    <div id="leadersStack" class="leadersStack"></div>
                </aside>

                <!-- CENTER: Feature + headlines -->
                <main class="main">

                    <!-- Feature story -->
                    <section class="feature">
                        <div class="story-head">
                            <div>
                                <strong id="feature-team-abbr">UHA</strong>
                                <span id="feature-team-name">Your League</span>
                            </div>
                            <div>IMAGE</div>
                        </div>

                        <div class="image">IMAGE</div>

                        <div class="overlay">
                            <h3 id="feature-headline">Welcome to the Portal</h3>
                            <p id="feature-dek">Live data will appear as soon as uploads/DB are wired.</p>
                            <small id="feature-time">just now</small>
                        </div>
                    </section>

                    <!-- Top headlines -->
                    <section class="top-headlines">
                        <div class="section-title">Top Headlines</div>
                        <div class="th-grid">
                            <div class="th-item">HEADLINE CARD 1</div>
                            <div class="th-item">HEADLINE CARD 2</div>
                            <div class="th-item">HEADLINE CARD 3</div>
                        </div>
                        <div class="th-caption">
                            <div><strong>Headline 1</strong><small>xx ago</small></div>
                            <div><strong>Headline 2</strong><small>xx ago</small></div>
                            <div><strong>Headline 3</strong><small>xx ago</small></div>
                        </div>
                    </section>

                    <!-- More headlines (same look) -->
                    <section class="more-headlines">
                        <div class="section-title">More Headlines</div>
                        <div class="th-grid">
                            <div class="th-item">HEADLINE CARD 1</div>
                            <div class="th-item">HEADLINE CARD 2</div>
                            <div class="th-item">HEADLINE CARD 3</div>
                        </div>
                        <div class="th-caption">
                            <div><strong>Headline 1</strong><small>xx ago</small></div>
                            <div><strong>Headline 2</strong><small>xx ago</small></div>
                            <div><strong>Headline 3</strong><small>xx ago</small></div>
                        </div>
                    </section>

                    <!-- Transactions — latest (25/50 toggle) -->
                    <section class="transactions" id="homeTransactions">
                        <div class="section-title">Transactions</div>

                        <?php $initialLimit = 25;
                        $hasMore = count($latestTxTop) > $initialLimit; ?>
                        <?php if ($hasMore): ?>
                            <div class="tx-controls" role="tablist" aria-label="Show transactions">
                                <button type="button" class="tx-pill" data-limit="25" aria-pressed="true">25</button>
                                <button type="button" class="tx-pill" data-limit="50" aria-pressed="false">50</button>
                            </div>
                        <?php endif; ?>

                        <div class="tx-mini">
                            <ul class="tx-mini-list">
                                <?php if (empty($latestTxTop)): ?>
                                    <li class="tx-mini-empty">No recent transactions.</li>
                                <?php else:
                                    $i = 0;
                                    foreach ($latestTxTop as $x):
                                        $type = $x['__type'];
                                        $i++;
                                        $hide = $i > $initialLimit ? ' data-hidden="true"' : ''; ?>
                                        <li class="tx-mini-row tx-<?= htmlspecialchars($type) ?>" <?= $hide ?>>
                                            <div class="who">
                                                <?php if ($type === 'trade'): ?>
                                                    <img class="txm-logo"
                                                        src="assets/img/logos/<?= htmlspecialchars($x['from']) ?>_dark.svg" alt="">
                                                    <span
                                                        class="txm-abbr"><?= htmlspecialchars($CODE2ABBR[$x['from']] ?? $x['from']) ?></span>
                                                    <span class="txm-sep">↔</span>
                                                    <span
                                                        class="txm-abbr"><?= htmlspecialchars($CODE2ABBR[$x['to']] ?? $x['to']) ?></span>
                                                    <img class="txm-logo"
                                                        src="assets/img/logos/<?= htmlspecialchars($x['to']) ?>_dark.svg" alt="">
                                                <?php else: ?>
                                                    <img class="txm-logo"
                                                        src="assets/img/logos/<?= htmlspecialchars($x['team']) ?>_dark.svg" alt="">
                                                    <span
                                                        class="txm-abbr"><?= htmlspecialchars($CODE2ABBR[$x['team']] ?? $x['team']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php
                                            if ($type === 'trade') {
                                                $label = 'TRADE';
                                                $href = 'transactions.php#trades';
                                            } elseif ($type === 'signing') {
                                                $label = 'SIGNING';
                                                $href = 'transactions.php#signings';
                                            } else {
                                                $label = 'MOVE';
                                                $href = 'transactions.php#moves';
                                            }
                                            ?>
                                            <a class="tx-chip txc-<?= htmlspecialchars($type) ?>"
                                                href="<?= $href ?>"><?= $label ?></a>

                                            <div class="desc"><?= tx_home_summary($x, $CODE2ABBR, $BASE_DRAFT_YEAR) ?></div>
                                            <div class="date"><?= htmlspecialchars($x['date'] ?? '') ?></div>
                                        </li>
                                    <?php endforeach; endif; ?>
                            </ul>

                            <div class="links">
                                <a class="btn ghost" href="transactions.php">All Transactions</a>
                            </div>
                        </div>
                    </section>


                </main>

<!-- RIGHT: Scores rail + standings preview -->
                <aside class="sidebar-right">

                    <div class="box" id="scoresCard">
                        <div class="title">Pro League Scores</div>

                        <div class="scores-dates">
                            <button type="button" class="nav prev" aria-label="Previous day">◀</button>
                            <div id="scoresDates" class="dates-strip"></div>
                            <button type="button" class="nav next" aria-label="Next day">▶</button>
                        </div>

                        <div class="scores-controls slim">
                            <div class="fill"></div>
                            <select id="scoresScope" class="scores-scope">
                                <option value="pro">Pro</option>
                                <option value="farm">Farm</option>
                                <option value="echl">ECHL</option>
                                <option value="juniors">Juniors</option>
                            </select>
                            <select id="scoresFilter" class="scores-filter">
                                <option value="all">All</option>
                                <option value="live">Live</option>
                                <option value="final">Final</option>
                                <option value="upcoming">Upcoming</option>
                            </select>
                        </div>

                        <div id="proScores" class="scores-list"></div>
                    </div>

                    <div id="standingsCard" class="box standings-box" data-season-games="84">
                        <div class="title">Pro League Standings</div>
                        <div id="standingsBox"></div>
                    </div>

                </aside>

            </div>
        </section>

        <footer class="footer">Placeholder footer • (c) Your League</footer>
    </div>
    <!-- JS (no inline data here; we’ll wire sources step-by-step) -->
    <script src="assets/js/nav.js"></script>
    <?php
    /* === Goalies leaders (CSV → window.UHA.statsData.goalies) === */
    $uploads = __DIR__ . '/data/uploads/';

    // find goalies CSV
    $goaliesFile = null;
    foreach (['UHA-V3Goalies.csv', 'UHA-Goalies.csv'] as $cand) {
        if (is_readable($uploads . $cand)) {
            $goaliesFile = $uploads . $cand;
            break;
        }
    }

    // find a teams file for team GP (eligibility)
    $teamsStatFile = null;
    foreach (['UHA-V3ProTeam.csv', 'UHA-Teams.csv', 'UHA-V3Teams.csv'] as $cand) {
        if (is_readable($uploads . $cand)) {
            $teamsStatFile = $uploads . $cand;
            break;
        }
    }

    $goalies = $goaliesFile ? parse_csv_assoc($goaliesFile) : [];
    $teamRows = $teamsStatFile ? parse_csv_assoc($teamsStatFile) : [];
    $teamGP = build_team_gp_map($teamRows, $aliases, $teamAbbrFromId);

    // eligibility filter
    $goaliesElig = array_values(array_filter($goalies, function ($r) use ($teamGP, $aliases, $teamAbbrFromId) {
        return goalie_eligible($r, $teamGP, $aliases, $teamAbbrFromId);
    }));

    // build leaders: note GAA sorts ascending
    $leadersGoalies = [
        'GAA' => takeTop($goaliesElig, $valGAA, 10, /*asc*/ true),
        'SV%' => takeTop($goaliesElig, $valSV, 10, /*asc*/ false),
        'SO' => takeTop($goaliesElig, $valSO, 10, /*asc*/ false),
        'W' => takeTop($goaliesElig, $valW, 10, /*asc*/ false),
    ];
    ?>
    <?php
    /* === Scores — build window.UHA.scores from schedule JSON === */

    $uploadsDir = __DIR__ . '/data/uploads/';
    $schedPrimary = $uploadsDir . 'schedule-current.json';
    $schedFallback = $uploadsDir . 'schedule.json';
    $teamsJsonPath = $uploadsDir . 'teams.json';
    $boxIndexPath = $uploadsDir . 'boxscores/index.json'; // optional
    
    // Load teams map (id -> {abbr, name, logo})
    $teamsMap = [];
    if (is_readable($teamsJsonPath)) {
        $tj = json_decode((string) file_get_contents($teamsJsonPath), true);
        foreach (($tj['teams'] ?? []) as $t) {
            $id = (string) ($t['id'] ?? $t['teamId'] ?? '');
            if ($id === '')
                continue;
            $abbr = (string) ($t['abbr'] ?? $t['abbre'] ?? $t['shortName'] ?? $t['name'] ?? '');
            $name = (string) ($t['shortName'] ?? $t['name'] ?? $abbr);
            $teamsMap[$id] = [
                'abbr' => $abbr,
                'name' => $name,
                'logo' => $abbr ? ("assets/img/logos/{$abbr}_light.svg") : null,
            ];
        }
    } ?>
    <?php
    /* === Home — Standings (Wild Card) data emitter (Pro) ===
       Mirrors standings.php: reads UHA-V3ProTeam.csv and emits window.UHA.standingsData
    */

    $csvCandidates = [
        __DIR__ . '/data/uploads/UHA-V3ProTeam.csv',
        __DIR__ . '/data/UHA-V3ProTeam.csv',
        __DIR__ . '/UHA-V3ProTeam.csv',
    ];
    $csvPath = null;
    foreach ($csvCandidates as $p) {
        if (is_readable($p)) {
            $csvPath = $p;
            break;
        }
    }

    $teams = [];
    if ($csvPath && ($h = fopen($csvPath, 'r')) !== false) {
        $hdr = fgetcsv($h);
        $idx = array_flip($hdr);
        $get = function (array $row, string $key, $def = '') use ($idx) {
            return isset($idx[$key]) ? $row[$idx[$key]] : $def;
        };
        while (($row = fgetcsv($h)) !== false) {
            $gp = (int) $get($row, 'GP', 0);
            $w = (int) $get($row, 'W', 0);
            $l = (int) $get($row, 'L', 0);
            $otw = (int) $get($row, 'OTW', 0);
            $otl = (int) $get($row, 'OTL', 0);
            $sow = (int) $get($row, 'SOW', 0);
            $sol = (int) $get($row, 'SOL', 0);
            $pts = (int) $get($row, 'Points', 0);
            // NEW: tiebreaker stats
            $rowWins = max(0, $w - $sow);           // ROW = W minus SO wins
            $rw = max(0, $w - $otw - $sow);    // RW  = W minus OT & SO wins
    
            $otl_total = $otl + $sol; // NHL displays OTL incl SO losses
    
            $teams[] = [
                'abbr' => (string) $get($row, 'Abbre', ''),
                'name' => (string) $get($row, 'Name', ''),
                'conf' => (string) $get($row, 'Conference', ''),
                'div' => (string) $get($row, 'Division', ''),
                'gp' => $gp,
                'w' => $w,
                'l' => $l,
                'ot' => $otl_total,
                'pts' => $pts,
                // NEW: include tiebreaker fields
                'row' => $rowWins,
                'rw' => $rw,
                // Optional clinch flag if your CSV includes it (x/y/z/p/e)
                'clinch' => (string) ($get($row, 'Clinch', '') ?: ''),
            ];
        }
        fclose($h);
    }

    /* normalize conf/div names like standings.php */
    foreach ($teams as &$tm) {
        $tm['confShort'] = preg_replace('/\s*Conference$/', '', $tm['conf']);
        $tm['divShort'] = preg_replace('/\s*Division$/', '', $tm['div']);
    }
    unset($tm);

    /* group into the structure standings.js expects */
    $divsByConf = [];
    foreach ($teams as $tm) {
        $conf = $tm['confShort'] ?: 'Eastern';
        $div = $tm['divShort'] ?: 'Atlantic';

        // define abbr for the logo path
        $abbr = strtoupper((string) ($tm['abbr'] ?? ''));

        $divsByConf[$conf][$div][] = [
            'abbr' => $abbr,
            'name' => $tm['name'],
            'logo' => $abbr !== '' ? "assets/img/logos/{$abbr}_dark.svg" : null,
            'gp' => $tm['gp'],
            'w' => $tm['w'],
            'l' => $tm['l'],
            'ot' => $tm['ot'],
            'pts' => $tm['pts'],
            // NEW
            'row' => $tm['row'] ?? 0,
            'rw' => $tm['rw'] ?? 0,
            'clinch' => $tm['clinch'] ?? '',
        ];
    }


    /* sort each division by points desc (tiebreakers happen on the full page) */
    foreach ($divsByConf as &$confDivs) {
        foreach ($confDivs as &$arr) {
            usort($arr, function ($a, $b) {
                // 1) Points
                if (($d = (($b['pts'] ?? 0) <=> ($a['pts'] ?? 0))))
                    return $d;
                // 2) Regulation wins (RW)
                if (($d = (($b['rw'] ?? 0) <=> ($a['rw'] ?? 0))))
                    return $d;
                // 3) ROW (Reg/Ot wins)
                if (($d = (($b['row'] ?? 0) <=> ($a['row'] ?? 0))))
                    return $d;
                // 4) Wins
                if (($d = (($b['w'] ?? 0) <=> ($a['w'] ?? 0))))
                    return $d;
                // 5) Fewer games played (higher P%)
                if (($d = (($a['gp'] ?? 0) <=> ($b['gp'] ?? 0))))
                    return $d;
                // 6) Stable fallback
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });

        }
    }
    unset($confDivs, $arr);

    $standingsData = [
        'east' => ['divisions' => $divsByConf['Eastern'] ?? []],
        'west' => ['divisions' => $divsByConf['Western'] ?? []],
    ];
    ?>
    <script>
        // Provide data before standings.js executes
        window.UHA = window.UHA || {};
        (function (U) {
            U.standingsData = <?= json_encode($standingsData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            // keep nickname-only labels on the home card (standings.js respects this flag)
            U.standingsNicknameOnly = true;
        })(window.UHA);
    </script>

    <script src="assets/js/standings.js"></script>
    <script>
        (function () {
            const card = document.getElementById('standingsCard');
            if (!card) return;

            const totalGames =
                Number(card.dataset.seasonGames || (window.UHA && UHA.seasonGamesPro) || 0);
            if (!totalGames) return; // need season length to compute GR

            function applyGpGrPts() {
                card.classList.add('gp-gr-pts');

                // header: remove W/L/OT, insert GR before PTS
                card.querySelectorAll('.st-head').forEach(h => {
                    h.querySelector('.col.w')?.remove();
                    h.querySelector('.col.l')?.remove();
                    h.querySelector('.col.ot')?.remove();
                    if (!h.querySelector('.col.gr')) {
                        const gr = document.createElement('div');
                        gr.className = 'col gr';
                        gr.textContent = 'GR';
                        h.insertBefore(gr, h.querySelector('.col.pts'));
                    }
                });

                // rows: compute GR = total - GP, remove W/L/OT, insert GR before PTS
                card.querySelectorAll('.st-row').forEach(r => {
                    const gp = parseInt((r.querySelector('.col.gp')?.textContent || '0').trim(), 10) || 0;
                    const grVal = Math.max(0, totalGames - gp);

                    r.querySelector('.col.w')?.remove();
                    r.querySelector('.col.l')?.remove();
                    r.querySelector('.col.ot')?.remove();

                    if (!r.querySelector('.col.gr')) {
                        const gr = document.createElement('div');
                        gr.className = 'col gr';
                        gr.textContent = grVal;
                        r.insertBefore(gr, r.querySelector('.col.pts'));
                    } else {
                        r.querySelector('.col.gr').textContent = grVal;
                    }
                });

                // keep logos correct if your swapper is loaded
                if (window.UHA_applyLogoVariants) UHA_applyLogoVariants(card);
            }

            // run once now; if standings re-renders, patch again
            applyGpGrPts();
            const mo = new MutationObserver((m) => { for (const x of m) { if (x.addedNodes.length) { applyGpGrPts(); break; } } });
            mo.observe(document.getElementById('standingsBox') || card, { childList: true, subtree: true });
        })();

        (function () {
            const sc = document.getElementById('standingsCard');
            if (!sc) return;

            function abbrFrom(img) {
                if (img.dataset && img.dataset.abbr) return img.dataset.abbr.toUpperCase();
                const m = /\/logos\/([A-Za-z0-9_-]+)_(?:dark|light)\.svg/i.exec(img.src || '');
                if (m) return m[1].toUpperCase();
                const alt = (img.alt || '').trim();
                if (alt.length >= 2 && alt.length <= 5) return alt.toUpperCase();
                return '';
            }
        })();
    </script>

    <?php
    // Optional: boxscore index (id -> {box, log})
    $boxIdx = [];
    if (is_readable($boxIndexPath)) {
        $bx = json_decode((string) file_get_contents($boxIndexPath), true);
        if (is_array($bx))
            $boxIdx = $bx;
    }

    // tiny helpers (local to this block)
    $gv = function (array $a, array $keys, $default = null) {
        foreach ($keys as $k) {
            if (isset($a[$k]) && $a[$k] !== '')
                return $a[$k];
        }
        return $default;
    };
    $truthy = function ($v) {
        $s = strtoupper(trim((string) $v));
        return in_array($s, ['TRUE', 'T', 'YES', 'Y', '1'], true);
    };
    $resolveTeamId = function (array $g, string $side): string {
        // Prefer XTeamId; else XTeam text
        $idKey = $side . 'TeamId';
        if (!empty($g[$idKey]))
            return (string) $g[$idKey];
        return (string) ($g[$side . 'Team'] ?? $g[$side] ?? '');
    };
    $teamInfo = function (array $g, string $side) use ($teamsMap, $resolveTeamId): array {
        $idOrText = $resolveTeamId($g, $side);
        if ($idOrText === '')
            return ['abbr' => 'TBD', 'name' => 'TBD', 'logo' => null];

        if (isset($teamsMap[$idOrText])) {
            $t = $teamsMap[$idOrText];
            return ['abbr' => $t['abbr'] ?: 'TBD', 'name' => $t['name'] ?: ($t['abbr'] ?: 'TBD'), 'logo' => $t['logo'] ?? null];
        }
        // treat as abbr/name text
        return ['abbr' => $idOrText, 'name' => $idOrText, 'logo' => null];
    };
    $isPlayed = function (array $g) use ($truthy): bool {
        if (array_key_exists('Play', $g))
            return $truthy($g['Play']);
        if (isset($g['visitorScore'], $g['homeScore'])) {
            return ((int) $g['visitorScore'] > 0) || ((int) $g['homeScore'] > 0);
        }
        if (isset($g['awayScore'], $g['homeScore'])) {
            return ((int) $g['awayScore'] > 0) || ((int) $g['homeScore'] > 0);
        }
        return false;
    };

    // Load schedule JSON (current → fallback)
    $schRaw = [];
    if (is_readable($schedPrimary))
        $schRaw = json_decode((string) file_get_contents($schedPrimary), true);
    if ((empty($schRaw) || empty($schRaw['games'])) && is_readable($schedFallback)) {
        $schRaw = json_decode((string) file_get_contents($schedFallback), true);
    }
    $games = $schRaw['games'] ?? [];

    // Build the exact structure assets/js/scores.js expects
    $scoresOut = ['pro' => [], 'farm' => [], 'echl' => [], 'juniors' => []];

    foreach ($games as $g) {
        $date = (string) $gv($g, ['date', 'Date', 'gameDate', 'GameDate'], '');
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $date))
            continue;

        $scope = strtolower((string) $gv($g, ['League', 'league', 'Scope', 'scope'], 'pro'));
        if (!isset($scoresOut[$scope]))
            $scope = 'pro';

        $away = $teamInfo($g, 'visitor'); // schedule uses visitor/home
        $home = $teamInfo($g, 'home');
        $aScore = (int) $gv($g, ['visitorScore', 'awayScore', 'vScore', 'aScore'], 0);
        $hScore = (int) $gv($g, ['homeScore', 'hScore'], 0);

        $status = (string) $gv($g, ['Status', 'status', 'State', 'state'], '');
        if ($status === '')
            $status = $isPlayed($g) ? 'Final' : 'Upcoming';

        $gid = (string) $gv($g, ['GameID', 'gameId', 'id'], '');
        $box = ($gid !== '' && isset($boxIdx[$gid]['box'])) ? $boxIdx[$gid]['box'] : null;
        $log = ($gid !== '' && isset($boxIdx[$gid]['log'])) ? $boxIdx[$gid]['log'] : null;

        $scoresOut[$scope][$date][] = [
            'away' => $away,
            'home' => $home,
            'aScore' => $aScore,
            'hScore' => $hScore,
            'status' => $status,
            'goals' => [],  // optional detail
            'box' => $box,
            'log' => $log,
            'id' => ($gid !== '' ? $gid : null),
        ];
    }

    // (debug) count total games emitted
    $totalGames = 0;
    foreach ($scoresOut as $sc => $dates)
        foreach ($dates as $d => $arr)
            $totalGames += count($arr);
    ?>
    <!-- scores emitter ok: <?= $totalGames ?> games -->
    <script>
        // Expose to scores.js / home.js BEFORE they load
        (function (U) {
            U = window.UHA = window.UHA || {};
            U.scores = <?= json_encode($scoresOut, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            U.defaultScoresScope = U.defaultScoresScope || 'pro';
        })();
    </script>
<?php
// ---- Build a simple ticker from the most recent date we have in $scoresOut ----
function pick_latest_date(array $byDate): string {
    $dates = array_keys($byDate);
    rsort($dates);
    return $dates[0] ?? '';
}
$tickerItems = [];
foreach (['pro','farm','echl','juniors'] as $sc) {
    if (!empty($scoresOut[$sc])) {
        $d = pick_latest_date($scoresOut[$sc]);
        if ($d !== '') {
            foreach ($scoresOut[$sc][$d] as $g) {
                $a = strtoupper($g['away']['abbr'] ?? $g['away']['name'] ?? 'AWY');
                $h = strtoupper($g['home']['abbr'] ?? $g['home']['name'] ?? 'HOM');
                $as = (int)($g['aScore'] ?? 0);
                $hs = (int)($g['hScore'] ?? 0);
                $st = trim((string)($g['status'] ?? ''));
                $label = $st !== '' ? $st : (($as || $hs) ? 'Final' : 'Upcoming');
                $tickerItems[] = "{$a} {$as} — {$h} {$hs} ({$label})";
            }
        }
        break; // first non-empty scope wins
    }
}
?>
<script>
  window.UHA = window.UHA || {};
  UHA.ticker = <?= json_encode($tickerItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  (function (U) {
      U.statsData = U.statsData || {};
      U.statsData.goalies = <?= json_encode($leadersGoalies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  })(window.UHA);

  window.UHA = window.UHA || {};
  UHA.leadersCompactN = 10;   // start expanded
  UHA.topNLeaders     = 5;  
  UHA.leadersCollapsible = false;
</script>

    <script src="assets/js/home.js?v=<?= filemtime(__DIR__ . '/assets/js/home.js') ?>"></script>
    <script src="assets/js/scores.js"></script>
    <script src="assets/js/auto-logos.js"></script>
</body>
</html>
