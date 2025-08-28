<?php
// admin/devlog-index-create.php — create FULLTEXT index ft_devlog(title,body,tags,author)
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-helpers.php';

if (function_exists('require_admin')) require_admin();
if (function_exists('require_perm')) require_perm('manage_devlog');

$dbc  = admin_db();
$csrf = admin_csrf();

// --- decode + sanitize the back URL ---
$rawBack = (string)($_GET['back'] ?? '');
$backDec = rawurldecode($rawBack);
$back    = function_exists('sanitize_admin_path') ? sanitize_admin_path($backDec) : $backDec;
if ($back === '' || $back === 'index.php') $back = 'devlog.php';

// CSRF
$tok = (string)($_GET['csrf'] ?? '');
if (!hash_equals($csrf, $tok)) {
  header('Location: ' . $back);
  exit;
}

$PRIMARY = 'devlog_entries';

// already have it?
$has = false;
if ($stmt = $dbc->prepare("SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = 'ft_devlog' AND INDEX_TYPE = 'FULLTEXT' LIMIT 1")) {
  $stmt->bind_param('s', $PRIMARY);
  $stmt->execute();
  $r = $stmt->get_result();
  $has = ($r && $r->num_rows > 0);
  $stmt->close();
}

$sep = (strpos($back, '?') !== false) ? '&' : '?';

if ($has) {
  header('Location: ' . $back . $sep . 'msg=' . rawurlencode('Full-text already enabled.'));
  exit;
}

if ($dbc->query("ALTER TABLE `{$PRIMARY}` ADD FULLTEXT `ft_devlog` (`title`,`body`,`tags`,`author`)")) {
  header('Location: ' . $back . $sep . 'msg=' . rawurlencode('Full-text search enabled.'));
} else {
  header('Location: ' . $back . $sep . 'err=' . rawurlencode('Could not create full-text index.'));
}
exit;
