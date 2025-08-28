<?php
// admin/admin-header.php — shared admin top bar + security banner (idempotent)

if (!defined('ADMIN_HEADER_RENDERED')) {
  define('ADMIN_HEADER_RENDERED', 1);

  @require_once __DIR__ . '/../includes/config.php';
  @require_once __DIR__ . '/../includes/session-guard.php';
  require_once __DIR__ . '/../includes/security-headers.php';
  require_once __DIR__ . '/../includes/toast-center.php';

  // Build site/admin links
  $siteUrl = defined('SITE_URL') ? (string) SITE_URL : '/index.php';
  $parsed = @parse_url($siteUrl);
  $path = isset($parsed['path']) ? $parsed['path'] : '';
  if ($path === '' || substr($path, -1) === '/')
    $siteUrl = rtrim($siteUrl, '/') . '/index.php';

  $adminHome = 'index.php';
  $logoutUrl = 'logout.php';
  $auditUrl = 'audit-log.php';
  $attemptsUrl = 'login-attempts.php';
  $tempBansUrl = 'temp-bans.php';
  $usersUrl = 'users.php';
  $licensesUrl = 'licenses.php';
  $devlogUrl = 'devlog.php';
  $admin2faUrl = 'admin-2fa-setup.php';
  $acctLocksUrl = 'account-locks.php';
  $admin2faCodesUrl = 'admin-2fa-codes.php';
  $altRadarUrl = 'alt-radar.php';

  if (function_exists('session_guard_boot'))
    session_guard_boot();

  $script = strtolower(basename($_SERVER['SCRIPT_NAME'] ?? ''));
  $pagesWithOwnBanner = [
    'user-security.php',
    'user_security.php'
  ];
  $skipBanner = in_array($script, $pagesWithOwnBanner, true);

  if (!isset($GLOBALS['__SG_BANNER_PRINTED']))
    $GLOBALS['__SG_BANNER_PRINTED'] = false;
  ?>
  <style>
    /* High-contrast sticky admin bar */
    .adminbar {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      padding: 8px 10px;
      position: sticky;
      top: 0;
      z-index: 1000;
      border-bottom: 1px solid #ff6565ff;
      background: #fff3cd;
      color: #332701;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      transition: box-shadow .2s ease;
    }

    .adminbar.is-stuck {
      box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
    }

    .adminbar a {
      color: #332701;
      font-weight: bold;
      text-decoration: none;
    }

    .adminbar a:hover {
      text-decoration: underline;
    }

    .adminbar .sep {
      color: #ff6565ff;
    }

    .sg-banner~.sg-banner {
      display: none !important;
    }

    /* spacer to push right-side controls */
    .flex-spacer {
      margin-left: auto;
    }

    /* ENV badge */
    .admin-env {
      font-size: .85rem;
      padding: .2rem .55rem;
      line-height: 1;
      border-radius: 999px;
      border: 1px solid;
      letter-spacing: .02em;
      user-select: none;
    }

    .admin-env.dev {
      background: #e7f1ff;
      color: #122b42;
      border-color: #9ac3ff;
    }

    .admin-env.staging {
      background: #fff3cd;
      color: #332701;
      border-color: #ffe69c;
    }

    .admin-env.prod {
      background: #e6ffed;
      color: #003a17;
      border-color: #bff2c9;
    }

    /* Help overlay */
    .admin-help-overlay {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, .45);
      z-index: 1001;
      padding: 12px;
    }

    .admin-help-sheet {
      background: #fff;
      color: #222;
      border: 1px solid #ffe69c;
      border-radius: 12px;
      box-shadow: 0 12px 30px rgba(0, 0, 0, .18);
      max-width: 720px;
      width: calc(100% - 32px);
      padding: 16px 18px;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }

    .admin-help-sheet h3 {
      margin: 0 0 8px;
      font-size: 1.1rem;
    }

    .admin-help-sheet table {
      width: 100%;
      border-collapse: collapse;
    }

    .admin-help-sheet th,
    .admin-help-sheet td {
      padding: 6px 8px;
      border-bottom: 1px solid #eee;
      text-align: left;
    }

    .admin-help-sheet kbd {
      background: #f7f7f7;
      border: 1px solid #ddd;
      border-bottom-color: #ccc;
      border-radius: 6px;
      padding: .1rem .4rem;
      font-family: ui-monospace, Menlo, Consolas, monospace;
      font-size: .9em;
    }

    .admin-help-close {
      float: right;
      border: 1px solid #ddd;
      background: #fff;
      border-radius: 8px;
      padding: .25rem .5rem;
      cursor: pointer;
    }
  </style>

  <script>
    // Mark the page as admin so we can safely style everything under this root
    document.documentElement.classList.add('admin-ui');
  </script>

  <!-- SINGLE GLOBAL COMPACT YELLOW THEME (no duplicates) -->
  <style id="admin-compact-yellow">
    :root {
      --admin-line: #ddd;
      --admin-muted: #667;
      --btn-bg: #fff3cd;
      --btn-text: #2b2300;
      --btn-border: #e6cf66;
      --btn-bg-hover: #fff1a8;
      --btn-border-hover: #e0c143;
      --btn-focus: #b58b00;

      --btn-py: .22rem;
      --btn-px: .5rem;
      --btn-radius: .4rem;
      --btn-minh: 26px;
      --icon-size: 24px;
      --icon-svg: 14px;
      --chip-fs: .72rem;
      --chip-py: .06rem;
      --chip-px: .38rem;
      --field-py: .28rem;
      --field-px: .48rem;
    }

    /* Buttons (covers <a class="btn">, .copybtn, and native buttons/inputs) */
    .admin-ui .btn,
    .admin-ui .copybtn,
    .admin-ui button,
    .admin-ui input[type="submit"],
    .admin-ui input[type="button"],
    .admin-ui input[type="reset"] {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: var(--btn-py) var(--btn-px);
      min-height: var(--btn-minh);
      border: 1px solid var(--btn-border) !important;
      border-radius: var(--btn-radius);
      background: var(--btn-bg) !important;
      color: var(--btn-text) !important;
      text-decoration: none;
      font-weight: 600;
      font-size: .9rem;
      line-height: 1;
      cursor: pointer;
      transition: background .12s ease, border-color .12s ease;
    }

    .admin-ui .btn:hover,
    .admin-ui .copybtn:hover,
    .admin-ui button:hover,
    .admin-ui input[type="submit"]:hover,
    .admin-ui input[type="button"]:hover,
    .admin-ui input[type="reset"]:hover {
      background: var(--btn-bg-hover) !important;
      border-color: var(--btn-border-hover) !important;
    }

    .admin-ui .btn:focus-visible,
    .admin-ui .copybtn:focus-visible,
    .admin-ui button:focus-visible {
      outline: 2px solid var(--btn-focus);
      outline-offset: 2px;
    }

    .admin-ui .btn[disabled],
    .admin-ui .copybtn[disabled],
    .admin-ui button[disabled],
    .admin-ui input[disabled] {
      opacity: .55;
      pointer-events: none;
    }

    /* Clamp SVGs so action icons don’t balloon */
    .admin-ui .btn svg,
    .admin-ui .copybtn svg,
    .admin-ui button svg {
      width: var(--icon-svg) !important;
      height: var(--icon-svg) !important;
      flex: 0 0 auto;
    }

    /* Icon buttons (edit/lock/plus etc.) */
    .admin-ui .iconbtn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: var(--icon-size);
      height: var(--icon-size);
      border: 1px solid var(--btn-border) !important;
      border-radius: var(--btn-radius);
      background: var(--btn-bg) !important;
      color: var(--btn-text) !important;
      text-decoration: none;
      vertical-align: middle;
      transition: background .12s ease, border-color .12s ease;
    }

    .admin-ui .iconbtn:hover {
      background: var(--btn-bg-hover) !important;
      border-color: var(--btn-border-hover) !important;
    }

    .admin-ui .iconbtn svg {
      width: var(--icon-svg) !important;
      height: var(--icon-svg) !important;
    }

    .admin-ui .iconbtn.disabled {
      opacity: .45;
      pointer-events: none;
    }

    .admin-ui .iconbtn.red {
      color: #a11212;
      border-color: #f3b4b4;
    }

    .admin-ui .iconbtn.green {
      color: #0a7d2f;
      border-color: #bfe6bf;
    }

    /* Filters / Filterbar: consistent non-stacking layout */
    .admin-ui .filters,
    .admin-ui .filterbar {
      display: flex !important;
      flex-wrap: wrap !important;
      align-items: flex-end !important;
      gap: .5rem !important;
    }

    /* Works whether fields are wrapped in <div> or placed directly */
    .admin-ui .filters>div,
    .admin-ui .filterbar>div {
      display: flex !important;
      align-items: center !important;
      gap: .35rem !important;
    }

    .admin-ui .filters>*:not(div),
    .admin-ui .filterbar>*:not(div) {
      /* if fields are placed directly under the form, keep them aligned too */
      align-self: flex-end;
    }

    /* Kill per-page label {display:block} that caused stacking */
    .admin-ui .filters label,
    .admin-ui .filterbar label {
      display: inline-block !important;
      margin: 0 .35rem 0 0 !important;
      color: var(--admin-muted) !important;
      font-size: .92rem !important;
    }

    /* Inputs/selects in filter rows (cover common types) */
    .admin-ui .filters input[type="text"],
    .admin-ui .filters input[type="search"],
    .admin-ui .filters input[type="number"],
    .admin-ui .filters input[type="email"],
    .admin-ui .filters input[type="date"],
    .admin-ui .filters input[type="time"],
    .admin-ui .filters input[type="datetime-local"],
    .admin-ui .filters select,
    .admin-ui .filterbar input[type="text"],
    .admin-ui .filterbar input[type="search"],
    .admin-ui .filterbar input[type="number"],
    .admin-ui .filterbar input[type="email"],
    .admin-ui .filterbar input[type="date"],
    .admin-ui .filterbar input[type="time"],
    .admin-ui .filterbar input[type="datetime-local"] .admin-ui .filterbar select {
      padding: var(--field-py) var(--field-px) !important;
      border: 1px solid var(--btn-border) !important;
      border-radius: 6px !important;
      background: #fff !important;
      font-size: .95rem !important;
      vertical-align: middle;
      min-width: 150px;
    }


    .admin-ui .filters input[type="text"],
    .admin-ui .filters input[type="search"],
    .admin-ui .filterbar input[type="text"],
    .admin-ui .filterbar input[type="search"] {
      max-width: 260px;
    }

    /* Kill page-level full-width filter controls so they don't stack */
    .admin-ui .filters input[type="text"],
    .admin-ui .filters input[type="search"],
    .admin-ui .filters select,
    .admin-ui .filterbar input[type="text"],
    .admin-ui .filterbar input[type="search"],
    .admin-ui .filterbar select {
      width: auto !important;
      /* <- cancel width:100% */
      max-width: unset !important;
      /* <- don't stretch */
      flex: 0 0 auto !important;
      /* <- size to content in the flex row */
    }

    /* Chips (counts etc.) */
    .admin-ui .chips {
      display: flex;
      flex-wrap: wrap;
      gap: .22rem;
    }

    .admin-ui .chip {
      display: inline-block;
      padding: var(--chip-py) var(--chip-px);
      border: 1px solid #ead9a6;
      border-radius: 999px;
      background: #fffbe6;
      font-size: var(--chip-fs);
      color: #5a4a00;
    }

    /* Toggle groups */
    .admin-ui .view-toggle {
      display: inline-flex;
      border: 1px solid var(--btn-border) !important;
      border-radius: .45rem;
      overflow: hidden;
      background: var(--btn-bg) !important;
    }

    .admin-ui .view-toggle a {
      padding: .26rem .48rem;
      text-decoration: none;
      color: var(--btn-text) !important;
      background: var(--btn-bg) !important;
      font-weight: 600;
      font-size: .9rem;
    }

    .admin-ui .view-toggle a.active {
      background: #ffef99 !important;
      border-left: 1px solid var(--btn-border-hover) !important;
      border-right: 1px solid var(--btn-border-hover) !important;
    }

    @media (prefers-contrast: more) {

      .admin-ui .btn,
      .admin-ui .copybtn,
      .admin-ui button,
      .admin-ui .iconbtn {
        background: #fff;
        color: #111;
        border-color: #222;
      }

      .admin-ui .btn:focus-visible,
      .admin-ui .copybtn:focus-visible,
      .admin-ui button:focus-visible,
      .admin-ui .iconbtn:focus-visible {
        outline: 3px solid #111;
      }
    }
  </style>

  <nav class="adminbar">
    <a href="<?= htmlspecialchars($adminHome) ?>">🏠 Admin Home</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($usersUrl) ?>">👥 Users</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($licensesUrl) ?>">🧾 Licenses</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($auditUrl) ?>">📝 Audit Log</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($attemptsUrl) ?>">🛡️ Login Attempts</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($altRadarUrl) ?>" title="CB Alt Radar">📡 Radar</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($tempBansUrl) ?>">🚫 Temp Bans</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($acctLocksUrl) ?>">🔒 Account Locks</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($devlogUrl) ?>">📰 Devlog</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($admin2faUrl) ?>">🔐 Admin 2FA</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($admin2faCodesUrl) ?>">🧾 2FA Backup Codes</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($siteUrl) ?>">🔙 Back to Site</a>
    <span class="sep">|</span>
    <a href="<?= htmlspecialchars($logoutUrl) ?>">🚪 Logout</a>

    <span class="flex-spacer"></span>
    <a href="#" id="adminHelpOpen">❓ Help</a>
    <?php
    // ENV badge
    $APP_ENV = 'DEV';
    if (defined('APP_ENV'))
      $APP_ENV = strtoupper((string) APP_ENV);
    elseif (!empty($config['env']))
      $APP_ENV = strtoupper((string) $config['env']);
    elseif (getenv('APP_ENV'))
      $APP_ENV = strtoupper((string) getenv('APP_ENV'));
    $envClass = strtolower($APP_ENV);
    if ($envClass === 'production') {
      $envClass = 'prod';
      $APP_ENV = 'PROD';
    }
    if (!in_array($envClass, ['dev', 'staging', 'prod'], true)) {
      $envClass = 'dev';
      $APP_ENV = 'DEV';
    }
    ?>
    <span class="admin-env <?= $envClass ?>" title="Environment">ENV: <?= htmlspecialchars($APP_ENV) ?></span>
  </nav>

  <!-- Help overlay -->
  <div class="admin-help-overlay" id="adminHelp" aria-hidden="true">
    <div class="admin-help-sheet" role="dialog" aria-modal="true" aria-labelledby="helpTitle">
      <button class="admin-help-close" type="button" id="adminHelpClose">Close</button>
      <h3 id="helpTitle">Keyboard Shortcuts</h3>
      <table>
        <thead>
          <tr>
            <th style="width:220px;">Shortcut</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><kbd>/</kbd></td>
            <td>Focus search</td>
          </tr>
          <tr>
            <td><kbd>[</kbd> / <kbd>]</kbd></td>
            <td>Prev / Next page</td>
          </tr>
          <tr>
            <td><kbd>e</kbd></td>
            <td>Export CSV (if available)</td>
          </tr>
          <tr>
            <td><kbd>d</kbd></td>
            <td>New Devlog Entry</td>
          </tr>
          <tr>
            <td><kbd>g</kbd> then <kbd>u</kbd>/<kbd>l</kbd>/<kbd>a</kbd></td>
            <td>Go Users / Licenses / Audit</td>
          </tr>
          <tr>
            <td><kbd>r</kbd></td>
            <td>Reset filters</td>
          </tr>
          <tr>
            <td><kbd>?</kbd> or <kbd>Shift</kbd>+<kbd>/</kbd></td>
            <td>Open this help</td>
          </tr>
          <tr>
            <td><kbd>Esc</kbd></td>
            <td>Close help / cancel quick-nav</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Shortcuts + sticky + filter memory -->
  <script>
    (function () {
      function $(s) { return document.querySelector(s); }
      function focusSearch() {
        var el = document.querySelector('.filters input[name="q"]')
          || document.querySelector('.filters input[type="search"]')
          || document.querySelector('.filters input[type="text"]');
        if (el) { el.focus(); if (el.select) el.select(); }
      }
      function clickIf(sel) { var a = $(sel); if (a) { a.click(); return true; } return false; }
      function hasTextFocus(e) {
        var tag = (e.target.tagName || '').toLowerCase();
        return /^(input|textarea|select)$/.test(tag) || e.target.isContentEditable;
      }

      var help = $('#adminHelp'), helpClose = $('#adminHelpClose');
      function openHelp() { if (help) { help.style.display = 'flex'; help.setAttribute('aria-hidden', 'false'); } }
      function closeHelp() { if (help) { help.style.display = 'none'; help.setAttribute('aria-hidden', 'true'); } }
      window.openAdminHelp = openHelp;
      (function () { var a = $('#adminHelpOpen'); if (a) a.addEventListener('click', function (e) { e.preventDefault(); openHelp(); }); })();
      if (helpClose) helpClose.addEventListener('click', function () { closeHelp(); });

      var chord = null, chordTimer = 0;
      function startChord() { chord = 'g'; clearTimeout(chordTimer); chordTimer = setTimeout(endChord, 1250); }
      function endChord() { chord = null; clearTimeout(chordTimer); }

      (function () {
        var bar = document.querySelector('.adminbar'); if (!bar) return;
        function onScroll() { bar.classList.toggle('is-stuck', window.scrollY > 4); }
        onScroll(); window.addEventListener('scroll', onScroll);
      })();

      document.addEventListener('keydown', function (e) {
        if (e.defaultPrevented) return;

        if (e.key === 'Escape') {
          if (help && help.style.display === 'flex') { e.preventDefault(); closeHelp(); return; }
          if (chord) { e.preventDefault(); endChord(); return; }
        }

        if (e.key === '?' || (e.shiftKey && e.key === '/')) {
          e.preventDefault();
          if (help && help.style.display !== 'flex') openHelp(); else closeHelp();
          return;
        }

        if (e.key === '/' && !hasTextFocus(e)) { e.preventDefault(); focusSearch(); return; }
        if (hasTextFocus(e)) return;

        if (e.key.toLowerCase() === 'e') { e.preventDefault(); if (clickIf('a.btn[href*="export=csv"], a[href*="export=csv"]')) return; }

        if (e.key.toLowerCase() === 'd') {
          var path = (location.pathname || '').toLowerCase();
          if (path.indexOf('devlog.php') !== -1) {
            e.preventDefault();
            var back = encodeURIComponent(location.href);
            window.location = 'devlog-edit.php?back=' + back;
            return;
          }
        }

        if (e.key.toLowerCase() === 'n') {
          var path = (location.pathname || '').toLowerCase();
          var back = encodeURIComponent(location.href);
          if (path.indexOf('users.php') !== -1) { window.location = 'user-edit.php?back=' + back; return; }
          if (path.indexOf('licenses.php') !== -1) { window.location = 'license-edit.php?back=' + back; return; }
        }

        if (e.key.toLowerCase() === 'r') {
          e.preventDefault();
          var resetLink = document.querySelector('.filters a[href*="users.php"], .filters a[href*="licenses.php"], .filters a[href*="devlog.php"]');
          if (resetLink) { resetLink.click(); return; }
          try {
            var page = (location.pathname.split('/').pop() || 'index').toLowerCase();
            var KEY = 'admin:filters:' + page;
            localStorage.removeItem(KEY);
          } catch (e) { }
          location.href = location.pathname;
          return;
        }

        var k = (e.key || '').toLowerCase();
        if (k === 'g') { e.preventDefault(); startChord(); return; }
        if (chord === 'g') {
          e.preventDefault();
          var dest = null;
          if (k === 'u') dest = 'users.php';
          else if (k === 'l') dest = 'licenses.php';
          else if (k === 'a') dest = 'audit-log.php';
          if (dest) { endChord(); window.location = dest; return; }
        }
      });

      window.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.filters'); if (!form) return;

        var page = (location.pathname.split('/').pop() || 'index').toLowerCase();
        var KEY = 'admin:filters:' + page;
        var EPHEMERAL = new Set(['msg', 'err', 'export']);

        function effParams() {
          var p = new URLSearchParams(location.search);
          EPHEMERAL.forEach(function (k) { p.delete(k); });
          return p;
        }
        function count(p) { var i = 0; p.forEach(function () { i++; }); return i; }
        function toObj(params) { return Object.fromEntries(params.entries()); }
        function fromObj(obj) { var p = new URLSearchParams(); for (var k in obj) if (Object.hasOwn(obj, k)) p.set(k, obj[k]); return p; }

        function collectForm() {
          var o = {};
          form.querySelectorAll('input[name], select[name]').forEach(function (el) {
            if (EPHEMERAL.has(el.name)) return;
            o[el.name] = el.value;
          });
          return o;
        }

        function loadState() {
          try {
            var raw = localStorage.getItem(KEY);
            if (!raw) return null;
            var s = JSON.parse(raw);
            if (!s || typeof s !== 'object') { localStorage.removeItem(KEY); return null; }
            s.params = s.params && typeof s.params === 'object' ? s.params : {};
            s.form = s.form && typeof s.form === 'object' ? s.form : {};
            EPHEMERAL.forEach(function (k) { delete s.params[k]; delete s.form[k]; });
            return s;
          } catch (e) { return null; }
        }
        function saveState(mut) {
          try {
            var s = loadState() || { params: {}, form: {} };
            mut(s);
            localStorage.setItem(KEY, JSON.stringify(s));
          } catch (e) { }
        }

        (function () {
          var s = loadState(); if (!s || !s.params) return;
          var cur = effParams(), saved = new URLSearchParams(fromObj(s.params));
          if (cur.toString() !== saved.toString()) {
            var u = new URL(location.href);
            ['msg', 'err', 'export'].forEach(function (k) { u.searchParams.delete(k); });
            saved.forEach(function (v, k) { u.searchParams.set(k, v); });
            history.replaceState(null, '', u.pathname + (u.search ? u.search : ''));
          }
        })();

        (function () {
          var s = loadState(); if (!s || !s.form) return;
          for (var k in s.form) if (Object.hasOwn(s.form, k)) {
            var el = form.querySelector('[name="' + k + '"]');
            if (!el) continue;
            el.value = s.form[k];
          }
        })();

        function storeParamsIfReal() {
          var eff = effParams();
          if (count(eff) > 0) saveState(function (s) { s.params = toObj(eff); });
        }
        storeParamsIfReal();
        window.addEventListener('popstate', storeParamsIfReal);

        var t = null;
        function saveFormDebounced() {
          clearTimeout(t);
          t = setTimeout(function () { saveState(function (s) { s.form = collectForm(); }); }, 150);
        }
        form.addEventListener('input', saveFormDebounced, true);
        form.addEventListener('change', saveFormDebounced, true);
        window.addEventListener('beforeunload', function () { try { saveState(function (s) { s.form = collectForm(); }); } catch (e) { } });

        document.querySelectorAll('.filters a[href]').forEach(function (a) {
          var href = (a.getAttribute('href') || '').toLowerCase();
          var file = page;
          var target = href.replace(/^\.\/+/, '').replace(/^\/+/, '').split('?')[0];
          if (target === file) a.addEventListener('click', function () { try { localStorage.removeItem(KEY); } catch (e) { } });
        });
      });
    })();
  </script>

  <?php
  if (!$skipBanner && function_exists('session_banner_html')) {
    if (!$GLOBALS['__SG_BANNER_PRINTED']) {
      if (function_exists('session_banner_html_once'))
        echo session_banner_html_once();
      else
        echo session_banner_html();
      $GLOBALS['__SG_BANNER_PRINTED'] = true;
    }
  }
}
