<?php
// admin/user-signout.php — invalidate all sessions for a user (admin only)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';

require_admin();
require_perm('manage_users');

$dbc = null;
if (isset($db) && $db instanceof mysqli) { $dbc = $db; }
elseif (function_exists('get_db'))       { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('DB not initialized'); }

// Ensure aux table
$dbc->query("CREATE TABLE IF NOT EXISTS user_session_revocations (
  user_id INT PRIMARY KEY,
  revoked_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Missing id'); }

$stmt = $dbc->prepare("INSERT INTO user_session_revocations (user_id, revoked_after)
                       VALUES (?, NOW())
                       ON DUPLICATE KEY UPDATE revoked_after=NOW()");
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) { log_audit('user_signout_everywhere', "id={$id}", 'admin'); }

header('Location: users.php');
exit;
