<?php
declare(strict_types=1);

// Allow specific scripts to bypass the guard (e.g., activation page)
if (defined('LICENSE_BYPASS') && LICENSE_BYPASS === true) {
    return;
}

// Pages allowed without license check (login, activate, etc.) — path-agnostic
$LICENSE_ALLOWLIST = [
    'admin/index.php',
    'admin/license-activate.php',
    'admin/license-status.php',
    'admin/dbtest.php',
    'admin/login.php', 
    'admin/logout.php', 
];

$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
foreach ($LICENSE_ALLOWLIST as $tail) {
    if (str_ends_with($script, '/' . $tail)) {
        return; // Skip check for these
    }
}

// Require LICENSE_KEY in config
if (!defined('LICENSE_KEY') || LICENSE_KEY === '') {
    license_die('Portal not configured: missing LICENSE_KEY in config.php.');
}

// Require database connection
if (!isset($db) || !($db instanceof mysqli)) {
    license_die('Database connection not available.');
}
function lg_wants_json(): bool {
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $xhr    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    return (str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest');
}

function lg_deny_redirect(string $reason, int $code = 403): never {
    http_response_code($code);

    if (lg_wants_json()) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $reason], JSON_PRETTY_PRINT);
        exit;
    }

    // Simple internal redirect to activation (no job site shenanigans)
    if (!headers_sent()) {
        header('Location: /sthportal/admin/license-activate.php');
        exit;
    }

    // Fallback HTML
    echo "<!doctype html><meta charset='utf-8'>
    <meta http-equiv='refresh' content='0;url=/sthportal/admin/license-activate.php'>
    <a href='/sthportal/admin/license-activate.php'>Continue</a>";
    exit;
}
// Query license row (with new expiry + optional domain)
$sql = "SELECT status, expires_at, registered_domain 
        FROM licenses 
        WHERE license_key = ? 
        LIMIT 1";
$stmt = $db->prepare($sql);
if (!$stmt) {
    license_die('License check failed (prepare).');
}
$key = LICENSE_KEY; // variable required for bind_param
$stmt->bind_param('s', $key);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    license_die('This installation is not activated for this serial.');
}

// Blocked → big splash
if (($row['status'] ?? '') === 'blocked') {
    license_die_blocked($key);
}

// Expired → deny
if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
    license_die('License has expired.');
}

// Optional domain lock
$host = $_SERVER['HTTP_HOST'] ?? '';
// Uncomment if you want to enforce domain matching
// if (!empty($row['registered_domain']) && !hash_equals($row['registered_domain'], $host)) {
//     license_die('License not valid for this domain.');
// }

// Only active/demo pass
if (!in_array(($row['status'] ?? ''), ['active','demo'], true)) {
    license_die('License is not active.');
}

// --- Passed all checks ---

/* ================== HELPERS ================== */
function license_die(string $reason): void {
    lg_deny_redirect($reason, 403);
}

function license_die_blocked(string $key): void {
    http_response_code(403);
    $keyEsc = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html><meta charset="utf-8"><title>STHS Portal – Unlicensed Copy</title>
<style>
 :root{--bg:#0b0f19;--card:#101826;--txt:#e5e7eb}
 body{margin:0;background:var(--bg);color:var(--txt);font-family:system-ui,Segoe UI,Arial}
 .wrap{min-height:100vh;display:grid;place-items:center;padding:32px}
 .card{max-width:760px;width:100%;background:var(--card);border:1px solid #1f2937;border-radius:16px;padding:28px}
 .badge{display:inline-block;background:rgba(239,68,68,.15);color:#fecaca;border:1px solid rgba(239,68,68,.4);padding:6px 10px;border-radius:999px;font-size:12px;text-transform:uppercase;letter-spacing:.08em}
 h1{margin:14px 0 8px;font-size:24px}
 p{color:#cbd5e1;line-height:1.6}
 .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
 .actions{margin-top:16px;display:flex;gap:10px;flex-wrap:wrap}
 .btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer}
 .btn-primary{background:#16a34a;color:#fff}
 .btn-outline{background:transparent;color:#e5e7eb;border:1px solid #374151}
</style>
<div class="wrap">
  <div class="card">
    <span class="badge">Blocked License</span>
    <h1>This copy of the portal is not licensed</h1>
    <p>License key <span class="mono">{$keyEsc}</span> has been <b>blocked</b>. Please obtain a valid key.</p>
    <div class="actions">
      <a class="btn btn-primary" href="/sthportal/admin/license-activate.php">Activate</a>
      <a class="btn btn-outline" href="/">Back to Home</a>
    </div>
  </div>
</div>
HTML;
    exit;
}
