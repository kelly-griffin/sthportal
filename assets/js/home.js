// UHA Portal — home: ticker + feature + Leaders (Skaters/Defense/Goalies/Rookies)
// Layout matches the screenshot: top tabs inside each box + All Leaders link.

(() => {
  const UHA = (window.UHA ||= {});
  const $ = s => document.querySelector(s);

  /* ===================== SCORE TICKER ===================== */
  (function buildTicker(){
    const track = $('#ticker-track');
    const items = UHA.ticker || [];
    if (!track || !items.length) return;
    const dup = [...items, ...items]; // seamless loop
    track.innerHTML = dup.map(t => `<span class="ticker-item">${t}</span>`).join('');
  })();

  /* ===================== FEATURE STORY ===================== */
  (function bindFeature(){
    const F = UHA.feature || {};
    const map = {
      '#feature-team-abbr': F.teamAbbr || 'TL',
      '#feature-team-name': F.teamName || 'Team Name',
      '#feature-headline' : F.headline || 'HEADLINE',
      '#feature-dek'      : F.dek || 'Lead paragraph...',
      '#feature-time'     : F.timeAgo || 'xx ago',
    };
    Object.entries(map).forEach(([sel,val])=>{
      const el = $(sel); if (el) el.textContent = val;
    });
  })();

  /* ===================== LEADERS PANELS =====================
     Config you can override in home.php before loading this file:
        UHA.leadersLinkBase = 'statistics.php'  // target for "All Leaders"
        UHA.topNLeaders = 10

     Data shapes supported:
       SIMPLE (single scope):
         UHA.statsData = {
           skaters: { 'PTS':[{name,team,val},...], 'G':[], 'A':[] },
           defense: { 'PTS':[], 'G':[], 'A':[] },
           goalies: { 'GAA':[], 'SV%':[], 'SO':[] },
           rookies: { 'PTS':[], 'G':[], 'A':[] }
         }

       MULTI (optional, if you later want Pro/Farm/etc.):
         UHA.statsDataByScope = {
           pro: { skaters:{...}, defense:{...}, goalies:{...}, rookies:{...} },
           farm:{ ... }
         }
         UHA.activeScope = 'pro'    // defaults to 'pro'
  =========================================================== */

  (function leaders(){
    const mount = $('#leadersStack');
    if (!mount) return;

    // Config
    const LINK_BASE = (UHA.leadersLinkBase || 'statistics.php');
    const TOP_N = Number.isFinite(UHA.topNLeaders) ? UHA.topNLeaders : 10;
    const scope = (UHA.activeScope || 'pro');

    // Panels + tabs
    const PANELS = [
      { key:'skaters',  title:'Skaters',  tabs:['PTS','G','A'] },
      { key:'defense',  title:'Defensemen', tabs:['PTS','G','A'] },
      { key:'goalies',  title:'Goalies',  tabs:['GAA','SV%','SO'] },
      { key:'rookies',  title:'Rookies',  tabs:['PTS','G','A'] },
    ];

    // Data access helpers
    function datasetFor(sectionKey){
      if (UHA.statsDataByScope) {
        const root = UHA.statsDataByScope[scope] || {};
        return root[sectionKey] || {};
      }
      return (UHA.statsData && UHA.statsData[sectionKey]) ? UHA.statsData[sectionKey] : {};
    }

    const DECIMALS = {
      'SV%': 3,
      'GAA': 2
      // others default to integer
    };

    function formatVal(stat, v){
      if (v == null) return '';
      if (typeof v !== 'number') return v;
      const d = DECIMALS[stat];
      return Number.isInteger(v) || d == null ? String(v) : v.toFixed(d);
    }

    // build one panel
    function buildPanel(panel){
      const card = document.createElement('section');
      card.className = 'leadersCard';

      // header
      card.innerHTML = `
        <div class="leadersHeader">
          <div class="leadersTitle">${panel.title}</div>
          <a class="leadersMore" href="#" aria-label="View all ${panel.title} leaders">All Leaders</a>
        </div>
        <div class="leadersTabs" role="tablist"></div>
        <div class="leadersList" role="list"></div>
      `;

      const tabsEl  = card.querySelector('.leadersTabs');
      const listEl  = card.querySelector('.leadersList');
      const moreEl  = card.querySelector('.leadersMore');

      let activeTab = panel.tabs[0];

      panel.tabs.forEach(tabName=>{
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'leadersTab';
        btn.textContent = labelFor(tabName);
        btn.setAttribute('role','tab');
        if (tabName === activeTab) btn.classList.add('active');
        btn.addEventListener('click', () => {
          activeTab = tabName;
          tabsEl.querySelectorAll('.leadersTab').forEach(b=>b.classList.toggle('active', b===btn));
          renderList();
        });
        tabsEl.appendChild(btn);
      });

      function labelFor(s){
        // Just pretty-case a few common stat keys
        if (s === 'SV%') return 'SV%';
        if (s === 'GAA') return 'GAA';
        if (s === 'PTS') return 'Points';
        if (s === 'G')   return 'Goals';
        if (s === 'A')   return 'Assists';
        return s;
      }

      function buildAllLeadersHref(sectionKey, statKey){
        const params = new URLSearchParams({
          view: sectionKey,   // skaters/defense/goalies/rookies
          stat: statKey,      // PTS/G/A etc.
          scope               // pro/farm (future-friendly)
        });
        return `${LINK_BASE}?${params.toString()}`;
      }

      function renderList(){
        listEl.innerHTML = '';

        // data rows for this section+stat
        const rows = (datasetFor(panel.key)[activeTab] || []).slice(0, TOP_N);

        // simple tie detection: same value => T(rank).
        let prevVal = null, rank = 0;
        rows.forEach((row, i) => {
          const val = row.val;
          const isTie = (prevVal !== null) && (typeof val === 'number' && typeof prevVal === 'number'
                        ? Math.abs(val - prevVal) < 1e-9
                        : val === prevVal);
          if (!isTie) rank = i + 1;
          prevVal = val;

          const prettyRank = (isTie ? `T${rank}.` : `${rank}.`);
          const prettyVal  = formatVal(activeTab, val);

          const item = document.createElement('div');
          item.className = 'leadersItem' + (i===0 ? ' top' : '');
          item.innerHTML = `
            <div class="leadersRank">${prettyRank}</div>
            <div class="leadersName">${row.name || ''}<small>${row.team || ''}</small></div>
            <div class="leadersValue">${prettyVal}</div>
          `;
          listEl.appendChild(item);
        });

        // update footer link
        moreEl.href = buildAllLeadersHref(panel.key, activeTab);
      }

      renderList();
      return card;
    }

    // mount all four panels
    mount.innerHTML = '';
    PANELS.forEach(p => mount.appendChild(buildPanel(p)));
  })();
})();
