// assets/js/home.js — stable reset (ticker + feature + leaders + tx toggle + log link normalizer)
// No jQuery. No unknown globals. Goals hydrator is intentionally NOT running here.

(() => {
  const U = (window.UHA = window.UHA || {});
  const qs  = (s, el=document) => el.querySelector(s);
  const qsa = (s, el=document) => Array.from(el.querySelectorAll(s));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  /* -------------------- Normalize “Game Log” links (safe) -------------------- */
  (function normalizeLogLinks(){
    const root = document.getElementById('scoresCard');
    if (!root) return;

    const fileByScope = { pro:'ProGameLog.php', farm:'FarmGameLog.php', echl:'ECHLGameLog.php', juniors:'JuniorsGameLog.php' };
    const getScope = () => (document.getElementById('scoresScope')?.value || 'pro').toLowerCase();

    function rewrite(scope) {
      const target = fileByScope[scope] || 'ProGameLog.php';
      root.querySelectorAll('.links a').forEach(a => {
        const href = a.getAttribute('href') || '';
        if (/gamelog\.php/i.test(href)) {
          a.setAttribute('href', href.replace(/(^|\/)gamelog\.php/i, `$1${target}`));
        }
      });
    }

    const run = () => rewrite(getScope());
    run();

    const obsTarget = root.querySelector('#proScores') || root.querySelector('.scores-list') || root;
    new MutationObserver(run).observe(obsTarget, { childList: true, subtree: true });
    document.getElementById('scoresScope')?.addEventListener('change', run);
  })();

  /* -------------------- Ticker -------------------- */
  function initTicker() {
    const track = qs('#ticker-track');
    if (!track) return;
    const items = Array.isArray(U.ticker) ? U.ticker
      : (U.ticker && Array.isArray(U.ticker.items) ? U.ticker.items : []);
    track.innerHTML = (items || []).map(it => {
      const text = typeof it === 'string'
        ? it
        : (it.text ?? `${it.home ?? ''} ${it.hs ?? ''} — ${it.away ?? ''} ${it.as ?? ''}`);
      return `<span class="ticker-item">${esc(text)}</span>`;
    }).join('');
  }

  /* -------------------- Feature (hero) -------------------- */
  function setText(sel, v){ const el = qs(sel); if (el) el.textContent = String(v ?? ''); }
  function initFeature(){
    const f = U.feature || {};
    setText('#feature-team-abbr', f.teamAbbr || f.abbr || '');
    setText('#feature-team-name', f.teamName || f.team || '');
    setText('#feature-headline',  f.headline || 'Welcome to the Portal');
    setText('#feature-dek',       f.dek || f.summary || 'Live data will appear as soon as uploads/DB are wired.');
    setText('#feature-time',      f.time || f.when || 'just now');
  }

  /* -------------------- Leaders -------------------- */
  const STAT_ALIASES = {
    'Points': ['PTS','Points','points','pt'],
    'Goals':  ['G','Goals','goals'],
    'Assists':['A','Assists','assists'],
    'GAA':    ['GAA','gaa'],
    'SV%':    ['SV%','SVPct','Save%','svp','sv%'],
    'SO':     ['SO','Shutouts','shutouts'],
    'W':      ['W','Wins','wins'],
  };
  function statsRoot() {
    if (U.statsDataByScope && U.statsDataByScope[U.activeScope]) return U.statsDataByScope[U.activeScope];
    return U.statsData || {};
  }
  function resolveSection(key){
    const r = statsRoot();
    return key === 'defense' ? (r.defense || r.defensemen || r.defenders || null) : (r[key] || null);
  }
  function pickStatArray(sectionObj, prettyKey){
    if (!sectionObj) return [];
    const keys = STAT_ALIASES[prettyKey] || [prettyKey];
    for (const k of keys) if (Array.isArray(sectionObj[k])) return sectionObj[k];
    const all = Object.keys(sectionObj);
    for (const k of keys) {
      const hit = all.find(gk => gk.toLowerCase() === String(k).toLowerCase());
      if (hit && Array.isArray(sectionObj[hit])) return sectionObj[hit];
    }
    return [];
  }
  let currentMetric = '';
  function valueFor(row){
    let v = row?.val ?? row?.value ?? row?.stat;
    if (v == null) return '';
    const n = Number(v);
    if (!Number.isFinite(n)) return String(v);
    if (currentMetric === 'SV%') return (Math.round(n * 1000) / 1000).toFixed(3);
    if (currentMetric === 'GAA') return (Math.round(n * 100) / 100).toFixed(2);
    return String(n);
  }
  function buildLeadersPanel(title, sectionKey, metrics){
    const sectionObj = resolveSection(sectionKey);
    const BASE_N = Number(U.leadersCompactN ?? 5);
    const FULL_N = Number(U.topNLeaders ?? 10);
    let expanded = false;

    const card = document.createElement('section'); card.className = 'leadersCard';
    const header = document.createElement('div'); header.className = 'leadersHeader';
    header.innerHTML = `<div class="leadersTitle">${esc(title)}</div>
      <div class="leadersHeaderActions"><button type="button" class="leadersExpand" aria-pressed="false">Show ${FULL_N}</button></div>`;
    card.appendChild(header);

    const tabs = document.createElement('div'); tabs.className = 'leadersTabs'; card.appendChild(tabs);
    const list = document.createElement('div'); list.className = 'leadersList'; card.appendChild(list);
    const footer = document.createElement('div'); footer.className = 'leadersFooter';
    footer.innerHTML = `<a class="leadersMore" href="statistics.php">All Leaders</a>`;
    card.appendChild(footer);

    const expandBtn = header.querySelector('.leadersExpand');
    expandBtn.addEventListener('click', () => {
      expanded = !expanded;
      expandBtn.setAttribute('aria-pressed', expanded ? 'true' : 'false');
      expandBtn.textContent = expanded ? `Show ${BASE_N}` : `Show ${FULL_N}`;
      render(currentMetric);
    });

    if (U.leadersCollapsible === true) {
      header.querySelector('.leadersTitle').style.cursor = 'pointer';
      header.querySelector('.leadersTitle').addEventListener('click', () => card.classList.toggle('is-collapsed'));
    }

    function render(metricPretty){
      currentMetric = metricPretty;
      qsa('.leadersTab', tabs).forEach(b => b.classList.toggle('active', b.dataset.metric === metricPretty));
      const rows = pickStatArray(sectionObj, metricPretty).slice(0, expanded ? FULL_N : BASE_N);
      list.innerHTML = rows.map((row, i) => {
        const name = esc(row?.name ?? '');
        const team = esc(row?.team ?? row?.tm ?? '');
        const val  = valueFor(row);
        const rank = row?.rank ?? (i + 1);
        const tTie = (row?.tied === true || String(rank).startsWith('T')) ? 'T' : '';
        return `<div class="leadersItem${i<1?' top':''}">
          <div class="leadersRank">${esc(String(rank))}</div>
          <div class="leadersName">${tTie ? 'T. ' : ''}${name}${team ? `<small>${team}</small>` : ''}</div>
          <div class="leadersValue">${esc(val)}</div>
        </div>`;
      }).join('') || `<div class="leadersItem"><div class="leadersName">—</div></div>`;
    }

    metrics.forEach((m, idx) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'leadersTab' + (idx === 0 ? ' active' : '');
      btn.dataset.metric = m;
      btn.textContent = m;
      btn.addEventListener('click', () => render(m));
      tabs.appendChild(btn);
    });

    render(metrics[0]);
    return card;
  }
  function initLeaders(){
    const root = document.getElementById('leadersStack');
    if (!root) return;
    const frag = document.createDocumentFragment();
    frag.appendChild(buildLeadersPanel('Skaters',    'skaters', ['Points','Goals','Assists']));
    frag.appendChild(buildLeadersPanel('Defensemen','defense', ['Points','Goals','Assists']));
    frag.appendChild(buildLeadersPanel('Goalies',    'goalies', ['GAA','SV%','SO','W']));
    frag.appendChild(buildLeadersPanel('Rookies',    'rookies', ['Points','Goals','Assists']));
    root.replaceChildren(frag);
  }

  /* -------------------- Transactions 25/50 toggle -------------------- */
  (function txToggle(){
    const root = document.getElementById('homeTransactions');
    if (!root) return;
    const rows = qsa('.tx-mini-row', root);
    const pills = qsa('.tx-pill', root);
    if (!pills.length) return;

    function apply(limit){
      rows.forEach((li, i) => i < limit ? li.removeAttribute('data-hidden')
                                        : li.setAttribute('data-hidden', 'true'));
      pills.forEach(btn => btn.setAttribute('aria-pressed', btn.dataset.limit == limit ? 'true' : 'false'));
    }
    pills.forEach(btn => btn.addEventListener('click', () => apply(parseInt(btn.dataset.limit, 10) || 25)));
  })();

  /* -------------------- Boot -------------------- */
  document.addEventListener('DOMContentLoaded', () => {
    initTicker();
    initFeature();
    initLeaders();
  });
})();
/* ===== Home-only: SOG hydrator (reads totals from Box Score page) ===== */
(() => {
  const wrap = document.getElementById('scoresCard');
  if (!wrap) return;

  const qsa = (s, el=document) => Array.from(el.querySelectorAll(s));
  const up  = s => String(s ?? '').trim().toUpperCase();

  function getCardTeams(card){
    // we’ll match by the nickname text shown in the card (“Avalanche”, “Sabres”…)
    const blocks = card.querySelectorAll('.team');
    const nameOf = el => (el?.querySelector('.meta .name, .name')?.textContent || '').trim();
    return { away: nameOf(blocks[0]), home: nameOf(blocks[1]) };
  }

  function findSOGNode(card){
    // common class hooks first
    let el = card.querySelector('.sog, .sog-line, .meta .sog');
    if (el) return el;
    // fallback: any element whose text starts with “SOG”
    return qsa('.meta *, .stats *, .stat, .small', card)
      .find(n => /^\s*SOG\b/i.test(n.textContent || '')) || null;
  }

  function makeAbsolute(href){
    if (!href) return '';
    if (/^https?:\/\//i.test(href)) return href;
    const { origin } = window.location;
    const base = window.location.pathname.replace(/\/[^/]*$/, '/');
    return origin + (href.startsWith('/') ? href : (base + href));
  }

  function parseShotsFromBoxHTML(html, awayNick, homeNick){
    const doc = new DOMParser().parseFromString(html, 'text/html');

    // find a <th> with “Shots”, then grab its table
    let shotsTable = null;
    const th = Array.from(doc.querySelectorAll('th'))
      .find(th => /shots/i.test(th.textContent || ''));
    if (th) shotsTable = th.closest('table');

    // fallback: pick a table whose header contains T (Total) and looks numeric
    if (!shotsTable) {
      shotsTable = Array.from(doc.querySelectorAll('table')).find(tbl => {
        const head = tbl.querySelector('tr');
        if (!head) return false;
        const tds = Array.from(head.querySelectorAll('th,td')).map(x => x.textContent.trim());
        return tds.some(t => /^T$/i.test(t)) && tbl.textContent.toLowerCase().includes('shots');
      });
    }
    if (!shotsTable) return null;

    const rows = Array.from(shotsTable.querySelectorAll('tr')).slice(1); // skip header
    if (!rows.length) return null;

    const lastNum = (row) => {
      const cells = Array.from(row.querySelectorAll('td,th'));
      for (let i = cells.length - 1; i >= 0; i--) {
        const n = parseInt(cells[i].textContent.replace(/[^\d]/g, ''), 10);
        if (Number.isFinite(n)) return n;
      }
      return null;
    };

    const rowName = (row) => (row.querySelector('td,th')?.textContent || '').trim();
    const awayRow = rows.find(r => rowName(r).toLowerCase().includes(awayNick.toLowerCase()));
    const homeRow = rows.find(r => rowName(r).toLowerCase().includes(homeNick.toLowerCase()));
    if (!awayRow || !homeRow) return null;

    const aSOG = lastNum(awayRow);
    const hSOG = lastNum(homeRow);
    if (!Number.isFinite(aSOG) || !Number.isFinite(hSOG)) return null;
    return { aSOG, hSOG };
  }

  function applySOG(card, aSOG, hSOG){
    const el = findSOGNode(card);
    if (!el) return;
    el.textContent = `SOG: ${aSOG} — ${hSOG}`;
    card.setAttribute('data-has-sog', '1'); // helpful if you want CSS to hide empty rows
  }

  async function hydrateCard(card){
    if (card.__sogDone) return;
    card.__sogDone = true;

    // Prefer the explicit “Box Score” link on the card
    const boxA = card.querySelector('.links a[href*="box"]') || card.querySelector('.links a:first-child');
    const href = makeAbsolute(boxA?.getAttribute('href') || '');
    if (!href) return;

    const { away, home } = getCardTeams(card);
    if (!away || !home) return;

    try {
      const r = await fetch(href, { credentials: 'same-origin' });
      if (!r.ok) return;
      const html = await r.text();
      const shots = parseShotsFromBoxHTML(html, away, home);
      if (shots) applySOG(card, shots.aSOG, shots.hSOG);
    } catch {}
  }

  function run(){
    qsa('#scoresCard .game-card').forEach(hydrateCard);
  }

  const list = wrap.querySelector('.scores-list') || wrap;
  new MutationObserver(run).observe(list, { childList:true, subtree:true });
  document.getElementById('scoresScope')?.addEventListener('change', () => setTimeout(run,0));
  document.getElementById('scoresDates')?.addEventListener('click',  () => setTimeout(run,0));
  window.addEventListener('load', () => setTimeout(run,0));
})();
