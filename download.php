<?php
// /sthportal/download.php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

$what = $_GET['what'] ?? '';
if ($what !== 'league') { http_response_code(400); exit('Unknown download.'); }

$dir = __DIR__ . '/data/league_files';
if (!is_dir($dir)) { http_response_code(404); exit('Nothing published.'); }

// Prefer DB if present, else fall back to newest file
$path = null; $name = null;
if ($db && $db->query("SHOW TABLES LIKE 'league_files'")->num_rows) {
  $res = $db->query("SELECT filename,rel_path FROM league_files ORDER BY id DESC LIMIT 1");
  if ($row = $res?->fetch_assoc()) {
    $path = __DIR__ . '/' . $row['rel_path'];
    $name = $row['filename'];
  }
}
if (!$path || !is_file($path)) {
  // find newest by mtime
  $files = glob($dir . '/*.{stc,std,zip}', GLOB_BRACE);
  usort($files, fn($a,$b)=>filemtime($b)<=>filemtime($a));
  $path = $files[0] ?? null;
  $name = basename((string)$path);
}
if (!$path || !is_file($path)) { http_response_code(404); exit('No league file found.'); }

header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $name . '"');
readfile($path);
