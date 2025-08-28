<?php
// admin/devlog-delete.php — hard delete a devlog entry, then bounce back with a toast
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

// strip any existing flags so we don't duplicate them
function strip_flags(string $url): string {
    $p = parse_url($url);
    $query = [];
    if (!empty($p['query'])) {
        parse_str($p['query'], $query);
        unset($query['saved'], $query['deleted'], $query['msg'], $query['err']);
    }
    $rebuilt = ($p['path'] ?? 'devlog.php');
    if (!empty($query)) $rebuilt .= '?' . http_build_query($query);
    return $rebuilt;
}
$cleanBack = strip_flags($back);

$id  = (int)($_GET['id'] ?? 0);
$tok = (string)($_GET['csrf'] ?? '');

if (!hash_equals($csrf, $tok) || $id <= 0) {
    header('Location: ' . $cleanBack);
    exit;
}

$st = $dbc->prepare("DELETE FROM devlog_entries WHERE id=?");
$st->bind_param('i', $id);
$ok  = $st->execute();
$aff = $dbc->affected_rows;
$st->close();

// build redirect with toast param
$sep = (strpos($cleanBack, '?') !== false) ? '&' : '?';
if ($ok && $aff > 0) {
    header('Location: ' . $cleanBack . $sep . 'msg=' . rawurlencode('Deleted!'));
} else {
    header('Location: ' . $cleanBack . $sep . 'err=' . rawurlencode('Delete failed.'));
}
exit;
