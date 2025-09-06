<?php
// home-scores.php — data-only emit for Scores rail (shape matches assets/js/scores.js)
// HTML-only goals source
require_once __DIR__ . '/includes/goals-extract.php';
// ---------- helpers ----------
$upper = static fn($s) => strtoupper(trim((string) $s));
$intOrNull = static fn($v) => (is_numeric($v) ? (int) $v : null);
$boolish = static fn($v) => is_bool($v) ? $v : (is_string($v) ? strcasecmp($v, 'true') === 0 : (bool) $v);
$firstFile = static function (array $candidates): ?string {
    foreach ($candidates as $p) {
        if ($p && is_file($p))
            return $p;
    }return null;
};
$firstDir = static function (array $candidates): ?string {
    foreach ($candidates as $p) {
        if ($p && is_dir($p))
            return rtrim($p, '/\\');
    }return null;
};

// ---------- locate inputs (robust paths; works in /sthportal and backup roots) ----------
$root = __DIR__;
$teamsPath = $firstFile([$root . '/assets/json/teams.json', $root . '/teams.json']);
$schedFull = $firstFile([$root . '/assets/json/schedule-full.json', $root . '/schedule-full.json']);
$schedCurr = $firstFile([$root . '/assets/json/schedule-current.json', $root . '/schedule-current.json']);
$boxDir = '/data/uploads/';

// ---------- SIM date anchor ----------
$simYMD = date('Y-m-d');
if (is_file($root . '/includes/sim_clock.php')) {
    require_once $root . '/includes/sim_clock.php';
    if (function_exists('sim_clock_now'))
        $simYMD = date('Y-m-d', strtotime(sim_clock_now()));
}

// ---------- team id -> {abbr,name,shortName} ----------
$id2 = [];
if ($teamsPath) {
    $teamsJson = json_decode((string) file_get_contents($teamsPath), true) ?: [];
    // shape A: { "teams": [ {id, abbr, name, shortName}, ... ] }
    if (isset($teamsJson['teams']) && is_array($teamsJson['teams'])) {
        foreach ($teamsJson['teams'] as $t) {
            $id = (string) ($t['id'] ?? '');
            if ($id === '')
                continue;
            $id2[$id] = [
                'abbr' => $upper($t['abbr'] ?? $t['code'] ?? $id),
                'name' => (string) ($t['name'] ?? ''),
                'shortName' => (string) ($t['shortName'] ?? ''),
            ];
        }
    } else {
        // shape B: { "12": {abbr,name,shortName}, ... }
        foreach ((array) $teamsJson as $tid => $t) {
            $id = (string) $tid;
            $id2[$id] = [
                'abbr' => $upper(is_array($t) ? ($t['abbr'] ?? $t['code'] ?? $id) : $t),
                'name' => is_array($t) ? (string) ($t['name'] ?? '') : (string) $t,
                'shortName' => is_array($t) ? (string) ($t['shortName'] ?? '') : '',
            ];
        }
    }
}

// ---------- load schedule ----------
$sched = [];
if ($schedFull)
    $sched = json_decode((string) file_get_contents($schedFull), true) ?: [];
if (empty($sched['games']) && $schedCurr)
    $sched = json_decode((string) file_get_contents($schedCurr), true) ?: [];
$rows = (array) ($sched['games'] ?? []);

// ---------- build pro -> { 'YYYY-MM-DD': [games] } ----------
$byDate = []; // <<< ENSURED DEFINED

foreach ($rows as $g) {
    $ymd = (string) ($g['date'] ?? '');
    if ($ymd === '' || (string) ($g['play'] ?? '') !== 'True')
        continue; // keep only scheduled/played rows

    // ids, teams
    $gid = (string) preg_replace('~\.html$~i', '', (string) ($g['link'] ?? ''));
    if ($gid === '')
        continue;

    $awayId = (string) ($g['visitorTeamId'] ?? $g['awayTeamId'] ?? '');
    $homeId = (string) ($g['homeTeamId'] ?? $g['hostTeamId'] ?? '');

    $A = $id2[$awayId] ?? ['abbr' => $upper($awayId), 'name' => (string) ($g['visitorTeam'] ?? ''), 'shortName' => ''];
    $H = $id2[$homeId] ?? ['abbr' => $upper($homeId), 'name' => (string) ($g['homeTeam'] ?? ''), 'shortName' => ''];

    // label from schedule (e.g., "7:00 PM ET")
    $timeField = $g['time'] ?? '';
    $startLabel = is_array($timeField) ? trim((string) ($timeField[0] ?? '')) : trim((string) $timeField);
    $status = $startLabel ?: 'Scheduled';

    // base game object (exactly what scores.js reads)
    $game = [
        'id' => $gid,
        'status' => $status,                                              // will be overwritten to FINAL if scores present
        'away' => ['abbr' => $A['abbr'], 'name' => ($A['shortName'] ?: $A['name'] ?: $A['abbr'])],
        'home' => ['abbr' => $H['abbr'], 'name' => ($H['shortName'] ?: $H['name'] ?: $H['abbr'])],
        'aScore' => null,
        'hScore' => null,
        'box' => "box.php?gid=" . rawurlencode($gid),
        'log' => "ProGameLog.php?Game=" . rawurlencode($gid),
    ];

    // schedule-sourced finals
    $aSc = $intOrNull($g['visitorScore'] ?? null);
    $hSc = $intOrNull($g['homeScore'] ?? null);
    if ($aSc !== null && $hSc !== null) {
        $game['aScore'] = $aSc;
        $game['hScore'] = $hSc;
        $game['status'] = $boolish($g['overtime'] ?? false) ? 'FINAL/OT' : 'FINAL';
    }
    // Attach goals straight from /data/uploads/{gid}.html for FINAL games
    $go = uha_extract_goals($gid, $A['abbr'], $H['abbr']);
    if (!empty($go)) {
        $game['goals'] = $go;
    }

    $byDate[$ymd][] = $game;
}

// if there are absolutely no dates, keep an empty object (not an array) so JSON is valid for scores.js
$proMap = (object) $byDate;

// pick active day: sim date if present in data; else newest available date; else sim date
$activeDay = array_key_exists($simYMD, $byDate)
    ? $simYMD
    : (count($byDate) ? array_keys($byDate)[count($byDate) - 1] : $simYMD);

// ---------- emit ----------
?>
<script>
    (function (U) {
        U = window.UHA = window.UHA || {};
        U.scores = {
            pro: <?= json_encode(
                $proMap,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            ) ?>,
            farm: {},
            echl: {},
            juniors: {}
        };
        // help scores.js anchor first paint
        U.scores.activeDay = <?= json_encode($activeDay) ?>;
        U.simDate = <?= json_encode($simYMD) ?>;

        // announce AFTER assignment (guards load order)
        setTimeout(() => document.dispatchEvent(new CustomEvent('UHA:scores-ready')), 0);
    })(window.UHA);
</script>
<?php /* goals-extract already required at top */ ?>