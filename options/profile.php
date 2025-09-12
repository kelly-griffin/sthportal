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
    <?php require_once __DIR__ . '/../includes/head-assets.php'; ?>
</head>

<body>
    <div class="site">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

        <div class="canvas">
            <div class="profile-container">
                <div class="profile-card">
                    <div class="page-surface">
                        <h1><?= h($title) ?></h1>
                        <p class="muted">Update your display name, email, password, and avatar. We’ll wire these saves
                            to
                            backend endpoints next.</p>

                        <!-- Card: Profile Info -->
                        <div class="card">
                            <h2>Profile</h2>
                            <form action="<?= h(u('options/profile.php')) ?>" method="post" autocomplete="off">
                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                <input type="hidden" name="do" value="update_profile">

                                <div class="form-row">
                                    <label for="display_name"><strong>Display Name</strong></label>
                                    <input id="display_name" name="display_name" type="text"
                                        value="<?= h($displayName) ?>" placeholder="e.g., Kelly J.">
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
                            <form action="<?= h(u('options/profile.php')) ?>" method="post"
                                enctype="multipart/form-data">
                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                <input type="hidden" name="do" value="upload_avatar">

                                <div class="form-row avatar">
                                    <div>
                                        <img src="<?= h($avatarUrl ?: 'https://placehold.co/128x128?text=Avatar') ?>"
                                            alt="Current avatar">
                                    </div>
                                    <div style="flex:1; min-width:280px;">
                                        <input type="file" name="avatar" accept="image/*">
                                        <p class="muted" style="margin:6px 0 0">PNG/JPG up to 2&nbsp;MB. Square images
                                            look
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
    </div>
</body>

</html>