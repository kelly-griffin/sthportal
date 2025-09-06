# UHA Portal — Page Audit Checklist

Use this template for **every page** in the site map.  
Copy → paste → fill → mark status (Not Started / In Progress / Done).

---

**Page:** `_____`  
**Route:** Public | Admin | API  
**URL(s):** `_____` (e.g., `news.php`, `/admin/news.php`, `/api/news.php`)  
**Owner/Status:** Not Started / In Progress / Done

## 1) Purpose
- One sentence: what this page does and who it’s for.

## 2) Includes & Guards
- Bootstrap: `require_once __DIR__ . '/../includes/bootstrap.php';`
- Admin guard (if admin): `require_once __DIR__ . '/../includes/license_guard.php';`
- UI includes (if public): `leaguebar.php`, `topbar.php`

## 3) Assets (in `<head>`)
```php
<?php require_once __DIR__ . '/includes/head-assets.php'; ?>
