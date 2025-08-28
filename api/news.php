<?php
// api/news.php — unified headlines feed for league + team stories
// Returns JSON: { ok: true, items: [{id,title,team,image,published_at,link}, ...] }

header('Content-Type: application/json; charset=utf-8');

// ---- Get a PDO connection (minimal, non-fatal if missing)
$pdo = null;

// If you already have a PDO in config/db.php as $pdo, we'll use it:
$cfg1 = __DIR__ . '/../config/db.php';
if (file_exists($cfg1)) {
  require_once $cfg1;
  if (isset($pdo) && $pdo instanceof PDO) {
    // good
  } elseif (defined('DB_DSN')) {
    try { $pdo = new PDO(DB_DSN, DB_USER ?? null, DB_PASS ?? null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch(Throwable $e) {}
  }
}

// If still no PDO, try ENV variables (optional)
if (!$pdo && getenv('DB_DSN')) {
  try {
    $pdo = new PDO(getenv('DB_DSN'), getenv('DB_USER') ?: null, getenv('DB_PASS') ?: null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  } catch (Throwable $e) {}
}

// If we still don't have DB, return a soft error so JS can fall back to file feeds or UHA.headlines
if (!$pdo) {
  http_response_code(200);
  echo json_encode(['ok'=>false, 'reason'=>'no_database']);
  exit;
}

try {
  $limit = isset($_GET['limit']) ? max(1, min(12, (int)$_GET['limit'])) : 6;

  // Minimal schema assumption:
  // stories(id, team_id NULL=league, title, hero_image_url, published_at (UTC), status)
  // teams(id, abbr, name)
  $sql = "
    SELECT s.id,
           s.title,
           s.hero_image_url AS image,
           s.published_at,
           s.team_id,
           COALESCE(t.abbr, '') AS team
    FROM stories s
    LEFT JOIN teams t ON t.id = s.team_id
    WHERE s.status = 'published'
    ORDER BY s.published_at DESC
    LIMIT :lim
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $items = array_map(function($r){
    return [
      'id'           => $r['id'],
      'title'        => $r['title'],
      'team'         => $r['team'] ?: null,  // null = league story
      'image'        => $r['image'] ?: null,
      'published_at' => $r['published_at'],
      'link'         => 'news-article.php?id=' . rawurlencode($r['id']),
    ];
  }, $rows);

  echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'server_error']);
}
