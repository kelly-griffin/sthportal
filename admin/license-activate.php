<?php
// Reachable for logged-in admins even if unlicensed
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';

require_admin(); // must be logged in; license NOT required here

$nextRaw = $_GET['next'] ?? $_POST['next'] ?? '';
$nextForRedirect = $nextRaw !== '' ? sanitize_admin_path($nextRaw) : '';

// ---- normalize DB handle regardless of variable name in includes/db.php
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }

if (!$dbc instanceof mysqli) {
    http_response_code(500);
    exit('Database handle not initialized. Check includes/db.php to ensure it creates a mysqli connection.');
}

$error = '';
$msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = trim((string)($_POST['license_key'] ?? ''));
    if ($key === '') {
        $error = 'Please enter a license key.';
    } else {
        $domain = $_SERVER['HTTP_HOST'] ?? null;
        $months = 24; // change to 12 if you want 1 year
        $expires_at = (new DateTimeImmutable('now'))->modify("+{$months} months")->format('Y-m-d H:i:s');

        $sql = "INSERT INTO licenses (license_key, status, registered_domain, expires_at, created_at, updated_at)
                VALUES (?, 'active', ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    status='active',
                    registered_domain=VALUES(registered_domain),
                    expires_at=VALUES(expires_at),
                    updated_at=NOW()";

        if ($stmt = $dbc->prepare($sql)) {
            $stmt->bind_param('sss', $key, $domain, $expires_at);
            $stmt->execute();
            $stmt->close();

            // log with masked key
            $masked = log_mask($key);
            log_audit('license_activated', "key={$masked}; domain={$domain}; expires={$expires_at}", 'admin');

            header('Location: ' . ($nextForRedirect !== '' ? $nextForRedirect : 'index.php'));
            exit;
        } else {
            $error = 'Database error preparing statement.';
        }
    }
}

// ---- resilient admin header loader (uses admin/admin-header.php if present)
$loadedHeader = false;
$candidates = [
    __DIR__ . '/admin-header.php',
    __DIR__ . '/header-admin.php',
    __DIR__ . '/header.php',
    __DIR__ . '/../includes/admin-header.php',
];
foreach ($candidates as $p) {
    if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) {
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Activate License</title>
    <style>.adminbar{display:flex;gap:12px;align-items:center;padding:10px;border-bottom:1px solid #ddd;background:#f7f7f7}
    .adminbar a{color:#333;text-decoration:none}.adminbar a:hover{text-decoration:underline}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}</style>
    </head><body>
    <nav class='adminbar'>
      <a href='index.php'>↩️ Back to Admin Home</a>
      <a href='licenses.php'>📜 Licenses</a>
      <a href='users.php'>👤 Users</a>
      <a href='logout.php'>🚪 Logout</a>
    </nav>";
}
?>
<h1>Activate License</h1>
<?php if ($error): ?><div style="color:#b00;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($msg):   ?><div style="color:#070;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<form method="post">
  <input type="hidden" name="next" value="<?= htmlspecialchars($nextForRedirect, ENT_QUOTES, 'UTF-8') ?>">
  <label>License key
    <input type="text" name="license_key" autofocus>
  </label>
  <button type="submit">Activate</button>
</form>
<?php if (!$loadedHeader) { echo "</body></html>"; } ?>
