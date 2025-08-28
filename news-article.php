<?php
// news-article.php — single story view
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';

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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); echo 'Not found'; exit; }

$stmt = $dbc->prepare("SELECT id, title, summary, body, hero_image_url, team_code, published_at
                       FROM stories
                       WHERE id=? AND status IN ('published','draft')");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$story = $res ? $res->fetch_assoc() : null;
if (!$story) { http_response_code(404); echo 'Not found'; exit; }

$hero = !empty($story['hero_image_url']) ? $story['hero_image_url'] : placeholder_img($story['team_code'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=1280, initial-scale=1">
  <title><?= htmlspecialchars($story['title']) ?> — UHA</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <link rel="stylesheet" href="assets/css/home.css">
  <link rel="stylesheet" href="assets/css/hotfix-portal.css">
  <style>
    .story-wrap{max-width:960px;margin:0 auto}
    .story-hero{aspect-ratio:16/9;background:#eef4ff;border:1px solid #cfd6e4;border-radius:10px;overflow:hidden}
    .story-hero img{display:block;width:100%;object-fit:cover}
    .story-title{font-size:28px;font-weight:900;margin:10px 0 6px}
    .story-meta{color:#6c7a93;font-size:12px;margin-bottom:8px}
    .story-summary{font-weight:600;margin-bottom:10px}
    .story-body{line-height:1.5}
    .story-body img{max-width:100%}
  </style>
</head>
<body>
<div class="site">
  <?php require_once __DIR__ . '/includes/topbar.php'; ?>
  <main class="content">
    <section class="content-col">
      <div class="story-wrap">
        <div class="story-hero">
          <?php if (strpos($hero, 'data:image/') !== 0): ?>
            <img src="<?= htmlspecialchars($hero) ?>" alt="">
          <?php endif; ?>
        </div>
        <h1 class="story-title"><?= htmlspecialchars($story['title']) ?></h1>
        <div class="story-meta"><?= htmlspecialchars(date('M j, Y g:ia', strtotime($story['published_at']))) ?></div>
        <p class="story-summary"><?= htmlspecialchars($story['summary']) ?></p>
        <article class="story-body">
          <?= $story['body'] ? $story['body'] : '' ?>
        </article>
      </div>
    </section>
  </main>
</div>
<script src="assets/js/urls.js"></script>
</body>
</html>
