<?php
// Messages — full replacement
// Direct messages UI with embed mode, inbox, thread, avatars, and auth-aware UX

require_once __DIR__ . '/../includes/bootstrap.php';
$title = 'Messages';
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
            <div class="messages-container">
                <div class="messages-card">
                    <?php if (!$embed): ?>
                        <h1><?= h($title) ?></h1><?php endif; ?>

                    <div class="msg-grid">
                        <!-- LEFT: Inbox -->
                        <aside id="inbox" class="dm-inbox">
                            <div class="scroll"><!-- inbox items render here --></div>
                        </aside>

                        <!-- RIGHT: Conversation -->
                        <section class="dm-panel">
                            <div class="toolbar">
                                <input type="search" id="userSearch" placeholder="Search user…" />
                                <button class="btn" id="startBtn" type="button">Start</button>
                            </div>
                            <!-- error + recipient -->
                            <div id="dmError" class="dm-error" role="alert" hidden></div>

                            <div class="to-line">
                                <span id="peerBadge" class="dm-badge"></span>
                                <span class="to"><span class="label">To:</span> <strong id="peerName"></strong></span>
                            </div>
                            <!-- conversation log -->
                            <div id="threadLog" class="log" aria-live="polite"></div>

                            <!-- composer -->
                            <form class="composer" id="dmForm" autocomplete="off">
                                <input id="dmInput" maxlength="2000" placeholder="Type a message…" />
                                <button class="btn" id="sendBtn" type="submit">Send</button>
                            </form>
                        </section>
                    </div>
                </div>
            </div>
            <script>
                (function () {
                    const pollUrl = '<?= u('api/dm/poll.php') ?>';
                    const postUrl = '<?= u('api/dm/post.php') ?>';
                    const findUrl = '<?= u('api/dm/users.php') ?>';
                    const AV = '<?= u('assets/avatar.php') ?>';

                    const inbox = document.getElementById('inbox');
                    const errBox = document.getElementById('dmError');
                    const log = document.getElementById('threadLog');
                    const form = document.getElementById('dmForm');
                    const input = document.getElementById('dmInput');
                    const userSearch = document.getElementById('userSearch');
                    const startBtn = document.getElementById('startBtn');
                    const badge = document.getElementById('peerBadge');
                    const badgeName = document.getElementById('peerName');

                    const myId = <?= (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0) ?>;
                    let currentPeer = 0, currentPeerName = '', lastId = 0, busy = false, timer = null;

                    function esc(s) { return (s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
                    function time(ts) { try { return new Date((ts || 0) * 1000).toLocaleTimeString(); } catch { return '' } }
                    function setError(msg) { errBox.textContent = msg || ''; }
                    function ensureAuthBanner() {
                        if (document.getElementById('auth-banner')) return;
                        const b = document.createElement('div');
                        b.id = 'auth-banner';
                        b.style.cssText = 'margin:0 0 8px;padding:8px 10px;border-radius:10px;background:#2b3646;color:#fff;border:1px solid #3b4a5f;display:flex;justify-content:space-between;align-items:center';
                        b.innerHTML = `<span>You’re logged out. Please sign in to continue.</span>
                   <a href="<?= h(u('login.php?back=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'))) ?>" target="_top"
                      class="btn" style="background:#1c78ff;border-color:#185fd0">Login</a>`;
                        const host = document.querySelector('.page-surface') || document.body;
                        host.prepend(b);
                    }
                    function disableDM(off) {
                        input.disabled = off || !currentPeer;
                        form.querySelector('button[type="submit"]').disabled = off || !currentPeer;
                        startBtn.disabled = off;
                    }

                    async function loadInbox() {
                        setError('');
                        const res = await fetch(pollUrl);
                        if (res.status === 401) { ensureAuthBanner(); disableDM(true); setError('Not signed in.'); return; }
                        if (!res.ok) { setError('Inbox error: ' + res.status); return; }
                        const data = await res.json();
                        inbox.innerHTML = (data.peers || []).map(p =>
                            `<button class="btn" style="display:flex;align-items:center;gap:8px;width:calc(100% - 12px);margin:6px"
               data-peer="${p.peer_id}" data-name="${esc(p.peer_name || ('User #' + p.peer_id))}">
         <img class="avatar" src="${AV}?u=${p.peer_id}&s=28" alt="">
         <span>${esc(p.peer_name || ('User #' + p.peer_id))}</span>
       </button>`).join('');
                        inbox.querySelectorAll('button[data-peer]').forEach(b => {
                            b.addEventListener('click', () => selectPeer(parseInt(b.dataset.peer, 10), b.dataset.name));
                        });
                    }

                    function selectPeer(id, name) {
                        currentPeer = id; currentPeerName = name || ('User #' + id);
                        badgeName.textContent = currentPeerName;
                        badge.style.display = 'block';
                        disableDM(false);
                        lastId = 0; log.innerHTML = ''; setError('');
                        loadThread(true);
                    }

                    async function loadThread(force = false) {
                        if (!currentPeer) return;
                        if (busy && !force) return; busy = true;
                        try {
                            const res = await fetch(`${pollUrl}?with=${currentPeer}&after=${lastId}`);
                            if (res.status === 401) { ensureAuthBanner(); disableDM(true); setError('Not signed in.'); return; }
                            if (!res.ok) { setError('Thread error: ' + res.status); return; }
                            const data = await res.json();
                            (data.messages || []).forEach(m => {
                                lastId = Math.max(lastId, m.id || 0);
                                const whoId = m.sender_id;
                                const whoName = (whoId === myId) ? 'You' : (m.sender_name || ('User #' + whoId));
                                const el = document.createElement('div');
                                el.className = 'msg';
                                el.innerHTML = `<img class="avatar" src="${AV}?u=${whoId}&s=28" alt="">
                        <div class="bubble"><strong>${esc(whoName)}</strong>
                        <span class="t" style="opacity:.7;font-size:12px">${time(m.ts)}</span><br>${esc(m.body)}</div>`;
                                log.appendChild(el);
                            });
                            if ((data.messages || []).length) log.scrollTop = log.scrollHeight;
                        } finally { busy = false; }
                    }

                    async function startConversation() {
                        const q = userSearch.value.trim(); if (!q) { setError('Type a name to search.'); return; }
                        setError('');
                        try {
                            const res = await fetch(findUrl + '?q=' + encodeURIComponent(q));
                            if (res.status === 401) { ensureAuthBanner(); disableDM(true); setError('Not signed in.'); return; }
                            if (!res.ok) { setError('Search error: ' + res.status); return; }
                            const data = await res.json();
                            if (!data.users || !data.users.length) { setError('No users found.'); return; }
                            const u = data.users[0];
                            console.debug('[DM] match', u);
                            selectPeer(u.id, u.name);
                        } catch (e) { setError('Search failed.'); }
                    }

                    async function sendDM() {
                        if (!currentPeer) { setError('Pick a recipient first.'); return; }
                        const body = input.value.trim(); if (!body) return;
                        input.value = ''; setError('');
                        const res = await fetch(postUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ to: currentPeer, body }) });
                        if (res.status === 401) { ensureAuthBanner(); disableDM(true); setError('Not signed in.'); return; }
                        if (!res.ok) { setError('Send error: ' + res.status); return; }
                        loadThread(true);
                    }

                    startBtn.addEventListener('click', startConversation);
                    form.addEventListener('submit', (e) => { e.preventDefault(); sendDM(); });

                    // boot
                    disableDM(false); // disabled until a peer is chosen
                    loadInbox();
                    setInterval(() => loadThread(false), 2500);
                })();
            </script>

</body>

</html>