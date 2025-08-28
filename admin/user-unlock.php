<?php
// admin/user-unlock.php — clear lock in users + audit table, toast redirect
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/account-locks.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/admin-helpers.php';

admin_require_perm('manage_users');

$dbc  = admin_db();
$csrf = admin_csrf();
$back = admin_back('users.php');

if (!hash_equals($csrf, (string)($_GET['csrf'] ?? ''))) {
  admin_redirect($back, ['err'=>'Bad CSRF token.']);
}

$id = max(0, (int)($_GET['id'] ?? 0));
if ($id <= 0) admin_redirect($back, ['err'=>'Invalid user id.']);

$st = $dbc->prepare("SELECT email FROM users WHERE id=?");
$st->bind_param('i', $id); $st->execute();
$email = (string)($st->get_result()->fetch_row()[0] ?? ''); $st->close();
if ($email === '') admin_redirect($back, ['err'=>'User not found.']);

$st = $dbc->prepare("UPDATE users SET locked_until=NULL, updated_at=NOW() WHERE id=?");
$st->bind_param('i', $id); $ok = $st->execute(); $st->close();

al_ensure($dbc);
al_clear_lock($dbc, $email, 'user');

if ($ok && function_exists('log_audit')) log_audit('user_unlock', ['id'=>$id,'email'=>$email], 'admin');
admin_redirect($back, $ok ? ['msg'=>"User #{$id} unlocked."] : ['err'=>'Unlock failed.']);
