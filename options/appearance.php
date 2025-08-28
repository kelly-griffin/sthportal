<?php
// /options/appearance.php — Appearance + Avatar upload wired to /assets/avatar.php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user-auth.php';

// ---- Gate: must be logged in (no admin requirement) ----
require_login();

if (session_status() !== PHP_SESSION_ACTIVE)
    @session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$title = 'Appearance';

// ---- Avatar helpers (point to /uploads/avatars to match assets/avatar.php) ----
function avatars_dir(): string
{
    return __DIR__ . '../uploads/avatars';
}
function ensure_dir(string $p): void
{
    if (!is_dir($p))
        @mkdir($p, 0775, true);
}
function allowed_mimes(): array
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
}
function max_bytes(): int
{
    return 2 * 1024 * 1024;
} // 2MB
function max_dims(): array
{
    return [1024, 1024];
}
function users_table_has_avatar(mysqli $db): bool
{
    $res = $db->query("SHOW COLUMNS FROM `users` LIKE 'avatar'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}


// ---- Flash helpers ----
$_SESSION['flash'] = $_SESSION['flash'] ?? [];
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['t' => $type, 'm' => $msg];
}
function flash_take(): array
{
    $out = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $out;
}

// ---- Handle Avatar POST ----
$uid = current_user_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
        http_response_code(400);
        exit('Bad Request (CSRF)');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'remove') {
        ensure_dir(avatars_dir());
        foreach (allowed_mimes() as $ext) {
            $p = avatars_dir() . "/{$uid}.{$ext}";
            if (is_file($p))
                @unlink($p);
        }
        try {
            $db = get_db();
            if (users_table_has_avatar($db)) {
                $stmt = $db->prepare('UPDATE `users` SET `avatar`=NULL WHERE `id`=? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $uid);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } catch (Throwable $e) { /* ignore */
        }
        unset($_SESSION['user']['avatar']);
        flash('success', 'Avatar removed.');
        header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/options/appearance.php'));
        exit;
    }

    if ($action === 'upload' && isset($_FILES['avatar'])) {
        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Upload failed (code ' . (int) $file['error'] . ').');
        } elseif ($file['size'] > max_bytes()) {
            flash('error', 'File too large. Max 2 MB.');
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file($file['tmp_name']);
            $map = allowed_mimes();
            if (!isset($map[$mime])) {
                flash('error', 'Unsupported type. Use JPG, PNG, or WEBP.');
            } else {
                $dims = @getimagesize($file['tmp_name']);
                if (!$dims) {
                    flash('error', 'Unable to read image.');
                } else {
                    [$w, $h] = [$dims[0], $dims[1]];
                    [$mw, $mh] = max_dims();
                    if ($w > $mw || $h > $mh) {
                        flash('error', "Image too large ({$w}x{$h}). Max {$mw}x{$mh}.");
                    } else {
                        ensure_dir(avatars_dir());
                        foreach ($map as $extOld) {
                            $old = avatars_dir() . "/{$uid}.{$extOld}";
                            if (is_file($old))
                                @unlink($old);
                        }
                        $ext = $map[$mime];
                        $dest = avatars_dir() . "/{$uid}.{$ext}";
                        if (@move_uploaded_file($file['tmp_name'], $dest)) {
                            // Optional DB sync (not required by assets/avatar.php)
                            $rel = '../uploads/avatars/' . $uid . '.' . $ext;
                            try {
                                $db = get_db();
                                if (users_table_has_avatar($db)) {
                                    $stmt = $db->prepare('UPDATE `users` SET `avatar`=? WHERE `id`=? LIMIT 1');
                                    if ($stmt) {
                                        $stmt->bind_param('si', $rel, $uid);
                                        $stmt->execute();
                                        $stmt->close();
                                    }
                                }
                            } catch (Throwable $e) { /* ignore */
                            }
                            $_SESSION['user']['avatar'] = $rel;
                            flash('success', 'Avatar updated.');
                        } else {
                            flash('error', 'Failed to save upload.');
                        }
                    }
                }
            }
        }
        header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/options/appearance.php'));
        exit;
    }
}

