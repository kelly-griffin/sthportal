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
    <style>
        :root {
            --stage: #585858ff;
            --ink: #E8EEF5;
            --ink-soft: #95A3B4;
            --line: #2F3F53;
            --card: #0D1117;
            --card-border: #FFFFFF1A;
        }

        body {
            margin: 0;
            background: #202428;
            color: var(--ink);
            font: 14px/1.5 system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif
        }

        .site {
            width: 1200px;
            margin: 0 auto;
            min-height: 100vh
        }

        .canvas {
            padding: 0 16px 40px
        }

        .wrap {
            max-width: 1000px;
            margin: 20px auto
        }

        .page-surface {
            margin: 12px 0 32px;
            padding: 16px;
            background: var(--stage);
            border-radius: 16px;
            box-shadow: inset 0 1px 0 #ffffff0d, 0 0 0 1px #ffffff0f
        }

        h1 {
            margin: 0 0 6px;
            font-size: 28px
        }

        .muted {
            color: var(--ink-soft)
        }

        .grid {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 16px
        }

        @media(max-width:920px) {
            .grid {
                grid-template-columns: 1fr
            }
        }

        .inbox {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            max-height: 70vh;
            overflow: auto
        }

        .thread {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 12px;
            max-height: 70vh;
            display: grid;
            grid-template-rows: auto 1fr auto
        }

        .toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 8px
        }

        .toolbar input {
            flex: 1;
            background: #0f1420;
            color: #e6eef8;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px 10px
        }

        .btn {
            display: inline-block;
            background: #1B2431;
            border: 1px solid var(--line);
            color: #E6EEF8;
            border-radius: 10px;
            padding: 6px 10px;
            text-decoration: none;
            cursor: pointer
        }

        .avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            display: block
        }

        .msg {
            display: grid;
            grid-template-columns: 28px 1fr;
            gap: 8px;
            margin: 6px 0
        }

        .msg .bubble {
            background: #121a26;
            border: 1px solid #223149;
            border-radius: 10px;
            padding: 8px 10px;
            color: #E8EEF5
        }

        .compose {
            display: flex;
            gap: 8px;
            margin-top: 8px
        }

        .compose input {
            flex: 1;
            background: #0f1420;
            color: #e6eef8;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px 10px
        }

        /* compact embed */
        <?php if ($embed): ?>
            body {
                background: #0f1420
            }

            .page-surface {
                background: transparent;
                box-shadow: none;
                padding: 0;
                margin: 0
            }

        <?php endif; ?>
        /* === Messages layout scope to avoid global collisions === */
        .dm .grid {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 16px;
            align-items: start;
        }

        @media (max-width: 860px) {
            .dm .grid {
                grid-template-columns: 1fr;
            }
        }


        .dm .inbox {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            min-height: 500px;
            overflow: auto;
        }

        .dm .thread {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 12px;
            min-height: 500px;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            /* toolbar, badge, log, compose */
            gap: 8px;
        }

        .dm .toolbar {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .dm .compose {
            display: flex;
            gap: 8px;
        }

        .dm .avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
        }

        .dm .msg {
            display: grid;
            grid-template-columns: 28px 1fr;
            gap: 8px;
        }

        /* Let the error line span both columns so it doesn't steal column 2 */
        .dm #dmError {
            grid-column: 1 / -1;
        }
    </style>
</head>

<body>
    <div class="site">
        <?php if (!$embed): ?>
            <?php include __DIR__ . '/../includes/topbar.php'; ?>
            <?php include __DIR__ . '/../includes/leaguebar.php'; ?>
        <?php endif; ?>

        <div class="canvas">
            <div class="wrap">
                <div class="page-surface">
                    <?php if (!$embed): ?>
                        <h1><?= h($title) ?></h1><?php endif; ?>
                    <div class="dm">
                        <div class="grid">
                            <div class="inbox" id="inbox"></div>

                            <div class="thread">
                                <div class="toolbar">
                                    <input id="userSearch" placeholder="Search user…" />
                                    <button class="btn" id="startBtn" type="button">Start</button>
                                </div>
                                <div id="dmError" style="color:#ffb4b4;padding:6px 0;min-height:1.2em"></div>
                                <div id="peerBadge" style="display:none;margin:-2px 0 6px;color:#CFE1F3">
                                    To: <strong id="peerName"></strong>
                                </div>

                                <div id="threadLog" style="overflow:auto"></div>
                                <form class="compose" id="dmForm" autocomplete="off">
                                    <input id="dmInput" maxlength="2000" placeholder="Type a message…" />
                                    <button class="btn" type="submit">Send</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
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