<?php if (!function_exists('u')) { require_once __DIR__ . '/bootstrap.php'; } 
/**
 * leaguebar.php
 * Secondary nav (league context) — shows a full league header on league pages,
 * and a slim tucked spacer (no text/pills) on non‑league pages like /admin or /tools.
 */
?>
<?php
// Shared base + URL helper (compatible with includes/topbar.php)
if (!isset($BASE)) {
  $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
  $BASE = preg_replace('~/(admin|tools)(/.*)?$~', '', $scriptPath);
  if (!$BASE || $BASE === $scriptPath) {
    $BASE = rtrim(dirname($scriptPath), '/');
  }
  $BASE = rtrim($BASE, '/');
}
  
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$isLeaguePage = (bool) preg_match('~/(home|standings|schedule|statistics|transactions|injuries|playoffs|entry-?drafts?)\.php$~i', $script);

// Decide whether this is a league page (render full bar) or not (render slim spacer)
$pathNow = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isNonLeague = (bool) preg_match('~/(admin|options)/~', $pathNow);
// Allow override by setting $showLeaguebar = true before include
if (isset($showLeaguebar) && $showLeaguebar === true) {
  $isNonLeague = false;
}
if (isset($hideLeaguebar) && $hideLeaguebar === true) {
  $isNonLeague = true;
}

// If non‑league, render only a slim tucked band and exit early
if ($isNonLeague):
  ?>
  <div class="context-header">
    <div class="context-frame slim" aria-hidden="true"></div>
  </div>
  <style>
    .context-header {
      background: transparent;
    }

    .context-header .context-frame {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 12px;
      border-radius: 14px;
    }

    /* Slim spacer for non-league pages — visible + tucked */
.context-frame.slim{
  height:16px;               /* gives us 6–8px to actually see */
  margin-top:-8px !important;/* tuck, but leave part visible */
  position:relative; 
  z-index:1;                 /* keep it below the top bar */
  background:transparent; border:0; box-shadow:none;
}
  </style>
  <?php return; endif; ?>

<?php
// League page — determine active tab + titles
if (empty($activeTab)) {
  $file = strtolower(basename($_SERVER['PHP_SELF'] ?? ''));
  $map = [
    'home' => ['home.php', 'AHL-home.php'],
    'standings' => ['standings.php', 'leaders.php', 'index.php'],
    'schedule' => ['schedule.php'],
    'statistics' => ['stats.php', 'statistics.php'],
    'transactions' => ['transactions.php'],
    'injuries' => ['injuries.php'],
    'playoffs' => ['playoffs.php'],
    'drafts' => ['entrydrafts.php', 'draft.php', 'drafts.php', 'entry-drafts.php']
  ];
  $activeTab = 'home';
  foreach ($map as $key => $files) {
    if (in_array($file, $files, true)) {
      $activeTab = $key;
      break;
    }
  }
}
$tabs = [
  'home' => ['label' => 'Home', 'href' => 'home.php'],
  'standings' => ['label' => 'Standings', 'href' => 'standings.php'],
  'schedule' => ['label' => 'Schedule', 'href' => 'schedule.php'],
  'statistics' => ['label' => 'Statistics', 'href' => 'statistics.php'],
  'transactions' => ['label' => 'Transactions', 'href' => 'transactions.php'],
  'injuries' => ['label' => 'Injuries', 'href' => 'injuries.php'],
  'playoffs' => ['label' => 'Playoffs', 'href' => 'playoffs.php'],
  'drafts' => ['label' => 'Entry Drafts', 'href' => 'entry-drafts.php'],
];
$titleMap = [
  'home' => 'Home',
  'standings' => 'Standings & Leaders',
  'schedule' => 'Schedule',
  'statistics' => 'Statistics',
  'transactions' => 'Transactions',
  'injuries' => 'Injuries',
  'playoffs' => 'Playoffs',
  'drafts' => 'Entry Drafts',
];
$ctxTitle = $titleMap[$activeTab] ?? 'League';
?>

<!-- League Context Header (full) -->
<section class="context-header <?= $isLeaguePage ? 'is-league' : 'is-nonleague' ?>">
  <div class="context-frame<?= $isLeaguePage ? '' : ' slim' ?>">
    <div class="context-inner">
      <div class="context-logo" aria-hidden="true"></div>
      <div class="context-titles">
        <div class="kicker" id="ctxKicker">NATIONAL HOCKEY LEAGUE</div>
        <div class="h1" id="ctxTitle"><?= htmlspecialchars($ctxTitle) ?></div>
        <div class="subnav" id="ctxSubnav">
          <?php foreach ($tabs as $key => $t):
            $isActive = ($key === $activeTab);
            $cls = 'pill' . ($isActive ? ' active' : '');
            $aria = $isActive ? ' aria-current="page"' : '';
            ?>
            <a class="<?= $cls ?>" href="<?= u($t['href']) ?>" <?= $aria ?>><?= htmlspecialchars($t['label']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
  /* Floating band like topbar; league pages get the full header */
  .context-header {
    background: transparent
  }

  .context-frame {
    max-width: 1200px;
    margin: 0 auto;
    padding: 8px 12px;
    border-radius: 14px;
    background: linear-gradient(#0f5fb4, #083e77);
    border: 1px solid #052a50;
    position: relative;
    z-index: 5;
    margin-top: -10px
  }

  .context-inner {
    display: flex;
    align-items: center;
    gap: 16px
  }

  .context-logo {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: #4aa3ff
  }

  .context-titles {
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: #cfe6ff
  }

  .kicker {
    font-size: .85rem;
    letter-spacing: .06em;
    color: #a8d0ff
  }

  .h1 {
    font-weight: 800;
    font-size: 1.35rem;
    letter-spacing: .02em;
    color: #e8f2ff
  }

  .subnav {
    margin-top: 4px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap
  }

  .pill {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 999px;
    background: #0b2440;
    color: #fff;
    text-decoration: none;
    border: 1px solid #0f3b6e
  }

  .pill:hover {
    background: #10345e
  }

  .pill.active {
    background: #1a4d8f;
    border-color: #1f65c1
  }

  /* --- R2 override: keep leaguebar visible and tucked under the topbar --- */
  .context-shell {
    /* ensures it actually renders */
    display: block !important;
    /* give it a definite height so it can be seen on non-league pages */
    min-height: 40px;
    /* adjust to taste (32–44px usually good) */
    /* tuck look */
    margin-top: -6px;
    /* pulls slightly under the topbar */
    z-index: 4 !important;
    /* below the topbar (which is typically > 5–10) */
  }

  /* create the “tucked edge” seam without a big line across the page */
  .context-shell {
    border-top: 0 !important;
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.06),
      /* soft highlight at top */
      inset 0 -1px 0 rgba(0, 0, 0, 0.25);
    /* subtle inner seam */
  }

  /* if pills/menus are hidden on admin/tools, still keep the shell visible */
  .subnav {
    visibility: visible;
  }
</style>