// ---- Does a local avatar file exist? ----
$hasLocal = false;
foreach (allowed_mimes() as $ext) {
    if (is_file(avatars_dir() . "/{$uid}.{$ext}")) {
        $hasLocal = true;
        break;
    }
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — UHA Portal</title>
    <style>
        :root {
            --stage: #585858ff;
            --card: #0D1117;
            --card-border: #FFFFFF1A;
            --ink: #E8EEF5;
            --ink-soft: #95A3B4;
            --site-width: 1200px;
        }

        body {
            margin: 0;
            background: #202428;
            color: var(--ink);
            font: 14px/1.5 system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        }

        .site {
            width: var(--site-width);
            margin: 0 auto;
            min-height: 100vh;
        }

        .canvas {
            padding: 0 16px 40px;
        }

        .wrap {
            max-width: 1000px;
            margin: 20px auto;
        }

        .page-surface {
            margin: 12px 0 32px;
            padding: 16px 16px 24px;
            background: var(--stage);
            border-radius: 16px;
            box-shadow: inset 0 1px 0 #ffffff0d, 0 0 0 1px #ffffff0f;
            min-height: calc(100vh - 220px);
            color: #E8EEF5;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 28px;
            line-height: 1.15;
            letter-spacing: .2px;
        }

        .muted {
            color: var(--ink-soft);
        }

        .card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 16px;
            margin: 18px 0;
            box-shadow: inset 0 1px 0 #ffffff12;
        }

        .card h2 {
            margin: 0 0 10px;
            color: #DFE8F5;
        }

        .card p {
            margin: 0 0 10px;
            color: #CFE1F3;
        }

        .btn {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 10px;
            border: 1px solid #2F3F53;
            background: #1B2431;
            color: #E6EEF8;
            text-decoration: none;
        }

        .btn:hover {
            background: #223349;
            border-color: #3D5270;
        }

        .btn.small {
            padding: 4px 8px;
            font-size: 12px;
            line-height: 1;
        }

        a {
            color: #9CC4FF;
            text-decoration: none;
        }

        a:hover {
            color: #C8DDFF;
        }

        .toggle {
            display: inline-flex;
            border: 1px solid #2F3F53;
            border-radius: 10px;
            overflow: hidden;
        }

        .toggle button {
            background: #0f1420;
            color: #cfe3ff;
            border: 0;
            padding: 8px 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .toggle button.active {
            background: #185a9d;
            color: #fff;
        }

        .demo {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 8px;
        }

        .swatch {
            border-radius: 10px;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 62px;
            font-weight: 700;
            color: #E8EEF5;
        }

        @media (max-width:900px) {
            .demo {
                grid-template-columns: 1fr;
            }
        }

        .avatar-wrap {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .avatar-preview {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            overflow: hidden;
            border: 1px solid #ffffff26;
            background: #0b0f14;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #9fb8d4;
        }

        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .help {
            color: #93a6bb;
            font-size: 12px;
        }

        input[type=file] {
            color: #cfe3ff;
        }
    </style>
</head>

<body>
    <div class="site">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

        <div class="canvas">
            <div class="wrap">
                <div class="page-surface">
                    <?php foreach (flash_take() as $f): ?>
                        <div class="card" style="border-color: <?= $f['t'] === 'error' ? '#ff5c5c66' : '#4caf5066' ?>;">
                            <strong
                                style="display:block;margin-bottom:6px;"><?= htmlspecialchars(strtoupper($f['t'])) ?></strong>
                            <div><?= htmlspecialchars($f['m']) ?></div>
                        </div>
                    <?php endforeach; ?>

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
                        <p>Upload a square image (JPG/PNG/WEBP). Max 2 MB, 1024×1024.</p>
                        <div class="avatar-wrap">
                            <div class="avatar-preview">
                                <img src="../assets/avatar.php?u=<?= (int) $uid ?>&s=84&v=<?= time() ?>"
                                    alt="Your avatar">
                            </div>

                            <form method="post" enctype="multipart/form-data"
                                style="display:flex; gap:10px; align-items:center;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                <input type="hidden" name="action" value="upload">
                                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
                                <button class="btn" type="submit">Upload</button>
                            </form>

                            <?php if ($hasLocal): ?>
                                <form method="post" onsubmit="return confirm('Remove your local avatar?');">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <button class="btn" type="submit">Remove</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <p class="help" style="margin-top:8px;">Files saved under
                            <code>/uploads/avatars/&lt;id&gt;.(jpg|png|webp)</code>. The preview is served via
                            <code>/assets/avatar.php?u=&lt;id&gt;</code> (used by chat/messaging too).</p>
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