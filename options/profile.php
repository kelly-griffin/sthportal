<?php
// /options/profile.php — Profile & Account (Options)
require_once __DIR__ . '/../includes/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE)
    @session_start();

// Gate: must be logged in
$user = $_SESSION['user'] ?? null;
if (!$user) {
    http_response_code(403);
    exit('Forbidden');
}

// CSRF token (forms POST here; backend wiring TBD)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

$displayName = (string) ($user['display_name'] ?? ($user['name'] ?? ''));
$email = (string) ($user['email'] ?? '');
$avatarUrl = (string) ($user['avatar_url'] ?? '');

$title = 'Profile & Account';
?>
<!doctype html>
<html lang="en">

<head>    
    <link rel="stylesheet" href="<?= h(u('css/nav.css')) ?>">
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
            background: transparent;
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
            font-size: 38px;
            margin: 8px 0 10px;
            color: #F2F6FF;
        }

        h2 {
            font-size: 18px;
            margin: 0 0 10px;
            color: #DFE8F5;
        }

        .muted {
            color: var(--ink-soft);
        }

        .card {
            background: #0D1117;
            border: 1px solid #FFFFFF1A;
            border-radius: 16px;
            padding: 16px;
            margin: 18px 0;
            color: inherit;
            box-shadow: inset 0 1px 0 #ffffff12;
        }

        .form-row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin: 8px 0;
        }

        .form-row label {
            flex: 0 0 180px;
        }

        .form-row input[type=text],
        .form-row input[type=email],
        .form-row input[type=password] {
            flex: 1;
            min-width: 280px;
            background: #0F1621;
            border: 1px solid #2F3F53;
            color: #E6EEF8;
            border-radius: 8px;
            padding: 6px 8px;
        }

        .form-row input::placeholder {
            color: #9FB0C2;
        }

        .form-row input:focus {
            outline: none;
            border-color: #6AA1FF;
            box-shadow: 0 0 0 2px #6AA1FF33;
        }

        .page-surface .btn {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 10px;
            border: 1px solid #2F3F53;
            background: #1B2431;
            color: #E6EEF8;
            text-decoration: none;
        }

        .page-surface .btn:hover {
            background: #223349;
            border-color: #3D5270;
        }

        .btn.primary {
            border-color: #3D6EA8;
        }

        .btn.danger {
            border-color: #D14;
        }

        .avatar {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar img {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #FFFFFF1A;
            background: #0B121A;
        }

        input[type=file] {
            background: #0F1621;
            border: 1px solid #2F3F53;
            color: #E6EEF5;
            border-radius: 8px;
            padding: 6px 8px;
        }

        input[type=file]::file-selector-button {
            margin-right: 8px;
            border: 1px solid #2F3F53;
            border-radius: 8px;
            background: #1B2431;
            color: #E6EEF5;
            padding: 6px 10px;
        }

        .page-surface a {
            color: #9CC4FF;
            text-decoration: none;
        }

        .page-surface a:hover {
            color: #C8DDFF;
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
                    <h1><?= h($title) ?></h1>
                    <p class="muted">Update your display name, email, password, and avatar. We’ll wire these saves to
                        backend endpoints next.</p>

                    <!-- Card: Profile Info -->
                    <div class="card">
                        <h2>Profile</h2>
                        <form action="<?= h(u('options/profile.php')) ?>" method="post" autocomplete="off">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                            <input type="hidden" name="do" value="update_profile">

                            <div class="form-row">
                                <label for="display_name"><strong>Display Name</strong></label>
                                <input id="display_name" name="display_name" type="text" value="<?= h($displayName) ?>"
                                    placeholder="e.g., Kelly J.">
                            </div>

                            <div class="form-row">
                                <label for="email"><strong>Email</strong></label>
                                <input id="email" name="email" type="email" value="<?= h($email) ?>"
                                    placeholder="you@example.com">
                            </div>

                            <div class="form-row" style="margin-top:12px;">
                                <button class="btn primary" type="submit">Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <!-- Card: Password -->
                    <div class="card">
                        <h2>Password</h2>
                        <form action="<?= h(u('options/profile.php')) ?>" method="post" autocomplete="off">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                            <input type="hidden" name="do" value="change_password">

                            <div class="form-row">
                                <label for="current_password"><strong>Current Password</strong></label>
                                <input id="current_password" name="current_password" type="password"
                                    placeholder="••••••••">
                            </div>
                            <div class="form-row">
                                <label for="new_password"><strong>New Password</strong></label>
                                <input id="new_password" name="new_password" type="password"
                                    placeholder="At least 8 characters">
                            </div>
                            <div class="form-row">
                                <label for="confirm_password"><strong>Confirm New</strong></label>
                                <input id="confirm_password" name="confirm_password" type="password"
                                    placeholder="Repeat new password">
                            </div>

                            <div class="form-row" style="margin-top:12px;">
                                <button class="btn primary" type="submit">Update Password</button>
                            </div>
                        </form>
                    </div>

                    <!-- Card: Avatar -->
                    <div class="card">
                        <h2>Avatar</h2>
                        <form action="<?= h(u('options/profile.php')) ?>" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                            <input type="hidden" name="do" value="upload_avatar">

                            <div class="form-row avatar">
                                <div>
                                    <img src="<?= h($avatarUrl ?: 'https://placehold.co/128x128?text=Avatar') ?>"
                                        alt="Current avatar">
                                </div>
                                <div style="flex:1; min-width:280px;">
                                    <input type="file" name="avatar" accept="image/*">
                                    <p class="muted" style="margin:6px 0 0">PNG/JPG up to 2&nbsp;MB. Square images look
                                        best (128×128).</p>
                                </div>
                            </div>

                            <div class="form-row" style="margin-top:12px;">
                                <button class="btn" type="submit">Upload Avatar</button>
                            </div>
                        </form>
                    </div>

                    <div class="card" style="display:none"><!-- Reserved for future: sessions, 2FA, etc. --></div>

                </div>
            </div>
        </div>
    </div>
</body>

</html>