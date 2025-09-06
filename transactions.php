<?php
/**
 * UHA Portal — transactions.php
 * Task: Clean single variant for roster moves (left mini-label Assign/Buyout/Recall/IR)
 *       + bottom note row fix.
 * Date: 2025-08-30
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/tx_helpers.php';   // <- helpers (no duplicates)
require_once __DIR__ . '/includes/sim_clock.php';     // <- site-wide sim date

/* ---------- Paths ---------- */
$TEAMS_JSON = __DIR__ . '/assets/json/teams.json';
$LEAGUE_LOG = __DIR__ . '/data/uploads/UHA-V3LeagueLog.csv';
$TRADE_INDEX = __DIR__ . '/assets/json/trade_dates.json';  // sticky index
$SIGNING_INDEX = __DIR__ . '/assets/json/signing_dates.json';
$MOVE_INDEX = __DIR__ . '/assets/json/move_dates.json';
/* ---------- Current "sim date" (stamps new trades) ---------- */
$SIM_DATE = sim_clock_now();

/* ---------- Build datasets ---------- */
$CODE2NAME = load_team_names_by_code($TEAMS_JSON);
$CODE2ABBR = load_team_codes($TEAMS_JSON);
$trades = parse_trades_with_index($LEAGUE_LOG, $TEAMS_JSON, $TRADE_INDEX, $SIM_DATE);


// Decide what to show on the right (logo + label)
function tx_move_side_label(array $m, array $code2abbr): array
{
  $name = '';
  $kind = ''; // kind: 'to' or 'from'
  if (!empty($m['to'])) {
    $name = $m['to'];
    $kind = 'to';
  } elseif (!empty($m['from'])) {
    $name = $m['from'];
    $kind = 'from';
  }

  if ($name === '') { // fallbacks for non-team “destinations”
    $action = strtolower($m['action'] ?? '');
    if (str_contains($action, 'waiver')) {
      $name = 'Waivers';
      $kind = 'to';
    } elseif (str_contains($action, 'bought out')) {
      $name = 'Free Agents';
      $kind = 'to';
    } elseif (str_contains($action, 'placed on ltir')) {
      $name = 'Long-Term IR';
      $kind = 'to';
    } elseif (str_contains($action, 'placed on ir')) {
      $name = 'Injured Reserve';
      $kind = 'to';
    } elseif (str_contains($action, 'activated')) {
      $name = 'Active Roster';
      $kind = 'to';
    }
  }

  // Try to map to an NHL code for a logo (affiliates likely won’t match — that’s ok)
  $code = $name ? ($code2abbr[normalize_name($name)] ?? null) : null;
  $badge = null;
  if (!$code) {
    $map = ['Waivers' => 'WA', 'Free Agents' => 'FA', 'Injured Reserve' => 'IR', 'Long-Term IR' => 'LTIR', 'Active Roster' => 'ACT'];
    $badge = $map[$name] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
  }
  return ['kind' => $kind, 'name' => $name, 'code' => $code, 'badge' => $badge];
}


/**
 * Left mini-label for roster moves (Assign | Buyout | Recall | IR)
 * Looks at action + notes + tags. Prefers specific verbs, then falls back to “to/from”.
 */
if (!function_exists('tx_move_left_label')) {
  function tx_move_left_label(array $m): string
  {
    $hay = strtolower(trim(
      ($m['action'] ?? '') . ' ' .
      ($m['notes'] ?? '') . ' ' .
      ($m['tags'] ?? '')
    ));

    // Buyout
    if (str_contains($hay, 'bought out') || str_contains($hay, 'buyout')) {
      return 'Buyout';
    }

    // IR / LTIR (placed or activated)
    if (
      str_contains($hay, 'ltir') ||
      str_contains($hay, 'long-term injured reserve') ||
      str_contains($hay, 'injured reserve') ||
      str_contains($hay, 'placed on ir') ||
      str_contains($hay, 'placed on ltir') ||
      str_contains($hay, 'activated from ir') ||
      str_contains($hay, 'activated off ir') ||
      str_contains($hay, 'activated from ltir') ||
      str_contains($hay, 'activated off ltir')
    ) {
      return 'IR';
    }

    // Recall
    if (str_contains($hay, 'recall')) {
      return 'Recall';
    }

    // Waiver
    if (str_contains($hay, 'waiver')) {
      return 'Waiver';
    }

    // Assign (assign/loan/send down/option/reassign)
    if (
      str_contains($hay, 'assign') ||
      str_contains($hay, 'loaned') ||
      str_contains($hay, 'sent down') ||
      str_contains($hay, 'reassigned') ||
      str_contains($hay, 'optioned')
    ) {
      return 'Assign';
    }

    // Directional tie-breakers when verbs aren’t explicit
    $act = strtolower((string) ($m['action'] ?? ''));
    if (str_contains(' ' . $act . ' ', ' from '))
      return 'Recall';
    if (str_contains(' ' . $act . ' ', ' to '))
      return 'Assign';

    return 'Assign';
  }
}

