# UHA Portal — Project Rules (Living Doc)

> If something here conflicts with code, the code is wrong. Fix the code or update this doc.

## 0) Golden rules
- **One DB accessor:** `get_db(): mysqli` (from `includes/bootstrap.php`). No PDO in shared code.
- **No output in bootstrap.** Pages and components own HTML.
- **URL helpers ONLY:** use `asset()` for assets and `url_root()` for internal links. `u()` and `$BASE` are shims.

## 1) File layout (top-level)
- `/includes/` — `bootstrap.php`, `config.php`, `functions.php`, guards, shared components (`leaguebar.php`, `topbar.php`).
- `/admin/` — all admin pages. Each one includes `admin/_admin_bootstrap.php` (which pulls license guard).
- `/api/` — JSON endpoints. **Never** include UI components here.
- `/media/` — public page variants (if any) that live in a subdir; they must use `../includes/bootstrap.php`.
- `/assets/` — css/js/img.
- `/docs/` — this folder (README, site-map, devlog).
- Everything else — public pages (home, news, standings…).

## 2) Bootstrap contract
```php
require_once __DIR__ . '/includes/bootstrap.php';
$db = get_db(); // mysqli

3) Helpers (use these; nothing else)
h($text)                        // escape HTML
asset('assets/css/global.css')  // assets (css/js/img)
url_root('news.php?page=2')     // internal links
u('path')                       // legacy shim -> url_root()
$BASE                           // legacy shim -> uha_base_url()


Never hardcode /sthportal/, /admin/, /api/, or use /../ in URLs.

4) Page skeleton (public)
<?php
require_once __DIR__ . '/includes/bootstrap.php';
$db = get_db();
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="<?= h(asset('assets/css/global.css')) ?>">
  <link rel="stylesheet" href="<?= h(asset('assets/css/nav.css')) ?>">
  <script defer src="<?= h(asset('assets/js/auto-logos.js')) ?>"></script>
</head>
<body>
  <?php require_once __DIR__ . '/includes/leaguebar.php'; ?>
  <?php require_once __DIR__ . '/includes/topbar.php'; ?>
  <!-- page content -->
</body>
</html>

5) Admin skeleton
<?php
require_once __DIR__ . '/_admin_bootstrap.php'; // includes license_guard
$db = get_db();
$mysqli = $db; // legacy alias if needed

6) API skeleton
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$db = get_db();
// build array...
echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

7) Data conventions

stories table backs News (commissioner/GM posts + auto-recaps).

Keys we use on pages: id, slug, title, summary, body, hero_image_url, team_code, status, published_at.

Team codes use UHA format (CBJ, MTL, …) — must match teams.json and auto-logos.js.

Dates: store UTC in DB; format in PHP. APIs emit ISO8601 (Y-m-dTH:i:sZ).

8) Styling & assets

Global CSS belongs to assets/css/global.css. Load it via asset().

Team logos live in /assets/img/logos/ and are swapped by assets/js/auto-logos.js.

News hero target region: 534×280 (cover).

9) Guards

license_guard.php is admin-only, included by admin/_admin_bootstrap.php.

Public pages and APIs do not include license guard.

10) Common mistakes checklist

☐ Did you use asset()/url_root() for every URL?

☐ Did you include ../includes/bootstrap.php from subdirs (/admin, /media, /api)?

☐ Are you echoing anything inside bootstrap? (Don’t.)

☐ Are you using get_db() (mysqli) instead of opening a new connection?

☐ When no id/slug is provided to article pages, do you redirect back to index?

11) Change discipline

Every change: backup → edit → test → log a one-liner in docs/devlog.md.

If you add or change a helper or convention, update this README immediately.