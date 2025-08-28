<?php
// admin/license-action.php — extend/block/unblock with toast redirect
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/admin-helpers.php';

admin_require_perm('manage_licenses');

$dbc  = admin_db();
$csrf = admin_csrf();
$back = admin_back('licenses.php');

if (!hash_equals($csrf, (string)($_GET['csrf'] ?? ''))) {
  admin_redirect($back, ['err'=>'Bad CSRF token.']);
}

$id  = max(0, (int)($_GET['id'] ?? 0));
$act = strtolower((string)($_GET['act'] ?? ''));
if ($id <= 0) admin_redirect($back, ['err'=>'Invalid license id.']);

$stmt = $dbc->prepare("SELECT id, status, expires_at FROM licenses WHERE id=?");
$stmt->bind_param('i', $id); $stmt->execute();
$lic = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$lic) admin_redirect($back, ['err'=>'License not found.']);

if ($act === 'extend') {
  $months = max(1, (int)($_GET['months'] ?? 6));
  $base = (!empty($lic['expires_at']) && strtotime($lic['expires_at']) > time())
    ? new DateTimeImmutable($lic['expires_at'])
    : new DateTimeImmutable('now');
  $newExp = $base->modify('+' . $months . ' months')->format('Y-m-d H:i:s');

  $st = $dbc->prepare("UPDATE licenses SET expires_at=?, updated_at=NOW() WHERE id=?");
  $st->bind_param('si', $newExp, $id); $ok = $st->execute(); $st->close();

  if ($ok) { if (function_exists('log_audit')) log_audit('license_extend',['id'=>$id,'months'=>$months,'to'=>$newExp],'admin');
    admin_redirect($back, ['msg'=>"License #{$id} extended +{$months}m (to {$newExp})."]);
  }
  admin_redirect($back, ['err'=>'Failed to extend license.']);
}

if ($act === 'block' || $act === 'unblock') {
  $to = $act === 'block' ? 'blocked' : 'active';
  $st = $dbc->prepare("UPDATE licenses SET status=?, updated_at=NOW() WHERE id=?");
  $st->bind_param('si', $to, $id); $ok = $st->execute(); $st->close();

  if ($ok) { if (function_exists('log_audit')) log_audit('license_'.$act,['id'=>$id,'status'=>$to],'admin');
    admin_redirect($back, ['msg'=>"License #{$id} " . ($act==='block'?'blocked':'unblocked') . "."]);
  }
  admin_redirect($back, ['err'=>'Failed to update license.']);
}

admin_redirect($back, ['err'=>'Unknown action.']);
