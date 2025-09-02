<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Power Rankings';
// Guard: if controller didn't pass $rankings, keep it a safe empty array
$rankings = (isset($rankings) && is_array($rankings)) ? $rankings : [];
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> — UHA Portal</title>
</head>

<body>
  <div class="site">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
    <div class="canvas">
      <div class="pr-container">
        <div class="pr-card">
          <h1><?= h($title) ?></h1>
          <p class="pr-lead">Manual/auto weekly rankings with notes.</p>

          <div class="pr-toolbar">
            <div class="spacer"></div>
            <label>Week
              <select id="pr-week">
                <option value="">This Week</option>
                <option value="next">Next Week</option>
              </select>
            </label>
            <label>Search
              <input id="pr-q" type="search" placeholder="team / note">
            </label>
          </div>
        
          <ul class="pr-board">
            <!-- Example row so layout is visible even with no data -->
            <li class="pr-row">
              <div class="pr-rank">1</div>
              <div class="pr-team">
                <img class="crest" src="<?= u('assets/img/logos/BOS_dark.svg') ?>" alt="Boston Bruins">
                <div class="name">Boston Bruins</div>
              </div>
              <div class="pr-record">12-2-1</div>
              <div class="pr-delta up">
                <span class="arrow">▲</span>
                <span class="chip">+3</span>
              </div>
              <div class="pr-notes">Placeholder note for team.</div>
            </li>

            <?php if (!empty($rankings)): ?>
              <?php foreach ($rankings as $i => $row):
                $trend = ($row['delta'] ?? 0) > 0 ? 'up' : (($row['delta'] ?? 0) < 0 ? 'down' : 'even');
              ?>
                <li class="pr-row">
                  <div class="pr-rank"><?= (int)($i + 1) ?></div>
                  <div class="pr-team">
                    <img class="crest" src="<?= u('assets/img/logos/' . h($row['team']) . '_dark.svg') ?>" alt="">
                    <div class="name"><?= h($row['team_name'] ?? $row['team'] ?? 'Team') ?></div>
                  </div>
                  <div class="pr-record"><?= h($row['record'] ?? '') ?></div>
                  <div class="pr-delta <?= $trend ?>">
                    <span class="arrow"><?= $trend === 'up' ? '▲' : ($trend === 'down' ? '▼' : '–') ?></span>
                    <span class="chip"><?= (($row['delta'] ?? 0) > 0 ? '+' : '') . (int)($row['delta'] ?? 0) ?></span>
                  </div>
                  <div class="pr-notes"><?= h($row['note'] ?? '') ?></div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