$BASE_DRAFT_YEAR = 2025;

$signings = parse_signings_with_index($LEAGUE_LOG, $TEAMS_JSON, $SIGNING_INDEX, $SIM_DATE);
$moves = parse_roster_moves_with_index($LEAGUE_LOG, $TEAMS_JSON, $MOVE_INDEX, $SIM_DATE);
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title>Transactions</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <script src="assets/js/transactions.js" defer></script>
</head>

<body>
  <div class="site">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="transactions-container">
        <div class="transactions-card">
          <h1>Transactions</h1>

          <nav class="stats-subnav" aria-label="Transactions Sections">
            <a href="#trades" class="tab-link active" data-tab="trades">Trades</a>
            <a href="#signings" class="tab-link" data-tab="signings">Signings</a>
            <a href="#moves" class="tab-link" data-tab="moves">Roster Moves</a>
          </nav>

          <!-- ==================== TRADES ==================== -->
          <section id="tab-trades" class="tab-panel active">
            <div class="tx-toolbar">
              <div class="label">TRADES</div>
              <div class="spacer"></div>
              <label>Team
                <select id="trades-team">
                  <option value="">All</option>
                  <?php
                  $opts = [];
                  foreach ($trades as $t) {
                    $opts[$t['from']] = true;
                    $opts[$t['to']] = true;
                  }
                  foreach (array_keys($opts) as $c)
                    echo '<option>' . htmlspecialchars($c) . '</option>';
                  ?>
                </select>
              </label>
              <label>Search <input id="trades-q" type="search" placeholder="player / pick / note"></label>
            </div>

            <ul class="tx-list" id="trades-list">
              <?php if (empty($trades)): ?>
                <li class="tx-card">
                  <div class="tx-mid">No trades yet.</div>
                </li>
              <?php else:
                foreach ($trades as $t): ?>
                  <li class="tx-card trade-card" data-date="<?= htmlspecialchars($t['date']) ?>"
                    data-from="<?= htmlspecialchars($t['from']) ?>" data-to="<?= htmlspecialchars($t['to']) ?>"
                    data-team="<?= htmlspecialchars($t['from'] ?: $t['to']) ?>"
                    data-text="<?= htmlspecialchars($t['assets_out'] . ' ' . $t['assets_in']) ?>">

                    <div class="trade-grid">
                      <!-- 1) LEFT TEAM -->
                      <div class="team left">
                        <img class="logo" src="assets/img/logos/<?= htmlspecialchars($t['from']) ?>_dark.svg" alt="">
                        <div>
                          <div class="team-full"><?= htmlspecialchars($CODE2NAME[$t['from']] ?? $t['from']) ?></div>
                          <div class="label">Receives</div>
                        </div>
                      </div>

                      <!-- 2) LEFT ASSETS (pulled toward center) -->
                      <div class="assets assets-left">
                        <ul>
                          <?php
                          $leftAssets = array_filter(array_map('trim', explode('+', str_replace(' + ', '+', $t['assets_in']))));
                          foreach ($leftAssets as $a):
                            ?>
                            <li><?= htmlspecialchars(prettify_asset($a, $BASE_DRAFT_YEAR)) ?></li>
                          <?php endforeach; ?>
                        </ul>
                      </div>

                      <!-- 3) DATE PILL -->
                      <div class="center">
                        <span class="date-pill"><?= htmlspecialchars($t['date']) ?></span>
                      </div>

                      <!-- 4) RIGHT ASSETS (pulled toward center) -->
                      <div class="assets assets-right">
                        <ul>
                          <?php
                          $rightAssets = array_filter(array_map('trim', explode('+', str_replace(' + ', '+', $t['assets_out']))));
                          foreach ($rightAssets as $a):
                            ?>
                            <li><?= htmlspecialchars(prettify_asset($a, $BASE_DRAFT_YEAR)) ?></li>
                          <?php endforeach; ?>
                        </ul>
                      </div>

                      <!-- 5) RIGHT TEAM -->
                      <div class="team right">
                        <div>
                          <div class="team-full"><?= htmlspecialchars($CODE2NAME[$t['to']] ?? $t['to']) ?></div>
                          <div class="label">Receives</div>
                        </div>
                        <img class="logo" src="assets/img/logos/<?= htmlspecialchars($t['to']) ?>_dark.svg" alt="">
                      </div>
                    </div>

                    <?php if (!empty($t['notes'])): ?>
                      <div class="tx-meta" style="margin-top:10px;">
                        <button type="button" class="tx-about-toggle">About trade</button>
                      </div>
                      <div class="tx-about"><?= nl2br(htmlspecialchars($t['notes'])) ?></div>
                    <?php endif; ?>
                  </li>

                <?php endforeach; endif; ?>
            </ul>
          </section>

          <!-- ==================== SIGNINGS ==================== -->
          <section id="tab-signings" class="tab-panel">
            <div class="tx-toolbar">
              <div class="label">SIGNINGS</div>
              <div class="spacer"></div>
              <label>Team
                <select id="signings-team">
                  <option value="">All</option>
                  <?php
                  $opts = [];
                  foreach ($signings as $s) {
                    $opts[$s['team']] = true;
                  }
                  foreach (array_keys($opts) as $c)
                    echo '<option>' . htmlspecialchars($c) . '</option>';
                  ?>
                </select>
              </label>
              <label>Search <input id="signings-q" type="search" placeholder="player / notes"></label>
            </div>

            <ul class="tx-list" id="signings-list">
              <?php if (empty($signings)): ?>
                <li class="tx-card">
                  <div class="tx-mid">No signings yet.</div>
                </li>
              <?php else:
                foreach ($signings as $s): ?>
                  <li class="tx-card signing-card" data-team="<?= htmlspecialchars($s['team']) ?>"
                    data-date="<?= htmlspecialchars($s['date']) ?>"
                    data-text="<?= htmlspecialchars($s['player'] . ' ' . $s['team'] . ' ' . $s['notes']) ?>">

                    <div class="signing-grid">
                      <!-- team -->
                      <div class="team left">
                        <img class="logo" src="assets/img/logos/<?= htmlspecialchars($s['team']) ?>_dark.svg" alt="">
                        <div>
                          <div class="team-full"><?= htmlspecialchars($CODE2NAME[$s['team']] ?? $s['team']) ?></div>
                          <div class="label">Signs</div>
                        </div>
                      </div>

                      <!-- date -->
                      <div class="center"><span class="date-pill"><?= htmlspecialchars($s['date']) ?></span></div>

                      <!-- terms -->
                      <div class="terms">
                        <div class="player"><?= htmlspecialchars($s['player']) ?></div>
                        <div class="meta">
                          <?= (int) $s['years'] ?> yrs •
                          <span class="tx-cap">AAV <?= htmlspecialchars($s['aav']) ?></span> •
                          <span class="tx-cap">Total <?= htmlspecialchars($s['total']) ?></span>
                          <?php if (!empty($s['type'])): ?> • <?= htmlspecialchars($s['type']) ?><?php endif; ?>
                        </div>
                      </div>
                    </div>

                    <!-- /.signing-grid -->

                    <?php if (!empty($s['clauses']) || !empty($s['notes'])): ?>
                      <?php if (!empty($s['notes'])): ?>
                        <div class="tx-note-row">
                          <span class="tx-note"><?= htmlspecialchars($s['notes']) ?></span>
                        </div>
                      <?php endif; ?>
                    <?php endif; ?>

                  </li>
                <?php endforeach; endif; ?>
            </ul>
          </section>

          <!-- ==================== ROSTER MOVES ==================== -->
          <section id="tab-moves" class="tab-panel">
            <div class="tx-toolbar">
              <div class="label">ROSTER MOVES</div>
              <div class="spacer"></div>
              <label>Team
                <select id="moves-team">
                  <option value="">All</option>
                  <?php
                  $opts = [];
                  foreach ($moves as $m) {
                    if (!empty($m['team']))
                      $opts[$m['team']] = true;
                  }
                  foreach (array_keys($opts) as $c)
                    echo '<option>' . htmlspecialchars($c) . '</option>';
                  ?>
                </select>
              </label>
              <label>Search <input id="moves-q" type="search" placeholder="player / move / notes"></label>
            </div>

            <ul class="tx-list" id="moves-list">
              <?php if (empty($moves)): ?>
                <li class="tx-card">
                  <div class="tx-mid">No roster moves yet.</div>
                </li>
              <?php else:
                foreach ($moves as $m): ?>
                  <li class="tx-card tx-move move-card" data-team="<?= htmlspecialchars($m['team']) ?>"
                    data-date="<?= htmlspecialchars($m['date']) ?>"
                    data-text="<?= htmlspecialchars($m['player'] . ' ' . $m['action'] . ' ' . $m['to'] . ' ' . $m['from'] . ' ' . $m['notes'] . ' ' . $m['tags']) ?>">

                    <div class="tx-card-main">
                      <!-- Left: team -->
                      <div class="team left">
                        <img class="logo" src="assets/img/logos/<?= htmlspecialchars($m['team']) ?>_dark.svg" alt="">
                        <div>
                          <div class="team-full"><?= htmlspecialchars($CODE2NAME[$m['team']] ?? $m['team']) ?></div>
                          <div class="label"><?= htmlspecialchars(tx_move_left_label($m), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                      </div>
                      <div class="tx-date-pill"><?= htmlspecialchars($m['date']) ?></div>

                      <!-- Right: details -->
                      <div class="action">
                        <div class="player"><?= htmlspecialchars($m['player']) ?></div>
                        <div class="meta">
                          <?= htmlspecialchars($m['action']) ?>
                          <?php if (!empty($m['to'])): ?> • to <?= htmlspecialchars($m['to']) ?><?php endif; ?>
                          <?php if (!empty($m['from'])): ?> • from <?= htmlspecialchars($m['from']) ?><?php endif; ?>
                        </div>
                      </div>
                    </div>

                    <?php $side = tx_move_side_label($m, $CODE2ABBR); ?>
                    <div class="tx-card-right">
                      <?php if ($side['name']): ?>
                        <div class="tx-right-team">
                          <?php if ($side['code']): ?>
                            <img class="logo" src="assets/img/logos/<?= htmlspecialchars($side['code']) ?>_dark.svg" alt="">
                          <?php else: ?>
                            <span class="tx-pseudo-logo"><?= htmlspecialchars($side['badge']) ?></span>
                          <?php endif; ?>
                          <div class="tx-right-text">
                            <div class="tx-right-name tx-team-name"><?= strtoupper(htmlspecialchars($side['name'])) ?></div>
                            <div class="tx-right-sub"><?= $side['kind'] === 'from' ? 'From' : 'To' ?></div>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                    <?php
                    // Bottom row: show buyout terms as plain text + notes (dedup + strip word 'Buyout')
                    $buyoutTerms = [];
                    $rawTags = array_filter(array_map('trim', explode(',', (string) ($m['tags'] ?? ''))));
                    foreach ($rawTags as $tag) {
                      if (stripos($tag, 'buyout') !== false) {
                        $t = str_ireplace('buyout', '', $tag);
                        $t = trim($t);
                        $t = ltrim($t, ':-•|/');
                        $t = trim($t);
                        if ($t !== '') {
                          $buyoutTerms[$t] = true;
                        }
                      }
                    }
                    $buyoutTerms = array_keys($buyoutTerms);
                    ?>
                    <?php if ($buyoutTerms || !empty($m['notes'])): ?>
                      <div class="tx-note-row">
                        <?php foreach ($buyoutTerms as $term): ?>
                          <span class="note-text"><?= htmlspecialchars($term) ?></span>
                        <?php endforeach; ?>
                        <?php if (!empty($m['notes'])): ?>
                          <span class="note-text"><?= htmlspecialchars($m['notes']) ?></span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>


                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </section>


        </div>
      </div>
    </div>
  </div>
</body>

</html>