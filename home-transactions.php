<?php
// home-transactions.php — data prep + row rendering for the Home “Latest Transactions” list.
// Contract: this partial is INCLUDED from inside the <ul class="tx-mini-list"> in home.php
// and MUST echo only <li class="tx-mini-row …"> items (no extra wrappers).

require_once __DIR__ . '/includes/sim_clock.php';
require_once __DIR__ . '/includes/tx_helpers.php';

// --- Date context ---
$SIM_DATE = sim_clock_now();

// --- Sources ---
$TEAMS_JSON     = __DIR__ . '/assets/json/teams.json';
$LEAGUE_LOG     = __DIR__ . '/data/uploads/UHA-V3LeagueLog.csv';
$TRADE_INDEX    = __DIR__ . '/assets/json/trade_dates.json';
$SIGNING_INDEX  = __DIR__ . '/assets/json/signing_dates.json';
$MOVE_INDEX     = __DIR__ . '/assets/json/move_dates.json';

// --- Team code maps ---
$CODE2ABBR = load_team_codes($TEAMS_JSON);

// --- Parse sources ---
$trades   = parse_trades_with_index($LEAGUE_LOG, $TEAMS_JSON, $TRADE_INDEX,    $SIM_DATE);
$signings = parse_signings_with_index($LEAGUE_LOG, $TEAMS_JSON, $SIGNING_INDEX, $SIM_DATE);
$moves    = parse_roster_moves_with_index($LEAGUE_LOG, $TEAMS_JSON, $MOVE_INDEX, $SIM_DATE);

// Mark types and merge
$merged = [];
foreach ($trades as $t)   { $t['__type'] = 'trade';   $merged[] = $t; }
foreach ($signings as $s) { $s['__type'] = 'signing'; $merged[] = $s; }
foreach ($moves as $m)    { $m['__type'] = 'move';    $merged[] = $m; }

// Sort newest first
usort($merged, function($a, $b){
  $da = strtotime($a['date'] ?? '');
  $db = strtotime($b['date'] ?? '');
  if ($da === $db) return 0;
  return $db <=> $da;
});

// Top 50 (JS toggles 25/50)
$latestTxTop = array_slice($merged, 0, 50);

