<?php
// /options/privacy.php — Options: Privacy (universal shell)
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Privacy';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — UHA Portal</title>
<style>
:root{
  --stage:#585858ff; --card:#0D1117; --card-border:#FFFFFF1A;
  --ink:#E8EEF5; --ink-soft:#95A3B4; --site-width:1200px;
}
body{ margin:0; background:#202428; color:var(--ink);
      font:14px/1.5 system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif; }
.site{ width:var(--site-width); margin:0 auto; min-height:100vh; }
.canvas{ padding:0 16px 40px; }
.wrap{ max-width:1000px; margin:20px auto; }

.page-surface{
  margin:12px 0 32px; padding:16px 16px 24px; background:var(--stage);
  border-radius:16px; box-shadow:inset 0 1px 0 #ffffff0d, 0 0 0 1px #ffffff0f;
  min-height:calc(100vh - 220px);
}

h1{ margin:0 0 6px; font-size:28px; line-height:1.15; letter-spacing:.2px; }
.muted{ color:var(--ink-soft); }

.grid2{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
@media (max-width:920px){ .grid2{ grid-template-columns:1fr; } }

.card{
  background:var(--card); border:1px solid var(--card-border);
  border-radius:16px; padding:16px; color:#E8EEF5;
  box-shadow:inset 0 1px 0 #ffffff12;
}
.card h2{ margin:0 0 10px; color:#DFE8F5; }
.card p{ margin:0 0 10px; color:#CFE1F3; }

.row{ display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
.col{ display:grid; gap:8px; }

label.chk, label.radio{ display:inline-flex; align-items:center; gap:8px; cursor:pointer; }
input[type="time"], select{
  background:#0f1420; color:#e6eef8; border:1px solid #2F3F53; border-radius:8px; padding:6px 8px;
}

.btn{
  display:inline-block; padding:6px 10px; border-radius:10px; border:1px solid #2F3F53;
  background:#1B2431; color:#E6EEF8; text-decoration:none;
}
.btn:hover{ background:#223349; border-color:#3D5270; }
.btn.small{ padding:4px 8px; font-size:12px; line-height:1; }
.btn[aria-disabled="true"]{ opacity:.6; cursor:not-allowed; }

.hr{ height:1px; background:#ffffff1a; margin:12px 0; }
code{ background:#131a23; padding:2px 6px; border-radius:6px; }
</style>
</head>
<body>
<div class="site">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/leaguebar.php'; ?>

  <div class="canvas"><div class="wrap"><div class="page-surface">
    <h1><?= h($title) ?></h1>
    <p class="muted">Control local data, consent, and what (if anything) the portal should collect. UI only for now; nothing is sent anywhere.</p>

    <div class="grid2">
      <!-- Data Collection -->
      <section class="card" id="collection">
        <h2>Data Collection</h2>
        <p>These switches control local opt-ins. Server-side collection is not enabled.</p>
        <div class="col">
          <label class="chk"><input type="checkbox" id="pAnalytics"> Allow usage analytics (anonymous)</label>
          <label class="chk"><input type="checkbox" id="pErrors"> Allow error & crash reports (anonymous)</label>
        </div>
        <div class="hr"></div>
        <button class="btn small" type="button" id="resetCollection">Reset</button>
        <p class="muted" style="margin-top:8px;">Stored as <code>portal:privacy:analytics</code> and <code>portal:privacy:errors</code>.</p>
      </section>

      <!-- Personalization -->
      <section class="card" id="personalization">
        <h2>Personalization</h2>
        <p>Let the portal remember preferences and tailor some UI bits.</p>
        <div class="col">
          <label class="chk"><input type="checkbox" id="pPersonalize"> Personalized UI (remember team/scope, quick filters)</label>
          <label class="chk"><input type="checkbox" id="pRemember" disabled> “Remember me” on this device <span class="muted">(server wiring later)</span></label>
        </div>
        <div class="hr"></div>
        <button class="btn small" type="button" id="resetPersonal">Reset</button>
        <p class="muted" style="margin-top:8px;">Stored as <code>portal:privacy:personalize</code> and <code>portal:privacy:remember</code>.</p>
      </section>

      <!-- Consent -->
      <section class="card" id="consent">
        <h2>Consent</h2>
        <p>Pick your consent level for optional features.</p>
        <div class="col">
          <label class="radio"><input type="radio" name="consent" value="essential"> Essential only</label>
          <label class="radio"><input type="radio" name="consent" value="full"> Full experience</label>
          <div class="row">
            <button class="btn small" type="button" id="showBanner">Show consent banner again</button>
          </div>
        </div>
        <div class="hr"></div>
        <button class="btn small" type="button" id="resetConsent">Reset</button>
        <p class="muted" style="margin-top:8px;">Stored as <code>portal:privacy:consent</code> and <code>portal:privacy:cookie-banner</code>.</p>
      </section>

      <!-- Local Data -->
      <section class="card" id="localdata">
        <h2>Local Data</h2>
        <p>Clear locally stored preferences and cached keys for this portal.</p>
        <div class="row">
          <button class="btn" type="button" id="clearPortal">Clear Portal Storage</button>
          <button class="btn small" type="button" id="resetPrivacy">Reset Privacy Only</button>
        </div>
        <p class="muted" style="margin-top:8px;">“Clear Portal Storage” removes all <code>localStorage</code> keys beginning with <code>portal:</code>.</p>
      </section>

      <!-- Data Access (placeholders) -->
      <section class="card" id="access">
        <h2>Data Access</h2>
        <p>Export or delete account—coming later.</p>
        <div class="row">
          <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Export My Data (soon)</a>
          <a class="btn small" href="#" aria-disabled="true" onclick="return false;">Delete My Account (soon)</a>
        </div>
      </section>

      <!-- Policy (placeholder text) -->
      <section class="card" id="policy">
        <h2>Policy</h2>
        <p class="muted">Draft: no third-party trackers, no ads, no sale of data. Any future collection will be opt-in and documented here.</p>
      </section>
    </div>
  </div></div></div>
</div>

<script>
/* ===== Local-only Privacy prefs (UI stub) ===== */
(function(){
  const K = (s) => `portal:privacy:${s}`;

  // Elements
  const pAnalytics = document.getElementById('pAnalytics');
  const pErrors    = document.getElementById('pErrors');
  const resetCollection = document.getElementById('resetCollection');

  const pPersonalize = document.getElementById('pPersonalize');
  const pRemember    = document.getElementById('pRemember'); // disabled placeholder
  const resetPersonal = document.getElementById('resetPersonal');

  const consentRadios = Array.from(document.querySelectorAll('input[name="consent"]'));
  const showBanner = document.getElementById('showBanner');
  const resetConsent = document.getElementById('resetConsent');

  const clearPortal = document.getElementById('clearPortal');
  const resetPrivacy = document.getElementById('resetPrivacy');

  // Defaults
  const DEF = {
    analytics:false, errors:false, personalize:false, remember:false, consent:'essential',
    'cookie-banner':'dismissed'
  };

  function setConsent(val){
    consentRadios.forEach(r => r.checked = (r.value === val));
  }

  function init(){
    pAnalytics.checked = (localStorage.getItem(K('analytics')) ?? (DEF.analytics?'true':'false')) === 'true';
    pErrors.checked    = (localStorage.getItem(K('errors'))    ?? (DEF.errors?'true':'false'))    === 'true';

    pPersonalize.checked = (localStorage.getItem(K('personalize')) ?? (DEF.personalize?'true':'false')) === 'true';
    pRemember.checked    = (localStorage.getItem(K('remember'))    ?? (DEF.remember?'true':'false'))    === 'true';

    const c = localStorage.getItem(K('consent')) || DEF.consent;
    setConsent(c);
  }

  // Listeners
  pAnalytics.addEventListener('change', ()=> localStorage.setItem(K('analytics'), pAnalytics.checked?'true':'false'));
  pErrors.addEventListener('change',    ()=> localStorage.setItem(K('errors'),    pErrors.checked?'true':'false'));
  resetCollection.addEventListener('click', ()=>{
    ['analytics','errors'].forEach(k=>localStorage.removeItem(K(k)));
    init();
  });

  pPersonalize.addEventListener('change', ()=> localStorage.setItem(K('personalize'), pPersonalize.checked?'true':'false'));
  pRemember.addEventListener('change',    ()=> localStorage.setItem(K('remember'),    pRemember.checked?'true':'false'));
  resetPersonal.addEventListener('click', ()=>{
    ['personalize','remember'].forEach(k=>localStorage.removeItem(K(k)));
    init();
  });

  consentRadios.forEach(r => r.addEventListener('change', ()=>{
    const sel = consentRadios.find(x=>x.checked)?.value || 'essential';
    localStorage.setItem(K('consent'), sel);
  }));
  showBanner.addEventListener('click', ()=>{
    // just mark as "needs to show" — your real banner can read this later
    localStorage.setItem(K('cookie-banner'), 'show');
    alert('Consent banner will be shown again (flag set).');
  });
  resetConsent.addEventListener('click', ()=>{
    ['consent','cookie-banner'].forEach(k=>localStorage.removeItem(K(k)));
    init();
  });

  clearPortal.addEventListener('click', ()=>{
    if (!confirm('Clear ALL portal storage on this device? This resets theme, defaults, notifications, etc.')) return;
    const keys = Object.keys(localStorage);
    keys.filter(k => k.startsWith('portal:')).forEach(k => localStorage.removeItem(k));
    init();
    alert('Cleared local portal storage.');
  });

  resetPrivacy.addEventListener('click', ()=>{
    const keys = Object.keys(localStorage);
    keys.filter(k => k.startsWith('portal:privacy:')).forEach(k => localStorage.removeItem(k));
    init();
  });

  init();
})();
</script>
</body>
</html>
