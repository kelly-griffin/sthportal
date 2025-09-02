<?php
if (!function_exists('u')) {
  require_once __DIR__ . '/bootstrap.php';
}
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

<?php
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$BASE = preg_replace('~/(admin|tools|options|media)(/.*)?$~', '', $scriptPath);
if (!$BASE || $BASE === $scriptPath) {
  $BASE = rtrim(dirname($scriptPath), '/');
}
$BASE = rtrim($BASE, '/');
$navCss = $BASE . '/assets/css/nav.css';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($navCss, ENT_QUOTES, 'UTF-8') ?>">

<header class="portal-header">
  <div class="header-frame">
    <div class="header-inner">
      <div class="portal-top">
        <div class="brand">
          <div class="logo" title="Portal Logo"></div>
          <div class="title" id="portal-title">UHA Portal</div>
        </div>
        <nav class="main-nav nav-wrap" aria-label="Primary">
          <div class="nav-item"><a class="nav-btn" href="<?= u('index.php') ?>">Splash</a></div>

          <div class="nav-item">
            <a class="nav-btn" href="<?= u('leagues.php') ?>">Leagues ▾</a>
            <div class="dropdown">
              <div class="menu-group">Leagues</div>
              <a href="<?= u("home.php") ?> ">Pro League (NHL)</a>
              <a href="<?= u("home-farm.php") ?> ">Farm League (AHL)</a>
              <a href="#">Development League (ECHL)</a>
              <a href="#">International (Multiple)</a>
              <a href="#">Junior Leagues (Multiple)</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="<?= u('players.php') ?>">Players ▾</a>
            <div class="dropdown">
              <div class="menu-group">Players</div>
              <a href="#">All Players</a>
              <a href="#">Free Agents</a>
              <a href="#">Waiver Wire</a>
              <a href="#">Prospect List</a>
              <a href="#">Compare Players</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="<?= u('front-office.php') ?>">Front Office ▾</a>
            <div class="dropdown">
              <div class="menu-group">Front Office</div>
              <a href="#">Team Dashboard</a>
              <a href="#">Roster Management</a>
              <a href="#">Lines &amp; Strategy</a>
              <a href="#">Depth Charts</a>
              <a href="#">Personnel Changes</a>
              <a href="#">Financial Management</a>
              <a href="#">Scouting Assignments</a>
              <a href="#">Cap Management Tools</a>
              <a href="#">Upload Lines</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="<?= u('tournaments.php') ?>">Tournaments ▾</a>
            <div class="dropdown">
              <div class="menu-group">Tournaments</div>
              <a href="#">World Cup of Hockey</a>
              <a href="#">Olympics</a>
              <a href="#">World Juniors</a>
              <a href="#">IIHF Worlds</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="<?= u('media-hub.php') ?>">Media ▾</a>
            <div class="dropdown">
              <div class="menu-group">Media</div>
              <a href="<?= u('media-hub.php') ?>">Media Hub</a>
              <a href="<?= u('media/news.php') ?>">News</a>
              <a href="<?= u('media/press-releases.php') ?>">Press Releases</a>
              <a href="<?= u('media/weekly-recaps.php') ?>">Weekly Recaps</a>
              <a href="<?= u('media/power-rankings.php') ?>">Power Rankings</a>
              <a href="<?= u('media/player-of-the-week.php') ?>">Player of the Week</a>
              <a href="<?= u('media/team-of-the-week.php') ?>">Team of the Week</a>
              <div class="menu-group">Social</div>
              <a href="<?= u('media/social.php') ?>">Social Hub</a>
              <a href="<?= u('media/chat.php') ?>">Chat</a>
              <a href="<?= u('media/messages.php') ?>">Direct Messaging</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="<?= u('options-hub.php') ?>">Options ▾</a>
            <div class="dropdown">
              <div class="menu-group">Options</div>
              <a href="<?= u('download.php?what=league') ?>">Download Latest League File</a>
              <a href="<?= u('options-hub.php') ?>">Options Hub</a>
              <a href="<?= u('options/appearance.php') ?>">Appearance</a>
              <a href="<?= u('options/defaults.php') ?>">Defaults</a>
              <a href="<?= u('options/notifications.php') ?>">Notifications</a>
              <a href="<?= u('options/privacy.php') ?>">Data &amp; Privacy</a>
              <a href="<?= u('options/profile.php') ?>">Profile &amp; Account</a>
              <a href="<?= u('options/gm-settings.php') ?>">GM Settings</a>
              <a href="<?= u('options/about.php') ?>">About Us</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="<?= u('admin/') ?>">Admin ▾</a>
            <div class="dropdown">
              <a href="<?= h(u('admin/assets-hub.php')) ?>?do=upload-league">Upload League File</a>
              <div class="menu-group">League Ops</div>
              <a href="<?= u('admin/users.php') ?>">GM Management</a>
              <a href="#">Trade Approvals</a>
              <a href="#">League Settings &amp; Toggles</a>

              <div class="divider"></div>
              <div class="menu-group">Schedule &amp; Data</div>
              <a href="<?= u('admin/pipeline-quickstart.php') ?>">Pipeline Quickstart</a>
              <a href="<?= u('admin/data-pipeline.php') ?>">Data Pipeline Hub</a>

              <div class="divider"></div>
              <div class="menu-group">Content</div>
              <a href="<?= u('admin/news.php') ?>">News Manager</a>
              <a href="<?= u('admin/devlog.php') ?>">Devlog</a>

              <div class="divider"></div>
              <div class="menu-group">Assets</div>
              <a href="<?= u('admin/assets-hub.php') ?>">Assets Hub</a>

              <div class="divider"></div>
              <div class="menu-group">Security</div>
              <a href="<?= u('admin/users.php') ?>">Users / Roles</a>
              <a href="<?= u('admin/account-locks.php') ?>">Account Locks</a>
              <a href="<?= u('admin/login-attempts.php') ?>">Login Attempts</a>

              <div class="divider"></div>
              <div class="menu-group">System</div>
              <a href="<?= u('admin/system-hub.php') ?>">System Hub</a>
            </div>
          </div>
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
</style>