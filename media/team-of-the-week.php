<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Team of the Week';

$firstTeam  = $firstTeam  ?? null;   // expect keys: LW, C, RW, D1, D2, G
$secondTeam = $secondTeam ?? null;

function renderTowCard(?array $team, string $label){?>
  <section class="tow-section">
    <h2><?= htmlspecialchars($label) ?></h2>
    <div class="tow-grid">
      <div class="tow-line forwards">
        <?= renderTowPlayer($team['LW'] ?? null, 'LW') ?>
        <?= renderTowPlayer($team['C']  ?? null, 'C')  ?>
        <?= renderTowPlayer($team['RW'] ?? null, 'RW') ?>
      </div>
      <div class="tow-line defense">
        <?= renderTowPlayer($team['D1'] ?? null, 'D') ?>
        <?= renderTowPlayer($team['D2'] ?? null, 'D') ?>
      </div>
      <div class="tow-line goalie">
        <?= renderTowPlayer($team['G'] ?? null, 'G') ?>
      </div>
    </div>
  </section>
  <?php
}

function renderTowPlayer(?array $p, string $role){
  // graceful stub if missing
  $team = htmlspecialchars($p['team'] ?? 'WSH');
  $name = htmlspecialchars($p['name'] ?? 'Sample Player');
  $num  = htmlspecialchars($p['num']  ?? '00');
  $pos  = htmlspecialchars($p['pos']  ?? $role);
  $photo= $p['photo'] ?? null;
  ?>
  <article class="tow-player" data-team="<?= $team ?>">
    <span class="role"><?= htmlspecialchars($role) ?></span>
    <div class="avatar"><?php if ($photo): ?><img src="<?= htmlspecialchars($photo) ?>" alt=""><?php endif; ?></div>
    <div>
      <div class="name"><?= $name ?></div>
      <div class="sub">
        <img src="<?= '../assets/img/logos/'.$team.'_light.svg' ?>" alt="">
        <span>#<?= $num ?></span>
        <span><?= $pos ?></span>
      </div>
      <?php if (!empty($p['note'])): ?>
        <span class="mini-pill"><?= htmlspecialchars($p['note']) ?></span>
      <?php endif; ?>
    </div>
  </article>
  <?php
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title) ?> — UHA Portal</title>
</head>
<body>
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

  <div class="canvas">
    <div class="tow-container">
      <div class="tow-card">
        <h1><?= htmlspecialchars($title) ?></h1>
        <p class="tow-lead">First and Second units by position.</p>

        <div class="tow-wrap">
          <?php renderTowCard(is_array($firstTeam)  ? $firstTeam  : null, 'First Team'); ?>
          <?php renderTowCard(is_array($secondTeam) ? $secondTeam : null, 'Second Team'); ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>