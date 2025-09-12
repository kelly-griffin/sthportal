<?php if (!function_exists('u')) { require_once __DIR__ . '/bootstrap.php'; }
/**
* leaguebar.php
* Secondary nav (league context) — shows a full league header on league pages.
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


$current = basename($_SERVER['SCRIPT_NAME'] ?? '');


// League-context pages (Pro + Farm variants map to the same logical keys)
$leagueTabs = [
'home.php' => ['key' => 'home', 'label' => 'Home', 'href' => 'home.php'],
'standings.php' => ['key' => 'standings', 'label' => 'Standings', 'href' => 'standings.php'],
'schedule.php' => ['key' => 'schedule', 'label' => 'Schedule', 'href' => 'schedule.php'],
'statistics.php' => ['key' => 'statistics', 'label' => 'Statistics', 'href' => 'statistics.php'],
'transactions.php' => ['key' => 'transactions','label' => 'Transactions','href' => 'transactions.php'],
'injuries.php' => ['key' => 'injuries', 'label' => 'Injuries', 'href' => 'injuries.php'],
'playoffs.php' => ['key' => 'playoffs', 'label' => 'Playoffs', 'href' => 'playoffs.php'],
'entry-drafts.php' => ['key' => 'entrydrafts', 'label' => 'Entry Drafts','href' => 'entry-drafts.php'],
'home-farm.php' => ['key' => 'home', 'label' => 'Home', 'href' => 'home-farm.php'],
'standings-farm.php' => ['key' => 'standings', 'label' => 'Standings', 'href' => 'standings-farm.php'],
'schedule-farm.php' => ['key' => 'schedule', 'label' => 'Schedule', 'href' => 'schedule-farm.php'],
'statistics-farm.php' => ['key' => 'statistics', 'label' => 'Statistics', 'href' => 'statistics-farm.php'],
'transactions-farm.php' => ['key' => 'transactions','label' => 'Transactions','href' => 'transactions-farm.php'],
'injuries-farm.php' => ['key' => 'injuries', 'label' => 'Injuries', 'href' => 'injuries-farm.php'],
'playoffs-farm.php' => ['key' => 'playoffs', 'label' => 'Playoffs', 'href' => 'playoffs-farm.php'],
'entry-drafts-farm.php' => ['key' => 'entrydrafts', 'label' => 'Entry Drafts','href' => 'entry-drafts-farm.php'],
];


// Global sections (not league pills, but still need header)
$globalTitles = [
'index.php' => 'Splash',
'leagues.php' => 'Leagues',
'players.php' => 'Players',
'front-office.php' => 'Front Office',
'tournaments.php' => 'Tournaments',
'media-hub.php' => 'Media',
'options-hub.php' => 'Options',
'admin' => 'Admin',
'admin/index.php' => 'Admin',
];


$isLeagueContext = isset($leagueTabs[$current]);
$isNonLeague = !$isLeagueContext;
$activeTab = $isLeagueContext ? $leagueTabs[$current]['key'] : null;


$contextTitle = 'NATIONAL HOCKEY LEAGUE';
$sectionTitle = $isLeagueContext
? ($leagueTabs[$current]['label'] ?? 'Home')
: ($globalTitles[$current] ?? '');
?>


<section class="context-header is-league">
  <div class="context-frame">
    <div class="context-head">
      <div class="context-kicker"><?= htmlspecialchars($contextTitle) ?></div>
      <div class="context-title" id="portal-title"><?= htmlspecialchars($sectionTitle) ?></div>
    </div>

    <div class="subnav" id="ctxSubnav">
<?php
$pillRow = [
['key'=>'home', 'label'=>'Home', 'href'=>'home.php'],
['key'=>'standings', 'label'=>'Standings', 'href'=>'standings.php'],
['key'=>'schedule', 'label'=>'Schedule', 'href'=>'schedule.php'],
['key'=>'statistics', 'label'=>'Statistics', 'href'=>'statistics.php'],
['key'=>'transactions','label'=>'Transactions','href'=>'transactions.php'],
['key'=>'injuries', 'label'=>'Injuries', 'href'=>'injuries.php'],
['key'=>'playoffs', 'label'=>'Playoffs', 'href'=>'playoffs.php'],
['key'=>'entrydrafts', 'label'=>'Entry Drafts','href'=>'entry-drafts.php'],
];
foreach ($pillRow as $t):
$isActive = ($t['key'] === $activeTab);
$cls = 'pill' . ($isActive ? ' active' : '');
$aria = $isActive ? ' aria-current="page"' : '';
?>
<a class="<?= $cls ?>" href="<?= u($t['href']) ?>"<?= $aria ?>>
<?= htmlspecialchars($t['label']) ?>
</a>
<?php endforeach; ?>
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
/* --- Glassy leaguebar pills, token-based --- */
#ctxSubnav .pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 14px;
  margin: 0 4px;
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

#ctxSubnav .pill:hover {
  background:
    linear-gradient(180deg, rgba(255,255,255,.14), rgba(255,255,255,0) 70%),
    radial-gradient(120% 100% at 50% -20%, rgba(255,255,255,.25), rgba(255,255,255,0) 60%);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.08),
    inset 0 6px 14px rgba(255,255,255,.10),
    inset 0 -8px 18px rgba(0,0,0,.30),
    var(--shadow-elevated);
}

#ctxSubnav .pill.active {
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
.context-head .context-kicker {
  color: var(--color-ink);
  font-weight: 700;
  text-shadow: 0 1px 3px rgba(0,0,0,.4);
}
.context-head .context-title {
  color: var(--color-ink);
  font-weight: 500;
  font-size: 10px;
  text-shadow: 0 1px 3px rgba(0,0,0,.4);
}

</style>