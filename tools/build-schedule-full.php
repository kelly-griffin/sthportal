<?php
// /tools/build-schedule-full.php
// Build season-wide schedule JSON (no 7-day filter).
// Output: /data/uploads/schedule-full.json

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$uploadDir    = __DIR__ . '/../data/uploads/';
$jsonDir = __DIR__ . '/../assets/json/';
$csvFile      = $uploadDir . 'UHA-V3ProSchedule.csv'; // master schedule
$outputFile   = $jsonDir . 'schedule-full.json';
$teamsFile    = $jsonDir . 'teams.json';
$timeMapFile  = $jsonDir . 'time-map.json';
$seasonStart  = new DateTime("2025-10-07"); // adjust if your season changes

header('Content-Type: text/plain; charset=utf-8');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- load teams.json ---
$teamMap = [];
if (file_exists($teamsFile)) {
    $teamsData = json_decode((string)file_get_contents($teamsFile), true);
    foreach (($teamsData['teams'] ?? []) as $team) {
        if (isset($team['id'], $team['name'])) {
            $teamMap[(string)$team['id']] = [
              'name'      => (string)$team['name'],
              'abbr'      => (string)($team['abbr'] ?? ''),
              'shortName' => (string)($team['shortName'] ?? ($team['name'] ?? '')),
            ];
        }
    }
}

// --- load time-map.json ---
$timeMap = [];
if (file_exists($timeMapFile)) {
    $timeMap = json_decode((string)file_get_contents($timeMapFile), true);
}

// --- read CSV master schedule ---
if (!file_exists($csvFile)) {
    file_put_contents($outputFile, json_encode(['games'=>[]], JSON_PRETTY_PRINT));
    echo "Missing CSV: " . $csvFile . "\n";
    exit;
}

$gamesOut = [];
if (($handle = fopen($csvFile, 'r')) !== false) {
    $header = fgetcsv($handle, 0, ',');
    if ($header === false) { $header=[]; }
    $idx = array_flip($header);
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        $rowData = [];
        foreach ($idx as $k => $i) $rowData[$k] = $row[$i] ?? '';

        $dayNumber = (int)($rowData['Day'] ?? 0);
        if ($dayNumber <= 0) continue;

        $gameDate = (clone $seasonStart);
        $gameDate->modify('+' . ($dayNumber - 1) . ' days');

        $visitorId = (string)($rowData['Visitor Team'] ?? '');
        $homeId    = (string)($rowData['Home Team'] ?? '');

        $vMeta = $teamMap[$visitorId] ?? ['name'=>$visitorId,'abbr'=>'','shortName'=>$visitorId];
        $hMeta = $teamMap[$homeId]    ?? ['name'=>$homeId,'abbr'=>'','shortName'=>$homeId];

        // Time lookup by home + slot
        $timeArray = [];
        if ($homeId && isset($timeMap[$homeId])) {
            $weekday = $gameDate->format('D');
            if (in_array($weekday, ['Mon','Tue','Wed','Thu','Fri'])) {
                $slotKey = 'Mon-Fri';
            } elseif ($weekday === 'Sat') {
                $slotKey = 'Sat';
            } else {
                $slotKey = 'Sun';
            }
            $choices = $timeMap[$homeId][$slotKey] ?? [];
            if (!empty($choices)) {
                $timeArray = [ $choices[ array_rand($choices) ] ];
            }
        }

        $gamesOut[] = [
            'gameNumber'    => $rowData['Game Number'] ?? '',
            'day'           => $dayNumber,
            'date'          => $gameDate->format('Y-m-d'),
            'weekday'       => $gameDate->format('D'),
            'month'         => $gameDate->format('F'),
            'visitorTeam'   => $vMeta['name'],
            'visitorTeamId' => $visitorId,
            'visitorScore'  => $rowData['Visitor Score'] ?? '',
            'homeTeam'      => $hMeta['name'],
            'homeTeamId'    => $homeId,
            'homeScore'     => $rowData['Home Score'] ?? '',
            'overtime'      => $rowData['Overtime'] ?? '',
            'shutout'       => $rowData['Shutout'] ?? '',
            'link'          => $rowData['Link'] ?? '',
            'play'          => $rowData['Play'] ?? '',
            'time'          => $timeArray,
            'broadcasters'  => []
        ];
    }
    fclose($handle);
}

// --- write JSON output ---
$out = [ 'games' => $gamesOut ];
file_put_contents($outputFile, json_encode($out, JSON_PRETTY_PRINT));
echo "Wrote: {$outputFile} Games: " . count($gamesOut) . "\n";
