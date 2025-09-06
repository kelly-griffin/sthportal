<?php
/* Extracted from home - Backup.php: Home — Standings (Wild Card) data emitter (Pro)
   Mirrors standings.php: reads UHA-V3ProTeam.csv and emits window.UHA.standingsData.
   This is a straight transplant (no wrappers), so it behaves exactly like the backup.
*/

// Locate Pro team file (same candidates as backup)
$csvCandidates = [
    __DIR__ . '/data/uploads/UHA-V3ProTeam.csv',
    __DIR__ . '/data/UHA-V3ProTeam.csv',
    __DIR__ . '/UHA-V3ProTeam.csv',
];
$csvPath = null;
foreach ($csvCandidates as $p) {
    if (is_readable($p)) { $csvPath = $p; break; }
}

$teams = [];
if ($csvPath && ($h = fopen($csvPath, 'r')) !== false) {
    $hdr = fgetcsv($h);
    $idx = array_flip($hdr);
    $get = function(array $row, string $key, $def = '') use ($idx) {
        return isset($idx[$key]) ? $row[$idx[$key]] : $def;
    };
    while (($row = fgetcsv($h)) !== false) {
        $gp  = (int) $get($row, 'GP', 0);
        $w   = (int) $get($row, 'W', 0);
        $l   = (int) $get($row, 'L', 0);
        $otw = (int) $get($row, 'OTW', 0);
        $otl = (int) $get($row, 'OTL', 0);
        $sow = (int) $get($row, 'SOW', 0);
        $sol = (int) $get($row, 'SOL', 0);
        $pts = (int) $get($row, 'Points', 0);

        // Backup tiebreaker fields
        $rowWins = max(0, $w - $sow);          // ROW = W minus SO wins
        $rw      = max(0, $w - $otw - $sow);   // RW  = W minus OT & SO wins
        $otl_total = $otl + $sol;              // NHL displays OTL incl SO losses

        $teams[] = [
            'abbr' => (string) $get($row, 'Abbre', ''),
            'name' => (string) $get($row, 'Name', ''),
            'conf' => (string) $get($row, 'Conference', ''),
            'div'  => (string) $get($row, 'Division', ''),
            'gp'   => $gp,
            'w'    => $w,
            'l'    => $l,
            'ot'   => $otl_total,
            'pts'  => $pts,
            'row'  => $rowWins,
            'rw'   => $rw,
            'clinch' => (string) ($get($row, 'Clinch', '') ?: ''),
        ];
    }
    fclose($h);
}

// Normalize conf/div labels as in backup
foreach ($teams as &$tm) {
    $tm['confShort'] = preg_replace('/\s*Conference$/', '', $tm['conf']);
    $tm['divShort']  = preg_replace('/\s*Division$/',   '', $tm['div']);
}
unset($tm);

// Group into structure standings.js expects
$divsByConf = [];
foreach ($teams as $tm) {
    $conf = $tm['confShort'] ?: $tm['conf'] ?: '';
    $div  = $tm['divShort'] ?: $tm['div'] ?: '';
    if ($conf === '' || $div === '') continue;
    if (!isset($divsByConf[$conf])) $divsByConf[$conf] = [];
    if (!isset($divsByConf[$conf][$div])) $divsByConf[$conf][$div] = [];
    $divsByConf[$conf][$div][] = $tm;
}

// Sort each division like backup (points, RW, ROW, wins, fewer GP, then name)
foreach ($divsByConf as &$confDivs) {
    foreach ($confDivs as &$arr) {
        usort($arr, function($a, $b) {
            if (($d = (($b['pts'] ?? 0) <=> ($a['pts'] ?? 0)))) return $d;
            if (($d = (($b['rw']  ?? 0) <=> ($a['rw']  ?? 0)))) return $d;
            if (($d = (($b['row'] ?? 0) <=> ($a['row'] ?? 0)))) return $d;
            if (($d = (($b['w']   ?? 0) <=> ($a['w']   ?? 0)))) return $d;
            if (($d = (($a['gp']  ?? 0) <=> ($b['gp']  ?? 0)))) return $d;
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
<script>(function(){
  window.UHA = window.UHA || {};
  (function(U){
    U.standingsData = <?php echo json_encode($standingsData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    U.standingsNicknameOnly = true; // match backup behavior on home
  })(window.UHA);
})();</script>
