<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';  // get_db()

/** @var mysqli $db */
$db = get_db();

// Inputs
$uid  = max(0, (int)($_GET['u'] ?? 0));
$size = max(16, min(256, (int)($_GET['s'] ?? 64))); // clamp 16–256

// Lookup the user's stored avatar path (relative), e.g. "uploads/avatars/abc123.jpg"
$rel = '';
if ($uid > 0) {
    if ($stmt = $db->prepare('SELECT avatar_path FROM users WHERE id=? LIMIT 1')) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->bind_result($rel);
        $stmt->fetch();
        $stmt->close();
    }
}

// Resolve to filesystem
$full = $rel ? app_path($rel) : '';
if (!$rel || !is_file($full)) {
    // Fallback image (put a 256x256 neutral PNG here)
    $fallback = app_path('assets/img/avatar-placeholder.png');
    if (is_file($fallback)) {
        $full = $fallback;
    } else {
        // Last resort: 404 (keep it simple)
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'no avatar';
        exit;
    }
}

// Basic cache headers
$mtime = filemtime($full) ?: time();
header('Cache-Control: public, max-age=86400, must-revalidate');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

// Content type
$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$ct  = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
][$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $ct);

// NOTE: If you want actual resizing to $size, plug in GD/ImageMagick here.
// For now we serve the original; callers can request ?s=… just for cache keys.
readfile($full);


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