// --- Helpers (guarded) ---
if (!function_exists('tx_norm_asset')) {
  function tx_norm_asset(string $s): string {
    $s = preg_replace('~\s*\(.*?\)~', '', $s);
    $s = preg_replace('~^Rights to\s+~i', '', $s);
    $s = preg_replace('~\bConditional\b~i', 'cond.', $s);
    $s = preg_replace('~\bRound\b~i', 'Rd', $s);
    $s = str_replace(['First','Second','Third','Fourth','Fifth','Sixth','Seventh'],
                     ['1st','2nd','3rd','4th','5th','6th','7th'], $s);
    return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('tx_top_assets')) {
  function tx_top_assets(string $assets, int $max = 2): array {
    $parts = array_values(array_filter(array_map('trim', preg_split('~\s*\+\s*~', (string)$assets))));
    $out = [];
    foreach ($parts as $p) { $out[] = tx_norm_asset($p); if (count($out) >= $max) break; }
    return $out ?: ['(details)'];
  }
}

if (!function_exists('tx_home_summary')) {
  // Compact summary for the “desc” column
  function tx_home_summary(array $x, array $abbr, int $baseDraftYear): string {
    switch ($x['__type'] ?? '') {
      case 'trade':
        $fromA = htmlspecialchars($abbr[$x['from']] ?? ($x['from'] ?? ''), ENT_QUOTES, 'UTF-8');
        $toA   = htmlspecialchars($abbr[$x['to']]   ?? ($x['to']   ?? ''), ENT_QUOTES, 'UTF-8');
        $left  = implode('; ', tx_top_assets($x['assets_out'] ?? '', 2));
        $right = implode('; ', tx_top_assets($x['assets_in']  ?? '', 2));
        return "{$fromA}: {$left} | {$toA}: {$right}";
      case 'signing':
        $yrs = (int)($x['years'] ?? 0);
        $aav = htmlspecialchars($x['aav'] ?? '', ENT_QUOTES, 'UTF-8');
        $ply = htmlspecialchars($x['player'] ?? '', ENT_QUOTES, 'UTF-8');
        return "{$ply} • {$yrs}yr • AAV {$aav}";
      default: // roster move
        $ply  = htmlspecialchars($x['player'] ?? '', ENT_QUOTES, 'UTF-8');
        $act  = htmlspecialchars($x['action'] ?? '', ENT_QUOTES, 'UTF-8');
        $to   = !empty($x['to'])   ? ' • to '   . htmlspecialchars($x['to'],   ENT_QUOTES, 'UTF-8') : '';
        $from = !empty($x['from']) ? ' • from ' . htmlspecialchars($x['from'], ENT_QUOTES, 'UTF-8') : '';
        return "{$ply} • {$act}{$to}{$from}";
    }
  }
}

if (!function_exists('tx_trade_headline')) {
  function tx_trade_headline(array $t, array $abbr, int $max = 2): string {
    $fromA = htmlspecialchars($abbr[$t['from']] ?? ($t['from'] ?? ''), ENT_QUOTES, 'UTF-8');
    $toA   = htmlspecialchars($abbr[$t['to']]   ?? ($t['to']   ?? ''), ENT_QUOTES, 'UTF-8');
    $left  = implode('; ', tx_top_assets($t['assets_out'] ?? '', $max));
    $right = implode('; ', tx_top_assets($t['assets_in']  ?? '', $max));
    return "{$fromA} ↔ {$toA} — {$fromA}: {$left} | {$toA}: {$right}";
  }
}

// Base draft year: follow transactions.php if defined; else SIM year
$BASE_DRAFT_YEAR = defined('BASE_DRAFT_YEAR') ? (int) BASE_DRAFT_YEAR
                                              : (int) date('Y', strtotime($SIM_DATE ?: 'now'));
// ---------- emit to window.UHA (consumed by assets/js/home.js) ----------

// --- resolve abbrev map + base year (used in headlines) ---
$abbr = is_array($CODE2ABBR ?? null) ? $CODE2ABBR : (is_array($teamAbbrFromId ?? null) ? $teamAbbrFromId : []);
$baseYear = defined('BASE_DRAFT_YEAR') ? (int) BASE_DRAFT_YEAR : (int) date('Y', strtotime($SIM_DATE ?? 'now'));

// 1) define BEFORE $pack
$mkHeadline = static function(array $m) use ($abbr, $baseYear): string {
  $type = strtolower((string)($m['type'] ?? $m['kind'] ?? $m['category'] ?? $m['__type'] ?? ''));
  if ($type === 'trade' && function_exists('tx_trade_headline')) {
    return (string) tx_trade_headline($m, $abbr, 2);                 // plain text
  }
  if (function_exists('tx_home_summary')) {
    return (string) tx_home_summary($m, $abbr, $baseYear);           // plain text
  }
  // fallback
  $team = $abbr[$m['team'] ?? ''] ?? ($m['team'] ?? ($m['to'] ?? ($m['from'] ?? '')));
  $what = $m['title'] ?? $m['what'] ?? ucfirst($type ?: 'Transaction');
  return trim($team ? "$team — $what" : $what);
};

// 2) pack uses the same param name ($m) and the already-defined $mkHeadline
// normalize abbrev map + keep $mkHeadline defined above this
$pack = static function(array $m) use ($abbr, $mkHeadline): array {
  $type = strtolower((string)($m['__type'] ?? $m['type'] ?? $m['kind'] ?? 'other'));

  // raw team codes (uppercased for logo filenames)
  $fromCode = strtoupper((string)($m['from'] ?? ''));
  $toCode   = strtoupper((string)($m['to']   ?? ''));
  $teamCode = strtoupper((string)($m['team'] ?? ($type === 'trade' ? '' : ($m['to'] ?? $m['from'] ?? ''))));

  // display abbrevs (safe lookups — no undefined index warnings)
  if ($type === 'trade') {
    $whoL = (string)($abbr[$fromCode] ?? $fromCode);
    $whoR = (string)($abbr[$toCode]   ?? $toCode);
  } else {
    $whoL = (string)($abbr[$teamCode] ?? $teamCode);
    $whoR = '';
  }

  return [
    'type'      => $type,
    'date'      => (string)($m['date'] ?? ''),
    'whoL'      => $whoL,
    'whoR'      => $whoR,
    'headline'  => $mkHeadline($m),  // plain-text summary
    'fromCode'  => $fromCode,        // for logos in JS
    'toCode'    => $toCode,
    'teamCode'  => $teamCode,
  ];
};


$src = is_array($latestTxTop ?? null) ? $latestTxTop : [];
$txItems = array_map($pack, $src);

// 3) emit to window.UHA (JS paints DOM)
?>
<script>
(function (U) {
  U = window.UHA = window.UHA || {};
  U.transactions = U.transactions || {};
  U.transactions.items = <?= json_encode($txItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
})(window.UHA);
</script>

