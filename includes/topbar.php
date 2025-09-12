<?php
// topbar.php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php'; // safe, no output

$tabs = [
  'splash' => [
    'label' => 'Splash',
    'href'  => 'index.php',
  ],
  'leagues' => [
    'label' => 'Leagues',
    'href'  => 'leagues.php',
    'children' => [
      ['type' => 'group', 'label' => 'Leagues'],
      ['label' => 'Pro League (NHL)',   'href' => 'teams.php'],
      ['label' => 'Farm League (AHL)',  'href' => 'teams.php'],
      ['label' => 'Development League (ECHL)', 'href' => '#'],
      ['label' => 'International (Multiple)',  'href' => '#'],
      ['label' => 'Junior Leagues (Multiple)', 'href' => '#'],
    ],
  ],
  'players' => [
    'label' => 'Players',
    'href'  => 'players.php',
    'children' => [
      ['type' => 'group', 'label' => 'Players'],
      ['label' => 'All Players',    'href' => '#'],
      ['label' => 'Free Agents',    'href' => '#'],
      ['label' => 'Waiver Wire',    'href' => '#'],
      ['label' => 'Prospect List',  'href' => '#'],
      ['label' => 'Compare Players','href' => '#'],
    ],
  ],
  'front-office' => [
    'label' => 'Front Office',
    'href'  => 'front-office.php',
    'children' => [
      ['type' => 'group', 'label' => 'Front Office'],
      ['label' => 'Team Dashboard',    'href' => '#'],
      ['label' => 'Roster Management', 'href' => '#'],
      ['label' => 'Lines & Strategy',  'href' => '#'],
      ['label' => 'Depth Charts',      'href' => '#'],
      ['label' => 'Personnel Changes', 'href' => '#'],
      ['label' => 'Financial Management', 'href' => '#'],
      ['label' => 'Scouting Assignments', 'href' => '#'],
      ['label' => 'Cap Management Tools', 'href' => '#'],
      ['label' => 'Upload Lines',         'href' => '#'],
    ],
  ],
  'tournaments' => [
    'label' => 'Tournaments',
    'href'  => 'tournaments.php',
    'children' => [
      ['type' => 'group', 'label' => 'Tournaments'],
      ['label' => 'World Cup of Hockey', 'href' => '#'],
      ['label' => 'Olympics',            'href' => '#'],
      ['label' => 'World Juniors',       'href' => '#'],
      ['label' => 'IIHF Worlds',         'href' => '#'],
    ],
  ],
  'media' => [
    'label' => 'Media',
    'href'  => 'media-hub.php',
    'children' => [
      ['type' => 'group', 'label' => 'Media'],
      ['label' => 'Media Hub',        'href' => 'media-hub.php'],
      ['label' => 'News',             'href' => 'media/news.php'],
      ['label' => 'Press Releases',   'href' => 'media/press-releases.php'],
      ['label' => 'Weekly Recaps',    'href' => 'media/weekly-recaps.php'],
      ['label' => 'Power Rankings',   'href' => 'media/power-rankings.php'],
      ['label' => 'Player of the Week','href' => 'media/player-of-the-week.php'],
      ['label' => 'Team of the Week',  'href' => 'media/team-of-the-week.php'],
      ['type' => 'divider'],
      ['type' => 'group', 'label' => 'Social'],
      ['label' => 'Social Hub',       'href' => 'media/social.php'],
      ['label' => 'Chat',             'href' => 'media/chat.php'],
      ['label' => 'Direct Messaging', 'href' => 'media/messages.php'],
    ],
  ],
  'options' => [
    'label' => 'Options',
    'href'  => 'options-hub.php',
    'children' => [
      ['type' => 'group', 'label' => 'Options'],
      ['label' => 'Download Latest League File', 'href' => 'download.php?what=league'],
      ['label' => 'Options Hub',                 'href' => 'options-hub.php'],
      ['label' => 'Appearance',                  'href' => 'options/appearance.php'],
      ['label' => 'Defaults',                    'href' => 'options/defaults.php'],
      ['label' => 'Notifications',               'href' => 'options/notifications.php'],
      ['label' => 'Data & Privacy',              'href' => 'options/privacy.php'],
      ['label' => 'Profile & Account',           'href' => 'options/profile.php'],
      ['label' => 'GM Settings',                 'href' => 'options/gm-settings.php'],
      ['label' => 'About Us',                    'href' => 'options/about.php'],
    ],
  ],
  'admin' => [
    'label' => 'Admin',
    'href'  => 'admin/index.php',
    'children' => [
      ['label' => 'Upload League File', 'href' => 'admin/assets-hub.php?do=upload-league'],
      ['type' => 'group', 'label' => 'League Ops'],
      ['label' => 'GM Management',        'href' => 'admin/users.php'],
      ['label' => 'Trade Approvals',      'href' => '#'],
      ['label' => 'League Settings & Toggles', 'href' => '#'],
      ['type' => 'divider'],
      ['type' => 'group', 'label' => 'Schedule & Data'],
      ['label' => 'Pipeline Quickstart',  'href' => 'admin/pipeline-quickstart.php'],
      ['label' => 'Data Pipeline Hub',    'href' => 'admin/data-pipeline.php'],
      ['type' => 'divider'],
      ['type' => 'group', 'label' => 'Content'],
      ['label' => 'News Manager', 'href' => 'admin/news.php'],
      ['label' => 'Devlog',       'href' => 'admin/devlog.php'],
      ['type' => 'divider'],
      ['type' => 'group', 'label' => 'Assets'],
      ['label' => 'Assets Hub',  'href' => 'admin/assets-hub.php'],
      ['type' => 'divider'],
      ['type' => 'group', 'label' => 'Security'],
      ['label' => 'Users / Roles',   'href' => 'admin/users.php'],
      ['label' => 'Account Locks',   'href' => 'admin/account-locks.php'],
      ['label' => 'Login Attempts',  'href' => 'admin/login-attempts.php'],
      ['type' => 'divider'],
      ['type' => 'group', 'label' => 'System'],
      ['label' => 'System Hub', 'href' => 'admin/system-hub.php'],
    ],
  ],
];

