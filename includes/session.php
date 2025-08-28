<?php
// includes/session.php — secure session bootstrap + helpers

// Detect HTTPS (works behind some proxies too)
function sp_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    foreach (['HTTP_X_FORWARDED_PROTO','HTTP_X_FORWARDED_SSL'] as $h) {
        if (!empty($_SERVER[$h]) && (stripos((string)$_SERVER[$h], 'https') !== false || $_SERVER[$h] === 'on')) return true;
    }
    return false;
}

/**
 * Start a hardened session (idempotent).
 * - Strict mode, cookies only, HttpOnly, SameSite=Lax, Secure on HTTPS
 * - Custom name so it doesn’t collide with other apps
 * - Sends basic security headers once
 */
function secure_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    // Basic security headers (only if not sent yet)
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // Minimal CSP (adjust later if you add CDNs)
        header("Content-Security-Policy: default-src 'self'; frame-ancestors 'self'; base-uri 'self'");
    }

    // Runtime INI hardening
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    if (sp_is_https()) @ini_set('session.cookie_secure', '1');

    // Name + cookie params (PHP 7.3+ supports 'samesite')
    if (!headers_sent()) {
        session_name('sthportal');
        $params = session_get_cookie_params();
        $cookie = [
            'lifetime' => 0,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => sp_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        // Use array form if available (PHP 7.3+)
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($cookie);
        } else {
            // Fallback (no explicit SameSite, still sets secure/httponly)
            session_set_cookie_params($cookie['lifetime'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
        }
    }

    session_start();
}

/** Call this right after a successful login */
function session_on_auth_success(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    // prevent fixation
    session_regenerate_id(true);
    // store a timestamp for idle tracking if you want later
    $_SESSION['auth_time'] = time();
}
