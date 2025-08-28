<?php
// admin/user-toggle-active.php — toggle active + toast redirect
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-helpers.php';

admin_require_perm('manage_users');

$dbc  = admin_db();
$csrf = admin_csrf();
$back = admin_back('users.php');

if (!hash_equals($csrf, (string)($_GET['csrf'] ?? ''))) {
  admin_redirect($back, ['err'=>'Bad CSRF token.']);
}

$id = max(0, (int)($_GET['id'] ?? 0));
$to = (int)($_GET['to'] ?? -1); // 0/1
if ($id <= 0 || ($to !== 0 && $to !== 1)) admin_redirect($back, ['err'=>'Invalid request.']);

function col_exists(mysqli $dbc, string $col): bool {
  $c = $dbc->real_escape_string($col);
  $r = $dbc->query("SHOW COLUMNS FROM `users` LIKE '{$c}'");
  return $r && $r->num_rows > 0;
}
$activeCol = null;
foreach (['active','is_active','enabled'] as $c) if (col_exists($dbc, $c)) { $activeCol = $c; break; }
if (!$activeCol) admin_redirect($back, ['err'=>'No active column on users table.']);
$hasUpdated = col_exists($dbc, 'updated_at');

$sql = $hasUpdated
  ? "UPDATE `users` SET `{$activeCol}`=?, updated_at=NOW() WHERE id=?"
  : "UPDATE `users` SET `{$activeCol}`=? WHERE id=?";
$st = $dbc->prepare($sql);
$st->bind_param('ii', $to, $id); $ok = $st->execute(); $st->close();

$verb = $to ? 'activated' : 'deactivated';
admin_redirect($back, $ok ? ['msg'=>"User #{$id} {$verb}."] : ['err'=>'Update failed.']);