$activePage = basename($_SERVER['SCRIPT_NAME'], '.php');
?>
<script>
  (function () {
    try {
      const val = localStorage.getItem('portal:theme') || 'system';
      const mode = val.startsWith('pack:') ? 'dark' : val;
      if (mode === 'system') {
        document.documentElement.removeAttribute('data-theme');
      } else {
        document.documentElement.setAttribute('data-theme', mode);
      }
    } catch (e) { }
  })();
</script>

<header class="portal-header">
  <div class="header-frame">
    <div class="header-inner">
      <div class="portal-top">
        <div class="brand">
          <div class="logo"><img src="<?= asset('assets/img/logos/portal-logo.png') ?>" alt="Portal Logo" height="64" width="64"></div>
        </div>
        <nav class="main-nav nav-wrap" aria-label="Primary">
  <?php
  $currentScript = ltrim($_SERVER['SCRIPT_NAME'] ?? '', '/');
  $activeBasename = basename($currentScript);
  $isActiveTab = function(array $tab) use ($activeBasename): bool {
    // Active if the tab's own href matches
    if (basename($tab['href']) === $activeBasename) return true;

    // Prevent league context pages from lighting parent dropdowns
    $leagueBases = [
      'home.php','standings.php','schedule.php','statistics.php','transactions.php',
      'injuries.php','playoffs.php','entry-drafts.php',
      'home-farm.php','standings-farm.php','schedule-farm.php','statistics-farm.php',
      'transactions-farm.php','injuries-farm.php','playoffs-farm.php','entry-drafts-farm.php',
    ];

    if (!empty($tab['children'])) {
      foreach ($tab['children'] as $child) {
        if (($child['type'] ?? 'link') !== 'link') continue;
        $childBase = basename($child['href']);
        if (in_array($childBase, $leagueBases, true)) continue; // ignore league links
        if ($childBase === $activeBasename) return true;        // only non-league child matches count
      }
    }
    return false;
  };
  ?>
  <?php foreach ($tabs as $key => $t): ?>
    <?php $hasChildren = !empty($t['children']);
          $active = $isActiveTab($t);
          $btnCls = 'nav-btn' . ($active ? ' active' : '');
          $aria = $active ? ' aria-current="page"' : '';
    ?>
    <div class="nav-item<?= $hasChildren ? ' has-dropdown' : '' ?>">
      <a class="<?= $btnCls ?>" href="<?= u($t['href']) ?>"<?= $aria ?>>
        <?= htmlspecialchars($t['label']) ?><?= $hasChildren ? ' ▾' : '' ?>
      </a>
      <?php if ($hasChildren): ?>
        <div class="dropdown">
          <?php foreach ($t['children'] as $child): ?>
            <?php if (($child['type'] ?? 'link') === 'divider'): ?>
              <div class="divider"></div>
            <?php elseif (($child['type'] ?? 'link') === 'group'): ?>
              <div class="menu-group"><?= htmlspecialchars($child['label']) ?></div>
            <?php else: ?>
              <a href="<?= u($child['href']) ?>"><?= htmlspecialchars($child['label']) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</nav>
        <div class="profile">
          <?php
          @require_once __DIR__ . '/user-auth.php';

          $isLoggedIn = function_exists('user_logged_in') ? user_logged_in() : !empty($_SESSION['user']);
          $displayName = null;
          if ($isLoggedIn) {
            if (function_exists('current_user_name')) {
              $displayName = current_user_name();
            }
            if (!$displayName && isset($_SESSION['user']['name'])) {
              $displayName = $_SESSION['user']['name'];
            }
            $uid = $_SESSION['user']['id'] ?? 0;
          }
          ?>

          <?php if ($isLoggedIn): ?>
            <div class="profile-info">
              <a href="<?= u('options/appearance.php') ?>" class="avatar-thumb">
                <img src="<?= $BASE ?>/assets/avatar.php?u=<?= (int) $uid ?>&s=28&v=<?= time() ?>" alt="Avatar">
              </a>
              <a href="<?= u('options/profile.php') ?>"
                class="username"><?= htmlspecialchars($displayName ?: 'User') ?></a>
            </div>
            <a class="btn" href="<?= u('logout.php') ?>">Logout</a>
          <?php else: ?>
            <a class="btn" href="<?= u('login.php') ?>">Login</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</header>

