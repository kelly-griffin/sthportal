// Game Log renderer — STHS param (?Game=123) + demo fallback (?demo=1)
(() => {
  const $ = s => document.querySelector(s);
  const qs = new URLSearchParams(location.search);
  const gameParam = qs.get('Game') ?? qs.get('id') ?? '';
  const isDemo = qs.get('demo') === '1';

  const root = $('#gamelogRoot');
  const links = $('#gameLinks');
  if (!root) return;

  function renderLinks(){
    const box = window.URLS?.gameBox(gameParam,'pro') || '#';
    const log = window.URLS?.gameLog(gameParam,'pro') || '#';
    links && (links.innerHTML = `
      <a class="btn ghost" href="${box}">Box Score</a>
      <a class="btn ghost" href="${log}">Game Log</a>
    `);
  }

  function groupByPeriod(plays){
    const out = {};
    plays.forEach(p => { (out[p.period] ||= []).push(p); });
    return out;
  }

  function render(data){
    renderLinks();
    if (!data){
      root.innerHTML = `
        <div class="game-empty">
          <div class="big">No game log available.</div>
          <div>Pass <code>?demo=1</code> to preview a sample.</div>
        </div>`;
      return;
    }

    const groups = groupByPeriod(data.plays||[]);
    const order = Object.keys(groups).map(n=>+n).sort((a,b)=>a-b);

    root.innerHTML = `
      <div class="card">
        <div class="card-title">Game Log — ${data.away?.abbr||'AWY'} @ ${data.home?.abbr||'HOM'} (${data.status||''})</div>
        <div class="log-plays">
          ${order.map(p => `
            <div class="log-period">Period ${p}</div>
            ${groups[p].map(x => `
              <div class="log-row">
                <div class="log-time">${x.time}</div>
                <div class="log-team">${x.team}</div>
                <div class="log-desc">${x.desc}</div>
              </div>
            `).join('')}
          `).join('')}
        </div>
      </div>
    `;
  }

  function sample(){
    return {
      status:"Final",
      away:{abbr:"PHI"}, home:{abbr:"TOR"},
      plays: [
        { period:1, time:"02:33", team:"PHI", desc:"Shot on goal by Tippett (saved)" },
        { period:1, time:"07:59", team:"PHI", desc:"GOAL — R. Poehling (8) from Pelletier, Abols" },
        { period:1, time:"10:42", team:"TOR", desc:"GOAL — M. Marner (26) from Matthews, Reilly" },
        { period:2, time:"03:15", team:"TOR", desc:"GOAL — A. Matthews (41) from Marner" },
        { period:3, time:"14:01", team:"TOR", desc:"Penalty — Too many men (bench) 2:00" }
      ]
    };
  }

  if (isDemo) { render(sample()); } else { render(null); }
})();
