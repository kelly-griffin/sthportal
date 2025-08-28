<?php
$csv = __DIR__ . '/../data/uploads/UHA-V3ProTeam.csv';
$out = __DIR__ . '/../data/uploads/team-map.json';

$rows = array_map('str_getcsv', file($csv));
$headers = array_shift($rows);

$map = [];
foreach ($rows as $r) {
    if (empty($r[0])) continue;
    $map[$r[0]] = $r[1]; // ID → Name
}
file_put_contents($out, json_encode($map, JSON_PRETTY_PRINT));
echo "Wrote team map with " . count($map) . " entries\n";