<style>
  /* Constrain header background */
  /* Topbar: float-style band, not full-bleed */
  .portal-header {
    background: transparent;
  }

  .portal-header .header-frame {
    max-width: 1200px;
    margin: 10px auto 0 auto;
    padding: 8px 12px;
    border-radius: 14px;
    background: linear-gradient(#0f5fb4, #083e77);
    border: 1px solid #052a50;
  }

  .portal-header .header-inner {
    max-width: 100%;
    margin: 0;
    padding: 0;
  }

  /* Leaguebar tuck under topbar */
  /* Let leaguebar control the tuck — do not force it here */
  .context-header .context-frame {
    margin-top: 0 !important;
    position: static !important;
    z-index: auto !important;
  }



  /* Dropdown readability tweaks */
  .dropdown .menu-group {
    padding: 6px 12px;
    display: block;
    color: #ccc;
    font-size: 0.85em;
    text-transform: uppercase;
    background: #1c1c1c;
    border-top: 1px solid #333;
    border-bottom: 1px solid #333;
    transition: background 0.2s ease;
  }

  .dropdown .menu-group:hover {
    background: #2a2a2a;
    color: #fff;
  }

  .dropdown .divider {
    height: 1px;
    background: #444;
    margin: 0;
  }

  /* Stop full-width line under header */
  .portal-header {
    border: 0 !important;
    box-shadow: none !important;
  }

  .portal-header::before,
  .portal-header::after {
    content: none !important;
    display: none !important;
  }

  /* --- R2 override: remove any bottom rule/line under topbar --- */
  header,
  .portal-header,
  {
  border-bottom: 0 !important;
  box-shadow: none !important;
  background-image: none !important;
  }

  header::before,
  header::after,
  .portal-header::before,
  .portal-header::after,
  {
  content: none !important;
  display: none !important;
  }

  .profile .username {
    margin-right: .5rem;
    font-weight: 800;
    font-size: 10px;
  }
</style>
<style id="topbar-local-overrides">
  /* Keep the top row from wrapping */
  .portal-header .portal-top {
    display: flex !important;
    align-items: center !important;
    gap: 12px;
    flex-wrap: nowrap !important;
  }

  /* Let the nav consume the middle; prevents push/wraps */
  .portal-header .portal-top .nav-wrap {
    flex: 1 1 auto !important;
    min-width: 0;
  }

  /* Right-side profile: name above button */
  .portal-header .portal-top .profile {
    margin-left: auto !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: flex-end !important;
    gap: 10px;
  }

  .portal-header .portal-top .profile .username {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.1;
    white-space: nowrap;
    max-width: 220px;
    /* avoid pushing the nav */
    overflow: hidden;
    text-overflow: ellipsis;
    opacity: .9;
  }

  .portal-header .portal-top .profile .btn {
    align-self: flex-end;
    line-height: 1;
    padding: 6px 12px;
    /* slightly tighter */
  }

  /* Optional: on narrower widths, hide the greeting to save space */
  @media (max-width: 1100px) {
    .portal-header .portal-top .profile .username {
      display: none;
    }
  }

  .profile {
    margin-left: auto !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px;
  }

  .profile-info {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .profile .btn {
    background-color: #1B2431;
  }

  .profile .username {
    font-size: 0.875rem;
    font-weight: 600;
    white-space: nowrap;
    color: inherit !important;
    text-decoration: none !important;
  }

  .profile .username:hover {
    text-decoration: underline;
    color: #cfe3ff;
  }

  .avatar-thumb {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    overflow: hidden;
    border: 1px solid #ffffff26;
    background: #0b0f14;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .avatar-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  /* --- Portal logo: glass tile --- */
  .portal-top .brand .logo {
    width: 64px;
    /* match your placeholder square */
    height: 64px;
    border-radius: 6px;
    /* same rounding as the pill buttons */
    position: relative;
    overflow: hidden;
    display: inline-flex;
    align-items: center;
    justify-content: center;

    /* tile base */
    background:
      linear-gradient(180deg, rgba(255, 255, 255, .08), rgba(255, 255, 255, 0) 60%),
      radial-gradient(150% 120% at 50% -30%, rgba(80, 160, 255, .12), rgba(0, 0, 0, 0) 70%);

    border: 1px solid rgba(255, 255, 255, .12);
    box-shadow:
      inset 0 0 0 1px rgba(255, 255, 255, .06),
      /* inner highlight ring */
      inset 0 6px 12px rgba(255, 255, 255, .06),
      /* subtle top glow */
      inset 0 -8px 16px rgba(0, 0, 0, .35),
      /* bottom depth */
      0 1px 2px rgba(0, 0, 0, .45);
    /* outer lift */
  }

  /* glossy “glass” layer */
  .portal-top .brand .logo::before {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
      linear-gradient(180deg,
        rgba(255, 255, 255, .35) 0%,
        rgba(255, 255, 255, .14) 40%,
        rgba(255, 255, 255, 0) 46%,
        rgba(0, 0, 0, .18) 100%);
    mix-blend-mode: screen;
    /* keeps the highlight light */
  }

  /* image stays contained behind the glass */
  .portal-top .brand .logo img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    display: block;
    filter: saturate(1.05) contrast(1.05);
    /* tiny pop under glass */
  }
/* --- Glassy nav buttons (token-based) --- */
.main-nav .nav-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 14px;
  border-radius: var(--radius-sm);

  background:
    linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,0) 60%),
    radial-gradient(120% 100% at 50% -20%, rgba(255,255,255,.18), rgba(255,255,255,0) 60%);
  border: 1px solid var(--color-border);
  box-shadow: var(--shadow-card), var(--shadow-elevated);

  color: var(--color-ink);
  font-weight: 600;
  text-decoration: none;
  transition: background .2s ease, box-shadow .2s ease;
}

.main-nav .nav-btn:hover {
  background:
    linear-gradient(180deg, rgba(255,255,255,.14), rgba(255,255,255,0) 70%),
    radial-gradient(120% 100% at 50% -20%, rgba(255,255,255,.25), rgba(255,255,255,0) 60%);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.08),
    inset 0 6px 14px rgba(255,255,255,.10),
    inset 0 -8px 18px rgba(0,0,0,.30),
    var(--shadow-elevated);
}

.main-nav .nav-btn:active {
  box-shadow:
    inset 0 2px 4px rgba(0,0,0,.45),
    inset 0 -2px 6px rgba(255,255,255,.05),
    var(--shadow-elevated);
}
.main-nav .nav-btn.active {
  background:
    linear-gradient(180deg, var(--color-accent) 35%, rgba(0,0,0,.15) 100%),
    radial-gradient(120% 100% at 50% -20%, rgba(255,255,255,.25), rgba(255,255,255,0) 60%);
  border: 1px solid var(--color-accent);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.10),
    inset 0 6px 12px rgba(255,255,255,.08),
    inset 0 -8px 16px rgba(0,0,0,.25),
    0 2px 6px var(--color-accent-hover);
  color: #fff;
}


</style>