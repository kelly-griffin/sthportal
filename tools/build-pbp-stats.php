<?php
/**
 * tools/build-pbp-stats.php  (v1.1.0)
 *
 * Robust parser: flatten HTML to text, split on <br> into lines,
 * then match PBP patterns. This avoids missing text nodes and
 * handles both "Play-by-Play" and "Full Play-by-Play" sections.
 */

declare(strict_types=1);

$ROOT        = dirname(__DIR__);
$UPLOAD_DIR  = $ROOT . '/data/uploads';
$DERIVED_DIR = $ROOT . '/data/derived';

$VERSION = '1.1.0';
$NOW     = gmdate('c');

/* ---------- utils ---------- */

function ensureDir(string $dir): void {
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
}

function html_load(string $file): ?DOMDocument {
    if (!is_file($file)) return null;
    $html = (string)file_get_contents($file);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    return $dom;
}

function text_of(?DOMNode $node): string {
    if (!$node) return '';
    return trim(html_entity_decode($node->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function norm_name(string $s): string {
    $s = preg_replace('/^\d+:\d+\s+of\s+\d+(st|nd|rd|th)\s+period\s*-\s*/i','', $s);
    $s = preg_replace('/\s+/',' ', $s);
    $s = trim($s);
    return mb_strtolower($s, 'UTF-8');
}

function period_offset_seconds(string $label): int {
    $pl = mb_strtolower(trim($label), 'UTF-8');
    if (strpos($pl, 'overtime') !== false) return 3 * 20 * 60;
    if (preg_match('/(\d+)(st|nd|rd|th)\s+period/i', $pl, $m)) {
        return ((int)$m[1] - 1) * 20 * 60;
    }
    return 0;
}

function parse_mm_ss(string $s): int {
    if (!preg_match('/^(\d+):(\d{2})$/', trim($s), $m)) return 0;
    return (int)$m[1] * 60 + (int)$m[2];
}

function load_pro_teams(string $file): array {
    if (!is_file($file)) return [];
    $json = json_decode((string)file_get_contents($file), true);
    if (!is_array($json)) return [];
    $list = [];
    if (isset($json['teams']) && is_array($json['teams'])) {
        foreach ($json['teams'] as $t) {
            if (is_string($t)) $list[] = $t;
            elseif (is_array($t)) {
                foreach (['fullName','name','teamName'] as $k) {
                    if (!empty($t[$k]) && is_string($t[$k])) { $list[] = $t[$k]; break; }
                }
            }
        }
    } elseif (isset($json[0])) { // flat array
        foreach ($json as $t) if (is_string($t)) $list[] = $t;
    }
    return $list;
}

function get_teams_and_rosters(DOMDocument $dom): array {
    $xp = new DOMXPath($dom);
    $teamA = $teamB = null;

    foreach ($xp->query("//h1[contains(@class,'STHSGame_Result')]") as $h1) {
        $txt = text_of($h1);
        if (strpos($txt, ' vs ') !== false) {
            [$teamA, $teamB] = array_map('trim', explode(' vs ', $txt, 2));
            break;
        }
    }

    $map = [];
    foreach ($xp->query("//h3[contains(@class,'STHSGame_PlayerStatTitle')]") as $h3) {
        $title = text_of($h3);
        if (!preg_match('/Players Stats for (.+)$/', $title, $m)) continue;
        $team = trim($m[1]);
        $div = $h3->nextSibling;
        while ($div && (!($div instanceof DOMElement) || strpos($div->getAttribute('class'), 'STHSGame_PlayerStatTable') === false)) {
            $div = $div->nextSibling;
        }
        if (!$div) continue;
        $pre = null;
        foreach ($div->getElementsByTagName('pre') as $p) { $pre = $p; break; }
        if (!$pre) continue;
        $lines = preg_split('/\R/u', text_of($pre));
        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === '' || stripos($line, 'Player Name') === 0) continue;
            if (preg_match('/^([A-Za-zÀ-ÖØ-öø-ÿ\.\'\- ]+?)\s{2,}/u', $line, $mm)) {
                $name = trim($mm[1]);
                if ($name !== '' && mb_strtolower($name,'UTF-8') !== 'scratches') $map[$name] = $team;
            }
        }
    }

    foreach ($xp->query("//h3[contains(@class,'STHSGame_GoalerStatsTitle')]") as $g3) {
        $div = $g3->nextSibling;
        while ($div) {
            if (($div instanceof DOMElement) && strpos($div->getAttribute('class'), 'STHSGame_GoalerStats') !== false) {
                $t = text_of($div);
                if (preg_match('/^([A-Za-zÀ-ÖØ-öø-ÿ\.\'\- ]+)\s+\(([^)]+)\),/u', $t, $m)) {
                    if (!isset($map[$m[1]])) $map[$m[1]] = $m[2];
                }
            }
            if ($div instanceof DOMElement && $div->tagName === 'h3') break;
            $div = $div->nextSibling;
        }
    }

    return [$teamA, $teamB, $map];
}

function flatten_html_to_lines(string $html): array {
    // Replace <br> with newlines, strip tags, decode entities, split to lines.
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = preg_split('/\R/u', $text);
    $out = [];
    foreach ($lines as $ln) {
        $ln = trim($ln);
        if ($ln !== '') $out[] = $ln;
    }
    return $out;
}

class Pen {
    public string $team;
    public int $start;
    public int $end;
    public string $kind;
    public string $player;
    public bool $cancel = false;
    public function __construct(string $team, int $start, string $kind, string $player) {
        $this->team = $team; $this->start = $start; $this->kind = $kind; $this->player = $player;
        $this->end = $start + (($kind === 'major') ? 300 : 120);
    }
    public function activeAt(int $t): bool {
        if ($this->cancel && ($this->kind === 'minor' || $this->kind === 'double_minor_part')) return false;
        return $this->start <= $t && $t < $this->end;
    }
}

/* ---------- main ---------- */

ensureDir($DERIVED_DIR);
$files = [];
if (is_dir($UPLOAD_DIR)) {
    foreach (scandir($UPLOAD_DIR) as $fn) {
        if ($fn === '.' || $fn === '..') continue;
        $full = $UPLOAD_DIR . '/' . $fn;
        if (is_file($full) && preg_match('/\.html?$/i', $fn)) $files[] = $full;
    }
}
natsort($files); $files = array_values($files);

$players = [];
$events  = [];
$summary = ['files' => [], 'skipped' => 0, 'skippedByWhitelist' => []];
$PRO_TEAMS_FILE = $UPLOAD_DIR . '/teams.json';
$PRO_TEAMS = load_pro_teams($PRO_TEAMS_FILE);

foreach ($files as $file) {
    $dom = html_load($file);
    if (!$dom) { $summary['skipped']++; continue; }
    [$teamA, $teamB, $roster] = get_teams_and_rosters($dom);

    // Optional: skip non-pro/farm games using teams.json whitelist
    if ($teamA && $teamB && $PRO_TEAMS) {
        $inA = in_array($teamA, $PRO_TEAMS, true);
        $inB = in_array($teamB, $PRO_TEAMS, true);
        if (!$inA || !$inB) { $summary['skippedByWhitelist'][] = basename($file); continue; }
    }

    $html = (string)file_get_contents($file);
    $lines = flatten_html_to_lines($html);

    $seq = 0;
    $active = [];

    foreach ($lines as $line) {
        $seq++;
        // Faceoff
        if (preg_match('/^(\d+:\d+)\s+of\s+(.+?)\s*-\s*([A-Za-zÀ-ÖØ-öø-ÿ\.\'\- ]+)\s+wins\s+face-off\s+versus\s+([A-Za-zÀ-ÖØ-öø-ÿ\.\'\- ]+)\s+in\s+(.+?)\.?$/u', $line, $m)) {
            [$all, $tm, $plab, $wName, $lName, $zoneTxt] = $m;
            $tnow = period_offset_seconds($plab) + parse_mm_ss($tm);
            $wTeam = $roster[$wName] ?? ''; $lTeam = $roster[$lName] ?? '';

            // zone
            $wZone = $lZone = 'NZ';
            $zt = mb_strtolower($zoneTxt, 'UTF-8');
            if (strpos($zt,'neutral zone') !== false) {
                $wZone = $lZone = 'NZ';
            } else {
                $zoneTeam = null;
                if ($teamA && stripos($zt, $teamA) !== false) $zoneTeam = $teamA;
                if ($zoneTeam === null && $teamB && stripos($zt, $teamB) !== false) $zoneTeam = $teamB;
                if ($zoneTeam) {
                    $wZone = ($wTeam === $zoneTeam) ? 'DZ' : 'OZ';
                    $lZone = ($lTeam === $zoneTeam) ? 'DZ' : 'OZ';
                }
            }

            // strength
            $downW = 0; $downL = 0;
            foreach ($active as $p) {
                if ($p->activeAt($tnow)) {
                    if ($p->team === $wTeam) $downW++;
                    elseif ($p->team === $lTeam) $downL++;
                }
            }
            $strength = ($downW < $downL) ? 'PP' : (($downW > $downL) ? 'SH' : 'EV');

            $wk = norm_name($wName); $lk = norm_name($lName);
            if (!isset($players[$wk])) $players[$wk] = ['name'=>$wName,'team'=>$wTeam,
                'total_taken'=>0,'total_won'=>0,'ev_taken'=>0,'ev_won'=>0,'pp_taken'=>0,'pp_won'=>0,'sh_taken'=>0,'sh_won'=>0,
                'oz_taken'=>0,'oz_won'=>0,'dz_taken'=>0,'dz_won'=>0,'nz_taken'=>0,'nz_won'=>0];
            if (!isset($players[$lk])) $players[$lk] = ['name'=>$lName,'team'=>$lTeam,
                'total_taken'=>0,'total_won'=>0,'ev_taken'=>0,'ev_won'=>0,'pp_taken'=>0,'pp_won'=>0,'sh_taken'=>0,'sh_won'=>0,
                'oz_taken'=>0,'oz_won'=>0,'dz_taken'=>0,'dz_won'=>0,'nz_taken'=>0,'nz_won'=>0];

            // totals
            $players[$wk]['total_taken']++; $players[$lk]['total_taken']++; $players[$wk]['total_won']++;

            // strength tallies
            if ($strength === 'EV') {
                $players[$wk]['ev_taken']++; $players[$wk]['ev_won']++; $players[$lk]['ev_taken']++;
            } elseif ($strength === 'PP') {
                $players[$wk]['pp_taken']++; $players[$wk]['pp_won']++; $players[$lk]['sh_taken']++;
            } else { // SH
                $players[$wk]['sh_taken']++; $players[$wk]['sh_won']++; $players[$lk]['pp_taken']++;
            }

            // zones
            $mapZ = ['OZ'=>'oz','DZ'=>'dz','NZ'=>'nz'];
            $players[$wk][$mapZ[$wZone].'_taken']++;
            $players[$wk][$mapZ[$wZone].'_won']++;
            $players[$lk][$mapZ[$lZone].'_taken']++;

            $events[] = ['file'=>basename($file),'seq'=>$seq,'period'=>$plab,'time'=>$tm,'winner'=>$wName,'winnerTeam'=>$wTeam,'loser'=>$lName,'loserTeam'=>$lTeam,'zoneText'=>$zoneTxt,'winnerZone'=>$wZone,'loserZone'=>$lZone,'strength'=>$strength];
            continue;
        }

        // Penalty start
        if (preg_match('/^(\d+:\d+)\s+of\s+(.+?)\s*-\s*(Minor|Double Minor|Major)\s+Penalty\s+to\s+([A-Za-zÀ-ÖØ-öø-ÿ\.\'\- ]+)\s+for\s+.+$/u', $line, $m)) {
            $tm = $m[1]; $plab = $m[2]; $kind = mb_strtolower($m[3],'UTF-8'); $player = trim($m[4]);
            $t = period_offset_seconds($plab) + parse_mm_ss($tm);
            $team = $roster[$player] ?? '';
            if ($team === '') continue;
            if ($kind === 'double minor') {
                $active[] = new Pen($team, $t, 'double_minor_part', $player);
                $active[] = new Pen($team, $t + 120, 'double_minor_part', $player);
            } elseif ($kind === 'minor') {
                $active[] = new Pen($team, $t, 'minor', $player);
            } else {
                $active[] = new Pen($team, $t, 'major', $player);
            }
            continue;
        }

        // Penalty over
        if (preg_match('/^(\d+:\d+)\s+of\s+(.+?)\s*-\s*Penalty\s+to\s+([A-Za-zÀ-ÖØ-öø-ÿ\.\'\- ]+)\s+is\s+over.*$/u', $line, $m)) {
            $tm = $m[1]; $plab = $m[2]; $player = trim($m[3]);
            $t = period_offset_seconds($plab) + parse_mm_ss($tm);
            foreach ($active as $p) {
                if ($p->player === $player && $p->activeAt($t)) { $p->end = $t; break; }
            }
            continue;
        }

        // Goal (cancel one opponent minor if advantage)
        if (preg_match('/^(\d+:\d+)\s+of\s+(.+?)\s*-\s*.*?Goal\s+by\s+([A-Za-zÀ-ÖØ-öø-ÿ\.\'\- ]+)\s+-/u', $line, $m)) {
            $tm = $m[1]; $plab = $m[2]; $scorer = trim($m[3]);
            $t = period_offset_seconds($plab) + parse_mm_ss($tm);
            $team = $roster[$scorer] ?? null;
            if ($team) {
                // compute down counts
                $down = [];
                foreach ($active as $p) if ($p->activeAt($t)) { $down[$p->team] = ($down[$p->team] ?? 0) + 1; }
                $opp = null;
                foreach (array_keys($down) as $tmTeam) if ($tmTeam !== $team) { $opp = $tmTeam; break; }
                if ($opp && ($down[$opp] ?? 0) > ($down[$team] ?? 0)) {
                    $idx = null; $earliest = PHP_INT_MAX;
                    foreach ($active as $i => $p) {
                        if ($p->team === $opp && $p->activeAt($t) && ($p->kind === 'minor' || $p->kind === 'double_minor_part')) {
                            if ($p->start < $earliest) { $earliest = $p->start; $idx = $i; }
                        }
                    }
                    if ($idx !== null) $active[$idx]->cancel = true;
                }
            }
            continue;
        }
    }

    $summary['files'][] = basename($file);
}

/* ---------- aggregate + write ---------- */

// Per-team totals
$teams = [];
foreach ($players as $k => $p) {
    $t = $p['team'] ?? '';
    if ($t === '') continue;
    if (!isset($teams[$t])) {
        $teams[$t] = [
            'total_taken'=>0,'total_won'=>0,
            'ev_taken'=>0,'ev_won'=>0,'pp_taken'=>0,'pp_won'=>0,'sh_taken'=>0,'sh_won'=>0,
            'oz_taken'=>0,'oz_won'=>0,'dz_taken'=>0,'dz_won'=>0,'nz_taken'=>0,'nz_won'=>0,
        ];
    }
    foreach ($teams[$t] as $key => $_) { $teams[$t][$key] += (int)($p[$key] ?? 0); }
}

ensureDir($DERIVED_DIR);
file_put_contents($DERIVED_DIR . '/pbp-faceoffs-players.json', json_encode($players, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($DERIVED_DIR . '/pbp-faceoffs-teams.json',   json_encode($teams, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($DERIVED_DIR . '/pbp-faceoffs-events.json',  json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$index = [
    'version' => $VERSION,
    'generatedAt' => $NOW,
    'inputs' => [
        'dir'   => 'data/uploads',
        'files' => $summary['files'],
    ],
    'outputs' => [
        'players' => 'data/derived/pbp-faceoffs-players.json',
        'teams'   => 'data/derived/pbp-faceoffs-teams.json',
        'events'  => 'data/derived/pbp-faceoffs-events.json',
    ],
];
file_put_contents($DERIVED_DIR . '/pbp-index.json', json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$out = ['ok'=>true,'version'=>$VERSION,'generatedAt'=>$NOW,'filesParsed'=>count($summary['files']),'outputs'=>$index['outputs']];
if (php_sapi_name() === 'cli') {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
