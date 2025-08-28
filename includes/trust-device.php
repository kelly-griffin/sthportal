<?php
// includes/trust-device.php
declare(strict_types=1);

// Only define helpers if they don't already exist (compat with totp.php)

if (!function_exists('admin_trust_cookie_name')) {
    function admin_trust_cookie_name(): string { return 'adm_trust_v1'; }
}

if (!function_exists('admin_trust_key')) {
    function admin_trust_key(): string {
        if (defined('ADMIN_TRUST_KEY') && ADMIN_TRUST_KEY) return ADMIN_TRUST_KEY;
        if (defined('ADMIN_PASSWORD_HASH')) return hash('sha256', ADMIN_PASSWORD_HASH);
        $seed = (string)ini_get('session.hash_function') . (string)phpversion() . ($_SERVER['SERVER_SOFTWARE'] ?? 'php');
        return hash('sha256', $seed);
    }
}

if (!function_exists('admin_trust_is_valid')) {
    function admin_trust_is_valid(): bool {
        $c = $_COOKIE[admin_trust_cookie_name()] ?? '';
        if ($c === '') return false;
        $json = base64_decode($c, true);
        if ($json === false) return false;
        $data = json_decode($json, true);
        if (!is_array($data)) return false;
        $u   = (string)($data['u'] ?? 'admin');
        $exp = (int)($data['exp'] ?? 0);
        $mac = (string)($data['mac'] ?? '');
        if ($exp < time()) return false;
        $calc = hash_hmac('sha256', $u.'|'.$exp, admin_trust_key());
        return hash_equals($calc, $mac);
    }
}

if (!function_exists('admin_trust_issue')) {
    function admin_trust_issue(string $user = 'admin', int $days = 30): void {
        $exp = time() + ($days * 86400);
        $payload = ['u' => $user ?: 'admin', 'exp' => $exp];
        $payload['mac'] = hash_hmac('sha256', $payload['u'].'|'.$payload['exp'], admin_trust_key());
        $val = base64_encode(json_encode($payload));
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(admin_trust_cookie_name(), $val, [
            'expires'  => $exp,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('admin_trust_clear')) {
    function admin_trust_clear(): void {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(admin_trust_cookie_name(), '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
