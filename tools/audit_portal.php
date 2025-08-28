<?php
// /sthportal/tools/audit_portal.php
// Read-only browser audit: finds pages missing bootstrap, leading output before <?php,
// bare constants (DB_*, LICENSE_KEY, RECAPTCHA_*), and auth/admin pages missing bootstrap.

declare(strict_types=1);

// Don't include bootstrap here (avoid license guard redirects)
$ROOT = realpath(__DIR__ . '/..');
chdir($ROOT);

$skipDirs = ['vendor','assets','data','node_modules','.git','.idea','.vscode'];
$issues = [
  'no_bootstrap'     => [],
  'leading_output'   => [],
  'bare_constants'   => [],
  'auth_pages'       => [],
  'recaptcha_calls'  => [],
];

$rxBootstrapAny = '#require_once\s+(?:__DIR__\s*\.\s*)?[\'"][./]*includes/bootstrap\.php[\'"]#i';
$rxBareConst    = '/\b(DB_HOST|DB_USER|DB_PASS|DB_NAME|DB_DSN|LICENSE_KEY|RECAPTCHA_(SITE|SECRET)_KEY)\b/';
$rxRecaptchaFn  = '/\brecaptcha_(enabled|site_key|secret|verify|verify_post)\b/';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
  $path = $file->getPathname();
  if (substr($path, -4) !== '.php') continue;
  $rel = str_replace($ROOT . DIRECTORY_SEPARATOR, '', $path);

  // skip directories
  $top = explode(DIRECTORY_SEPARATOR, $rel)[0] ?? '';
  if (in_array($top, $skipDirs, true)) continue;

  $src = @file_get_contents($path);
  if ($src === false) continue;

  // has bootstrap?
  $hasBootstrap = (bool)preg_match($rxBootstrapAny, $src);
  if (!$hasBootstrap) $issues['no_bootstrap'][] = $rel;

  // leading output before first <?php ?
  $leading = false;
  $trim = ltrim($src);
  if (strpos($trim, '<?php') !== 0) $leading = true;
  if ($leading) $issues['leading_output'][] = $rel;

  // bare constants
  if (preg_match($rxBareConst, $src)) $issues['bare_constants'][] = $rel;

  // auth/admin entry points missing bootstrap
  if (preg_match('#(^|/)login\.php$#i', $rel) || preg_match('#(^|/)logout\.php$#i', $rel) || $rel === 'admin/index.php') {
    if (!$hasBootstrap) $issues['auth_pages'][] = $rel;
  }

  // recaptcha functions without bootstrap
  if (preg_match($rxRecaptchaFn, $src) && !$hasBootstrap) $issues['recaptcha_calls'][] = $rel;
}

function section(string $title, array $items) {
  echo '<h2>'.$title.' <small>(' . count($items) . ')</small></h2>';
  if (!$items) { echo '<p class="ok">None ✅</p>'; return; }
  echo '<ul>';
  foreach ($items as $i) echo '<li><code>'.htmlspecialchars($i).'</code></li>';
  echo '</ul>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Portal Audit</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:2rem;color:#222}
    h1{margin:.2rem 0}
    h2{margin-top:1.6rem}
    code{background:#f6f6f6;padding:.15rem .35rem;border:1px solid #eee;border-radius:.25rem}
    .ok{color:#0c5132}
    .warn{color:#8a1020}
    .muted{color:#666}
    .card{border:1px solid #ddd;border-radius:.5rem;padding:1rem;margin:1rem 0}
  </style>
</head>
<body>
  <h1>Portal Audit</h1>
  <div class="muted">Root: <code><?= htmlspecialchars($ROOT) ?></code> · <?= date('Y-m-d H:i:s') ?></div>

  <div class="card">
    <?php section('Files missing includes/bootstrap.php', $issues['no_bootstrap']); ?>
    <?php section('Files with possible leading output before &lt;?php', $issues['leading_output']); ?>
    <?php section('Files touching bare constants (DB_*, LICENSE_KEY, RECAPTCHA_*)', $issues['bare_constants']); ?>
    <?php section('Auth/Admin entry points missing bootstrap', $issues['auth_pages']); ?>
    <?php section('Files calling recaptcha_* without bootstrap', $issues['recaptcha_calls']); ?>
  </div>

  <p class="muted">Tip: for each file above, add at the top:<br>
    <code>&lt;?php require_once __DIR__ . '/includes/bootstrap.php'; ?&gt;</code>
  </p>
</body>
</html>
