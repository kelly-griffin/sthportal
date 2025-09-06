<?php
declare(strict_types=1);

/**
 * includes/functions.php
 * Global helpers — no output, no side effects.
 */
/**
 * Canonical base URL for the app (e.g., "/sthportal"), regardless of page.
 * - Uses the script's directory, not the filename (so no "/home.php" in paths).
 * - Strips common subroots (admin/media/api/tools/options) if the page lives there.
 */
if (!defined('APP_BASE_URL')) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $dir    = rtrim(dirname($script), '/');           // "/sthportal"
    // If we're in a subroot, strip it: "/sthportal/admin" -> "/sthportal"
    $dir    = preg_replace('~/(admin|media|api|tools|options)$~', '', $dir);
    define('APP_BASE_URL', $dir ?: '');
}

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('asset')) {
    function asset(string $p): string { return APP_BASE_URL . '/' . ltrim($p, '/'); }
}

if (!function_exists('url_root')) {
    function url_root(string $p): string { return APP_BASE_URL . '/' . ltrim($p, '/'); }
}

// Legacy shims (leave them so old templates work)
if (!function_exists('u')) {
    function u(string $p = ''): string { return url_root($p); }
}
if (!function_exists('uha_base_url')) {
    function uha_base_url(): string { return APP_BASE_URL; }
}
// (Optional) legacy $BASE for very old templates
$GLOBALS['BASE'] = APP_BASE_URL;

// HTML escape
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

// Legacy URL passthrough some pages call
if (!function_exists('u')) {
  function u(string $path = ''): string { return $path; }
}

// Compute a root-relative base URL so /admin and /api don’t bleed into paths
if (!function_exists('uha_base_url')) {
  function uha_base_url(): string {
    $script = str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? '');
    // Strip common sub-roots used in this portal
    $base = preg_replace('~/(admin|tools|options|media|api)(/.*)?$~','', $script);
    $base = rtrim((string)$base, '/');
    return $base; // can be '' when hosted at web root
  }
}

// Root-relative asset url: asset('assets/css/global.css')
if (!function_exists('asset')) {
  function asset(string $p): string { return uha_base_url() . '/' . ltrim($p, '/'); }
}

// Root-relative internal link: url_root('news.php?page=2')
if (!function_exists('url_root')) {
  function url_root(string $p): string { return uha_base_url() . '/' . ltrim($p, '/'); }
}

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