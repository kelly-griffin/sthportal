<?php
require_once __DIR__ . '/maintenance.php';
// includes/config.php — deduped + guarded (safe to include multiple times)

if (!defined('STHPORTAL_CONFIG_GUARD')) {
    define('STHPORTAL_CONFIG_GUARD', 1);

    // helper: define only if not already defined
    if (!function_exists('cfg_define')) {
        function cfg_define(string $name, $value): void
        {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
// --- Environment flag for the admin header ---
if (!defined('APP_ENV')) {
  // Options: DEV, STAGING, PROD
  define('APP_ENV', 'DEV');
}
// (Optional) you can also export APP_ENV at the server level; header reads getenv('APP_ENV') too.

    // timezone (kept inside guard so it’s set once)
    date_default_timezone_set('America/Toronto');

    // --- Database ---
    cfg_define('DB_HOST', 'localhost');
    cfg_define('DB_USER', 'admin');
    cfg_define('DB_PASS', '?5ab43c2de1!');
    cfg_define('DB_NAME', 'sthportal');

    // --- Admin sign-in (hash generated with password_hash) ---
    cfg_define('ADMIN_PASSWORD_HASH', '$2y$10$WuyYOlPVzfgom51Zd6Hp7OBdFMgpa4iyNTYHcJTa0Di.9hXaG.9Fi');

    // --- Licensing (constants used by older parts; DB-backed pages also exist) ---
    cfg_define('LICENSE_KEY', 'DEV-LOCAL-KEY');
    cfg_define('PORTAL_SECRET', 'cdbab67767983d3cc9ddb48a8afc32ee7e86cfcb74a2888c49daa398cc95707a');
    cfg_define('LICENSE_EXPIRES_AT', '2026-08-09 13:15:12');
    cfg_define('LICENSE_DOMAIN', 'localhost');

    // --- Site-user lockout policy ---
    cfg_define('USER_LOCKOUT_MAX_FAILS', 5);          // per-account failures
    cfg_define('USER_LOCKOUT_DURATION_MINUTES', 24 * 60); // minutes (24h)

    // --- Rate limiting window (used by ua_should_captcha / rate block) ---
    cfg_define('LOGIN_RATE_WINDOW_SECONDS', 600);        // 10 minutes
    cfg_define('LOGIN_RATE_MAX_FAILS_PER_IP', 15);       // per-IP failures in window
    cfg_define('LOGIN_RATE_MAX_FAILS_PER_IP_EMAIL', 6);  // per-(IP+email) failures

    // --- CAPTCHA thresholds (when to show CAPTCHA) ---
    // For testing you had these at 1/1; keep that here. Raise later (e.g., 8/3) for production.
    cfg_define('LOGIN_CAPTCHA_AFTER_IP', 8);
    cfg_define('LOGIN_CAPTCHA_AFTER_IP_EMAIL', 3);

    // --- reCAPTCHA (v2 Checkbox) ---
    // Flip RECAPTCHA_ENABLED to true after you’ve put valid dev/prod keys here.
    cfg_define('RECAPTCHA_ENABLED', false);
    cfg_define('RECAPTCHA_SITE_KEY', '6Lc0eaArAAAAADeOmQ_NNIaXQ5Je__ara3Hg8LLJ');
    cfg_define('RECAPTCHA_SECRET_KEY', '6Lc0eaArAAAAAD8mtltfJdjAC6ltCE34iBiaSsxy');

    // --- SMTP (PHPMailer). If false or PHPMailer missing, falls back to mail() ---
    cfg_define('SMTP_ENABLED', true);
    cfg_define('SMTP_HOST', 'smtp.gmail.com');
    cfg_define('SMTP_PORT', 587);
    cfg_define('SMTP_SECURE', 'tls');                   // 'tls' | 'ssl' | ''
    cfg_define('SMTP_USER', 'griffin.k.r.1988@gmail.com');
    cfg_define('SMTP_PASS', 'ffsx iero gfwu ejgi');
    cfg_define('SMTP_FROM', 'griffin.k.r.1988@gmail.com');
    cfg_define('SMTP_FROM_NAME', 'STH Portal');
    cfg_define('SMTP_ALLOW_SELF_SIGNED', false);
    cfg_define('SITE_URL', 'http://localhost/sthportal/index.php');

    // --- Session hardening (idle timeout + banner/IP lock) ---
    cfg_define('SESSION_IDLE_TIMEOUT_SECONDS', 1800);  
    cfg_define('AUTH_LOCK_IP', true);                 // bounce session if IP changes
    cfg_define('AUTH_SHOW_BANNER', true);             // show the countdown banner

    // Season start date (used for schedule conversion)
define('SEASON_START_DATE', '2025-10-07');
}
