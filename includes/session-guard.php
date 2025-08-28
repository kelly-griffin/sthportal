<?php
// includes/session-guard.php — unified guard: idle timeout + optional IP lock + lock enforcement + global sign-out + banner
// Hyphen routes. Safe drop-in: replaces prior version and keeps the banner helper.

// ---------------- Boot session ----------------
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

// ---------------- Config (overrides allowed via includes/config.php) ----------------
if (!defined('SESSION_IDLE_TIMEOUT_SECONDS')) define('SESSION_IDLE_TIMEOUT_SECONDS', 1800); // 30m
if (!defined('AUTH_LOCK_IP'))                define('AUTH_LOCK_IP', true);
if (!defined('AUTH_SHOW_BANNER'))            define('AUTH_SHOW_BANNER', true);

// ---------------- DB helpers (non-intrusive; use existing handle if present) ----------------
require_once __DIR__ . '/db.php';
function sg_db(): ?mysqli {
    foreach (['db','conn','mysqli'] as $g) if (isset($GLOBALS[$g]) && $GLOBALS[$g] instanceof mysqli) return $GLOBALS[$g];
    if (function_exists('get_db')) { $h = get_db(); if ($h instanceof mysqli) return $h; }
    return null; // guard will no-op if DB isn’t available
}
function sg_dbname(mysqli $db): string {
    $r = $db->query('SELECT DATABASE()'); if ($r && ($row=$r->fetch_row())) return (string)$row[0]; return '';
}

