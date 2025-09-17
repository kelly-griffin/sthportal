<?php
declare(strict_types=1);

// …your existing APP_ROOT / APP_BASE_URL / asset() / url_root() etc…

// Ensure a session for flash messages
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/**
 * Flash API (session-backed)
 * - flash_set($key, $msg, $type)  → store a one-time message
 * - flash_take($key)              → read & remove
 * - flash_peek($key)              → read without removing
 */
if (!function_exists('flash_set')) {
    function flash_set(string $key, string $msg, string $type = 'info'): void {
        $_SESSION['flash'][$key] = ['msg' => $msg, 'type' => $type, 'ts' => time()];
    }
}

if (!function_exists('flash_take')) {
    function flash_take(string $key): ?array {
        if (!empty($_SESSION['flash'][$key])) {
            $v = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $v;
        }
        return null;
    }
}

if (!function_exists('flash_peek')) {
    function flash_peek(string $key): ?array {
        return $_SESSION['flash'][$key] ?? null;
    }
}


/**
 * Global helper functions and constants.
 * - Defines APP_ROOT (filesystem) and APP_BASE_URL (public URL base).
 * - Provides h(), asset(), url_root(), app_path(), and legacy shims.
 *
 * Safe to include anywhere (public, admin, media, api).
 */

// -----------------------------
// Canonical roots
// -----------------------------

// Filesystem root for the app (…/sthportal). `includes` is one level down.
if (!defined('APP_ROOT')) {
    $root = realpath(__DIR__ . '/..');
    if ($root === false) { $root = dirname(__DIR__); }
    define('APP_ROOT', str_replace('\\', '/', $root));
}

// Public base URL for the app (e.g., "/sthportal").
// Uses the script's directory, never the filename; strips known subroots.
if (!defined('APP_BASE_URL')) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/'); // e.g., /sthportal/home.php
    $dir    = rtrim(dirname($script), '/');                           // e.g., /sthportal
    // If we are inside a subroot, strip it back to app base
    $dir    = preg_replace('~/(admin|media|api|leagues|tournaments|options)$~', '', $dir);
    define('APP_BASE_URL', $dir ?: '');
}

// -----------------------------
// Core helpers
// -----------------------------
if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('asset')) {
    /** Build a public URL to an asset (css/js/img/json). */
    function asset(string $path): string {
        return APP_BASE_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url_root')) {
    /** Build a public URL to an internal page/route. */
    function url_root(string $path): string {
        return APP_BASE_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('app_path')) {
    /** Build a filesystem path under the app root. */
    function app_path(string $path = ''): string {
        return APP_ROOT . '/' . ltrim($path, '/');
    }
}

// -----------------------------
// Legacy shims (do not remove)
// -----------------------------
if (!function_exists('u')) {
    /** Legacy alias of url_root(). */
    function u(string $path = ''): string { return url_root($path); }
}
if (!function_exists('uha_base_url')) {
    /** Legacy base URL getter. */
    function uha_base_url(): string { return APP_BASE_URL; }
}
// Old global var some templates might still use
$GLOBALS['BASE'] = APP_BASE_URL;


/* Keep your existing helpers below (e.g., goToAdminBtn(), etc.) */

// Creates a Link to Admin Button
function goToAdminBtn($label = 'Go to Admin') {
    // Detect protocol
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    
    // Figure out base path from current script dir
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    
    // Force it to point to /admin/ in that base
    $url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/admin/';
    
    // Add icon before label
    $icon = '⚙'; // replace with <img> or SVG if desired
    
    // Return styled button
    return '<div style="margin-top:1rem">'
         . '<a class="btn" href="' . $url . '">'
         . $icon . ' ' . htmlspecialchars($label)
         . '</a>'
         . '</div>';
}