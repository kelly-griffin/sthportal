<?php
// includes/tx_helpers.php
declare(strict_types=1);

/* ============================== */
/*  Basic string / team helpers   */
/* ============================== */

if (!function_exists('normalize_name')) {
    function normalize_name($s)
    {
        $s = strtoupper(trim((string) $s));
        $s = str_replace(['.', 'É'], ['', 'E'], $s);
        return preg_replace('/\s+/', ' ', $s);
    }
}

if (!function_exists('read_json_source')) {
    // Accept file path OR raw JSON string
    function read_json_source(string $source): array
    {
        if (is_file($source)) {
            $txt = (string) file_get_contents($source);
            $data = json_decode($txt, true);
            return is_array($data) ? $data : [];
        }
        $trim = ltrim($source);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $data = json_decode($source, true);
            return is_array($data) ? $data : [];
        }
        return [];
    }
}

if (!function_exists('load_team_codes')) {
    // full name (or shortName) -> ABBR
    function load_team_codes(string $source): array
    {
        $map = [];
        $j = read_json_source($source);
        foreach (($j['teams'] ?? []) as $t) {
            if (!empty($t['name']) && !empty($t['abbr'])) {
                $map[normalize_name($t['name'])] = strtoupper($t['abbr']);
            }
            if (!empty($t['shortName']) && !empty($t['abbr'])) {
                $map[normalize_name($t['shortName'])] = strtoupper($t['abbr']);
            }
        }
        return $map;
    }
}

if (!function_exists('load_team_names_by_code')) {
    // ABBR -> Full Name
    function load_team_names_by_code(string $source): array
    {
        $map = [];
        $j = read_json_source($source);
        foreach (($j['teams'] ?? []) as $t) {
            if (!empty($t['abbr']) && !empty($t['name'])) {
                $map[strtoupper($t['abbr'])] = $t['name'];
            }
        }
        return $map;
    }
}

if (!function_exists('fallback_code')) {
    function fallback_code(string $name): string
    {
        $p = preg_split('/\s+/', normalize_name($name));
        return strtoupper(substr($p[0] ?? '', 0, 1) . substr($p[count($p) - 1] ?? '', 0, 2));
    }
}

if (!function_exists('code_from_name')) {
    function code_from_name(string $name, array $map): string
    {
        $n = normalize_name($name);
        return $map[$n] ?? fallback_code($name);
    }
}

if (!function_exists('detect_delim')) {
    function detect_delim(string $file): string
    {
        $b = file_get_contents($file, false, null, 0, 4096) ?: '';
        return (substr_count($b, "\t") > substr_count($b, ",")) ? "\t" : ",";
    }
}

/* ============================== */
/*        Tiny JSON indexes       */
/* ============================== */

