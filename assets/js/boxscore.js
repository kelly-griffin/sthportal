// Box Score renderer — works with STHS param (?Game=123) + demo fallback (?demo=1)
(() => {
  const $ = s => document.querySelector(s);
  const qs = new URLSearchParams(location.search);
  const gameParam = qs.get('Game') ?? qs.get('id') ?? '';
  const isDemo = qs.get('demo') === '1';

  const root = $('#boxscoreRoot');
  const links = $('#gameLinks');
  if (!root) return;

  function badge(t){ return t?.abbr ? `<span class="badge">${t.abbr}</span>` : ''; }
  function logo(t){
    if (t?.logo) return `<img class="team-logo" src="${t.logo}" alt="${t.name||t.abbr||''}">`;
    return `<span class="team-logo badge">${t?.abbr||'—'}</span>`;
  }
  function num(n){ return Number.isFinite(n) ? n : '—'; }
  function sum(arr){ return (arr||[]).reduce((a,b)=>a+(+b||0),0); }

  function renderLinks(data){
    const g = data?.gameNo ?? gameParam ?? '';
    const box = window.URLS?.gameBox(g,'pro') || '#';
    const log = window.URLS?.gameLog(g,'pro') || '#';
    links && (links.innerHTML = `
      <a class="btn ghost" href="${box}">Box Score</a>
      <a class="btn ghost" href="${log}">Game Log</a>
    `);
  }

  function render(data){
    if (!data){
      root.innerHTML = `
        <div class="game-empty">
          <div class="big">No box score available.</div>
          <div>Pass <code>?demo=1</code> to preview a sample.</div>
        </div>`;
      return;
    }

    renderLinks(data);

    const a = data.away, h = data.home;
    const aSOG = a?.sog || [], hSOG = h?.sog || [];
    const per = Math.max(aSOG.length, hSOG.length, 3);

    // Header
    const header = `
      <div class="game-header">
        <div class="side away">
          ${logo(a)}
          <div class="name">${a?.name||a?.abbr||'Away'}</div>
        </div>
        <div class="scoreline">
          <div class="status">${data.status||''}</div>
          <div class="score">${num(a?.score)} — ${num(h?.score)}</div>
          <div class="date">${data.date||''}</div>
        </div>
        <div class="side home">
          ${logo(h)}
          <div class="name">${h?.name||h?.abbr||'Home'}</div>
        </div>
      </div>`;

    // Shots by period
    let shotsRows = '';
    for (let i=0;i<per;i++){
      shotsRows += `
        <div class="row">
          <div class="cell period">P${i+1}</div>
          <div class="cell val">${num(aSOG[i])}</div>
          <div class="cell val">${num(hSOG[i])}</div>
        </div>`;
    }
    shotsRows += `
      <div class="row total">
        <div class="cell period">Total</div>
        <div class="cell val">${num(sum(aSOG))}</div>
        <div class="cell val">${num(sum(hSOG))}</div>
      </div>`;

    // Powerplays
    const pp = `
      <div class="pp-grid">
        <div class="pp-row"><span>${badge(a)} Power Play</span><b>${a?.pp||'—'}</b></div>
        <div class="pp-row"><span>${badge(h)} Power Play</span><b>${h?.pp||'—'}</b></div>
      </div>`;

    // Scoring summary
    const scoring = (data.goals||[]).map(g => `
      <div class="play">
        <div class="when">P${g.period} • ${g.time}</div>
        <div class="who">${badge({abbr:g.team})} ${g.desc||''}</div>
      </div>
    `).join('') || '<div class="muted">No scoring plays recorded.</div>';

    // Goalies
    const goalies = (data.goalies||[]).map(gl => `
      <div class="g-row">
        <div class="g-team">${badge({abbr:gl.team})}</div>
        <div class="g-name">${gl.name||''}</div>
        <div class="g-sv">SV: ${num(gl.sv)}</div>
        <div class="g-ga">GA: ${num(gl.ga)}</div>
        <div class="g-toi">TOI: ${gl.toi||'—'}</div>
      </div>
    `).join('') || '<div class="muted">No goalie data.</div>';

    // Stars
    const stars = (data.stars||[]).slice(0,3).map((s,i)=>`
      <div class="star">
        <div class="star-rank">${['1st','2nd','3rd'][i]}</div>
        <div class="star-name">${badge({abbr:s.team})} ${s.name||''}</div>
      </div>
    `).join('') || '<div class="muted">No three stars available.</div>';

    root.innerHTML = `
      ${header}

      <div class="grid-2">
        <section class="card">
          <div class="card-title">Shots by Period</div>
          <div class="shots-grid">
            <div class="head"></div>
            <div class="head">${badge(a)} ${a?.abbr||'AWY'}</div>
            <div class="head">${badge(h)} ${h?.abbr||'HOM'}</div>
            ${shotsRows}
          </div>
        </section>

        <section class="card">
          <div class="card-title">Special Teams</div>
          ${pp}
        </section>
      </div>

      <section class="card">
        <div class="card-title">Scoring Summary</div>
        <div class="plays">${scoring}</div>
      </section>

      <section class="card">
        <div class="card-title">Goalies</div>
        <div class="goalies">${goalies}</div>
      </section>

      <section class="card">
        <div class="card-title">Three Stars</div>
        <div class="stars">${stars}</div>
      </section>
    `;
  }

  function sample(){
    return {
      gameNo: 123,
      date: "2025-03-25",
      status: "Final",
      away: { abbr:"PHI", name:"Flyers", score:2, sog:[5,7,7], pp:"1/4" },
      home: { abbr:"TOR", name:"Maple Leafs", score:7, sog:[9,11,10], pp:"2/3" },
      goals: [
        { period:1, time:"07:59", team:"PHI", desc:"Ryan Poehling (8) from Pelletier, Abols" },
        { period:1, time:"10:42", team:"TOR", desc:"M. Marner (26) from Matthews, Reilly" },
        { period:2, time:"03:15", team:"TOR", desc:"A. Matthews (41) from Marner" }
      ],
      goalies: [
        { team:"PHI", name:"S. Ersson", sv:23, ga:5, toi:"58:12" },
        { team:"TOR", name:"I. Samsonov", sv:17, ga:2, toi:"60:00" }
      ],
      stars: [
        { team:"TOR", name:"M. Marner" },
        { team:"TOR", name:"A. Matthews" },
        { team:"PHI", name:"R. Poehling" }
      ]
    };
  }

  // Boot
  if (isDemo) {
    render(sample());
  } else {
    // No backend yet; render empty. (When STHS is ready, swap in parsing here.)
    render(null);
  }
})();
