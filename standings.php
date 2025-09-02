<?php
require_once __DIR__ . '/includes/bootstrap.php';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>UHA: NHL Standings</title>
  <style>.table-title, .conf-h { font-weight: 800; }</style>
  <script src="assets/js/standings.js" defer></script>
</head>
<body>
  <div class="site">

    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="standings-container">
        <div class="standings-card">
  <h1>Standings</h1>

  <?php
    // --- Input & config ---
    $view = isset($_GET['view']) ? strtolower($_GET['view']) : 'wildcard'; // wildcard, division, conference, league
    if (!in_array($view, ['wildcard','division','conference','league'], true)) { $view = 'wildcard'; }

    // Try multiple possible CSV locations
    $csvCandidates = [
      __DIR__ . '/data/uploads/UHA-V3ProTeam.csv',
      __DIR__ . '/data/UHA-V3ProTeam.csv',
      __DIR__ . '/UHA-V3ProTeam.csv'
    ];
    $csvPath = null;
    foreach ($csvCandidates as $p) { if (file_exists($p)) { $csvPath = $p; break; } }
    if ($csvPath === null) { $csvPath = __DIR__ . '/UHA-V3ProTeam.csv'; } // dev fallback

    // --- Load CSV ---
    $teams = [];
    if (($h = fopen($csvPath, 'r')) !== false) {
      $headers = fgetcsv($h);
      while (($row = fgetcsv($h)) !== false) {
        $t = array_combine($headers, $row);
        // Build derived stats
        $gp  = (int)$t['GP'];
        $w   = (int)$t['W'];
        $l   = (int)$t['L'];
        $otw = (int)$t['OTW'];
        $otl = (int)$t['OTL'];
        $sow = (int)$t['SOW'];
        $sol = (int)$t['SOL'];
        $pts = (int)$t['Points'];
        $gf  = (int)$t['GF'];
        $ga  = (int)$t['GA'];

        $rw  = $w - $otw - $sow;     // Regulation wins
        $row = $w - $sow;            // Regulation + OT wins (exclude SO)
        $otl_total = $otl + $sol;    // NHL shows OTL incl SO Losses
        $pct = $gp > 0 ? $pts / (2 * $gp) : 0.0;
        $diff = $gf - $ga;

        $teams[] = [
          'abbr' => $t['Abbre'],
          'city' => $t['City'],
          'name' => $t['Name'],
          'full' => trim((string)$t['Name']),
          'conf' => $t['Conference'],
          'div'  => $t['Division'],
          'gp' => $gp, 'w' => $w, 'l' => $l,
          'ot' => $otl_total,
          'pts' => $pts, 'pct' => $pct,
          'rw' => $rw, 'row' => $row, 'gf' => $gf, 'ga' => $ga, 'diff' => $diff,
          'streak' => $t['Streak'],
        ];
      }
      fclose($h);
    }

    // Normalise conference/division names (remove " Conference"/" Division")
    foreach ($teams as &$tm) {
      $tm['confShort'] = preg_replace('/\\s*Conference$/','',$tm['conf']);
      $tm['divShort']  = preg_replace('/\\s*Division$/','',$tm['div']);
    }
    unset($tm);

    // --- Tiebreaker chain (easily tweaked later) ---
    function tiebreakCmp($a, $b) {
      $keys = [
        ['pts','desc'],
        ['rw','desc'],    // NHL: regulation wins
        ['row','desc'],   // then ROW
        ['w','desc'],     // then total wins
        ['diff','desc'],  // then goal differential
        ['gf','desc'],    // then goals for
        ['abbr','asc'],   // stable final
      ];
      foreach ($keys as $k) {
        $key = $k[0]; $dir = $k[1];
        if ($a[$key] == $b[$key]) continue;
        if ($dir === 'desc') return ($a[$key] > $b[$key]) ? -1 : 1;
        else                 return ($a[$key] < $b[$key]) ? -1 : 1;
      }
      return 0;
    }

    // Group helpers
    function groupBy($arr, $key) {
      $out = [];
      foreach ($arr as $it) { $out[$it[$key]][] = $it; }
      return $out;
    }

    // Build per-division sorted lists
    $byConf = groupBy($teams, 'confShort');
    foreach ($byConf as $c => &$lst) { usort($lst, 'tiebreakCmp'); } unset($lst);
    $byDiv = groupBy($teams, 'divShort');
    foreach ($byDiv as $d => &$lst) { usort($lst, 'tiebreakCmp'); } unset($lst);

    // Build views
    function renderTable($rows, $title=null, $cutIndex=null) { ?>
      <div class="stand-table">
        <?php if ($title): ?><div class="table-title"><?= htmlspecialchars($title) ?></div><?php endif; ?>
        <table class="uha-table">
          <thead>
            <tr>
              <th class="rank">Rank</th>
              <th class="team">Team</th>
              <th>GP</th><th>W</th><th>L</th><th>OT</th>
              <th class="pts">PTS</th>
              <th class="pct">P%</th>
              <th class="rw">RW</th><th class="row">ROW</th>
              <th>GF</th><th>GA</th><th class="diff">DIFF</th>
              <th>STRK</th>
            </tr>
          </thead>
          <tbody>
            <?php $r=1; foreach ($rows as $tm): ?>
              <?php if ($cutIndex !== null && $r === $cutIndex+1): ?>
                <tr class="cut-line"><td colspan="14"></td></tr>
              <?php endif; ?>
              <tr>
                <td class="rank"><?= $r ?></td>
                <td class="team">
                  <img class="crest" src="/sthportal/assets/img/logos/<?= htmlspecialchars($tm['abbr']) ?>_dark.svg" alt="" loading="lazy">
                  <span class="t"><?= htmlspecialchars($tm['full'] ?? $tm['name']) ?></span>
                </td>
                <td><?= $tm['gp'] ?></td>
                <td><?= $tm['w'] ?></td>
                <td><?= $tm['l'] ?></td>
                <td><?= $tm['ot'] ?></td>
                <td class="pts"><strong><?= $tm['pts'] ?></strong></td>
                <td class="pct"><?= number_format($tm['pct'], 3) ?></td>
                <td class="rw"><?= $tm['rw'] ?></td>
                <td class="row"><?= $tm['row'] ?></td>
                <td><?= $tm['gf'] ?></td>
                <td><?= $tm['ga'] ?></td>
                <td class="diff"><?= ($tm['diff']>0?'+':'') . $tm['diff'] ?></td>
                <td><?= htmlspecialchars($tm['streak']) ?></td>
              </tr>
            <?php $r++; endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php }

    // Tabs
    $tabs = [
      'wildcard'  => 'Wild Card',
      'division'  => 'Division',
      'conference'=> 'Conference',
      'league'    => 'League'
    ];
  ?>

  <div class="standings-tabs">
    <?php foreach ($tabs as $key=>$label): $active = ($key===$view) ? 'active' : ''; ?>
      <a class="pill <?= $active ?>" href="?view=<?= $key ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
  </div>

  
  <?php if ($view === 'wildcard'): ?>
    <?php foreach (['Eastern'=>['Atlantic','Metropolitan'], 'Western'=>['Central','Pacific']] as $conf=>$divs): ?>
      <h2 class="conf-h"><?= $conf ?> Conference</h2>
      <?php
        $pool = [];
        foreach ($divs as $dv) {
          $lst = array_values(array_filter($teams, fn($t)=>$t['divShort']===$dv));
          usort($lst, 'tiebreakCmp');
          $pool = array_merge($pool, array_slice($lst, 3));
          renderTable(array_slice($lst, 0, 3), $dv . ' Division');
        }
        usort($pool, 'tiebreakCmp');
      ?>
      <?php renderTable($pool, 'Wild Card', 2); ?>
    <?php endforeach; ?>

  <?php elseif ($view === 'division'): ?>

    <?php foreach (['Eastern'=>['Atlantic','Metropolitan'], 'Western'=>['Central','Pacific']] as $conf=>$divs): ?>
      <h2 class="conf-h"><?= $conf ?> Conference</h2>
      <div class="stack-cols">
        <?php foreach ($divs as $dv): 
          $lst = array_values(array_filter($teams, fn($t)=>$t['divShort']===$dv));
          usort($lst, 'tiebreakCmp');
          renderTable($lst, $dv);
        endforeach; ?>
      </div>
    <?php endforeach; ?>

  <?php elseif ($view === 'conference'): ?>
    <?php foreach (['Eastern','Western'] as $conf): 
      $lst = array_values(array_filter($teams, fn($t)=>$t['confShort']===$conf));
      usort($lst, 'tiebreakCmp');
      renderTable($lst, $conf . ' Conference');
    endforeach; ?>

  <?php else: /* league */ ?>
    <?php $all = $teams; usort($all, 'tiebreakCmp'); renderTable($all, 'League'); ?>
  <?php endif; ?>

</div>

       </div>
    </div>    