<?php
// Chat — full replacement
// Renders league chat with optional embed mode and resilient auth handling

require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Chat';
$embed = !empty($_GET['embed']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h($title) ?> — UHA Portal</title>
  <style>
    :root{
      --stage:#585858ff; --ink:#E8EEF5; --ink-soft:#95A3B4;
      --card:#0D1117; --card-border:#FFFFFF1A; --line:#2F3F53;
    }
    body{margin:0;background:#202428;color:var(--ink);font:14px/1.5 system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
    .site{width:1200px;margin:0 auto;min-height:100vh}
    .canvas{padding:0 16px 40px}
    .wrap{max-width:1000px;margin:20px auto}
    .page-surface{margin:12px 0 32px;padding:16px;background:var(--stage);border-radius:16px;box-shadow:inset 0 1px 0 #ffffff0d,0 0 0 1px #ffffff0f}
    h1{margin:0 0 6px;font-size:28px}
    .muted{color:var(--ink-soft)}

    .chat-card{background:var(--card);border:1px solid var(--card-border);border-radius:16px;padding:12px;box-shadow:inset 0 1px 0 #ffffff12}
    .toolbar{display:flex;gap:8px;align-items:center;margin:2px 0 8px}
    .toolbar select{background:#0f1420;border:1px solid var(--line);border-radius:10px;padding:6px 10px;color:#E6EEF8}
    .log{background:#0f1420;border:1px solid var(--line);border-radius:12px;min-height:320px;max-height:56vh;overflow:auto;padding:10px}
    .composer{display:flex;gap:8px;align-items:center;margin-top:10px}
    .composer input{flex:1;background:#0f1420;border:1px solid var(--line);border-radius:10px;padding:8px 10px;color:#E6EEF8}
    .btn{display:inline-block;background:#1B2431;border:1px solid var(--line);color:#E6EEF8;border-radius:10px;padding:6px 10px;text-decoration:none;cursor:pointer}

    .avatar{width:24px;height:24px;border-radius:50%;object-fit:cover;display:block}
    .msg{display:grid;grid-template-columns:24px 1fr;gap:8px;margin:6px 0}
    .msg .bubble{background:#121a26;border:1px solid #223149;border-radius:10px;padding:6px 8px;color:#E8EEF5}
    .msg .t{opacity:.7;font-size:12px;margin-left:6px}

    /* Compact embed */
    <?php if ($embed): ?>
    body{background:#0f1420}
    .page-surface{background:transparent;box-shadow:none;padding:0;margin:0}
    <?php endif; ?>
  </style>
</head>
<body>
  <div class="site">
    <?php if (!$embed): ?>
      <?php include __DIR__ . '/../includes/topbar.php'; ?>
      <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
    <?php endif; ?>

    <div class="canvas"><div class="wrap"><div class="page-surface">
      <?php if (!$embed): ?>
        <h1><?= h($title) ?></h1>
        <p class="muted">League chat (beta). Keep it friendly—mods can remove messages.</p>
      <?php endif; ?>

      <section class="chat-card">
        <div class="toolbar">
          <label>Channel&nbsp;
            <select id="channel">
              <option value="general" selected>General</option>
            </select>
          </label>
          <span class="muted" style="margin-left:auto">Messages auto‑refresh every ~2.5s.</span>
        </div>
        <div class="log" id="log" aria-live="polite"></div>
        <form class="composer" id="chatForm" autocomplete="off">
          <input id="chatInput" maxlength="2000" placeholder="Type a message…" />
          <button class="btn" id="sendBtn" type="submit">Send</button>
          <button class="btn" id="refreshBtn" type="button">Refresh</button>
        </form>
      </section>
    </div></div></div>
  </div>

  <script>
  (function(){
    const pollUrl = '<?= u('api/chat/poll.php') ?>';
    const postUrl = '<?= u('api/chat/post.php') ?>';
    const AV = '<?= u('assets/avatar.php') ?>';

    const channelSel = document.getElementById('channel');
    const log = document.getElementById('log');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const refreshBtn = document.getElementById('refreshBtn');
    const sendBtn = document.getElementById('sendBtn');

    let lastId = 0, busy = false, timer = null;

    function esc(s){return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));}
    function time(ts){try{return new Date((ts||0)*1000).toLocaleTimeString();}catch{return ''}}

    function ensureAuthBanner(){
      if (document.getElementById('auth-banner')) return;
      const b = document.createElement('div');
      b.id = 'auth-banner';
      b.style.cssText = 'margin:0 0 8px;padding:8px 10px;border-radius:10px;background:#2b3646;color:#fff;border:1px solid #3b4a5f;display:flex;justify-content:space-between;align-items:center';
      b.innerHTML = `<span>You’re logged out. Please sign in to continue.</span>
                     <a href="<?= h(u('login.php?back=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'))) ?>" target="_top" class="btn" style="background:#1c78ff;border-color:#185fd0">Login</a>`;
      const host = document.querySelector('.page-surface') || document.body;
      host.prepend(b);
    }

    function disableChat(off){ input.disabled = off; sendBtn.disabled = off; }

    async function poll(force){
      if (busy && !force) return; busy = true;
      try {
        const res = await fetch(pollUrl + '?channel=' + encodeURIComponent(channelSel.value) + '&after=' + lastId);
        if (res.status === 401){ ensureAuthBanner(); disableChat(true); return; }
        if (!res.ok) return;
        const data = await res.json();
        (data.messages || []).forEach(m => {
          lastId = Math.max(lastId, m.id||0);
          const el = document.createElement('div');
          el.className = 'msg';
          const name = m.username || m.user_name || m.display_name || ('User #' + (m.user_id||0));
          el.innerHTML = `<img class="avatar" src="${AV}?u=${m.user_id||0}&s=24" alt="">
                          <div class="bubble"><strong>${esc(name)}</strong>
                          <span class="t">${time(m.ts)}</span><br>${esc(m.body)}</div>`;
          log.appendChild(el);
        });
        if ((data.messages||[]).length) log.scrollTop = log.scrollHeight;
      } catch(e) {
        // swallow
      } finally { busy = false; }
    }

    async function sendMessage(){
      const body = input.value.trim(); if (!body) return;
      input.value = '';
      try {
        const res = await fetch(postUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({channel: channelSel.value || 'general', body})});
        if (res.status === 401){ ensureAuthBanner(); disableChat(true); return; }
        if (!res.ok) return;
        await poll(true);
      } catch(e) {}
    }

    form.addEventListener('submit', (e)=>{ e.preventDefault(); sendMessage(); });
    refreshBtn.addEventListener('click', ()=> poll(true));
    channelSel.addEventListener('change', ()=> { lastId = 0; log.innerHTML=''; poll(true); });

    poll(true);
    timer = setInterval(()=> poll(false), 2500);
  })();
  </script>
</body>
</html>
