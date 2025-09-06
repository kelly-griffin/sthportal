// Scores cards — robust init: auto-pick nearest date with data, tolerate missing elements

// ---- live data getter (no caching!) ----
const SCORES_DEFAULT = { pro: {}, farm: {}, echl: {}, juniors: {} };
function S() { return (window.UHA && window.UHA.scores) || SCORES_DEFAULT; }

(() => {
  const $  = s => document.querySelector(s);
  const $$ = s => [...document.querySelectorAll(s)];
  const pad = n => String(n).padStart(2, '0');

  // Elements (be tolerant with selectors)
  const wrap   = $('#scoresCard');
  const elStrip = $('#scoresDates');
  const elScope = $('#scoresScope');
  const elFilter = $('#scoresFilter');
  const list = $('#proScores') || (wrap && wrap.querySelector('.scores-list'));
  if (!wrap || !list) { console.warn('Scores: container not found'); return; }

  // State
  const DATES_VISIBLE = 3;
  let scope = (window.UHA && UHA.defaultScoresScope) || 'pro';
  let filter = 'all';
  let viewDate = todayStrip(new Date());
  let didPickInitial = false; // <-- only pick initial date once

  // Helpers
  function todayStrip(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
  function fmtDate(d) { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }
  function parseDateStr(s) { const [y, m, da] = s.split('-').map(Number); return new Date(y, (m || 1) - 1, da || 1); }
  function prettyDateShort(d) {
    return {
      label1: d.toLocaleDateString(undefined, { month: 'numeric', day: 'numeric' }),
      label2: d.toLocaleDateString(undefined, { weekday: 'short' }).toUpperCase()
    };
  }
  function statusKind(s = '') {
    const t = String(s).toLowerCase();
    if (t.includes('final')) return 'final';
    if (/(live|1st|2nd|3rd|ot|so)/.test(t)) return 'live';
    return 'upcoming';
  }

  // Data access (tolerant)
  function SCORES() { return (window.UHA && UHA.scores) || {}; }
  function byScope(sc) { return SCORES()[sc] || {}; }
  function datesForScope(sc) {
    return Object.keys(byScope(sc)).filter(k => /^\d{4}-\d{2}-\d{2}$/.test(k)).sort();
  }
  function gamesFor(sc, dStr) {
    const arr = (byScope(sc)[dStr] || []).map(g => ({ ...g }));
    return filter === 'all' ? arr : arr.filter(g => statusKind(g.status) === filter);
  }
  function payloadAnchorDateStr() {
    const U = (window.UHA || {}), S = (U.scores || {});
    return S.activeDay || U.simDate || null;
  }

  // Pick a good initial date ONCE
  function pickInitialDate() {
    const anchor = payloadAnchorDateStr();
    if (anchor && gamesFor(scope, anchor).length) { viewDate = parseDateStr(anchor); return; }
    const dstr = fmtDate(viewDate);
    if (gamesFor(scope, dstr).length) return;
    const ds = datesForScope(scope);
    if (ds.length) viewDate = parseDateStr(ds[ds.length - 1]); // newest with games
  }
  function ensureInitialDate() {
    if (didPickInitial) return;
    pickInitialDate();
    didPickInitial = true;
  }

  function renderDates() {
    if (!elStrip) return;
    elStrip.innerHTML = '';
    const start = new Date(viewDate); start.setDate(start.getDate() - Math.floor(DATES_VISIBLE / 2));
    for (let i = 0; i < DATES_VISIBLE; i++) {
      const d = new Date(start); d.setDate(start.getDate() + i);
      const key = fmtDate(d);
      const { label1, label2 } = prettyDateShort(d);
      const n = (byScope(scope)[key] || []).length;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'date-pill' + (fmtDate(d) === fmtDate(viewDate) ? ' active' : '');
      btn.innerHTML = `<div class="d1">${label1}</div><div class="d2">${label2}</div><div class="d3">${n ? `${n} Games` : '—'}</div>`;
      btn.addEventListener('click', () => { viewDate = todayStrip(d); render(); });
      elStrip.appendChild(btn);
    }
  }

  function teamObj(t) {
    if (!t) return { abbr: 'TBD', name: 'TBD', logo: null, sog: null };
    if (typeof t === 'string') return { abbr: t, name: t, logo: null, sog: null };
    return { abbr: t.abbr || t.name || 'TBD', name: t.name || t.abbr || 'TBD', logo: t.logo || null, sog: (typeof t.sog === 'number' ? t.sog : null) };
  }
  function logoHTML(team) {
    const abbr = (team.abbr || team.name || 'TBD').toUpperCase();
    const src = team.logo || `assets/img/logos/${abbr}_dark.svg`;
    return `<img class="logo" data-abbr="${abbr}" alt="${team.name}" src="${src}">`;
  }
  function mkTeamRow(side, t, score, link) {
    return `
      <div class="team ${side}">
        <a class="logo-link" href="${link || '#'}" title="${t.name}">${logoHTML(t)}</a>
        <div class="meta"><div class="name">${t.name}</div><div class="sog">SOG: ${t.sog ?? '—'}</div></div>
        <div class="big-score">${score ?? '—'}</div>
      </div>`;
  }

  function renderGames() {
    list.innerHTML = '';
    const key = fmtDate(viewDate);
    const games = gamesFor(scope, key);
    if (!games.length) {
      list.innerHTML = `<div class="scores-empty">No games for ${key}.</div>`;
      return;
    }
    games.forEach((g, idx) => {
      const status = statusKind(g.status);
      const away = teamObj(g.away), home = teamObj(g.home);
      const gid = g.id || `${away.abbr}-${home.abbr}-${key}-${idx}`;
      const card = document.createElement('article');
      card.className = `game-card status-${status}`;
      card.dataset.gid = gid;
      const badgeText = status === 'final' ? 'FINAL' : (status === 'live' ? 'LIVE' : (g.status || ''));
      card.innerHTML = `
        <div class="status-badge">${badgeText}</div>
        ${mkTeamRow('away', away, g.aScore, `team.php?team=${encodeURIComponent(away.abbr)}`)}
        ${mkTeamRow('home', home, g.hScore, `team.php?team=${encodeURIComponent(home.abbr)}`)}
        <!-- goals-v2 is injected by assets/js/goals.js -->
        <div class="links">
          <a class="btn ghost" href="${g.box || `boxscore.php?id=${encodeURIComponent(gid)}`}">Box Score</a>
          <a class="btn ghost" href="${g.log || `gamelog.php?id=${encodeURIComponent(gid)}`}">Game Log</a>
        </div>`;
      list.appendChild(card);
      if (window.Goals) window.Goals.inject(card, g);
    });
    window.UHA_applyLogoVariants && window.UHA_applyLogoVariants(list);
  }

  // Arrow controls (tolerant to different wrappers/classes)
  function bindNav() {
    const prev = wrap.querySelector('#scoresDates .prev, .scores-dates .prev, .scores-controls .prev, [data-nav="prev"]');
    const next = wrap.querySelector('#scoresDates .next, .scores-dates .next, .scores-controls .next, [data-nav="next"]');
    prev && prev.addEventListener('click', () => { viewDate.setDate(viewDate.getDate() - 1); render(); });
    next && next.addEventListener('click', () => { viewDate.setDate(viewDate.getDate() + 1); render(); });
  }

  function render() { renderDates(); renderGames(); }                // re-render only
  function renderAll() { ensureInitialDate(); render(); bindNav(); }  // one-time init + render

  // Initial render; re-render once when payload lands (keeps your activeDay)
  renderAll();
  document.addEventListener('UHA:scores-ready', () => { didPickInitial = false; renderAll(); }, { once: true });

  // helper for quick jumps
  window.UHA = window.UHA || {};
  window.UHA._scoresGoto = (dateStr) => { viewDate = todayStrip(parseDateStr(dateStr)); render(); };
})();
