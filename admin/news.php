<?php
// admin/news.php — manage stories
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_admin();

$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
if (!$dbc) { die('Database not available'); }

$res = $dbc->query("SELECT id,title,status,is_auto,published_at FROM stories ORDER BY published_at DESC LIMIT 100");
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=1280, initial-scale=1"><title>Manage News — Admin</title>
  <link rel="stylesheet" href="../../assets/css/nav.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/hotfix-portal.css">
  <style>
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid #cfd6e4;padding:6px 8px;text-align:left}
    th{background:#eef4ff}
    .toolbar{display:flex;justify-content:flex-end;margin:8px 0;gap:8px}
    .btn{display:inline-flex;padding:6px 10px;border:1px solid #cfd6e4;border-radius:8px;text-decoration:none;color:#0b1220;background:#fff}
  </style>
</head>
<body>
<div class="site">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
  <main class="content">
    <section class="content-col">
      <div class="section-title"><span>Manage News</span></div>
      <div class="toolbar">
        <a class="btn" href="/admin/news-new.php">+ New Article</a>
        <a class="btn" href="/admin/news-auto-recap.php">⚡ Auto Recap</a>
      </div>
      <table>
        <thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Auto</th><th>Published</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
            <td><?= ((int)$r['is_auto']===1?'Yes':'No') ?></td>
            <td><?= htmlspecialchars($r['published_at']) ?></td>
            <td>
              <a href="/admin/news-new.php?id=<?= (int)$r['id'] ?>">Edit</a> ·
              <a href="/news-article.php?id=<?= (int)$r['id'] ?>">View</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>
</div>
</body>
</html>
