<?php
// /assets/avatar.php — serves user avatars for chat/messages & previews.
// Usage: /assets/avatar.php?u=123&s=84
// Looks in /uploads/<id>.(jpg|png|webp) and /uploads/avatars/<id>.(ext).
// Falls back to an SVG with initials if no file found.

declare(strict_types=1);

// Params
$uid  = (int)($_GET['u'] ?? 0);
$size = (int)($_GET['s'] ?? 64);
if ($size < 16)  $size = 16;
if ($size > 256) $size = 256;

$root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');

$paths = [
    $root . '/uploads/avatars/' . $uid . '.jpg',
    $root . '/uploads/avatars/' . $uid . '.png',
    $root . '/uploads/avatars/' . $uid . '.webp',
];

// Serve file if present
foreach ($paths as $p) {
    if (is_file($p)) {
        $ext  = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg'
              : ($ext === 'png' ? 'image/png' : 'image/webp');
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=600'); // 10 min
        readfile($p);
        exit;
    }
}

// No file — fallback SVG with initials
$name = 'User';

// Optional: try DB for user name if your stack exposes get_db()
try {
    if ($uid > 0 && function_exists('get_db')) {
        $db = get_db();
        if ($db instanceof mysqli) {
            if ($stmt = $db->prepare('SELECT `name` FROM `users` WHERE `id`=? LIMIT 1')) {
                $stmt->bind_param('i', $uid);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    if ($row = $res->fetch_assoc()) $name = (string)$row['name'];
                }
                $stmt->close();
            }
        }
    }
} catch (Throwable $e) {
    // ignore; we'll use default "User"
}

$initials = strtoupper(substr(trim($name), 0, 2));

// Precompute numbers (no math in the heredoc)
$font  = (int)round($size * 0.42);
$cx    = (string)($size / 2);
$cy    = (string)($size / 2);
$r     = (string)($size / 2);
$textY = (string)($size * 0.54); // nudged baseline for better visual centering

// Colors
$bg = '#1B2431';
$fg = '#9fb8d4';

// Build SVG (no expressions inside the heredoc)
$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="$size" height="$size" viewBox="0 0 $size $size">
  <rect width="$size" height="$size" fill="$bg" rx="$r" ry="$r"/>
  <text x="$cx" y="$textY" font-family="system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif"
        font-size="$font" font-weight="800" text-anchor="middle" fill="$fg">$initials</text>
</svg>
SVG;

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: private, max-age=300');
echo $svg;
