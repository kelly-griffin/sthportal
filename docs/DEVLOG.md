# Devlog

## 2025-09-05
- Consolidated URL helpers into `functions.php` (`asset`, `url_root`, `u`, `$BASE` shim).
- Slimmed `bootstrap.php` (no output/guards; provides `get_db(): mysqli`).
- Admin now includes `admin/_admin_bootstrap.php` which loads `license_guard.php`.
- News API wired to mysqli and root-relative links.
