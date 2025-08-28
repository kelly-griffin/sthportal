<?php
// /options/notifications.php — Options: Notifications (universal shell)
require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Notifications';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — UHA Portal</title>
<style>
:root{ --stage:#585858ff; --card:#0D1117; --card-border:#FFFFFF1A; --ink:#E8EEF5; --ink-soft:#95A3B4; --site-width:1200px; }
body{ margin:0; background:#202428; color:var(--ink); font:14px/1.5 system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif; }
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
  background:var(--card); border:1px solid var(--card-border); border-radius:16px; padding:16px;
  color:#E8EEF5; box-shadow:inset 0 1px 0 #ffffff12;
}
.card h2{ margin:0 0 10px; color:#DFE8F5; }
.card p{ margin:0 0 10px; color:#CFE1F3; }

.row{ display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
.col{ display:grid; gap:8px; }

.btn{
  display:inline-block; padding:6px 10px; border-radius:10px; border:1px solid #2F3F53;
  background:#1B2431; color:#E6EEF8; text-decoration:none;
}
.btn.small{ padding:4px 8px; font-size:12px; line-height:1; }
.btn:hover{ background:#223349; border-color:#3D5270; }
.btn[aria-disabled="true"]{ opacity:.6; cursor:not-allowed; }

label.chk, label.radio{ display:inline-flex; align-items:center; gap:8px; cursor:pointer; }
input[type="time"], select{
  background:#0f1420; color:#e6eef8; border:1px solid #2F3F53; border-radius:8px; padding:6px 8px;
}
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
    <p class="muted">Pick what you want to be notified about. These save locally for now; we’ll wire delivery later.</p>

    <div class="grid2">
      <!-- Delivery Channels -->
      <section class="card" id="channels">
        <h2>Delivery Channels</h2>
        <p>Choose how the portal should notify you.</p>
        <div class="col">
          <label class="chk"><input type="checkbox" id="chSite"> In-portal banners</label>
          <label class="chk"><input type="checkbox" id="chEmail" disabled> Email <span class="muted">(coming soon)</span></label>
          <label class="chk"><input type="checkbox" id="chDiscord" disabled> Discord/Webhook <span class="muted">(coming soon)</span></label>
        </div>
        <div class="hr"></div>
        <button class="btn small" type="button" id="resetChannels">Reset</button>
        <p class="muted" style="margin-top:8px;">Stored as <code>portal:notify:channel:*</code>.</p>
      </section>

      <!-- Event Types -->
      <section class="card" id="event-types">
        <h2>Event Types</h2>
        <p>What you want to hear about.</p>
        <div class="col">
          <strong>Team-specific</strong>
          <label class="chk"><input type="checkbox" id="evTransactions"> Transactions</label>
          <label class="chk"><input type="checkbox" id="evWaivers"> Waiver claims</label>
          <label class="chk"><input type="checkbox" id="evTrades"> Trades</label>
          <label class="chk"><input type="checkbox" id="evInjuries"> Injuries</label>
          <label class="chk"><input type="checkbox" id="evSuspensions"> Suspensions</label>
          <label class="chk"><input type="checkbox" id="evGameFinalTeam"> Game final — your team</label>

          <strong style="margin-top:10px;">League-wide</strong>
          <label class="chk"><input type="checkbox" id="evGameFinalLeague"> Game finals — league</label>
          <label class="chk"><input type="checkbox" id="evScheduleChanges"> Schedule changes</label>
          <label class="chk"><input type="checkbox" id="evGmMessages"> GM/bulletin messages</label>
        </div>
        <div class="hr"></div>
        <button class="btn small" type="button" id="resetEvents">Reset</button>
        <p class="muted" style="margin-top:8px;">Stored as <code>portal:notify:event:*</code>.</p>
      </section>

      <!-- Frequency -->
      <section class="card" id="frequency">
        <h2>Frequency</h2>
        <p>Instant alerts or a daily digest.</p>
        <div class="col">
          <label class="radio"><input type="radio" name="freq" value="instant"> Instant</label>
          <label class="radio"><input type="radio" name="freq" value="daily"> Daily digest</label>
          <div class="row">
            <label class="radio"><input type="checkbox" id="digestTimeEnable"> Set digest time</label>
            <input type="time" id="digestTime" value="09:00" disabled>
          </div>
        </div>
        <div class="hr"></div>
        <button class="btn small" type="button" id="resetFreq">Reset</button>
        <p class="muted" style="margin-top:8px;">Stored as <code>portal:notify:freq</code> and <code>portal:notify:freq-time</code>.</p>
      </section>

      <!-- Quiet Hours -->
      <section class="card" id="quiet">
        <h2>Quiet Hours</h2>
        <p>Mute non-critical alerts overnight.</p>
        <div class="row" style="gap:16px;">
          <label class="chk"><input type="checkbox" id="qhEnable"> Enable</label>
          <div class="row">
            <span>From</span> <input type="time" id="qhStart" value="23:00" disabled>
            <span>to</span> <input type="time" id="qhEnd" value="07:00" disabled>
          </div>
        </div>
        <div class="hr"></div>
        <button class="btn small" type="button" id="resetQuiet">Reset</button>
        <p class="muted" style="margin-top:8px;">Stored as <code>portal:notify:quiet</code>, <code>:quiet-start</code>, <code>:quiet-end</code>.</p>
      </section>

      <!-- Master Reset -->
      <section class="card">
        <h2>Reset All Notifications</h2>
        <p>Clear everything on this page and return to defaults.</p>
        <button class="btn" type="button" id="resetAll">Reset Everything</button>
        <p class="muted" style="margin-top:8px;">Local only; no server settings are changed.</p>
      </section>
    </div>
  </div></div></div>
</div>

<script>
/* ===== Local-only prefs (UI stub; no server wiring yet) ===== */
(function(){
  const K = (s) => `portal:notify:${s}`;

  // Elements
  const chSite = document.getElementById('chSite');
  const chEmail = document.getElementById('chEmail');
  const chDiscord = document.getElementById('chDiscord');
  const resetChannels = document.getElementById('resetChannels');

  const events = {
    evTransactions: 'event:transactions',
    evWaivers: 'event:waivers',
    evTrades: 'event:trades',
    evInjuries: 'event:injuries',
    evSuspensions: 'event:suspensions',
    evGameFinalTeam: 'event:game_final_team',
    evGameFinalLeague: 'event:game_final_league',
    evScheduleChanges: 'event:schedule_changes',
    evGmMessages: 'event:gm_messages'
  };
  const resetEvents = document.getElementById('resetEvents');

  const freqRadios = Array.from(document.querySelectorAll('input[name="freq"]'));
  const digestTimeEnable = document.getElementById('digestTimeEnable');
  const digestTime = document.getElementById('digestTime');
  const resetFreq = document.getElementById('resetFreq');

  const qhEnable = document.getElementById('qhEnable');
  const qhStart = document.getElementById('qhStart');
  const qhEnd = document.getElementById('qhEnd');
  const resetQuiet = document.getElementById('resetQuiet');

  const resetAll = document.getElementById('resetAll');

  // Defaults
  const DEF = {
    channel: { site: true, email: false, discord: false },
    freq: 'instant',
    'freq-time': '09:00',
    quiet: false, 'quiet-start': '23:00', 'quiet-end': '07:00',
    events: Object.fromEntries(Object.values(events).map(k => [k, false]))
  };

  function setRadio(name, val){
    freqRadios.forEach(r => r.checked = (r.value === val));
  }

  function init(){
    // Channels
    chSite.checked = (localStorage.getItem(K('channel:site')) ?? (DEF.channel.site ? 'true':'false')) === 'true';
    chEmail.checked = (localStorage.getItem(K('channel:email')) ?? (DEF.channel.email ? 'true':'false')) === 'true';
    chDiscord.checked = (localStorage.getItem(K('channel:discord')) ?? (DEF.channel.discord ? 'true':'false')) === 'true';

    // Events
    Object.entries(events).forEach(([id,key])=>{
      const el = document.getElementById(id);
      const def = DEF.events[key] ? 'true' : 'false';
      el.checked = (localStorage.getItem(K(key)) ?? def) === 'true';
    });

    // Frequency
    const f = localStorage.getItem(K('freq')) || DEF.freq;
    setRadio('freq', f);
    const t = localStorage.getItem(K('freq-time')) || DEF['freq-time'];
    digestTime.value = t;
    const timeOn = localStorage.getItem(K('freq-time-enable')) === 'true';
    digestTimeEnable.checked = timeOn;
    digestTime.disabled = !timeOn;

    // Quiet hours
    qhEnable.checked = (localStorage.getItem(K('quiet')) ?? (DEF.quiet?'true':'false')) === 'true';
    qhStart.value = localStorage.getItem(K('quiet-start')) || DEF['quiet-start'];
    qhEnd.value = localStorage.getItem(K('quiet-end')) || DEF['quiet-end'];
    qhStart.disabled = qhEnd.disabled = !qhEnable.checked;
  }

  // Listeners
  chSite.addEventListener('change', ()=> localStorage.setItem(K('channel:site'), chSite.checked ? 'true':'false'));
  chEmail.addEventListener('change',()=> localStorage.setItem(K('channel:email'), chEmail.checked ? 'true':'false'));
  chDiscord.addEventListener('change',()=> localStorage.setItem(K('channel:discord'), chDiscord.checked ? 'true':'false'));
  resetChannels.addEventListener('click', ()=>{
    ['site','email','discord'].forEach(c=>localStorage.removeItem(K(`channel:${c}`)));
    init();
  });

  Object.entries(events).forEach(([id,key])=>{
    const el = document.getElementById(id);
    el.addEventListener('change', ()=> localStorage.setItem(K(key), el.checked ? 'true':'false'));
  });
  resetEvents.addEventListener('click', ()=>{
    Object.values(events).forEach(key=>localStorage.removeItem(K(key)));
    init();
  });

  freqRadios.forEach(r=> r.addEventListener('change', ()=>{
    localStorage.setItem(K('freq'), (freqRadios.find(x=>x.checked)?.value)||'instant');
  }));
  digestTimeEnable.addEventListener('change', ()=>{
    localStorage.setItem(K('freq-time-enable'), digestTimeEnable.checked ? 'true':'false');
    digestTime.disabled = !digestTimeEnable.checked;
  });
  digestTime.addEventListener('change', ()=> localStorage.setItem(K('freq-time'), digestTime.value));
  resetFreq.addEventListener('click', ()=>{
    ['freq','freq-time','freq-time-enable'].forEach(k=>localStorage.removeItem(K(k)));
    init();
  });

  qhEnable.addEventListener('change', ()=>{
    localStorage.setItem(K('quiet'), qhEnable.checked ? 'true':'false');
    qhStart.disabled = qhEnd.disabled = !qhEnable.checked;
  });
  qhStart.addEventListener('change', ()=> localStorage.setItem(K('quiet-start'), qhStart.value));
  qhEnd.addEventListener('change', ()=> localStorage.setItem(K('quiet-end'), qhEnd.value));
  resetQuiet.addEventListener('click', ()=>{
    ['quiet','quiet-start','quiet-end'].forEach(k=>localStorage.removeItem(K(k)));
    init();
  });

  resetAll.addEventListener('click', ()=>{
    const keys = Object.keys(localStorage);
    keys.filter(k=>k.startsWith('portal:notify:')).forEach(k=>localStorage.removeItem(k));
    init();
  });

  init();
})();
</script>
</body>
</html>
