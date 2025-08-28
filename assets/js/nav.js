// UHA Portal — nav + context header
(() => {
  const UHA = (window.UHA ||= {});
  // default contexts (colors can be overwritten by PHP later)
  const ctx = UHA.contexts = UHA.contexts || {
    'league-uha':    { kicker:'National Hockey League', title:'NHL Central',
      sub:['Home','Standings','Schedule','Leaders','Transactions','Injuries','Playoffs'],
      brand:['#1e88e5','#0d47a1','#64b5f6'] },
    'league-echl':   { kicker:'ECHL League', title:'ECHL Central',
      sub:['Standings','Schedule','Leaders','Transactions','Injuries','Playoffs'],
      brand:['#e53935','#8e0000','#ef9a9a'] },
    'league-intl':   { kicker:'International', title:'Global Leagues',
      sub:['Standings','Schedule','Leaders','Worlds','Olympics','World Cup'],
      brand:['#43a047','#1b5e20','#a5d6a7'] },
    'league-juniors':{ kicker:'Junior Leagues', title:'Prospects & Draft Board',
      sub:['Standings','Schedule','Leaders','Draft Eligibles','CHL','NCAA'],
      brand:['#ff8f00','#e65100','#ffd180'] },
    'team-view':     { kicker:'Team: Halifax Moose (example)', title:'Team Hub',
      sub:['Roster','Lines','Stats','Schedule','History','Transactions'],
      brand:['#7e57c2','#4527a0','#b39ddb'] },
  };

  const $ = s => document.querySelector(s);
  const ctxKicker = $('#ctxKicker');
  const ctxTitle  = $('#ctxTitle');
  const ctxSubnav = $('#ctxSubnav');

  function applyBrand([b1,b2,b3]){
    const r = document.documentElement.style;
    r.setProperty('--brand',  b1);
    r.setProperty('--brand-2',b2);
    r.setProperty('--brand-3',b3);
  }

  function setContext(key){
    const c = ctx[key] || ctx['league-uha'];
    ctxKicker && (ctxKicker.textContent = c.kicker);
    ctxTitle  && (ctxTitle.textContent  = c.title);
    if (ctxSubnav) {
  const serverLinks = ctxSubnav.querySelectorAll('a');
  if (serverLinks.length) {
    // Keep hrefs/active states; just ensure the styling class is present.
    serverLinks.forEach(a => a.classList.add('pill'));
  } else {
    // Fallback: build demo pills only if nothing was rendered by PHP.
    ctxSubnav.innerHTML = '';
    c.sub.forEach((label,i) => {
      const a = document.createElement('a');
      a.href = '#';
      a.className = 'pill' + (i===0 ? ' active' : '');
      a.textContent = label;
      ctxSubnav.appendChild(a);
    });
  }
}
    applyBrand(c.brand);
    UHA.context = key;
  }

  // choose context from config, query (?league=echl), or default
  const getParam = n => new URLSearchParams(location.search).get(n);
  const startCtx = UHA.context || (p => p ? `league-${p}` : null)(getParam('league')) || 'league-uha';

  setContext(startCtx);
  UHA.setContext = setContext;
})();
