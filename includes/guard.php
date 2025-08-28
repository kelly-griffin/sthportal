<?php
// includes/guard.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/db.php'; // provides $db (mysqli)

/** Session helpers */
function is_admin(): bool { return !empty($_SESSION['is_admin']); }
function has_perm(string $p): bool {
    if (!empty($_SESSION['is_admin'])) return true; // full admin = all perms
    return !empty($_SESSION['perms'][$p] ?? null);
}

/** Safe "next" handling (admin-only relative paths) */
function sanitize_next(?string $next): string {
    $next = $next ?? '';
    if ($next === '') return '';
    if (preg_match('#^(?:/|)(?:[^?]*/)?admin/[^?]+\.php(?:\?.*)?$#i', $next)) {
        return $next;
    }
    return '';
}

/** Simple redirect */
function redirect(string $target, array $params = []): void {
    if (!headers_sent()) {
        if ($params) {
            $query = http_build_query($params);
            $target .= (strpos($target, '?') === false ? '?' : '&') . $query;
        }
        header('Location: ' . $target);
    }
    exit;
}

/** Is there any valid (active/demo & not expired) license? */
function is_licensed(mysqli $db): bool {
    $sql = "SELECT 1
              FROM licenses
             WHERE status IN ('active','demo')
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1";
    if ($res = $db->query($sql)) {
        return (bool)$res->fetch_row();
    }
    return false;
}

/** Per-page flags (pages can set before including this file) */
if (!isset($REQUIRE_ADMIN))   $REQUIRE_ADMIN = true;
if (!isset($REQUIRE_LICENSE)) $REQUIRE_LICENSE = true;

$CURRENT = basename($_SERVER['SCRIPT_NAME'] ?? '');

/** 1) Admin gate: if not signed in, always go to login (never to license) */
if ($REQUIRE_ADMIN && !is_admin()) {
    $next = $_SERVER['REQUEST_URI'] ?? '';
    redirect('login.php', ['next' => $next]);
}

/** 2) License gate: only after you’re signed-in; whitelist key pages */
$WHITELIST = ['login.php', 'license-activate.php', 'licenses.php', 'logout.php', 'index.php'];
if ($REQUIRE_LICENSE && !in_array($CURRENT, $WHITELIST, true) && !is_licensed($db)) {
    $next = $_SERVER['REQUEST_URI'] ?? '';
    redirect('license-activate.php', ['next' => $next]);
}
