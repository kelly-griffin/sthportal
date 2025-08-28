<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';

require_admin();

// ---- normalize DB handle
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }

// license + permission
require_license($dbc);
require_perm('manage_users');

// ensure users table exists
$dbc->query("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  email VARCHAR(190) NOT NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'member',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// CSRF token
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Missing id.'); }

// load user
$stmt = $dbc->prepare("SELECT id, name, email FROM users WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$user) { http_response_code(404); exit('User not found.'); }

// handle POST delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Bad CSRF token.');
    }
    if ($stmt = $dbc->prepare("DELETE FROM users WHERE id=?")) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        log_audit('user_deleted', "id={$id}; name={$user['name']}; email={$user['email']}", 'admin');
        header('Location: users.php');
        exit;
    } else { http_response_code(500); exit('DB error preparing delete.'); }
}

// ---- header loader
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) {
  echo "<!doctype html><html><head><meta charset='utf-8'><title>Delete User</title>
  <style>.adminbar{display:flex;gap:12px;align-items:center;padding:10px;border-bottom:1px solid #ddd;background:#f7f7f7}
  .adminbar a{color:#333;text-decoration:none}.adminbar a:hover{text-decoration:underline}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}</style>
  </head><body>
  <nav class='adminbar'>
    <a href='index.php'>↩️ Back to Admin Home</a>
    <a href='users.php'>👤 Users</a>
    <a href='logout.php'>🚪 Logout</a>
  </nav>";
}
?>
<h1>Delete User</h1>

<p>Are you sure you want to delete this user?</p>
<ul>
  <li><strong>Name:</strong> <?= htmlspecialchars($user['name']) ?></li>
  <li><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></li>
</ul>

<form method="post" style="margin-top:.5rem;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
  <button type="submit" style="color:#b00">Yes, delete</button>
  <a href="users.php" style="margin-left:.5rem;">Cancel</a>
</form>

<?php if (!$loadedHeader) { echo "</body></html>"; } ?>
