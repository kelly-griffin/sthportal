<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
/** @var mysqli $db */
$db = get_db();


function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$perPage = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$total = 0;
if ($rc = $db->query("SELECT COUNT(*) AS c FROM stories WHERE status='published'")) {
  if ($r = $rc->fetch_assoc()) $total = (int)$r['c'];
  $rc->free();
}

$sql = "
  SELECT id, slug, title, summary, hero_image_url, team_code, published_at
  FROM stories
  WHERE status='published'
  ORDER BY published_at DESC
  LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$res = $stmt->get_result();
$items = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function dateTiny(?string $ts): string {
  if (!$ts) return '';
  $t = strtotime($ts);
  return $t > 0 ? date('M j, Y', $t) : '';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>News — UHA Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preload" href="<?= h(asset('assets/css/global.css')) ?>" as="style">
  <link rel="stylesheet" href="<?= h(asset('assets/css/global.css')) ?>">
  <link rel="stylesheet" href="<?= h(asset('assets/css/nav.css')) ?>">
  <script defer src="<?= h(asset('assets/js/auto-logos.js')) ?>"></script>
  <style>
    .news-index-wrap{ max-width:1100px; margin:24px auto; padding:0 16px; }
    .news-head{ display:flex; align-items:center; justify-content:space-between; margin:0 0 16px; }
    .news-grid{ display:grid; grid-template-columns:repeat(12,1fr); gap:16px; }
    .news-card{ grid-column:span 4; background:var(--color-card); border-radius:16px; overflow:hidden; box-shadow:var(--shadow-1); display:flex; flex-direction:column; min-height:260px; }
    .news-thumb{ width:100%; height:180px; background-size:cover; background-position:50% 50%; background-color:var(--color-elev-1); }
    .news-thumb.logo{ display:flex; align-items:center; justify-content:center; }
    .news-thumb.logo img{ width:88px; height:88px; object-fit:contain; }
    .news-body{ padding:12px 14px 16px; display:flex; flex-direction:column; gap:8px; }
    .news-title{ font-weight:800; line-height:1.2; font-size:16px; color:var(--color-ink); }
    .news-summary{ color:var(--color-ink-2); font-size:13px; line-height:1.35; min-height:2.6em; }
    .news-meta{ display:flex; gap:10px; align-items:center; font-size:12px; color:var(--color-ink-3); }
    .pager{ display:flex; justify-content:center; gap:8px; margin:22px 0 10px; }
    .pager a, .pager span{ padding:6px 10px; border-radius:10px; text-decoration:none; }
    .pager a{ background:var(--color-elev-1); color:var(--color-ink); }
    .pager .cur{ background:var(--color-accent); color:#fff; }
    @media (max-width:980px){ .news-card{ grid-column:span 6; } }
    @media (max-width:640px){ .news-card{ grid-column:span 12; } }
  </style>
</head>
<body class="news-canvas">
<?php
require_once __DIR__ . '/includes/leaguebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>
<main class="news-index-wrap">
  <div class="news-head">
    <h1 class="page-title">League News</h1>
    <div class="news-actions"></div>
  </div>

  <section class="news-grid">
    <?php foreach ($items as $it):
      $href   = url_root('news-article.php?id=' . (int)$it['id']);
      $title  = h($it['title'] ?? '');
      $summary= h($it['summary'] ?? '');
      $date   = dateTiny($it['published_at'] ?? null);
      $team   = $it['team_code'] ?? '';
      $hero   = trim((string)($it['hero_image_url'] ?? ''));
    ?>
      <a class="news-card" href="<?= h($href) ?>">
        <?php if ($hero !== ''): ?>
          <div class="news-thumb" style="background-image:url('<?= h($hero) ?>')"></div>
        <?php else: ?>
          <div class="news-thumb logo">
            <img class="logo" data-abbr="<?= h($team) ?>" alt="<?= h($team) ?>">
          </div>
        <?php endif; ?>
        <div class="news-body">
          <div class="news-title"><?= $title ?></div>
          <?php if ($summary): ?><div class="news-summary"><?= $summary ?></div><?php endif; ?>
          <div class="news-meta">
            <?php if ($team): ?><span class="tag"><?= h($team) ?></span><?php endif; ?>
            <?php if ($date): ?><span class="date"><?= h($date) ?></span><?php endif; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </section>

  <?php
  $pages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
  if ($pages > 1):
    echo '<nav class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
      $cls = $i === $page ? 'cur' : '';
      $url = url_root('news.php?page=' . $i);
      echo $i === $page ? '<span class="cur">'.$i.'</span>' : '<a href="'.h($url).'" class="'.$cls.'">'.$i.'</a>';
    }
    echo '</nav>';
  endif;
  ?>
</main>
</body>
</html>
