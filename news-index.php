<?php
// news-index.php — list of published stories
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';


$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 12;
$off  = ($page-1)*$per;

$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
if (!$dbc) { die('Database not available'); }

function placeholder_img(?string $teamCode): string {
  $teamCode = strtoupper(trim((string)$teamCode));
  $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
  if ($teamCode !== '') {
    $path = "/assets/logos/{{TEAM}}.png";
    $path = str_replace("{{TEAM}}", $teamCode, $path);
    if ($docRoot && file_exists($docRoot.$path)) return $path;
  }
  $fallback = "/assets/img/news-placeholder.png";
  return ($docRoot && file_exists($docRoot.$fallback)) ? $fallback : "data:image/gif;base64,R0lGODlhAQABAAAAACw=";
}

$total = 0;
$q1 = $dbc->query("SELECT COUNT(*) AS c FROM stories WHERE status='published'");
if ($q1) { $row = $q1->fetch_assoc(); $total = (int)$row['c']; }

$stmt = $dbc->prepare("SELECT id, title, summary, hero_image_url, team_code, published_at
                       FROM stories
                       WHERE status='published'
                       ORDER BY published_at DESC
                       LIMIT ? OFFSET ?");
$stmt->bind_param('ii', $per, $off);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=1280, initial-scale=1">
  <title>News — UHA</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <style>
    .news-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:12px}
    .news-card{background:#fff;border:1px solid #cfd6e4;border-radius:10px;overflow:hidden;display:flex;flex-direction:column}
    .news-thumb{aspect-ratio:16/9;background:#eef4ff;display:block;width:100%;object-fit:cover}
    .news-body{padding:10px}
    .news-title{font-weight:900;margin:0 0 6px}
    .news-dek{color:#445066;font-size:14px;margin:0 0 8px}
    .news-meta{font-size:12px;color:#6c7a93}
    .pager{margin:12px 0;display:flex;gap:8px;align-items:center}
    .pager a{padding:4px 8px;border:1px solid #cfd6e4;border-radius:6px;text-decoration:none;color:#0b1220}
  </style>
</head>
<body>
<div class="site">
  <?php require_once __DIR__ . '/includes/topbar.php'; ?>
  <main class="content">
    <section class="content-col">
      <div class="section-title"><span>League News</span></div>
      <div class="news-grid">
        <?php foreach ($rows as $r): ?>
          <article class="news-card">
            <a href="/news-article.php?id=<?= (int)$r['id'] ?>">
              <?php
                $src = !empty($r['hero_image_url'])
                  ? $r['hero_image_url']
                  : placeholder_img($r['team_code'] ?? '');
              ?>
              <?php if (strpos($src, 'data:image/') === 0): ?>
                <div class="news-thumb"></div>
              <?php else: ?>
                <img class="news-thumb" src="<?= htmlspecialchars($src) ?>" alt="">
              <?php endif; ?>
            </a>
            <div class="news-body">
              <h3 class="news-title">
                <a href="/news-article.php?id=<?= (int)$r['id'] ?>" style="text-decoration:none;">
                  <?= htmlspecialchars($r['title']) ?>
                </a>
              </h3>
              <p class="news-dek"><?= htmlspecialchars($r['summary']) ?></p>
              <div class="news-meta"><?= htmlspecialchars(date('M j, Y g:ia', strtotime($r['published_at']))) ?></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="pager">
        <?php $pages = max(1, (int)ceil($total/$per)); ?>
        <?php if ($page>1): ?><a href="?page=<?= $page-1 ?>">Prev</a><?php endif; ?>
        <span>Page <?= $page ?> / <?= $pages ?></span>
        <?php if ($page<$pages): ?><a href="?page=<?= $page+1 ?>">Next</a><?php endif; ?>
      </div>
    </section>
  </main>
</div>
<script src="assets/js/urls.js"></script>
</body>
</html>
