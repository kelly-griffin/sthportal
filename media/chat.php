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
</head>

<body>
  <div class="site">
    <?php if (!$embed): ?>
      <?php include __DIR__ . '/../includes/topbar.php'; ?>
      <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
    <?php endif; ?>

    <div class="canvas">
      <div class="chat-container">
        <div class="chat-card">
          <?php if (!$embed): ?>
            <h1><?= h($title) ?></h1>
            <p class="chat-lead">League chat (beta). Keep it friendly—mods can remove messages.</p>
          <?php endif; ?>

          <section class="chat-box">
            <div class="toolbar">
              <label>Channel&nbsp;
                <select id="channel">
                  <option value="general" selected>General</option>
                </select>
              </label>
              <span class="muted">Messages auto‑refresh every ~2.5s.</span>
            </div>
            <div class="log" id="log" aria-live="polite"></div>
            <form class="composer" id="chatForm" autocomplete="off">
              <input id="chatInput" maxlength="2000" placeholder="Type a message…" />
              <button class="btn" id="sendBtn" type="submit">Send</button>
              <button class="btn" id="refreshBtn" type="button">Refresh</button>
            </form>
          </section>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function () {
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

      function esc(s) { return (s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[c])); }
      function time(ts) { try { return new Date((ts || 0) * 1000).toLocaleTimeString(); } catch { return '' } }

      function ensureAuthBanner() {
        if (document.getElementById('auth-banner')) return;
        const b = document.createElement('div');
        b.id = 'auth-banner';
        b.style.cssText = 'margin:0 0 8px;padding:8px 10px;border-radius:10px;background:#2b3646;color:#fff;border:1px solid #3b4a5f;display:flex;justify-content:space-between;align-items:center';
        b.innerHTML = `<span>You’re logged out. Please sign in to continue.</span>
                     <a href="<?= h(u('login.php?back=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'))) ?>" target="_top" class="btn" style="background:#1c78ff;border-color:#185fd0">Login</a>`;
        const host = document.querySelector('.page-surface') || document.body;
        host.prepend(b);
      }

      function disableChat(off) { input.disabled = off; sendBtn.disabled = off; }

      async function poll(force) {
        if (busy && !force) return; busy = true;
        try {
          const res = await fetch(pollUrl + '?channel=' + encodeURIComponent(channelSel.value) + '&after=' + lastId);
          if (res.status === 401) { ensureAuthBanner(); disableChat(true); return; }
          if (!res.ok) return;
          const data = await res.json();
          (data.messages || []).forEach(m => {
            lastId = Math.max(lastId, m.id || 0);
            const el = document.createElement('div');
            el.className = 'msg';
            const name = m.username || m.user_name || m.display_name || ('User #' + (m.user_id || 0));
            el.innerHTML = `<img class="avatar" src="${AV}?u=${m.user_id || 0}&s=24" alt="">
                          <div class="bubble"><strong>${esc(name)}</strong>
                          <span class="t">${time(m.ts)}</span><br>${esc(m.body)}</div>`;
            log.appendChild(el);
          });
          if ((data.messages || []).length) log.scrollTop = log.scrollHeight;
        } catch (e) {
          // swallow
        } finally { busy = false; }
      }

      async function sendMessage() {
        const body = input.value.trim(); if (!body) return;
        input.value = '';
        try {
          const res = await fetch(postUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ channel: channelSel.value || 'general', body }) });
          if (res.status === 401) { ensureAuthBanner(); disableChat(true); return; }
          if (!res.ok) return;
          await poll(true);
        } catch (e) { }
      }

      form.addEventListener('submit', (e) => { e.preventDefault(); sendMessage(); });
      refreshBtn.addEventListener('click', () => poll(true));
      channelSel.addEventListener('change', () => { lastId = 0; log.innerHTML = ''; poll(true); });

      poll(true);
      timer = setInterval(() => poll(false), 2500);
    })();
  </script>
</body>

</html>