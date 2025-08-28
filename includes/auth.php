<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/security-ip.php';
security_ip_enforce_or_deny($_SESSION['user']['email'] ?? null);

function _redirect(string $path, array $params = []): void {
    if (!headers_sent()) {
        if ($params) {
            $path .= (strpos($path,'?') !== false ? '&' : '?') . http_build_query($params);
        }
        header('Location: ' . $path);
    }
    exit;
}

function sanitize_admin_path(string $path): string {
    // strip nested next= to avoid recursion
    $path = preg_replace('/([&?])next=[^&]*/i', '$1', $path);
    // only allow admin/*.php targets
    if (preg_match('#^(?:/|)(?:[^?]*/)?admin/[^?]+\.php(?:\?.*)?$#i', $path)) {
        if (strlen($path) > 512) return 'index.php'; // cap length
        return $path;
    }
    return 'index.php';
}

function _next(): string {
    $raw = $_SERVER['REQUEST_URI'] ?? 'index.php';
    return sanitize_admin_path($raw);
}

function require_admin(): void {
    if (empty($_SESSION['is_admin'])) {
        _redirect('login.php', ['next' => _next()]);
    }
}

function has_perm(string $p): bool {
    if (!empty($_SESSION['is_admin'])) return true; // full admin = all perms
    return !empty($_SESSION['perms'][$p] ?? null);
}

function require_perm(string $p): void {
    // Ensure we’re logged in; if not, send to login (with next=)
    require_admin();
    if (!has_perm($p)) {
        http_response_code(403);
        exit('Forbidden: missing ' . htmlspecialchars($p));
    }
}
function require_login(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (empty($_SESSION['user'])) {
        if (!headers_sent()) {
            $next = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header("Location: /login.php?next={$next}");
        }
        exit;
    }
}
function is_licensed(mysqli $db): bool {
    $sql = "SELECT 1 FROM licenses WHERE status IN ('active','demo')
            AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1";
    if ($res = $db->query($sql)) return (bool)$res->fetch_row();
    return false;
}

function require_license(mysqli $db): void {
    if (!is_licensed($db)) {
        _redirect('license-activate.php', ['next' => _next()]);
    }
}
