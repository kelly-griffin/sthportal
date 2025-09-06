<?php
// /tools/build-schedule-json.php
// Directly builds schedule-current.json from CSV (no schedule.json)

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$uploadDir    = __DIR__ . '/../data/uploads/';
$jsonDir = __DIR__ . '/../assets/json/';
$csvFile      = $uploadDir . 'UHA-V3ProSchedule.csv'; // master schedule
$tickerFile   = $jsonDir . 'ticker-current.json';
$outputFile   = $jsonDir . 'schedule-current.json';
$teamsFile    = $jsonDir . 'teams.json';
$timeMapFile  = $jsonDir . 'time-map.json';

$seasonStart = new DateTime("2025-10-07");

// --- load sim day from ticker ---
$simDay = 1;
if (file_exists($tickerFile)) {
    $ticker = json_decode(file_get_contents($tickerFile), true);
    if (!empty($ticker['todayDay'])) {
        $simDay = (int)$ticker['todayDay'];
    }
}

// --- calculate week range ---
$weekStart = clone $seasonStart;
$weekStart->modify('+' . ($simDay - 1) . ' days');
$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');

// --- load teams.json ---
$teamMap = [];
if (file_exists($teamsFile)) {
    $teamsData = json_decode(file_get_contents($teamsFile), true);
    if (!empty($teamsData['teams'])) {
        foreach ($teamsData['teams'] as $team) {
            if (isset($team['id']) && isset($team['name'])) {
                $teamMap[(string)$team['id']] = $team['name']; // ID -> Name
            }
        }
    }
}

// --- load time-map.json ---
$timeMap = [];
if (file_exists($timeMapFile)) {
    $timeMap = json_decode(file_get_contents($timeMapFile), true);
}

// --- read CSV master schedule ---
if (!file_exists($csvFile)) {
    file_put_contents($outputFile, json_encode([
        'weekStart' => $weekStart->format('Y-m-d'),
        'weekEnd'   => $weekEnd->format('Y-m-d'),
        'games'     => []
    ], JSON_PRETTY_PRINT));
    exit("No CSV schedule found\n");
}

$gamesOut = [];
if (($handle = fopen($csvFile, "r")) !== false) {
    $headers = fgetcsv($handle); // first row
    while (($row = fgetcsv($handle)) !== false) {
        $rowData = array_combine($headers, $row);

        // Use 'Day' column to calculate actual date
        $dayNumber = (int)($rowData['Day'] ?? 0);
        if ($dayNumber <= 0) continue;

        $gameDate = clone $seasonStart;
        $gameDate->modify('+' . ($dayNumber - 1) . ' days');

        // Only keep games inside the current week window
        if ($gameDate < $weekStart || $gameDate > $weekEnd) {
            continue;
        }

        // IDs straight from CSV
        $visitorId = (string)($rowData['Visitor Team'] ?? '');
        $homeId    = (string)($rowData['Home Team'] ?? '');

        // Names from teamMap
        $visitorName = $teamMap[$visitorId] ?? $visitorId;
        $homeName    = $teamMap[$homeId] ?? $homeId;

        // --- lookup time based on Home Team ID + weekday ---
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
                $timeArray = [ $choices[array_rand($choices)] ]; // random pick
            }
        }

        $gamesOut[] = [
            'gameNumber'    => $rowData['Game Number'] ?? '',
            'day'           => $dayNumber,
            'date'          => $gameDate->format('Y-m-d'),
            'weekday'       => $gameDate->format('D'),
            'month'         => $gameDate->format('F'),
            'visitorTeam'   => $visitorName,
            'visitorTeamId' => $visitorId,
            'visitorScore'  => $rowData['Visitor Score'] ?? '',
            'homeTeam'      => $homeName,
            'homeTeamId'    => $homeId,
            'homeScore'     => $rowData['Home Score'] ?? '',
            'overtime'      => $rowData['Overtime'] ?? '',
            'shutout'       => $rowData['Shutout'] ?? '',
            'link'          => $rowData['Link'] ?? '',
            'play'          => $rowData['Play'] ?? '',
            'time'          => $timeArray,
            'broadcasters'  => [] // placeholder for now
        ];
    }
    fclose($handle);
}

// --- write JSON output ---
$out = [
    'weekStart' => $weekStart->format('Y-m-d'),
    'weekEnd'   => $weekEnd->format('Y-m-d'),
    'games'     => $gamesOut
];

file_put_contents($outputFile, json_encode($out, JSON_PRETTY_PRINT));
echo "Wrote: $outputFile SimDay = $simDay ({$weekStart->format('Y-m-d')} → {$weekEnd->format('Y-m-d')}) Games: " . count($gamesOut) . "\n";
