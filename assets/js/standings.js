// UHA Standings (Wild Card view) — nickname-only + Full Standings links + team links
(() => {
  const $ = s => document.querySelector(s);

  const card =
    document.getElementById('standingsCard') ||
    [...document.querySelectorAll('.sidebar-right .box')].find(b =>
      b.querySelector('.title')?.textContent?.toLowerCase().includes('standings')
    );
  if (!card) return;

  let mount = card.querySelector('#standingsBox');
  if (!mount) {
    mount = document.createElement('div');
    mount.id = 'standingsBox';
    card.appendChild(mount);
  }

  const LINK_BASE = (window.UHA && UHA.standingsLinkBase) || 'standings.php';
  const NICKNAME_ONLY = (window.UHA && Object.prototype.hasOwnProperty.call(UHA, 'standingsNicknameOnly'))
    ? !!UHA.standingsNicknameOnly
    : true;

  const DATA = (window.UHA && UHA.standingsData) || demoData();

  const fmt = n => (Number.isFinite(n) ? n : '—');

  function nicknameFromName(name = '') {
    const multi = ['Maple Leafs','Blue Jackets','Golden Knights','Red Wings','Hockey Club'];
    for (const m of multi) if (name.endsWith(m)) return m;
    const parts = name.trim().split(/\s+/);
    return parts[parts.length - 1] || name;
  }
  function displayName(t) {
    const full = t.name || t.abbr || '';
    if (!NICKNAME_ONLY) return full;
    if (t.nickname) return t.nickname;
    return nicknameFromName(full);
  }

  function headerRow() {
    return `
      <div class="st-row st-head">
        <div class="col rank">#</div>
        <div class="col team">Team</div>
        <div class="col gp">GP</div>
        <div class="col gr">GR</div>
        <div class="col pts">PTS</div>
      </div>`;
  }

  function teamCell(t){
  const title = (t.name || t.abbr || '');
  const shown = displayName(t);
  const clinch = t.clinch ? `<span class="clinch">${t.clinch}</span>` : '';
  const href = (window.URLS ? URLS.team(t.abbr, 'pro') : '#');
  // use data-abbr instead of a src so auto-logos can pick dark/light
  const logo = `<img class="logo" data-abbr="${t.abbr || ''}" alt="${title}" />`;
  return `<a class="team-inner" title="${title}" href="${href}">
    <div class="logo">${logo}</div>
    <div class="name">${shown}${clinch}</div>
  </a>`;
}

function row(rank, t){
  // get the season length straight from the card attribute
  const card = document.getElementById('standingsCard');
  const seasonGames = parseInt(card?.getAttribute('data-season-games'), 10) || 82;

  const gr = Math.max(0, seasonGames - (t.gp ?? 0));

  return `
    <div class="st-row">
      <div class="col rank">${rank}</div>
      <div class="col team">${teamCell(t)}</div>
      <div class="col gp">${fmt(t.gp)}</div>
      <div class="col gr">${fmt(gr)}</div>
      <div class="col pts">${fmt(t.pts)}</div>
    </div>`;
}


  function splitTop3(divTeams){
    const arr = [...divTeams].sort((a,b)=> (b.pts??0)-(a.pts??0));
    return { top3: arr.slice(0,3), rest: arr.slice(3) };
  }

  function buildConference(key, label, conf){
    const divKeys = Object.keys(conf.divisions || {});
    if (divKeys.length !== 2) {
      return `<div class="st-conf"><div class="st-conf-head"><div class="title">${label}</div><a class="st-link" href="${LINK_BASE}?conference=${key}">Full Standings</a></div><div class="st-note">Standings data missing.</div></div>`;
    }
    const [d1, d2] = divKeys;
    const {top3: d1top, rest: d1rest} = splitTop3(conf.divisions[d1]);
    const {top3: d2top, rest: d2rest} = splitTop3(conf.divisions[d2]);

    const pool = [...d1rest, ...d2rest].sort((a,b)=>(b.pts??0)-(a.pts??0));

    let html = `<div class="st-conf">
      <div class="st-conf-head">
        <div class="title">${label}</div>
        <a class="st-link" href="${LINK_BASE}?conference=${key}">Full Standings</a>
      </div>`;

    html += `<div class="st-div"><div class="st-div-title">${d1}</div>${headerRow()}`;
    d1top.forEach((t, i)=> html += row(i+1, t));
    html += `</div>`;

    html += `<div class="st-div"><div class="st-div-title">${d2}</div>${headerRow()}`;
    d2top.forEach((t, i)=> html += row(i+1, t));
    html += `</div>`;

    html += `<div class="st-div"><div class="st-div-title">Wild Card</div>${headerRow()}`;
    pool.forEach((t, i)=>{
      if (i === 2) html += `<div class="st-wc-cut"></div>`;
      html += row(i+1, t);
    });
    html += `</div>`;

    html += `</div>`;
    return html;
  }

function render(){
  const east = DATA.east || {};
  const west = DATA.west || {};

  mount.innerHTML = `
    <div class="standings wild-card">
      ${buildConference('east','Eastern', east)}
      ${buildConference('west','Western', west)}
      <div class="st-bottom-link"><a href="${LINK_BASE}">Full Standings</a></div>
    </div>
  `;

  // run AFTER HTML is in the DOM
  if (typeof window.UHA_applyLogoVariants === 'function') {
    try { window.UHA_applyLogoVariants(mount); } catch {}
  }
}
  render();

  function demoData(){
    return {
      east: {
        divisions: {
          Atlantic: [
            {abbr:'TOR',name:'Toronto Maple Leafs',gp:82,w:52,l:26,ot:4,pts:108,clinch:'y'},
            {abbr:'TBL',name:'Tampa Bay Lightning',gp:82,w:47,l:27,ot:8,pts:102,clinch:'x'},
            {abbr:'FLA',name:'Florida Panthers',gp:82,w:47,l:31,ot:4,pts:98,clinch:'x'},
            {abbr:'BOS',name:'Boston Bruins',gp:82,w:39,l:33,ot:10,pts:88},
            {abbr:'BUF',name:'Buffalo Sabres',gp:82,w:39,l:36,ot:7,pts:85},
            {abbr:'DET',name:'Detroit Red Wings',gp:82,w:39,l:35,ot:8,pts:86},
            {abbr:'MTL',name:'Montréal Canadiens',gp:82,w:40,l:31,ot:11,pts:91,clinch:'e'},
            {abbr:'OTT',name:'Ottawa Senators',gp:82,w:45,l:30,ot:7,pts:97}
          ],
          Metropolitan: [
            {abbr:'WSH',name:'Washington Capitals',gp:82,w:51,l:22,ot:9,pts:111,clinch:'z'},
            {abbr:'CAR',name:'Carolina Hurricanes',gp:82,w:47,l:30,ot:5,pts:99,clinch:'x'},
            {abbr:'NJD',name:'New Jersey Devils',gp:82,w:43,l:32,ot:7,pts:91,clinch:'x'},
            {abbr:'NYR',name:'New York Rangers',gp:82,w:39,l:36,ot:7,pts:85},
            {abbr:'NYI',name:'New York Islanders',gp:82,w:35,l:35,ot:12,pts:82},
            {abbr:'PIT',name:'Pittsburgh Penguins',gp:82,w:34,l:36,ot:12,pts:80},
            {abbr:'CBJ',name:'Columbus Blue Jackets',gp:82,w:40,l:33,ot:9,pts:89},
            {abbr:'PHI',name:'Philadelphia Flyers',gp:82,w:33,l:39,ot:10,pts:76}
          ]
        }
      },
      west: {
        divisions: {
          Central: [
            {abbr:'WPG',name:'Winnipeg Jets',gp:82,w:56,l:22,ot:4,pts:116,clinch:'p'},
            {abbr:'DAL',name:'Dallas Stars',gp:82,w:50,l:26,ot:6,pts:106,clinch:'x'},
            {abbr:'COL',name:'Colorado Avalanche',gp:82,w:49,l:29,ot:4,pts:102,clinch:'x'},
            {abbr:'MIN',name:'Minnesota Wild',gp:82,w:45,l:30,ot:7,pts:97},
            {abbr:'STL',name:'St. Louis Blues',gp:82,w:44,l:30,ot:8,pts:96,clinch:'x'},
            {abbr:'NSH',name:'Nashville Predators',gp:82,w:30,l:44,ot:8,pts:68},
            {abbr:'CHI',name:'Chicago Blackhawks',gp:82,w:25,l:46,ot:11,pts:61},
            {abbr:'ARI',name:'Arizona Coyotes',gp:82,w:28,l:45,ot:9,pts:65}
          ],
          Pacific: [
            {abbr:'VGK',name:'Vegas Golden Knights',gp:82,w:50,l:22,ot:10,pts:110,clinch:'y'},
            {abbr:'LAK',name:'Los Angeles Kings',gp:82,w:48,l:25,ot:9,pts:105,clinch:'x'},
            {abbr:'EDM',name:'Edmonton Oilers',gp:82,w:48,l:29,ot:5,pts:101,clinch:'x'},
            {abbr:'CGY',name:'Calgary Flames',gp:82,w:41,l:27,ot:14,pts:96},
            {abbr:'VAN',name:'Vancouver Canucks',gp:82,w:38,l:30,ot:14,pts:90,clinch:'e'},
            {abbr:'SEA',name:'Seattle Kraken',gp:82,w:35,l:41,ot:6,pts:76},
            {abbr:'SJS',name:'San Jose Sharks',gp:82,w:20,l:50,ot:12,pts:52,clinch:'e'},
            {abbr:'UHC',name:'Utah Hockey Club',gp:82,w:38,l:31,ot:13,pts:89}
          ]
        }
      }
    };
  }
})();