// ---------------- IP + URL helpers ----------------
function _sg_norm_ip(string $ip): string {
    $ip = trim($ip);
    if ($ip === '' || strtolower($ip) === 'unknown') return '0.0.0.0';
    if ($ip === '::1') return '127.0.0.1';
    if (stripos($ip, '::ffff:') === 0) return substr($ip, 7);
    return $ip;
}
function _sg_get_ip(): string {
    $h = $_SERVER;
    if (!empty($h['HTTP_CF_CONNECTING_IP'])) return _sg_norm_ip($h['HTTP_CF_CONNECTING_IP']);
    if (!empty($h['HTTP_X_FORWARDED_FOR'])) foreach (array_map('trim', explode(',', (string)$h['HTTP_X_FORWARDED_FOR'])) as $p) if ($p!=='') return _sg_norm_ip($p);
    if (!empty($h['HTTP_X_REAL_IP'])) return _sg_norm_ip($h['HTTP_X_REAL_IP']);
    if (!empty($h['REMOTE_ADDR'])) return _sg_norm_ip($h['REMOTE_ADDR']);
    return '0.0.0.0';
}
function _sg_login_url(string $reason): string {
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $root   = preg_replace('#/admin$#','',$base);
    return $scheme . '://' . $host . $root . '/login.php?reason=' . rawurlencode($reason);
}
function _sg_is_logged_in(): bool {
    return !empty($_SESSION['user']) || !empty($_SESSION['is_admin']);
}
function _sg_user_id(mysqli $db = null): ?int {
    $db = $db ?: sg_db();
    if (!$db) return null;
    // Prefer session-stored id if present
    if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    // Fallback via email
    $email = $_SESSION['user']['email'] ?? null;
    if (!$email) return null;
    if (!$stmt = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1")) return null;
    $stmt->bind_param('s', $email);
    $stmt->execute(); $stmt->bind_result($id);
    $uid = $stmt->fetch() ? (int)$id : null;
    $stmt->close();
    if ($uid) $_SESSION['user']['id'] = $uid; // cache for future
    return $uid;
}

// ---------------- Ensure tiny aux tables (idempotent) ----------------
function _sg_ensure_tables(mysqli $db = null): void {
    $db = $db ?: sg_db(); if (!$db) return;
    $db->query("CREATE TABLE IF NOT EXISTS account_locks (
        user_id INT PRIMARY KEY,
        is_locked TINYINT(1) NOT NULL DEFAULT 0,
        reason VARCHAR(255) NULL,
        locked_at DATETIME NULL,
        unlocked_at DATETIME NULL,
        updated_by VARCHAR(128) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $db->query("CREATE TABLE IF NOT EXISTS user_session_revocations (
        user_id INT PRIMARY KEY,
        revoked_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

// ---------------- Checks: locked? revoked? ----------------
function _sg_is_locked(int $userId, mysqli $db = null): bool {
    $db = $db ?: sg_db(); if (!$db) return false;
    if (!$stmt = $db->prepare("SELECT is_locked FROM account_locks WHERE user_id=? LIMIT 1")) return false;
    $stmt->bind_param('i', $userId); $stmt->execute(); $stmt->bind_result($locked);
    $ok = $stmt->fetch() ? ((int)$locked === 1) : false;
    $stmt->close(); return $ok;
}
function _sg_revoked_after(int $userId, mysqli $db = null): ?string {
    $db = $db ?: sg_db(); if (!$db) return null;
    if (!$stmt = $db->prepare("SELECT revoked_after FROM user_session_revocations WHERE user_id=? LIMIT 1")) return null;
    $stmt->bind_param('i', $userId); $stmt->execute(); $stmt->bind_result($ra);
    $val = $stmt->fetch() ? (string)$ra : null; $stmt->close();
    return $val ?: null;
}

// ---------------- Guard main ----------------
if (!function_exists('session_guard_boot')) {
    function session_guard_boot(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!_sg_is_logged_in()) return;

        $db = sg_db();
        _sg_ensure_tables($db);

        $now = time();
        $ttl = max(0, (int)SESSION_IDLE_TIMEOUT_SECONDS);
        $last = (int)($_SESSION['last_activity'] ?? $now);

        // Idle timeout
        if ($ttl > 0 && ($now - $last) > $ttl) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                @setcookie(session_name(), '', time()-42000, $p['path'] ?? '/', $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
            }
            @session_destroy();
            header('Location: ' . _sg_login_url('timeout')); exit;
        }

        // Optional IP lock
        if (AUTH_LOCK_IP) {
            $ip = _sg_get_ip();
            if (!isset($_SESSION['ip_lock'])) {
                $_SESSION['ip_lock'] = $ip;
            } elseif ($_SESSION['ip_lock'] !== $ip) {
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    @setcookie(session_name(), '', time()-42000, $p['path'] ?? '/', $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
                }
                @session_destroy();
                header('Location: ' . _sg_login_url('ip_change')); exit;
            }
        }

        // --- NEW: lock + global sign-out enforcement ---
        $uid = _sg_user_id($db);
        if ($uid) {
            // if locked, end session immediately
            if (_sg_is_locked($uid, $db)) {
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    @setcookie(session_name(), '', time()-42000, $p['path'] ?? '/', $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
                }
                @session_destroy();
                header('Location: ' . _sg_login_url('locked')); exit;
            }
            // global sign-out: compare session issued_at to revoked_after
            if (empty($_SESSION['issued_at'])) $_SESSION['issued_at'] = $now; // set if missing
            $revokedAfter = _sg_revoked_after($uid, $db);
            if ($revokedAfter && strtotime($revokedAfter) > (int)$_SESSION['issued_at']) {
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    @setcookie(session_name(), '', time()-42000, $p['path'] ?? '/', $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
                }
                @session_destroy();
                header('Location: ' . _sg_login_url('signed_out')); exit;
            }
        }

        // Touch activity
        $_SESSION['last_activity'] = $now;
    }
}

// ---------------- Banner (unchanged API) ----------------
if (!function_exists('session_banner_html')) {
    function session_banner_html(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!AUTH_SHOW_BANNER) return '';
        if (!_sg_is_logged_in()) return '';

        $ttl = (int)SESSION_IDLE_TIMEOUT_SECONDS; if ($ttl <= 0) return '';
        if (!isset($_SESSION['last_activity']) || !is_int($_SESSION['last_activity'])) $_SESSION['last_activity'] = time();
        $sec = max(0, $ttl - (time() - (int)$_SESSION['last_activity']));

        ob_start(); ?>
        <div id="sgBanner"
             style="margin:.6rem 0;padding:8px 10px;border:1px solid #c7d2fe;background:#eef2ff;border-radius:8px;display:flex;gap:.6rem;align-items:center">
          <div><strong>Session</strong> will auto-expire after inactivity.</div>
          <div>Time left: <span class="sgTime" style="font-feature-settings:'tnum' 1; font-variant-numeric:tabular-nums">--:--</span></div>
          <div style="margin-left:auto;display:flex;gap:.4rem;align-items:center">
            <button type="button" id="sgRefresh"
                    style="padding:.3rem .6rem;border:1px solid #6366f1;border-radius:6px;background:#fff;cursor:pointer">
              Refresh
            </button>
          </div>
        </div>
        <script>
        (function(){
          var wrap = document.getElementById('sgBanner'); if(!wrap) return;
          var el = wrap.querySelector('.sgTime');
          var sec = <?= (int)$sec ?>;
          function fmt(s){ var m=Math.floor(s/60), ss=s%60; return m+':'+('0'+ss).slice(-2); }
          function tick(){ try{ el.textContent = fmt(sec);}catch(e){} if(sec<=0){return;} sec -= 1; setTimeout(tick, 1000); }
          tick();
          var btn = document.getElementById('sgRefresh'); if(btn) btn.addEventListener('click', function(){ window.location.reload(); });
        })();
        </script>
        <?php
        return (string)ob_get_clean();
    }
}

// ---------------- Auto-boot ----------------
if (function_exists('session_guard_boot')) { session_guard_boot(); }

if (!function_exists('session_banner_html_once')) {
    function session_banner_html_once(): string {
        static $printed = false;
        if ($printed) return '';
        $printed = true;
        return function_exists('session_banner_html') ? (string)session_banner_html() : '';
    }
}