if (!function_exists('tx_index_read')) {
    function tx_index_read(string $path): array
    {
        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }
}
if (!function_exists('tx_index_write')) {
    function tx_index_write(string $path, array $idx): void
    {
        $dir = dirname($path);
        if (!is_dir($dir))
            @mkdir($dir, 0775, true);
        file_put_contents($path, json_encode($idx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

/* ============================== */
/*           Formatting           */
/* ============================== */

if (!function_exists('format_usd')) {
    function format_usd(int $amount): string
    {
        if ($amount >= 1000000)
            return '$' . number_format($amount / 1000000, 2) . 'M';
        if ($amount >= 1000)
            return '$' . number_format($amount / 1000, 0) . 'k';
        return '$' . number_format($amount, 0);
    }
}

if (!function_exists('ordinal')) {
    function ordinal(int $n): string
    {
        if ($n % 100 >= 11 && $n % 100 <= 13)
            return $n . 'th';
        $suf = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'][$n % 10] ?? 'th';
        return $n . $suf;
    }
}

if (!function_exists('get_base_draft_year')) {
    function get_base_draft_year(): int
    {
        // Your rule: Y1 = 2025
        $base = 2025;
        if (function_exists('sim_clock_read')) {
            $season = sim_clock_read()['season'] ?? '';
            if (preg_match('/(\d{4})/', (string) $season, $m))
                $base = (int) $m[1];
        }
        return $base;
    }
}

if (!function_exists('prettify_asset')) {
    // "Y:3-RND:5-BOS" -> "2027 5th (BOS)" when base=2025
    function prettify_asset(string $raw, ?int $baseDraftYear = null): string
    {
        $raw = trim($raw);
        if ($baseDraftYear === null)
            $baseDraftYear = 2025;
        if (preg_match('/^Y:(\d+)-RND:(\d+)-([A-Z]{2,3})$/i', $raw, $m)) {
            $y = (int) $m[1];
            $rnd = (int) $m[2];
            $tm = strtoupper($m[3]);
            $year = $baseDraftYear + max(0, $y - 1);
            return $year . ' ' . ordinal($rnd) . ' (' . $tm . ')';
        }
        return $raw;
    }
}

/* ============================== */
/*          Affiliates            */
/* ============================== */

if (!function_exists('load_affiliates_map')) {
    function load_affiliates_map(): array
    {
        // project root assumed one level up from includes/
        $root = dirname(__DIR__);
        $path = $root . '/assets/json/affiliates.json';
        if (is_file($path)) {
            $j = json_decode((string) file_get_contents($path), true);
            if (is_array($j) && !empty($j['affiliates']) && is_array($j['affiliates']))
                return $j['affiliates'];
        }
        return [];
    }
}
if (!function_exists('affiliate_for_team')) {
    function affiliate_for_team(string $nhlTeam, array $aff): string
    {
        $norm = normalize_name($nhlTeam);
        foreach ($aff as $k => $v) {
            if (normalize_name($k) === $norm)
                return (string) $v;
        }
        return $nhlTeam . ' AHL';
    }
}

/* ============================== */
/*         Trades parser          */
/* ============================== */

if (!function_exists('parse_trades_with_index')) {
    function parse_trades_with_index(string $csvFile, string $teamsSource, string $indexPath, string $stampDate): array
    {
        $rows = [];
        if (!is_file($csvFile))
            return $rows;

        $name2abbr = load_team_codes($teamsSource);
        $delim = detect_delim($csvFile);
        $fh = fopen($csvFile, 'r');
        if (!$fh)
            return $rows;

        $groups = [];
        while (($r = fgetcsv($fh, 0, $delim)) !== false) {
            if (count($r) < 3)
                continue;
            $id = (int) trim((string) $r[0]);
            $msg = trim((string) $r[1]);
            $ts = trim((string) $r[2]);

            if (!preg_match('/^TRADE\s*:\s*From\s+(.+?)\s+to\s+(.+?)\s*:\s*(.+?)\.?$/i', $msg, $m))
                continue;
            [$_, $fromName, $toName, $asset] = $m;

            $t = strtotime($ts) ?: 0;
            $minuteKey = $t ? date('Y-m-d H:i', $t) : '0';
            $a = normalize_name($fromName);
            $b = normalize_name($toName);
            $pair = [$a, $b];
            sort($pair, SORT_STRING);
            $key = $minuteKey . '|' . implode('::', $pair);

            if (!isset($groups[$key])) {
                $groups[$key] = ['t' => $t, 'from' => $fromName, 'to' => $toName, 'a_to_b' => [], 'b_to_a' => [], 'min_id' => $id, 'max_id' => $id];
            }
            $g = &$groups[$key];
            $g['min_id'] = min($g['min_id'], $id);
            $g['max_id'] = max($g['max_id'], $id);
            if (normalize_name($fromName) === normalize_name($g['from']))
                $g['a_to_b'][] = $asset;
            else
                $g['b_to_a'][] = $asset;
            unset($g);
        }
        fclose($fh);

        $idx = tx_index_read($indexPath);
        $dirty = false;

        foreach ($groups as $g) {
            $sig = ($g['t'] ? date('Y-m-d H:i', $g['t']) : '0') . '|' .
                normalize_name($g['from']) . '|' . normalize_name($g['to']) . '|' .
                $g['min_id'] . '-' . $g['max_id'];

            if (empty($idx[$sig])) {
                $idx[$sig] = $stampDate;
                $dirty = true;
            }

            $rows[] = [
                'date' => $idx[$sig],
                'time' => '',
                'from' => code_from_name($g['from'], $name2abbr),
                'to' => code_from_name($g['to'], $name2abbr),
                'assets_out' => implode(' + ', $g['a_to_b'] ?: ['—']),
                'assets_in' => implode(' + ', $g['b_to_a'] ?: ['—']),
                'retained' => '',
                'cap_in' => '',
                'cap_out' => '',
                'notes' => '',
            ];
        }

        if ($dirty)
            tx_index_write($indexPath, $idx);
        usort($rows, function ($a, $b) {
            if (($a['date'] ?? '') !== ($b['date'] ?? ''))
                return strcmp($b['date'], $a['date']); // newer date first
            $ta = (int) ($a['sort_t'] ?? 0);
            $tb = (int) ($b['sort_t'] ?? 0);
            if ($ta !== $tb)
                return $tb <=> $ta; // newer time first
            $ia = (int) ($a['sort_id'] ?? 0);
            $ib = (int) ($b['sort_id'] ?? 0);
            return $ib <=> $ia; // larger id (newer) first
        });
        array_walk($rows, function (&$r) {
            unset($r['sort_t'], $r['sort_id']);
        });
        return $rows;
    }
}

/* ============================== */
/*        Signings parser         */
/* ============================== */

if (!function_exists('parse_signings_with_index')) {
    function parse_signings_with_index(string $csvFile, string $teamsSource, string $indexPath, string $stampDate): array
    {
        $rows = [];
        if (!is_file($csvFile))
            return $rows;

        $name2abbr = load_team_codes($teamsSource);
        $delim = detect_delim($csvFile);
        $fh = fopen($csvFile, 'r');
        if (!$fh)
            return $rows;

        $groups = []; // key -> data
        $lastKey = null;

        while (($r = fgetcsv($fh, 0, $delim)) !== false) {
            if (count($r) < 3)
                continue;
            $id = (int) trim((string) $r[0]);
            $msg = trim((string) $r[1]);
            $ts = trim((string) $r[2]);

            // Star-note line
            if (preg_match('/^\*+\s*(.+)$/', $msg, $mStar)) {
                $txt = trim($mStar[1]);
                if ($lastKey && isset($groups[$lastKey])) {
                    if (preg_match('/\b(2\s*-\s*way|two\s*-\s*way|two\s+way)\b/i', $txt)) {
                        $groups[$lastKey]['clauses'][] = 'Two-way contract';
                    } elseif (preg_match('/\b(NMC|NTC)\b/i', $txt, $mC)) {
                        $groups[$lastKey]['clauses'][] = strtoupper($mC[1]);
                    } else {
                        $groups[$lastKey]['notes'][] = rtrim($txt, '.');
                    }
                }
                continue;
            }

            // Player signed with Team for $X for Y year(s).
            if (!preg_match('/^(.+?)\s+signed with\s+(.+?)\s+for\s+\$([\d,.\- ]+)\s+for\s+(\d+)\s+year\(s\)\./i', $msg, $m))
                continue;

            $player = trim($m[1]);
            $teamName = trim($m[2]);
            $moneyRaw = trim($m[3]);
            $years = (int) $m[4];

            $amount = (int) preg_replace('/[^\d]/', '', $moneyRaw);  // per-year (AAV)
            if ($amount <= 0 || $years <= 0)
                continue;

            $t = strtotime($ts) ?: 0;
            $minuteKey = $t ? date('Y-m-d H:i', $t) : '0';
            $key = $minuteKey . '|' . normalize_name($player) . '|' . normalize_name($teamName);

            if (!isset($groups[$key])) {
                $inlineClauses = [];
                if (preg_match('/\b(NMC|NTC)\b/i', $msg, $mC))
                    $inlineClauses[] = strtoupper($mC[1]);
                if (preg_match('/\b(2\s*-\s*way|two\s*-\s*way|two\s+way)\b/i', $msg))
                    $inlineClauses[] = 'Two-way contract';

                $groups[$key] = [
                    't' => $t,
                    'id_min' => $id,
                    'id_max' => $id,
                    'player' => $player,
                    'teamName' => $teamName,
                    'years' => $years,
                    'aav' => $amount,
                    'clauses' => $inlineClauses,
                    'notes' => [],
                ];
            } else {
                $groups[$key]['id_min'] = min($groups[$key]['id_min'], $id);
                $groups[$key]['id_max'] = max($groups[$key]['id_max'], $id);
            }

            $lastKey = $key;
        }
        fclose($fh);

        $idx = tx_index_read($indexPath);
        $dirty = false;

        foreach ($groups as $g) {
            $sig = ($g['t'] ? date('Y-m-d H:i', $g['t']) : '0') . '|' .
                normalize_name($g['player']) . '|' . normalize_name($g['teamName']) . '|' .
                $g['id_min'] . '-' . $g['id_max'];

            if (empty($idx[$sig])) {
                $idx[$sig] = $stampDate;
                $dirty = true;
            }

            $aav = (int) $g['aav'];
            $total = (int) $g['aav'] * max(1, (int) $g['years']);

            $rows[] = [
                'date' => $idx[$sig],
                'player' => $g['player'],
                'team' => code_from_name($g['teamName'], $name2abbr),
                'years' => (int) $g['years'],
                'aav' => format_usd($aav),
                'total' => format_usd($total),
                'type' => '',
                'clauses' => implode(' • ', array_unique(array_filter($g['clauses']))),
                'notes' => implode(' ', array_unique(array_filter($g['notes']))),
                // NEW:
                'sort_t' => (int) $g['t'],
                'sort_id' => (int) $g['id_max'],
            ];
        }

        if ($dirty)
            tx_index_write($indexPath, $idx);
        usort($rows, function ($a, $b) {
            if (($a['date'] ?? '') !== ($b['date'] ?? ''))
                return strcmp($b['date'], $a['date']);
            $ta = (int) ($a['sort_t'] ?? 0);
            $tb = (int) ($b['sort_t'] ?? 0);
            if ($ta !== $tb)
                return $tb <=> $ta;
            $ia = (int) ($a['sort_id'] ?? 0);
            $ib = (int) ($b['sort_id'] ?? 0);
            return $ib <=> $ia;
        });
        array_walk($rows, function (&$r) {
            unset($r['sort_t'], $r['sort_id']);
        });
        return $rows;

    }
}

/* ============================== */
/*       Roster Moves parser      */
/* ============================== */

if (!function_exists('parse_roster_moves_with_index')) {
    function parse_roster_moves_with_index(string $csvFile, string $teamsSource, string $indexPath, string $stampDate): array
    {
        $rows = [];
        if (!is_file($csvFile))
            return $rows;

        $name2abbr = load_team_codes($teamsSource);
        $affMap = function_exists('load_affiliates_map') ? load_affiliates_map() : [];
        $delim = detect_delim($csvFile);

        $fh = fopen($csvFile, 'r');
        if (!$fh)
            return $rows;

        $groups = [];
        $lastKey = null;

        while (($r = fgetcsv($fh, 0, $delim)) !== false) {
            if (count($r) < 3)
                continue;
            $id = (int) trim((string) $r[0]);
            $msg = trim((string) $r[1]);
            $ts = trim((string) $r[2]);

            // star-note -> attach to last
            if (preg_match('/^\*+\s*(.+)$/', $msg, $mStar)) {
                if ($lastKey && isset($groups[$lastKey])) {
                    $txt = trim($mStar[1]);
                    if (preg_match('/\b(2\s*-\s*way|two\s*-\s*way|two\s+way)\b/i', $txt)) {
                        $groups[$lastKey]['tags'][] = 'Two-way';
                    } else {
                        $groups[$lastKey]['notes'][] = rtrim($txt, '.');
                    }
                }
                continue;
            }

            $t = strtotime($ts) ?: 0;
            $minuteKey = $t ? date('Y-m-d H:i', $t) : '0';

            $add = function (array $g) use (&$groups, &$lastKey, $id, $minuteKey) {
                $sigParts = [
                    $minuteKey,
                    normalize_name($g['teamName'] ?? ''),
                    normalize_name($g['player'] ?? ''),
                    strtoupper($g['action'] ?? ''),
                    normalize_name($g['toName'] ?? ''),
                    normalize_name($g['fromName'] ?? ''),
                ];
                $key = implode('|', $sigParts);
                if (!isset($groups[$key])) {
                    $g['id_min'] = $id;
                    $g['id_max'] = $id;
                    $groups[$key] = $g + ['tags' => [], 'notes' => []];
                } else {
                    $groups[$key]['id_min'] = min($groups[$key]['id_min'], $id);
                    $groups[$key]['id_max'] = max($groups[$key]['id_max'], $id);
                }
                $lastKey = $key;
            };

            /* ---- your special lines ---- */

            // "<Player> of <Team> was sent to pro."
            if (preg_match('/^(.+?)\s+of\s+(.+?)\s+was\s+sent\s+to\s+pro\.\s*$/i', $msg, $m)) {
                $player = trim($m[1]);
                $teamName = trim($m[2]);
                $fromAHL = function_exists('affiliate_for_team') ? affiliate_for_team($teamName, $affMap) : ($teamName . ' AHL');
                $add(['t' => $t, 'teamName' => $teamName, 'player' => $player, 'fromName' => $fromAHL, 'action' => 'Recalled', 'tags' => ['Recall']]);
                continue;
            }

            // "<Player> of <Team> was sent to farm."
            if (preg_match('/^(.+?)\s+of\s+(.+?)\s+was\s+sent\s+to\s+farm\.\s*$/i', $msg, $m)) {
                $player = trim($m[1]);
                $teamName = trim($m[2]);
                $toAHL = function_exists('affiliate_for_team') ? affiliate_for_team($teamName, $affMap) : ($teamName . ' AHL');
                $add(['t' => $t, 'teamName' => $teamName, 'player' => $player, 'toName' => $toAHL, 'action' => 'Assigned', 'tags' => ['AHL']]);
                continue;
            }

            // "<Team> paid $X to release <Player>."
            if (preg_match('/^(.+?)\s+paid\s+\$([\d,.\- ]+)\s+to\s+release\s+(.+?)\.\s*$/i', $msg, $m)) {
                $teamName = trim($m[1]);
                $amount = (int) preg_replace('/[^\d]/', '', $m[2]);
                $player = trim($m[3]);
                $note = 'Buyout ' . format_usd($amount) . '/yr';
                $add(['t' => $t, 'teamName' => $teamName, 'player' => $player, 'action' => 'Bought out', 'tags' => ['Buyout'], 'notes' => [$note]]);
                continue;
            }

            // "Game N - <Player> from <Team> is injured ... out for <n> <unit>."
            if (preg_match('/^Game\s+\d+\s*-\s*(.+?)\s+from\s+(.+?)\s+is\s+injured.*?\bout\s+for\s+(\d+)\s*(day|days|week|weeks|month|months)\b/i', $msg, $m)) {
                $player = trim($m[1]);
                $teamName = trim($m[2]);
                $n = (int) $m[3];
                $unit = strtolower($m[4]);
                $days = match ($unit) {
                    'day', 'days' => $n,
                    'week', 'weeks' => $n * 7,
                    'month', 'months' => $n * 30,
                    default => $n
                };
                if ($days > 7 && is_nhl_team($teamName, $name2abbr)) {
                    // Only auto-IR for NHL clubs; ignore farm/affiliates
                    $add(['t' => $t, 'teamName' => $teamName, 'player' => $player, 'action' => 'Placed on IR', 'tags' => ['IR']]);
                }
                continue;
            }

            /* ---- generic patterns ---- */

            if (preg_match('/^(.+?)\s+claimed\s+(.+?)\s+off\s+waivers(?:\s+from\s+(.+?))?\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => trim($m[1]), 'player' => trim($m[2]), 'fromName' => trim($m[3] ?? ''), 'action' => 'Claimed (waivers)', 'tags' => ['Waivers']]);
                continue;
            }
            if (preg_match('/^(.+?)\s+placed\s+(.+?)\s+on\s+(unconditional\s+)?waivers\.\s*$/i', $msg, $m)) {
                $tags = ['Waivers'];
                if (!empty($m[3]))
                    $tags[] = 'Unconditional';
                $add(['t' => $t, 'teamName' => trim($m[1]), 'player' => trim($m[2]), 'action' => 'Placed on waivers', 'tags' => $tags]);
                continue;
            }
            if (preg_match('/^(.+?)\s+was\s+placed\s+on\s+(unconditional\s+)?waivers\s+by\s+(.+?)\.\s*$/i', $msg, $m)) {
                $tags = ['Waivers'];
                if (!empty($m[2]))
                    $tags[] = 'Unconditional';
                $add(['t' => $t, 'teamName' => trim($m[3]), 'player' => trim($m[1]), 'action' => 'Placed on waivers', 'tags' => $tags]);
                continue;
            }
            if (preg_match('/^(.+?)\s+cleared\s+waivers(?:\s+and\s+was\s+assigned\s+to\s+(.+?))?\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => '', 'player' => trim($m[1]), 'toName' => trim($m[2] ?? ''), 'action' => 'Cleared waivers', 'tags' => ['Waivers']]);
                continue;
            }
            if (preg_match('/^(.+?)\s+assigned\s+(.+?)\s+to\s+(.+?)\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => trim($m[1]), 'player' => trim($m[2]), 'toName' => trim($m[3]), 'action' => 'Assigned']);
                continue;
            }
            if (preg_match('/^(.+?)\s+was\s+assigned\s+to\s+(.+?)\s+by\s+(.+?)\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => trim($m[3]), 'player' => trim($m[1]), 'toName' => trim($m[2]), 'action' => 'Assigned']);
                continue;
            }
            if (preg_match('/^(.+?)\s+recalled\s+(.+?)\s+from\s+(.+?)\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => trim($m[1]), 'player' => trim($m[2]), 'fromName' => trim($m[3]), 'action' => 'Recalled']);
                continue;
            }
            if (preg_match('/^(.+?)\s+was\s+recalled\s+from\s+(.+?)\s+by\s+(.+?)\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => trim($m[3]), 'player' => trim($m[1]), 'fromName' => trim($m[2]), 'action' => 'Recalled']);
                continue;
            }
            if (preg_match('/^(.+?)\s+loaned\s+(.+?)\s+to\s+(.+?)\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => trim($m[1]), 'player' => trim($m[2]), 'toName' => trim($m[3]), 'action' => 'Loaned', 'tags' => ['Loan']]);
                continue;
            }
            if (preg_match('/^(.+?)\s+bought\s+out\s+(.+?)\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => trim($m[1]), 'player' => trim($m[2]), 'action' => 'Bought out', 'tags' => ['Buyout']]);
                continue;
            }
            if (preg_match('/^(.+?)\s+bought\s+out\s+by\s+(.+?)\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => trim($m[2]), 'player' => trim($m[1]), 'action' => 'Bought out', 'tags' => ['Buyout']]);
                continue;
            }
            if (preg_match('/^(.+?)\s+placed\s+(.+?)\s+on\s+(LTIR|IR)\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => trim($m[1]), 'player' => trim($m[2]), 'action' => 'Placed on ' . strtoupper($m[3]), 'tags' => [strtoupper($m[3])]]);
                continue;
            }
            if (preg_match('/^(.+?)\s+activated\s+(.+?)\s+from\s+(LTIR|IR)\.\s*$/i', $msg, $m)) {
                $add(['t' => $t, 'teamName' => trim($m[1]), 'player' => trim($m[2]), 'action' => 'Activated from ' . strtoupper($m[3]), 'tags' => [strtoupper($m[3])]]);
                continue;
            }

            // else ignore
        }
        fclose($fh);

        // Build sticky rows
        $idx = tx_index_read($indexPath);
        $dirty = false;

        foreach ($groups as $key => $g) {
            $sig = $key . '|' . $g['id_min'] . '-' . $g['id_max'];
            if (empty($idx[$sig])) {
                $idx[$sig] = $stampDate;
                $dirty = true;
            }

            $rows[] = [
                'date' => $idx[$sig],
                'team' => code_from_name($g['teamName'] ?? '', $name2abbr),
                'teamName' => $g['teamName'] ?? '',
                'player' => $g['player'] ?? '',
                'action' => $g['action'] ?? '',
                'to' => $g['toName'] ?? '',
                'from' => $g['fromName'] ?? '',
                'tags' => implode(' • ', array_unique(array_filter($g['tags'] ?? []))),
                'notes' => implode(' ', array_unique(array_filter($g['notes'] ?? []))),
                // NEW:
                'sort_t' => (int) ($g['t'] ?? 0),
                'sort_id' => (int) ($g['id_max'] ?? 0),
            ];
        }

        if ($dirty)
            tx_index_write($indexPath, $idx);
        usort($rows, function ($a, $b) {
            if (($a['date'] ?? '') !== ($b['date'] ?? ''))
                return strcmp($b['date'], $a['date']);
            $ta = (int) ($a['sort_t'] ?? 0);
            $tb = (int) ($b['sort_t'] ?? 0);
            if ($ta !== $tb)
                return $tb <=> $ta;
            $ia = (int) ($a['sort_id'] ?? 0);
            $ib = (int) ($b['sort_id'] ?? 0);
            return $ib <=> $ia;
        });
        array_walk($rows, function (&$r) {
            unset($r['sort_t'], $r['sort_id']); });
        return $rows;
    }
}
if (!function_exists('is_nhl_team')) {
    // Uses the already-loaded name→abbr map to decide if a name is an NHL club
    function is_nhl_team(string $name, array $name2abbr): bool
    {
        return isset($name2abbr[normalize_name($name)]);
    }
}