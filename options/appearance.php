<?php
// options/appearance.php — Avatar upload + Appearance settings
declare(strict_types=1);

// --- Includes (bootstrap first so get_db() exists) ---
require_once __DIR__ . '/../includes/bootstrap.php'; // bootstrap loads functions/db/config
$title = 'Appearance — UHA Portal';

// --- Session (for flash) ---
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// --- Flash helpers (shims if not already defined in functions.php) ---
if (!function_exists('flash_set')) {
    function flash_set(string $key, string $msg, string $type = 'info'): void {
        $_SESSION['flash'][$key] = ['msg' => $msg, 'type' => $type, 'ts' => time()];
    }
}
if (!function_exists('flash_take')) {
    function flash_take(string $key): ?array {
        if (!empty($_SESSION['flash'][$key])) {
            $v = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $v;
        }
        return null;
    }
}

/** @var mysqli $db */
$db = get_db();

// make sure users.avatar_path exists
$__col = $db->query("SHOW COLUMNS FROM `users` LIKE 'avatar_path'");
if ($__col && $__col->num_rows === 0) {
    $db->query("ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) NULL");
}
if ($__col) { $__col->free(); }

// Replace with your own current user fetch
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    header('Location: ' . url_root('login.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $avatarsDir = app_path('uploads/avatars'); // ALWAYS …/sthportal/uploads/avatars
    if (!is_dir($avatarsDir)) {
        @mkdir($avatarsDir, 0775, true);
    }

    $tmp  = $_FILES['avatar']['tmp_name'] ?? '';
    $name = $_FILES['avatar']['name'] ?? 'avatar';
    $size = (int)($_FILES['avatar']['size'] ?? 0);
    $err  = (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($err === UPLOAD_ERR_OK && $tmp && is_uploaded_file($tmp)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (finfo_file($finfo, $tmp) ?: '') : '';
        if ($finfo) finfo_close($finfo);

        $okMime = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
        if (!isset($okMime[$mime])) {
            flash_set('appearance', 'Unsupported image type. Use PNG, JPG, or WebP.', 'error');
        } elseif ($size > 3 * 1024 * 1024) {
            flash_set('appearance', 'Max file size is 3MB.', 'error');
        } else {
            $ext  = $okMime[$mime];
            $base = preg_replace('~[^a-z0-9]+~i', '-', pathinfo($name, PATHINFO_FILENAME)) ?: 'avatar';
            $finalFilename = $base . '-' . bin2hex(random_bytes(5)) . '.' . $ext;

            $destFs  = $avatarsDir . '/' . $finalFilename;
            $relPath = 'uploads/avatars/' . $finalFilename;

            if (!@move_uploaded_file($tmp, $destFs)) {
                flash_set('appearance', 'Upload failed. Please try again.', 'error');
            } else {
                if ($stmt = $db->prepare('UPDATE users SET avatar_path=? WHERE id=? LIMIT 1')) {
                    $stmt->bind_param('si', $relPath, $uid);
                    $stmt->execute();
                    $stmt->close();
                    flash_set('appearance', 'Avatar updated.', 'ok');
                } else {
                    @unlink($destFs);
                    flash_set('appearance', 'Could not save avatar path.', 'error');
                }
            }
        }
        header('Location: ' . url_root('options/appearance.php'));
        exit;
    } elseif ($err !== UPLOAD_ERR_NO_FILE) {
        flash_set('appearance', 'Upload error. Code: ' . $err, 'error');
        header('Location: ' . url_root('options/appearance.php'));
        exit;
    }
}

// Fetch current avatar path for preview
$currentRel = '';
$res = $db->query('SELECT avatar_path FROM users WHERE id=' . (int)$uid . ' LIMIT 1');
if ($res && $row = $res->fetch_assoc()) {
    $currentRel = (string)($row['avatar_path'] ?? '');
}
if ($res) $res->free();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appearance — UHA Portal</title>
    <?php require_once __DIR__ . '/../includes/head-assets.php'; ?>
</head>

<body>
    <div class="site">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

        <div class="canvas">
            <div class="appear-container">
                <div class="appear-card">
                <div class="page-surface">
                    <?php if ($flash = flash_take('appearance')): ?>
                        <div class="card" style="border-color: <?= $flash['type'] === 'error' ? '#ff5c5c66' : '#4caf5066' ?>;">
                            <strong style="display:block;margin-bottom:6px;">
                                <?= htmlspecialchars(strtoupper($flash['type'])) ?>
                            </strong>
                            <div><?= htmlspecialchars($flash['msg']) ?></div>
                        </div>
                    <?php endif; ?>

                    <h1><?= h($title) ?></h1>
                    <p class="muted">Choose your preferred theme. Saved in your browser and applied immediately.</p>

                    <div class="card" id="themeCard">
                        <h2>Theme</h2>
                        <p>Pick a mode below. “System” follows your OS preference.</p>

                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <div class="toggle" id="themeToggle">
                                <button type="button" data-mode="system">System</button>
                                <button type="button" data-mode="light">Light</button>
                                <button type="button" data-mode="dark">Dark</button>
                            </div>
                            <button class="btn small" id="resetTheme" type="button">Reset</button>
                        </div>

                        <div class="demo" aria-hidden="true">
                            <div class="swatch">Text / Cards</div>
                            <div class="swatch">Buttons</div>
                            <div class="swatch">Borders</div>
                        </div>

                        <p class="muted" style="margin-top:8px;">
                            This sets <code>data-theme="light|dark"</code> on <code>&lt;html&gt;</code>
                            and stores <code>portal:theme</code> in <code>localStorage</code>.
                        </p>
                    </div>

                    <!-- Avatar Upload Card -->
                    <div class="card">
                        <h2>Avatar</h2>
                        <p>Upload a square image (JPG/PNG/WEBP). Max 3 MB.</p>
                        <div class="avatar-wrap">
                            <div class="avatar-preview">
                                <img src="<?= h(asset('assets/avatar.php')) ?>?u=<?= (int)$uid ?>&s=64&v=<?= time() ?>" alt="Avatar" />
                            </div>

                            <form method="post" enctype="multipart/form-data"
                                style="display:flex; gap:10px; align-items:center;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
                                <button class="btn" type="submit">Upload</button>
                            </form>
                        </div>
                        <p class="help" style="margin-top:8px;">Files saved under
                            <code>/uploads/avatars/&lt;id&gt;.(jpg|png|webp)</code>. The preview is served via
                            <code>/assets/avatar.php?u=&lt;id&gt;</code>.</p>
                    </div>

                </div>
            </div>
            </div>
        </div>
    </div>

    <script>
        /* Theme toggle: localStorage + data-theme on <html> */
        (function () {
            const KEY = 'portal:theme';
            const root = document.documentElement;
            const tg = document.getElementById('themeToggle');
            const reset = document.getElementById('resetTheme');

            function apply(mode) {
                if (mode === 'system') {
                    localStorage.setItem(KEY, 'system');
                    root.removeAttribute('data-theme');
                } else {
                    localStorage.setItem(KEY, mode);
                    root.setAttribute('data-theme', mode);
                }
                Array.from(tg.querySelectorAll('button')).forEach(b => {
                    b.classList.toggle('active', b.dataset.mode === mode);
                });
            }

            const saved = localStorage.getItem(KEY) || 'system';
            apply(saved);

            tg.addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-mode]');
                if (!btn) return;
                apply(btn.dataset.mode);
            });
            reset.addEventListener('click', () => apply('system'));
        })();
    </script>
</body>

</html>
