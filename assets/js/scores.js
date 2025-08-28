// Scores cards — robust init: auto-pick nearest date with data, tolerate missing elements
(() => {
  const $  = s => document.querySelector(s);
  const $$ = s => [...document.querySelectorAll(s)];
  const pad = n => String(n).padStart(2, '0');

  // Elements (be tolerant with selectors)
  const wrap    = $('#scoresCard');
  const elStrip = $('#scoresDates');
  const elScope = $('#scoresScope');
  const elFilter= $('#scoresFilter');
  const list    = $('#proScores') || (wrap && wrap.querySelector('.scores-list'));

  if (!wrap || !list) { console.warn('Scores: container not found'); return; }

  // State
  const DATES_VISIBLE = 5;
  let scope  = (window.UHA && UHA.defaultScoresScope) || 'pro';
  let filter = 'all';
  let viewDate = todayStrip(new Date());

  // Helpers
  function todayStrip(d){ return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
  function fmtDate(d){ return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`; }
  function parseDateStr(s){ const [y,m,da] = s.split('-').map(Number); return new Date(y, (m||1)-1, da||1); }
  function prettyDateShort(d){
    return {
      label1: d.toLocaleDateString(undefined, { month:'numeric', day:'numeric' }),
      label2: d.toLocaleDateString(undefined, { weekday:'short' }).toUpperCase()
    };
  }
  function statusKind(s=''){
    const t = String(s).toLowerCase();
    if (t.includes('final')) return 'final';
    if (/(live|1st|2nd|3rd|ot|so)/.test(t)) return 'live';
    return 'upcoming';
  }

  // Data access (tolerant)
  function SCORES(){ return (window.UHA && UHA.scores) || {}; }
  function byScope(sc){ return SCORES()[sc] || {}; }
  function datesForScope(sc){
    return Object.keys(byScope(sc)).filter(k => /^\d{4}-\d{2}-\d{2}$/.test(k)).sort();
  }
  function gamesFor(sc, dStr){
    const arr = (byScope(sc)[dStr] || []).map(g => ({...g}));
    return filter === 'all' ? arr : arr.filter(g => statusKind(g.status) === filter);
  }

  // Pick a good initial date: today if available, else most recent date with games
  function pickInitialDate(){
    const dstr = fmtDate(viewDate);
    if (gamesFor(scope, dstr).length) return;
    const ds = datesForScope(scope);
    if (ds.length) viewDate = parseDateStr(ds[ds.length - 1]); // newest with games
  }

  function renderDates(){
    if (!elStrip) return;
    elStrip.innerHTML = '';
    // center window on viewDate
    const start = new Date(viewDate); start.setDate(start.getDate() - Math.floor(DATES_VISIBLE/2));
    for (let i = 0; i < DATES_VISIBLE; i++){
      const d = new Date(start); d.setDate(start.getDate() + i);
      const key = fmtDate(d);
      const {label1, label2} = prettyDateShort(d);
      const n = (byScope(scope)[key] || []).length;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'date-pill' + (fmtDate(d) === fmtDate(viewDate) ? ' active' : '');
      btn.innerHTML = `<div class="d1">${label1}</div><div class="d2">${label2}</div><div class="d3">${n?`${n} Games`:'—'}</div>`;
      btn.addEventListener('click', () => { viewDate = todayStrip(d); renderAll(); });
      elStrip.appendChild(btn);
    }
  }

  function teamObj(t){
    if (!t) return { abbr:'TBD', name:'TBD', logo:null, sog:null };
    if (typeof t === 'string') return { abbr:t, name:t, logo:null, sog:null };
    return { abbr: t.abbr || t.name || 'TBD', name: t.name || t.abbr || 'TBD', logo: t.logo || null, sog: (typeof t.sog==='number'?t.sog:null) };
  }
  function logoHTML(team){
    return team.logo
      ? `<img alt="${team.name}" src="${team.logo}" class="logo">`
      : `<div class="logo badge">${team.abbr}</div>`;
  }
  function mkTeamRow(side, t, score, link){
    return `
      <div class="team ${side}">
        <a class="logo-link" href="${link || '#'}" title="${t.name}">${logoHTML(t)}</a>
        <div class="meta"><div class="name">${t.name}</div><div class="sog">SOG: ${t.sog ?? '—'}</div></div>
        <div class="big-score">${score ?? '—'}</div>
      </div>`;
  }
  function goalInner(goal){
    if (!goal) return '<div class="goal-empty">Goals</div>';
    const s = goal.scorer || {};
    const shot = s.headshot ? `<img src="${s.headshot}" alt="${s.name||''}" class="headshot">`
                            : `<div class="headshot empty"></div>`;
    const title = s.name ? `${s.name}${typeof s.goals==='number'?` (${s.goals})`:''}` : 'Goal';
    const assists = (goal.assists && goal.assists.length) ? goal.assists.join(', ') : '';
    const line = goal.line || '';
    return `<div class="goal-inner">
      <div class="play-btn">▶</div>
      <div class="goal-body">${shot}<div class="goal-text">
        <div class="goal-title">${title}</div>
        <div class="goal-assists">${assists}</div>
        <div class="goal-line">${line}</div>
      </div></div></div>`;
  }

  function renderGames(){
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
      const gid  = g.id || `${away.abbr}-${home.abbr}-${key}-${idx}`;
      const card = document.createElement('article');
      card.className = `game-card status-${status}`;
      card.dataset.gid = gid;
      const badgeText = status==='final' ? 'FINAL' : (status==='live' ? 'LIVE' : (g.status || ''));
      card.innerHTML = `
        <div class="status-badge">${badgeText}</div>
        ${mkTeamRow('away', away, g.aScore, `team.php?team=${encodeURIComponent(away.abbr)}`)}
        ${mkTeamRow('home', home, g.hScore, `team.php?team=${encodeURIComponent(home.abbr)}`)}
        <div class="goals">
          <div class="goal-nav left" data-dir="-1" ${g.goals && g.goals.length>1?'':'hidden'}>◀</div>
          <div class="goal-card">${goalInner((g.goals||[])[0])}</div>
          <div class="goal-nav right" data-dir="1" ${g.goals && g.goals.length>1?'':'hidden'}>▶</div>
        </div>
        <div class="links">
          <a class="btn ghost" href="${g.box || `boxscore.php?id=${encodeURIComponent(gid)}`}">Box Score</a>
          <a class="btn ghost" href="${g.log || `gamelog.php?id=${encodeURIComponent(gid)}`}">Game Log</a>
        </div>`;
      // slider state
      card._goals = (g.goals || []).slice();
      card._idx = 0;
      list.appendChild(card);
    });

    // wire slider buttons per card
    list.querySelectorAll('.game-card').forEach(card => {
      const left  = card.querySelector('.goal-nav.left');
      const right = card.querySelector('.goal-nav.right');
      const pane  = card.querySelector('.goal-card');
      const goals = card._goals;
      function show(i){
        if (!goals.length) return;
        card._idx = (i + goals.length) % goals.length;
        pane.innerHTML = goalInner(goals[card._idx]);
      }
      left?.addEventListener('click', ()=> show(card._idx - 1));
      right?.addEventListener('click',()=> show(card._idx + 1));
    });
  }

  function renderAll(){ pickInitialDate(); renderDates(); renderGames(); }

  // Controls
  wrap.querySelector('.scores-dates .prev')?.addEventListener('click', ()=>{ viewDate.setDate(viewDate.getDate()-1); renderAll(); });
  wrap.querySelector('.scores-dates .next')?.addEventListener('click', ()=>{ viewDate.setDate(viewDate.getDate()+1); renderAll(); });
  elScope?.addEventListener('change', e => { scope = e.target.value; renderAll(); });
  elFilter?.addEventListener('change', e => { filter = e.target.value; renderAll(); });

  // Boot
  renderAll();

  // expose helper for quick jumps
  window.UHA = window.UHA || {};
  window.UHA._scoresGoto = (dateStr) => { viewDate = todayStrip(parseDateStr(dateStr)); renderAll(); };
})();
