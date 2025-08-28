<?php
// includes/maintenance.php — maintenance guard + auto admin badge
// Usage: require_once __DIR__ . '/maintenance.php' from includes/config.php (already done).

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

if (!defined('MAINTENANCE_JSON_PATH'))      define('MAINTENANCE_JSON_PATH', __DIR__ . '/.maintenance.json');
if (!defined('MAINTENANCE_AUTO_GUARD'))     define('MAINTENANCE_AUTO_GUARD', true);  // run guard on include
if (!defined('MAINTENANCE_BADGE_AUTOINJECT')) define('MAINTENANCE_BADGE_AUTOINJECT', true); // show badge on admin pages

/* ---------------- helpers ---------------- */
if (!function_exists('maint_ip_norm')) {
    function maint_ip_norm(string $ip): string {
        $ip = trim($ip);
        if ($ip === '' || strtolower($ip) === 'unknown') return '0.0.0.0';
        if ($ip === '::1') return '127.0.0.1';
        if (stripos($ip, '::ffff:') === 0) return substr($ip, 7);
        return $ip;
    }
}
if (!function_exists('maint_client_ip')) {
    function maint_client_ip(): string {
        $h = $_SERVER;
        if (!empty($h['HTTP_CF_CONNECTING_IP'])) return maint_ip_norm($h['HTTP_CF_CONNECTING_IP']);
        if (!empty($h['HTTP_X_FORWARDED_FOR'])) {
            foreach (array_map('trim', explode(',', (string)$h['HTTP_X_FORWARDED_FOR'])) as $p) if ($p !== '') return maint_ip_norm($p);
        }
        if (!empty($h['HTTP_X_REAL_IP'])) return maint_ip_norm($h['HTTP_X_REAL_IP']);
        if (!empty($h['REMOTE_ADDR'])) return maint_ip_norm($h['REMOTE_ADDR']);
        return '0.0.0.0';
    }
}
if (!function_exists('maint_is_admin')) {
    function maint_is_admin(): bool {
        if (!empty($_SESSION['is_admin'])) return true;
        $u = $_SESSION['user'] ?? null;
        if (is_array($u)) {
            if (!empty($u['is_admin'])) return true;
            if (!empty($u['role']) && strtolower((string)$u['role']) === 'admin') return true;
        }
        return false;
    }
}

/* ---------------- state ---------------- */
if (!function_exists('maintenance_state')) {
    function maintenance_state(): array {
        $defaults = [
            'enabled' => false,
            'message' => 'We&rsquo;re doing some maintenance. Please check back soon.',
            'allow_admin_bypass' => true,
            'allow_ips' => [],
            'updated_at' => null,
            'updated_by' => null,
        ];
        $p = MAINTENANCE_JSON_PATH;
        if (!is_file($p)) return $defaults;
        $raw = (string)@file_get_contents($p);
        if ($raw === '') return $defaults;
        $data = json_decode($raw, true);
        if (!is_array($data)) return $defaults;
        $data['enabled'] = (bool)($data['enabled'] ?? false);
        $data['message'] = (string)($data['message'] ?? $defaults['message']);
        $data['allow_admin_bypass'] = (bool)($data['allow_admin_bypass'] ?? true);
        $ips = $data['allow_ips'] ?? [];
        if (!is_array($ips)) $ips = [];
        $data['allow_ips'] = array_values(array_unique(array_filter(array_map('maint_ip_norm', array_map('strval', $ips)))));
        $data['updated_at'] = (string)($data['updated_at'] ?? '');
        $data['updated_by'] = (string)($data['updated_by'] ?? '');
        return $data;
    }
}
if (!function_exists('maintenance_save')) {
    function maintenance_save(array $state): bool {
        $state['updated_at'] = gmdate('c');
        $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return (bool)@file_put_contents(MAINTENANCE_JSON_PATH, $json);
    }
}
if (!function_exists('maintenance_is_on')) {
    function maintenance_is_on(): bool {
        $s = maintenance_state();
        return !empty($s['enabled']);
    }
}

/* ---------------- guard ---------------- */
if (!function_exists('maintenance_guard')) {
    function maintenance_guard(): void {
        // never block admin pages (so you can toggle off)
        $path = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('#/admin(/|$)#', $path)) return;

        $state = maintenance_state();
        if (empty($state['enabled'])) return;

        // IP allowlist
        $ip = maint_client_ip();
        if (in_array($ip, $state['allow_ips'], true)) return;

        // Admin bypass allowed?
        if (!empty($state['allow_admin_bypass']) && maint_is_admin()) return;

        // 503 page
        http_response_code(503);
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=utf-8');

        $msg = $state['message'] ?: 'We&rsquo;re doing some maintenance. Please check back soon.';
        ?>
<!doctype html>
<meta charset="utf-8">
<title>Maintenance</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f5f7fb;margin:0}
  .wrap{max-width:680px;margin:8vh auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.04);padding:24px}
  h1{margin:0 0 8px 0;font-size:1.6rem}
  .muted{color:#555}
  .box{margin-top:12px;padding:12px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc}
  code{background:#f1f5f9;padding:2px 6px;border-radius:6px}
</style>
<div class="wrap">
  <h1>We’ll be right back</h1>
  <div class="muted"><?= $msg ?></div>
  <div class="box">
    <div class="muted">Your IP: <code><?= htmlspecialchars($ip) ?></code></div>
  </div>
</div>
<?php
        exit;
    }
}

/* ---------------- admin badge ---------------- */
if (!function_exists('maintenance_admin_badge_html')) {
    function maintenance_admin_badge_html(): string {
        if (!maintenance_is_on()) return '';
        // only render on admin pages for admins
        $path = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if (!preg_match('#/admin(/|$)#', $path)) return '';
        if (!maint_is_admin()) return '';

        // build link
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        $adminBase = preg_replace('#/admin$#','/admin',$base);
        $href = $adminBase . '/maintenance.php';

        ob_start(); ?>
        <a href="<?= htmlspecialchars($href) ?>"
           style="position:fixed;top:10px;right:10px;z-index:9999;
                  display:inline-flex;align-items:center;gap:.35rem;
                  padding:.25rem .6rem;border:1px solid #ef9a9a;border-radius:999px;
                  background:#ffebee;color:#b71c1c;text-decoration:none;font-weight:600">
          <span style="width:.5rem;height:.5rem;border-radius:999px;background:#e53935;display:inline-block"></span>
          MAINTENANCE ON
        </a>
        <?php
        return (string)ob_get_clean();
    }
}

// Auto-inject badge at end of admin HTML pages unless disabled
if (MAINTENANCE_BADGE_AUTOINJECT && php_sapi_name() !== 'cli') {
    register_shutdown_function(function(){
        // Skip if not admin area or maintenance off or not admin
        if (!maintenance_is_on()) return;
        $path = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if (!preg_match('#/admin(/|$)#', $path)) return;
        if (!maint_is_admin()) return;

        // Avoid corrupting downloads/exports
        $q = array_change_key_case($_GET, CASE_LOWER);
        if (isset($q['export']) && in_array(strtolower((string)$q['export']), ['csv','json','xml'], true)) return;

        // Try to detect non-HTML responses via header hints (best-effort)
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') === 0 && stripos($h, 'text/html') === false) return;
        }

        echo maintenance_admin_badge_html();
    });
}

/* ---------------- autorun guard ---------------- */
if (MAINTENANCE_AUTO_GUARD) maintenance_guard();
