<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Social Hub';
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
      <div class="wrap social-container">
        <div class="page-surface social-card">
          <h1><?= h($title) ?></h1>
          <p class="muted">Central place for social posts and media embeds. (Placeholders for now.)</p>

          <div class="grid2">
            <section class="card">
              <h2>X / Twitter</h2>
              <div class="embed">Embed placeholder</div>
            </section>
            <section class="card">
              <h2>Instagram</h2>
              <div class="embed">Embed placeholder</div>
            </section>
            <section class="card">
              <h2>Direct Messages</h2>
              <iframe class="embed-frame" src="<?= u('media/messages.php?embed=1') ?>" title="Messages"
                loading="lazy"></iframe>
              <p style="margin-top:10px">
                <a class="btn" href="<?= u('media/messages.php') ?>">Open full messages</a>
              </p>
            </section>
            <section class="card">
              <h2>League Chat</h2>
              <iframe class="embed-frame" src="<?= u('media/chat.php?embed=1') ?>" title="League Chat"
                loading="lazy"></iframe>

              <p style="margin-top:10px">
                <a class="btn" href="<?= u('media/chat.php') ?>">Open full chat</a>
              </p>
            </section>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>