<?php
// admin/devlog-undelete.php — restores a devlog entry from audit_log snapshot

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';

require_admin();
require_perm('manage_devlog');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }
$dbc->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST only'); }
if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }

$sid = (int)($_POST['sid'] ?? 0);
if ($sid <= 0) { header('Location: devlog.php'); exit; }

// Load snapshot
$stmt = $dbc->prepare("SELECT details FROM audit_log WHERE id=? AND event='devlog_soft_delete'");
$stmt->bind_param('i', $sid);
$stmt->execute();
$stmt->bind_result($details);
if (!$stmt->fetch()) { $stmt->close(); header('Location: devlog.php?msg=restore_missing'); exit; }
$stmt->close();

$payload = json_decode((string)$details, true);
$row = is_array($payload) && isset($payload['row']) && is_array($payload['row']) ? $payload['row'] : null;
if (!$row) { header('Location: devlog.php?msg=restore_invalid'); exit; }

// Reinsert (let id auto-assign to avoid collisions)
$title      = (string)($row['title'] ?? '');
$body       = (string)($row['body'] ?? '');
$tags       = (string)($row['tags'] ?? '');
$created_by = (string)($row['created_by'] ?? '');
$created_at = (string)($row['created_at'] ?? null);
$updated_at = (string)($row['updated_at'] ?? null);

if ($created_at === '') $created_at = null;
if ($updated_at === '') $updated_at = null;

if ($created_at && $updated_at) {
  $sql = "INSERT INTO devlog (title, body, tags, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?)";
  $stmt = $dbc->prepare($sql);
  $stmt->bind_param('ssssss', $title, $body, $tags, $created_by, $created_at, $updated_at);
} elseif ($created_at) {
  $sql = "INSERT INTO devlog (title, body, tags, created_by, created_at) VALUES (?,?,?,?,?)";
  $stmt = $dbc->prepare($sql);
  $stmt->bind_param('sssss', $title, $body, $tags, $created_by, $created_at);
} else {
  $sql = "INSERT INTO devlog (title, body, tags, created_by) VALUES (?,?,?,?)";
  $stmt = $dbc->prepare($sql);
  $stmt->bind_param('ssss', $title, $body, $tags, $created_by);
}
$stmt->execute();
$newId = (int)$stmt->insert_id;
$stmt->close();

// Audit restore
$actor = $_SESSION['user']['email'] ?? 'admin';
log_audit('devlog_undelete', ['sid'=>$sid,'restored_id'=>$newId,'title'=>$title], $actor, __log_ip());

// Done
header('Location: devlog.php?msg=restored&id='.$newId);
exit;
