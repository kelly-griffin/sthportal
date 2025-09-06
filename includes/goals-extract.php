<?php
/**
 * includes/goals-extract.php
 * Read /data/uploads/{gid}.html (or .htm) and extract goal lines into:
 *   ['away' => [ {player, period, time, assists[]}... ],
 *    'home' => [ {player, period, time, assists[]}... ]]
 *
 * Returns [] if nothing was found (caller can omit the block).
 */

if (!function_exists('uha_extract_goals')) {
    function uha_extract_goals(string $gid, string $awayAbbr, string $homeAbbr): array
    {
        // ---------- locate file ----------
        $root = dirname(__DIR__);
        $candidates = [
            $root . '/data/uploads/' . $gid . '.html',
            $root . '/data/uploads/' . $gid . '.htm',
        ];
        $path = null;
        foreach ($candidates as $p) {
            if (is_file($p) && is_readable($p)) {
                $path = $p;
                break;
            }
        }
        if (!$path)
            return [];

        $html = (string) @file_get_contents($path);
        if ($html === '')
            return [];

        // ---------- DOM + XPath ----------
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        if (stripos($html, '<meta charset=') === false) {
            $html = '<meta charset="utf-8">' . $html;
        }
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new DOMXPath($dom);

        // ---------- helpers ----------
        $norm = static function (?string $s): string {
            $s = html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8');
            $s = preg_replace('~\s+~u', ' ', $s ?? '');
            $s = trim($s);
            $s = mb_strtolower($s, 'UTF-8');
            $s = preg_replace('~[^\p{L}\p{N}\s]~u', '', $s);
            return trim(preg_replace('~\s+~u', ' ', $s));
        };
        $innerHTML = static function (DOMNode $n) use ($dom): string {
            $out = '';
            foreach ($n->childNodes as $c)
                $out .= $dom->saveHTML($c);
            return $out;
        };
        $tidyLine = static function (string $line): string {
            $line = html_entity_decode(strip_tags($line), ENT_QUOTES, 'UTF-8');
            $line = preg_replace('~\s+~u', ' ', $line);
            return trim($line);
        };

        // ---------- build map: normalized team name -> ABBR from class="STHSBoxScore_TeamName_XXX"
        $nameToAbbr = [];
        foreach ($xp->query("//*[contains(@class,'STHSBoxScore_TeamName_')]") as $el) {
            $cls = (string) $el->getAttribute('class');
            if (preg_match('~STHSBoxScore_TeamName_([A-Z]{2,4})~', $cls, $m)) {
                $abbr = strtoupper($m[1]);
                $name = $norm($el->textContent);
                if ($abbr && $name)
                    $nameToAbbr[$name] = $abbr;
            }
        }

        // ---------- result buckets ----------
        $out = ['away' => [], 'home' => []];
        $foundAny = false;

// drop this over your existing $parseLine in includes/goals-extract.php
$parseLine = static function (string $line, string $period): ?array {
  // quick rejects
  if ($line === '' || stripos($line, 'no goal') !== false) return null;

  // "Team , Rest  at MM:SS  After"
  if (!preg_match('~^\s*(?:\d+\.\s*)?(?<team>[^,]+?)\s*,\s*(?<rest>.+?)\s+at\s+(?<time>\d{1,2}:\d{2})(?<after>.*)$~u', $line, $m)) {
    return null;
  }
  $team  = trim($m['team']);
  $rest  = trim($m['rest']);   // before time (contains assists)
  $time  = $m['time'];         // MM:SS
  $after = trim($m['after']);  // after time (contains tags like (PP), (SH), …)

  // PLAYER: remove (...) from $rest and trailing digits
  $player = preg_replace('~\([^()]*\)~u', '', $rest);
  $player = preg_replace('~\s+\d+$~', '', $player);
  $player = trim($player);
  if ($player === '') return null;

  // ASSISTS: first (...) group in $rest that contains names (skip tag-only groups)
  $assists = [];
  if (preg_match_all('~\(([^()]*)\)~u', $rest, $rg)) {
    $TAGSET = ['PP','PPG','PPO','SH','SHG','EN','ENG','EV','OT','GWG','PS','SO','4ON4','3ON3','4V4','3V3'];
    foreach ($rg[1] as $grp) {
      $tokens = array_values(array_filter(array_map('trim', explode(',', $grp)), static fn($t) => $t !== ''));
      if (!$tokens) continue;
      if (count($tokens) === 1 && strcasecmp($tokens[0], 'Unassisted') === 0) { $assists = []; break; }

      $names = [];
      $allTags = true;
      foreach ($tokens as $tok) {
        $u = strtoupper(preg_replace('~[\s\.-]~', '', $tok));
        $isTag = in_array($u, $TAGSET, true) || preg_match('~^\d+V\d+$~', $u);
        if ($isTag) continue;
        $allTags = false;
        $names[] = preg_replace('~\s+\d+$~', '', $tok);
      }
      if (!$allTags && $names) { $assists = $names; break; }
    }
  }

  // NOTE (strength): scan (...) groups in $after (post-time), prefer the last group
  $note = null;
  if ($after !== '' && preg_match_all('~\(([^()]*)\)~u', $after, $ag)) {
    $map = [
      'PP' => 'PPG', 'PPG' => 'PPG', 'PPO' => 'PPG',
      'SH' => 'SHG', 'SHG' => 'SHG',
      'EN' => 'EN',  'ENG' => 'EN',
      'PS' => 'PS',  'SO'  => 'SO',
    ];
    for ($i = count($ag[1]) - 1; $i >= 0; $i--) {
      foreach (explode(',', $ag[1][$i]) as $tok) {
        $u = strtoupper(preg_replace('~[\s\.-]~', '', trim($tok)));
        if (isset($map[$u])) { $note = $map[$u]; break 2; }
      }
    }
  }

  return [
    'teamRaw' => $team,
    'goal' => [
      'player'  => $player,
      'period'  => $period,
      'time'    => $time,
      'assists' => $assists,
      'note'    => $note, // 'PPG' | 'SHG' | 'EN' | 'PS' | 'SO' | null
    ],
  ];
};
        // ---------- decide side (captures are defined ABOVE; order matters) ----------
        $decideSide = static function (string $teamRaw) use ($norm, $nameToAbbr, $homeAbbr, $awayAbbr): ?string {
            $tn = $norm($teamRaw);
            if ($tn === '')
                return null;

            if (isset($nameToAbbr[$tn])) {
                $abbr = strtoupper($nameToAbbr[$tn]);
                if ($abbr === strtoupper($homeAbbr))
                    return 'home';
                if ($abbr === strtoupper($awayAbbr))
                    return 'away';
            }

            // loose fallback (rare)
            if ($awayAbbr && stripos($teamRaw, $awayAbbr) !== false)
                return 'away';
            if ($homeAbbr && stripos($teamRaw, $homeAbbr) !== false)
                return 'home';

            return null;
        };

        // ============================================================
        // Path A: legacy blocks — <div class="STHSGame_GoalPeriod1/2/3/OT">
        // ============================================================
        foreach (['1', '2', '3', 'OT'] as $pVal) {
            $q = sprintf("//div[contains(@class,'STHSGame_GoalPeriod%s')]", $pVal);
            foreach ($xp->query($q) as $blk) {
                $lines = preg_split('~<br\s*/?>~i', $innerHTML($blk));
                foreach ($lines as $ln) {
                    $t = $tidyLine($ln);
                    if ($t === '')
                        continue;
                    $parsed = $parseLine($t, $pVal);
                    if (!$parsed)
                        continue;
                    $side = $decideSide($parsed['teamRaw']);
                    if (!$side)
                        continue;
                    $out[$side][] = $parsed['goal'];
                    $foundAny = true;
                }
            }
        }

        // ============================================================
        // Path B: "Period per period" table
        // ============================================================
        if (!$foundAny) {
            // table may have a specific class OR just contain the phrase
            $tbl = $xp->query("(//table[contains(@class,'STHSBoxScore_PeriodPerPeriod') or .//td[contains(translate(normalize-space(.),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'period per period')]])[1]")->item(0);
            if ($tbl instanceof DOMNode) {
                $hdrs = $xp->query(".//tr[td[contains(translate(normalize-space(.),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'period')]]", $tbl);
                foreach ($hdrs as $hdr) {
                    $hdrText = $tidyLine($hdr->textContent);
                    $p = null;
                    if (preg_match('~(^|\s)(1st|first)\b~i', $hdrText))
                        $p = '1';
                    elseif (preg_match('~(^|\s)(2nd|second)\b~i', $hdrText))
                        $p = '2';
                    elseif (preg_match('~(^|\s)(3rd|third)\b~i', $hdrText))
                        $p = '3';
                    elseif (preg_match('~\b(ot|overtime)\b~i', $hdrText))
                        $p = 'OT';
                    if (!$p)
                        continue;

                    // lines live in the immediate next row, first cell
                    $cell = $xp->query('./following-sibling::tr[1]/td[1]', $hdr)->item(0);
                    if (!$cell)
                        continue;

                    $lines = preg_split('~<br\s*/?>~i', $innerHTML($cell));
                    foreach ($lines as $ln) {
                        $t = $tidyLine($ln);
                        if ($t === '')
                            continue;
                        $parsed = $parseLine($t, $p);
                        if (!$parsed)
                            continue;
                        $side = $decideSide($parsed['teamRaw']);
                        if (!$side)
                            continue;
                        $out[$side][] = $parsed['goal'];
                        $foundAny = true;
                    }
                }
            }
        }

        // ---------- finalize ----------
        if (!$foundAny || (count($out['away']) === 0 && count($out['home']) === 0)) {
            return [];
        }
        return $out;
    }
}
?>
<script>
    // Example lookups you can replace with your real sources:
    window.UHA = window.UHA || {};
    UHA.playerIdResolver = (name, team) => (UHA.playerIds?.[team]?.[name] || null);
    // Minimal sample. Replace with your full map when ready.
    UHA.playerIds = {
        EDM: { "Connor McDavid": "8478402" }
    };
    // Team colors (primary hex); used for avatar rings/placeholders.
    UHA.teamColors = {
        BUF: "#003087", OTT: "#C52032", FLA: "#C8102E", EDM: "#041E42" /* … */
    };
</script>