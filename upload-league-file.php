<?php
// /sthportal/upload-league-file.php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

$isAdmin = !empty($_SESSION['is_admin']);
if (!$isAdmin) { http_response_code(403); exit('Forbidden'); }

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  http_response_code(400); exit('Bad CSRF token');
}

if (empty($_FILES['league_file']['tmp_name']) || $_FILES['league_file']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400); exit('No file uploaded or upload error.');
}

$allowedExt = ['stc','std','zip'];
$orig = $_FILES['league_file']['name'];
$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
  http_response_code(400); exit('Unsupported file type.');
}
$size = (int)$_FILES['league_file']['size'];
if ($size <= 0 || $size > 200*1024*1024) {
  http_response_code(400); exit('File too large.');
}

$destDir = __DIR__ . '/data/league_files';
if (!is_dir($destDir)) @mkdir($destDir, 0775, true);

// versioned name
$ts = (new DateTimeImmutable('now'))->format('Ymd-His');
$base = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
$finalName = "{$ts}_{$base}.{$ext}";
$destPath = $destDir . '/' . $finalName;

if (!move_uploaded_file($_FILES['league_file']['tmp_name'], $destPath)) {
  http_response_code(500); exit('Failed to move uploaded file.');
}
@chmod($destPath, 0644);

// compute checksum
$sha256 = hash_file('sha256', $destPath);

// notes
$notes = trim((string)($_POST['notes'] ?? ''));
$notes = mb_substr($notes, 0, 500);

// DB record (creates table if missing)
if ($db) {
  $db->query("CREATE TABLE IF NOT EXISTS league_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    rel_path VARCHAR(255) NOT NULL,
    filesize BIGINT NOT NULL,
    sha256 CHAR(64) NOT NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $stmt = $db->prepare("INSERT INTO league_files (filename,rel_path,filesize,sha256,notes)
                        VALUES (?,?,?,?,?)");
  $rel = 'data/league_files/' . $finalName;
  $stmt->bind_param('ssiss', $finalName, $rel, $size, $sha256, $notes);
  $stmt->execute();
}

// also update a “current” symlink/copy so download is stable
$current = $destDir . '/current.' . $ext;
@unlink($current);
@symlink($destPath, $current); // on Windows this may fail; fallback to copy
if (!file_exists($current)) {
  @copy($destPath, $current);
}

header('Location: options.php');
exit;
