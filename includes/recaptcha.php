<?php
// includes/recaptcha.php — minimal helper for reCAPTCHA v2 verify (editor‑friendly)
require_once __DIR__ . '/config.php';

// Helpers return empty strings if constants aren’t defined (avoids IDE warnings)
if (!function_exists('recaptcha_site_key')) {
  function recaptcha_site_key(): string {
    return defined('RECAPTCHA_SITE_KEY') ? (string) constant('RECAPTCHA_SITE_KEY') : '';
  }
}
if (!function_exists('recaptcha_secret_key')) {
  function recaptcha_secret_key(): string {
    return defined('RECAPTCHA_SECRET_KEY') ? (string) constant('RECAPTCHA_SECRET_KEY') : '';
  }
}

function recaptcha_enabled(): bool {
  return defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED
      && recaptcha_site_key() !== ''
      && recaptcha_secret_key() !== '';
}

/**
 * Verify a POSTed token against Google.
 * @return array [bool ok, string errorMessage]
 */
function recaptcha_verify_post(?string $responseToken, ?string $remoteIp = null): array {
    if (!recaptcha_enabled()) return [true, ''];
    $token = trim((string)$responseToken);
    if ($token === '') return [false, 'CAPTCHA missing'];

    $secret = recaptcha_secret_key();

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $remoteIp ?: ($_SERVER['REMOTE_ADDR'] ?? ''),
            ]),
            'timeout' => 6,
        ],
    ];
    $ctx = stream_context_create($opts);
    $json = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    if ($json === false) return [false, 'CAPTCHA verify failed (network)'];

    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['success'])) {
        $err = isset($data['error-codes']) ? implode(',', (array)$data['error-codes']) : 'unknown';
        return [false, 'CAPTCHA failed: ' . $err];
    }
    return [true, ''];
}
