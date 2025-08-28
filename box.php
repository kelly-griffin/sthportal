<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$gid = isset($_GET['gid']) ? preg_replace('~[^A-Za-z0-9\-]~','',(string)$_GET['gid']) : '';
$rel = $gid ? ("data/uploads/{$gid}.html") : '';
$abs = $rel ? (__DIR__ . '/' . $rel) : '';
$payload = '';
if ($abs && is_file($abs)) {
  $raw = (string)file_get_contents($abs);
  if (preg_match('~<body[^>]*>(.*)</body>~si', $raw, $m)) $payload = $m[1]; else $payload = $raw;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Boxscore <?= h($gid ?: '') ?></title>
<link rel="stylesheet" href="assets/css/nav.css">
<link rel="stylesheet" href="assets/css/schedule.css">
<link rel="stylesheet" href="assets/css/schedule-polish.css">
<link rel="stylesheet" href="assets/css/game.css">
<style>
  .box-wrap{max-width:1200px;margin:20px auto}
  .box-embed .card{max-width:unset}
  .box-embed table{width:100%}
</style>
<script src="assets/js/dark-swap.js" defer></script>
</head>
<body>
  <div class="site">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>
    <div class="canvas">
      <div class="box-wrap">
        <?php if (!$payload): ?>
          <p>Boxscore not found for <?= h($gid) ?>.</p>
        <?php else: ?>

<?php
// ---------- Tiny header with logos + Final line ----------
$teamsDb = __DIR__ . '/data/uploads/teams.json';
$schedDb = __DIR__ . '/data/uploads/schedule-current.json';
$meta = null; $teamsMeta = [];
if (is_file($teamsDb)) {
  $td = json_decode((string)file_get_contents($teamsDb), true);
  foreach (($td['teams'] ?? []) as $t) $teamsMeta[(string)$t['id']] = $t;
}
if (is_file($schedDb) && $gid !== '') {
  $jd = json_decode((string)file_get_contents($schedDb), true);
  foreach (($jd['games'] ?? []) as $g) {
    $link = (string)($g['link'] ?? '');
    if (trim($link) === ($gid . '.html')) { $meta = $g; break; }
  }
}
if ($meta) {
  $vid = (string)($meta['visitorTeamId'] ?? $meta['visitorTeam'] ?? '');
  $hid = (string)($meta['homeTeamId'] ?? $meta['homeTeam'] ?? '');
  $vScore = (int)($meta['visitorScore'] ?? 0);
  $hScore = (int)($meta['homeScore'] ?? 0);
  $vAbbr = (string)($teamsMeta[$vid]['abbr'] ?? '');
  $hAbbr = (string)($teamsMeta[$hid]['abbr'] ?? '');
  $vName = (string)($teamsMeta[$vid]['shortName'] ?? $teamsMeta[$vid]['name'] ?? 'Visitor');
  $hName = (string)($teamsMeta[$hid]['shortName'] ?? $teamsMeta[$hid]['name'] ?? 'Home');
  $winnerFirst = ($vScore > $hScore)
    ? [$vAbbr,$vName,$vScore,$hAbbr,$hName,$hScore]
    : [$hAbbr,$hName,$hScore,$vAbbr,$vName,$vScore];
  $logoBase = 'assets/img/logos/';
  $vLogo = $logoBase . ($vAbbr ?: 'unknown') . '_light.svg';
  $hLogo = $logoBase . ($hAbbr ?: 'unknown') . '_light.svg';
  echo '<div class="card game-header dark-row" role="region" aria-label="Game Summary Header">';
  echo '<div class="side"><img class="team-logo" data-swap-dark src="'.h($vLogo).'" alt="'.h($vAbbr).' logo"><span class="name">'.h($vName).'</span></div>';
  echo '<div class="scoreline"><div class="status">Final</div><div class="score">'.max($vScore,$hScore).'–'.min($vScore,$hScore).'</div><div class="date">';
  echo 'Final: ' . h($winnerFirst[0]) . ' ' . (int)$winnerFirst[2] . ', ' . h($winnerFirst[3]) . ' ' . (int)$winnerFirst[5];
  echo '</div></div>';
  echo '<div class="side" style="justify-content:flex-end"><span class="name">'.h($hName).'</span><img class="team-logo" data-swap-dark src="'.h($hLogo).'" alt="'.h($hAbbr).' logo"></div>';
  echo '</div>';
}
?>

          <div class="box-embed">
            <?= $payload ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
