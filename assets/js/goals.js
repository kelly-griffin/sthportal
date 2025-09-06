// assets/js/goals.js — spotlight goals (avatar + 3 rows), with name/totals formatting
(() => {
    const PERIOD_ORDER = { "1": 1, "2": 2, "3": 3, "OT": 4 };

    function parseTimeToSec(period, mmss) {
        const [m, s] = String(mmss).split(':').map(x => parseInt(x, 10) || 0);
        return ((PERIOD_ORDER[period] - 1) * 1200) + (m * 60 + s);
    }

    // ---- formatting helpers ---------------------------------------------------
    function stripTimeTail(s) {
        return String(s || '').replace(/\s+at\s+\d{1,2}:\d{2}.*$/i, '').trim();
    }
    function cleanScorer(raw) {
        // remove trailing "(...)" like assists/PP tags, then trailing goal-count digits
        let s = stripTimeTail(raw);
        s = s.replace(/\s*\([^)]*\)\s*$/, '');
        s = s.replace(/\s+\d+$/, '');
        return s.trim();
    }
    function parseNameTotal(raw) {
        // from "Alex DeBrincat 4 (…)" -> { name:"Alex DeBrincat", total:4 }
        let s = stripTimeTail(raw).replace(/\s*\([^)]*\)\s*$/, '');
        const m = s.match(/^(.*?)(?:\s+(\d+))?$/);
        const name = (m && m[1]) ? m[1].trim() : s.trim();
        const t = (m && m[2]) ? parseInt(m[2], 10) : null;
        return { name, total: Number.isFinite(t) ? t : null };
    }
    function abbrevName(full) {
        // "Michael Rasmussen" -> "M. Rasmussen"; keeps compound last names
        const parts = String(full || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '';
        const first = parts[0];
        const last = parts.length > 1 ? parts.slice(1).join(' ') : '';
        return (first ? first[0].toUpperCase() + '.' : '') + (last ? ' ' + last : '');
    }
    function isTagToken(tok) {
        const u = String(tok || '')
            .trim()
            .toUpperCase()
            .replace(/[\s\.-]/g, '');
        if (!u) return true; // ignore empties
        // common strength/notes
        const TAGS = new Set([
            'PP', 'PPG', 'PPO', 'SH', 'SHG', 'EN', 'ENG', 'EV', 'OT', 'GWG', 'PS', 'SO',
            '4ON4', '3ON3', '5V5', '4V4', '3V3'
        ]);
        return TAGS.has(u) || /^\d+V\d+$/.test(u);
    }

    function extractAssistsFromString(raw) {
        // read parentheses groups BEFORE " at MM:SS"; pick the first group with names
        const s = String(raw || '').replace(/\s+at\s+\d{1,2}:\d{2}.*$/i, '');
        const groups = [...s.matchAll(/\(([^()]*)\)/g)].map(m => m[1]);
        const TAGS = new Set(['PP', 'PPG', 'PPO', 'SH', 'SHG', 'EN', 'ENG', 'EV', 'OT', 'GWG', 'PS', 'SO', '4ON4', '3ON3', '4V4', '3V3']);
        for (const g of groups) {
            const toks = g.split(',').map(t => t.trim()).filter(Boolean);
            if (toks.length === 1 && /unassisted/i.test(toks[0])) return [];
            const names = toks.filter(tok => {
                const u = tok.toUpperCase().replace(/[\s\.-]/g, '');
                return !TAGS.has(u) && !/^\d+V\d+$/.test(u);
            }).map(n => n.replace(/\s+\d+$/, ''));
            if (names.length) return names;
        }
        return [];
    }

    function extractStrengthTag(raw) {
        // Look for the *last* (...) group and map tags like PP -> PPG, SH -> SHG, EN -> EN
        const s = String(raw || '').replace(/\s+at\s+\d{1,2}:\d{2}.*$/i, '');
        const groups = [...s.matchAll(/\(([^()]*)\)/g)].map(m => m[1].trim());
        if (!groups.length) return null;
        const last = groups[groups.length - 1];
        const toks = last.split(',').map(t => t.trim().toUpperCase().replace(/[\s\.-]/g, ''));
        const map = new Map([
            ['PP', 'PPG'], ['PPG', 'PPG'], ['PPO', 'PPG'],
            ['SH', 'SHG'], ['SHG', 'SHG'],
            ['EN', 'EN'], ['ENG', 'EN'],
            ['PS', 'PS'], ['SO', 'SO']
        ]);
        for (const t of toks) if (map.has(t)) return map.get(t);
        return null;
    }
function strengthFromGoal(r) {
  if (r?.note) return r.note; // comes from PHP now
  // fallback: parse from scorer string if note is missing
  const s = String(r?.player || '').replace(/\s+at\s+\d{1,2}:\d{2}.*$/i, '');
  const groups = [...s.matchAll(/\(([^()]*)\)/g)].map(m => m[1].trim());
  if (!groups.length) return null;
  const last = groups[groups.length - 1];
  const toks = last.split(',').map(t => t.trim().toUpperCase().replace(/[\s\.-]/g,''));
  const map = new Map([['PP','PPG'],['PPG','PPG'],['PPO','PPG'],['SH','SHG'],['SHG','SHG'],['EN','EN'],['ENG','EN'],['PS','PS'],['SO','SO']]);
  for (const t of toks) if (map.has(t)) return map.get(t);
  return null;
}


    // ---- headshots + team color ----------------------------------------------
    function resolveHeadshot(name, teamAbbr) {
        try {
            const id = window.UHA?.playerIdResolver?.(name, teamAbbr);
            if (id) return `https://assets.nhle.com/mugs/nhl/20252026/${String(teamAbbr).toUpperCase()}/${id}.png`;
        } catch { }
        return null;
    }
    function teamColor(abbr) {
        const map = window.UHA?.teamColors || {};
        const v = map[abbr]?.primary || map[abbr];
        return v || '#2a2f3a';
    }

    // ---- merge & order goals --------------------------------------------------
    function buildTimeline(game) {
        const A = game.away?.abbr || 'AWY';
        const H = game.home?.abbr || 'HOM';
        const rows = [];
        (Array.isArray(game.goals?.away) ? game.goals.away : []).forEach(g => rows.push({ side: 'away', ...g }));
        (Array.isArray(game.goals?.home) ? game.goals.home : []).forEach(g => rows.push({ side: 'home', ...g }));
        rows.sort((a, b) => {
            const pa = PERIOD_ORDER[a.period] || 9;
            const pb = PERIOD_ORDER[b.period] || 9;
            if (pa !== pb) return pa - pb;
            return parseTimeToSec(a.period, a.time) - parseTimeToSec(b.period, b.time);
        });
        let a = 0, h = 0;
        rows.forEach(r => { (r.side === 'away') ? a++ : h++; r.afterA = a; r.afterH = h; r.A = A; r.H = H; });
        return rows;
    }

    // ---- render ---------------------------------------------------------------
    function renderSpotlight(card, game, rows, idx) {
        card.querySelector('.goals-v2, .goals-v3')?.remove();
        if (!rows.length) return;

        const r = rows[idx];
        const teamAbbr = r.side === 'away' ? game.away?.abbr : game.home?.abbr;

        // derive display values (fallback to parsed totals if PHP didn’t provide them)
        const { name: scorerName, total: scorerTotalParsed } = parseNameTotal(r.player);
        const scorerTotal = (typeof r.totals?.scorer === 'number') ? r.totals.scorer : scorerTotalParsed;

        // wrapper
        const wrap = document.createElement('div');
        wrap.className = 'goals-v3';
        wrap.dataset.count = rows.length;

        const sp = document.createElement('div');
        sp.className = 'g3-spot';

        // avatar
        const av = document.createElement('div');
        av.className = 'g3-av';
        const ring = teamColor(teamAbbr || '');
        av.style.boxShadow = `0 0 0 2px ${ring} inset, 0 0 0 1px var(--color-border, #2a2f3a)`;
        const mugUrl = resolveHeadshot(scorerName, teamAbbr);
        if (mugUrl) {
            const img = document.createElement('img');
            img.loading = 'lazy'; img.alt = scorerName; img.src = mugUrl;
            av.appendChild(img);
        } else {
            const ph = document.createElement('div');
            ph.className = 'g3-ph'; ph.textContent = (scorerName[0] || '').toUpperCase();
            ph.style.background = ring; av.appendChild(ph);
        }
        sp.appendChild(av);

        // text
        const txt = document.createElement('div');
        txt.className = 'g3-text';

        // Row 1: Scorer (##)
        const row1 = document.createElement('div');
        row1.className = 'g3-row1';
        const scorerEl = document.createElement('span');
        scorerEl.className = 'g3-scorer';
        scorerEl.textContent = scorerName;
        row1.appendChild(scorerEl);
        if (scorerTotal != null) {
            const t = document.createElement('span'); t.className = 'g3-total'; t.textContent = ` (${scorerTotal})`;
            row1.appendChild(t);
        }
        txt.appendChild(row1);
        const strength = strengthFromGoal(r);
if (strength) {
  const badge = document.createElement('span');
  badge.className = 'g3-badge ' + (
    strength === 'PPG' ? 'is-ppg' :
    strength === 'SHG' ? 'is-shg' :
    strength === 'EN'  ? 'is-en'  :
    strength === 'PS'  ? 'is-ps'  : ''
  );
  badge.textContent = strength;
  row1.appendChild(badge);
}


        // Row 2: Assist1 (##), Assist2 (##)
        const row2 = document.createElement('div');
        row2.className = 'g3-row2';

        // prefer extractor data; fallback to parsing from scorer string
        let assistNames = Array.isArray(r.assists) && r.assists.length
            ? r.assists
            : extractAssistsFromString(r.player);

        // filter any lingering tags (defensive)
        assistNames = assistNames.filter(n => !isTagToken(n));

        const assistTotals = Array.isArray(r.totals?.assists) ? r.totals.assists : [];

        const assistsDisplay = assistNames.map((raw, i) => {
            const { name, total } = parseNameTotal(raw);
            const shownTotal = (typeof assistTotals[i] === 'number') ? assistTotals[i] : total;
            return shownTotal != null ? `${abbrevName(name)} (${shownTotal})` : abbrevName(name);
        }).join(', ');

        // hide the row if truly unassisted/empty
        if (!assistsDisplay || /unassisted/i.test(assistsDisplay)) {
            row2.style.display = 'none';
        } else {
            row2.textContent = assistsDisplay;
        }
        txt.appendChild(row2);

        // Row 3: TEA # - TEA # (P# MM:SS)  (time ONLY here)
        const row3 = document.createElement('div');
        row3.className = 'g3-row3';
        const A = r.A, H = r.H;
        row3.innerHTML = (r.side === 'away')
            ? `${A} <b>${r.afterA}</b> - ${H} ${r.afterH} (P${r.period} ${r.time})`
            : `${A} ${r.afterA} - <b>${H} ${r.afterH}</b> (P${r.period} ${r.time})`;
        txt.appendChild(row3);

        sp.appendChild(txt);
        wrap.appendChild(sp);

        // nav (ticks + arrows) if >1
        if (rows.length > 1) {
            const nav = document.createElement('div'); nav.className = 'g3-nav';
            const prev = document.createElement('button'); prev.className = 'g3-prev'; prev.type = 'button'; prev.textContent = '‹';
            const ticks = document.createElement('div'); ticks.className = 'g3-ticks';
            rows.forEach((_, i) => {
                const t = document.createElement('span');
                t.className = 'g3-tick' + (i === idx ? ' is-active' : '');
                t.dataset.idx = String(i);
                ticks.appendChild(t);
            });
            const next = document.createElement('button'); next.className = 'g3-next'; next.type = 'button'; next.textContent = '›';
            nav.append(prev, ticks, next); wrap.appendChild(nav);

            const gotoIdx = (n) => {
                const N = rows.length; const j = ((n % N) + N) % N;
                renderSpotlight(card, game, rows, j);
            };
            prev.addEventListener('click', () => gotoIdx(idx - 1));
            next.addEventListener('click', () => gotoIdx(idx + 1));
            ticks.addEventListener('click', (e) => {
                const el = e.target.closest('.g3-tick'); if (el?.dataset.idx) gotoIdx(parseInt(el.dataset.idx, 10));
            });
        }

        const links = card.querySelector('.links');
        if (links) card.insertBefore(wrap, links); else card.appendChild(wrap);
    }

    function inject(card, game) {
        if (!card || !game || !game.goals) return;
        const rows = buildTimeline(game);
        if (!rows.length) return;
        renderSpotlight(card, game, rows, 0);
    }

    window.Goals = { inject };

    // Passive sweep if this file loads after scores.js
    try {
        const wrap = document.querySelector('#scoresCard');
        if (wrap && window.UHA?.scores) {
            const day = UHA.scores.activeDay || UHA.simDate;
            const games = Array.isArray(UHA.scores.pro?.[day]) ? UHA.scores.pro[day] : [];
            const byId = Object.fromEntries(games.map(g => [g.id, g]));
            wrap.querySelectorAll('.scores-list .game-card').forEach(card => {
                const gid = card.getAttribute('data-gid');
                if (gid && byId[gid]) inject(card, byId[gid]);
            });
        }
    } catch { }
})();
