<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
/** @var mysqli $db */
$db = get_db();


function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';

if ($id > 0) {
  $sql = "SELECT * FROM stories WHERE status='published' AND id=? LIMIT 1";
  $stmt = $db->prepare($sql);
  $stmt->bind_param('i', $id);
} else {
  $sql = "SELECT * FROM stories WHERE status='published' AND slug=? LIMIT 1";
  $stmt = $db->prepare($sql);
  $stmt->bind_param('s', $slug);
}
$stmt->execute();
$res = $stmt->get_result();
$story = $res->fetch_assoc();
$stmt->close();

if (!$story) {
  http_response_code(404);
  echo "Story not found.";
  exit;
}

$title   = (string)($story['title'] ?? '');
$summary = (string)($story['summary'] ?? '');
$body    = (string)($story['body'] ?? '');
$hero    = trim((string)($story['hero_image_url'] ?? ''));
$team    = (string)($story['team_code'] ?? '');
$pub     = (string)($story['published_at'] ?? '');

function dateLong(?string $ts): string {
  if (!$ts) return '';
  $t = strtotime($ts);
  return $t > 0 ? date('F j, Y g:ia', $t) : '';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= h($title) ?> — News — UHA Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preload" href="<?= h(asset('assets/css/global.css')) ?>" as="style">
  <link rel="stylesheet" href="<?= h(asset('assets/css/global.css')) ?>">
  <link rel="stylesheet" href="<?= h(asset('assets/css/nav.css')) ?>">
  <script defer src="<?= h(asset('assets/js/auto-logos.js')) ?>"></script>
  <style>
    .news-article-wrap{ max-width:960px; margin:24px auto; padding:0 16px; }
    .article-head{ margin:0 0 12px; }
    .article-title{ font-weight:900; font-size:32px; line-height:1.1; margin:0 0 8px; color:var(--color-ink); }
    .article-meta{ display:flex; align-items:center; gap:12px; font-size:13px; color:var(--color-ink-3); }
    .article-hero{ margin:18px 0; width:100%; height:380px; background-size:cover; background-position:50% 50%; border-radius:16px; background-color:var(--color-elev-1); display:block; }
    .article-hero.logo{ display:flex; align-items:center; justify-content:center; }
    .article-hero.logo img{ width:120px; height:120px; object-fit:contain; }
    .article-body{ color:var(--color-ink-1); font-size:16px; line-height:1.6; }
    .article-body p{ margin:0 0 1em; }
    @media (max-width:700px){ .article-title{ font-size:26px; } .article-hero{ height:240px; } }
  </style>
</head>
<body class="news-article-canvas">
<?php
require_once __DIR__ . '/includes/leaguebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>
<main class="news-article-wrap">
  <header class="article-head">
    <h1 class="article-title"><?= h($title) ?></h1>
    <div class="article-meta">
      <?php if ($team): ?><span class="tag"><?= h($team) ?></span><?php endif; ?>
      <?php $dl = dateLong($pub); if ($dl): ?><time datetime="<?= h($pub) ?>"><?= h($dl) ?></time><?php endif; ?>
    </div>
  </header>

  <?php if ($hero !== ''): ?>
    <div class="article-hero" style="background-image:url('<?= h($hero) ?>')"></div>
  <?php else: ?>
    <div class="article-hero logo">
      <img class="logo" data-abbr="<?= h($team) ?>" alt="<?= h($team) ?>">
    </div>
  <?php endif; ?>

  <?php if ($summary): ?>
    <p class="article-dek" style="font-size:18px; color:var(--color-ink-2); margin:0 0 12px;"><?= h($summary) ?></p>
  <?php endif; ?>

  <article class="article-body">
    <?= $body /* assume sanitized on save */ ?>
  </article>
</main>
</body>
</html>
