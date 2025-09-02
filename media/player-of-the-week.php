<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Player of the Week';

/* guards so the page doesn’t explode if data isn’t wired yet */
$winner   = $winner   ?? null;
$nominees = (isset($nominees) && is_array($nominees)) ? $nominees : [];
?>
<!doctype html><html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> — UHA Portal</title>
</head>
<body>
<div class="site">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
  
<div class="canvas">
  <div class="potw-container">
    <div class="potw-card">
    <h1><?= h($title) ?></h1>
    <p class="potw-lead">Nominees, winner, and honourable mentions.</p>

        <!-- Winner / Hero -->
        <?php if ($winner): ?>
          <?php
            $team   = h($winner['team'] ?? '');
            $name   = h($winner['name'] ?? 'Player');
            $pos    = h($winner['pos']  ?? '');
            $num    = h($winner['num']  ?? '');
            $stat   = h($winner['headline_stat'] ?? 'PTS');
            $value  = h($winner['headline_val']  ?? '0');
            $note   = h($winner['note'] ?? '');
            $photo  = $winner['photo'] ?? null;
          ?>    
<section class="potw-hero" data-team="<?= $team ?>">
            <div class="potw-avatar">
              <?php if ($photo): ?>
                <img src="<?= u($photo) ?>" alt="">
              <?php endif; ?>
            </div>
            <div>
              <div class="potw-title"><?= $name ?></div>
              <div class="potw-sub">
                <img class="team-logo" src="<?= u('assets/img/logos/'.$team.'_light.svg') ?>" alt="">
                <span>#<?= $num ?></span>
                <span><?= $pos ?></span>
              </div>
              <?php if ($note): ?><p class="potw-lead" style="margin:8px 0 0;"><?= $note ?></p><?php endif; ?>
            </div>
            <div class="potw-statbox">
              <div class="label"><?= $stat ?></div>
              <div class="value"><?= $value ?></div>
              <div class="meta">Week summary</div>
            </div>
          </section>
        <?php else: ?>
          <!-- stub so layout shows before wiring -->
          <section class="potw-hero" data-team="WSH">
            <div class="potw-avatar"></div>
            <div>
              <div class="potw-title">Sample Winner</div>
              <div class="potw-sub">
                <img class="team-logo" src="<?= u('assets/img/logos/WSH_light.svg') ?>" alt="">
                <span>#8</span><span>LW</span>
              </div>
            </div>
            <div class="potw-statbox">
              <div class="label">PTS</div>
              <div class="value">9</div>
              <div class="meta">4 GP · 6G 3A</div>
            </div>
          </section>
        <?php endif; ?>

        <!-- Nominees -->
        <div class="potw-grid">
          <?php if (!empty($nominees)): foreach ($nominees as $p): ?>
            <?php
              $t = h($p['team'] ?? '');
              $nm = h($p['name'] ?? 'Player');
              $ps = h($p['pos'] ?? '');
              $n  = h($p['num'] ?? '');
              $st = h($p['stat'] ?? 'PTS');
              $vv = h($p['val']  ?? '0');
              $ph = $p['photo'] ?? null;
            ?>
            <article class="potw-item" data-team="<?= $t ?>">
              <div class="avatar"><?php if ($ph): ?><img src="<?= u($ph) ?>" alt=""><?php endif; ?></div>
              <div>
                <div class="name"><?= $nm ?></div>
                <div class="sub">
                  <img class="team-logo" src="<?= u('assets/img/logos/'.$t.'_light.svg') ?>" alt="">
                  <span>#<?= $n ?></span><span><?= $ps ?></span>
                </div>
                <span class="stat-pill"><?= $st ?>: <?= $vv ?></span>
              </div>
            </article>
          <?php endforeach; else: ?>
            <!-- a couple of stubs so the grid shape is obvious -->
            <article class="potw-item" data-team="EDM"><div class="avatar"></div>
              <div><div class="name">Nominee A</div><div class="sub"><img class="team-logo" src="<?= u('assets/img/logos/EDM_light.svg') ?>" alt=""><span>#97</span><span>C</span></div><span class="stat-pill">PTS: 7</span></div>
            </article>
            <article class="potw-item" data-team="TOR"><div class="avatar"></div>
              <div><div class="name">Nominee B</div><div class="sub"><img class="team-logo" src="<?= u('assets/img/logos/TOR_light.svg') ?>" alt=""><span>#34</span><span>C</span></div><span class="stat-pill">G: 5</span></div>
            </article>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</body>
</html>