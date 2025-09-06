<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

/** @var mysqli $db */
$db = get_db();

// root-relative link builder via helpers
if (!function_exists('url_root')) {
  require_once __DIR__ . '/../includes/functions.php';
}

try {
  $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;
  if ($limit < 1)  $limit = 1;
  if ($limit > 50) $limit = 50;

  $sql = "
    SELECT
      s.id,
      s.title,
      s.team_code        AS team,
      s.hero_image_url   AS image,
      DATE_FORMAT(CONVERT_TZ(s.published_at, '+00:00', '+00:00'), '%Y-%m-%dT%H:%i:%sZ') AS published_at
    FROM stories s
    WHERE s.status = 'published'
    ORDER BY s.published_at DESC
    LIMIT ?
  ";
  $stmt = $db->prepare($sql);
  $stmt->bind_param('i', $limit);
  $stmt->execute();
  $res = $stmt->get_result();

  $items = [];
  while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    $items[] = [
      'id'           => $id,
      'title'        => (string)$row['title'],
      'team'         => ($row['team'] ?? '') ?: null,
      'image'        => ($row['image'] ?? '') ?: null,
      'published_at' => (string)$row['published_at'],
      'link'         => url_root('news-article.php?id=' . $id), // root-relative
    ];
  }
  $stmt->close();

  echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
