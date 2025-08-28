<?php
// includes/security-headers.php — safe defaults for all pages (run once)
if (defined('SECURITY_HEADERS_SENT')) return;
define('SECURITY_HEADERS_SENT', 1);

$https = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
if (function_exists('header') && !headers_sent()) {
  // Clickjacking, MIME sniffing, referrers, and basic permissions
  header('X-Frame-Options: DENY');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: same-origin');
  header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

  // Content Security Policy (kept permissive for inline bits + reCAPTCHA support)
  $csp = [
    "default-src 'self'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
    "img-src 'self' data: https://www.gstatic.com/recaptcha/",
    "style-src 'self' 'unsafe-inline'",
    "script-src 'self' 'unsafe-inline' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/",
    "frame-src 'self' https://www.google.com/recaptcha/",
    "connect-src 'self'",
  ];
  header('Content-Security-Policy: ' . implode('; ', $csp));

  // HSTS only when HTTPS (set to ~180 days)
  if ($https) {
    header('Strict-Transport-Security: max-age=15552000; includeSubDomains; preload');
  }
}

// Harden session cookies (best-effort; if session already started we still set INI defaults)
@ini_set('session.cookie_httponly', '1');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.cookie_samesite', 'Strict');
if ($https) @ini_set('session.cookie_secure', '1');
