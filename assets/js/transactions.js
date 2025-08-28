// UHA Portal — Transactions (cards)
(function(){
  function activate(tab){
    const links = document.querySelectorAll('.stats-subnav .tab-link');
    const panels = document.querySelectorAll('.tab-panel');
    links.forEach(a => a.classList.toggle('active', a.dataset.tab === tab));
    panels.forEach(p => p.classList.toggle('active', p.id === ('tab-' + tab)));
  }

  document.addEventListener('DOMContentLoaded', () => {
    // tabs
    const links = document.querySelectorAll('.stats-subnav .tab-link');
    if (links.length){
      const initial = (location.hash || '#trades').slice(1);
      activate(initial);
      links.forEach(a => a.addEventListener('click', e => {
        e.preventDefault(); const tab = a.dataset.tab || 'trades';
        activate(tab); history.replaceState(null, '', '#' + tab);
      }));
    }

    // about/notes toggles
    document.body.addEventListener('click', e => {
      const btn = e.target.closest('.tx-about-toggle');
      if (!btn) return;
      const card = btn.closest('.tx-card');
      if (card) card.classList.toggle('open');
    });

    // filtering helpers
    function attachFilter(listId, teamSelId, searchId){
      const list = document.querySelector(listId);
      const teamSel = document.querySelector(teamSelId);
      const q = document.querySelector(searchId);
      if (!list) return;

      const items = Array.from(list.children);

      // newest first by data-date if present
      items.sort((a,b) => (Date.parse(b.dataset.date||'')||0) - (Date.parse(a.dataset.date||'')||0))
           .forEach(li => list.appendChild(li));

      function apply(){
        const team = (teamSel?.value || '').toUpperCase();
        const term = (q?.value || '').trim().toLowerCase();
        items.forEach(li => {
          const hay = (li.dataset.text || '').toLowerCase();
          const hitTeam = !team || (li.dataset.team === team || li.dataset.from === team || li.dataset.to === team);
          const hitText = !term || hay.includes(term);
          li.style.display = (hitTeam && hitText) ? '' : 'none';
        });
      }
      teamSel && teamSel.addEventListener('change', apply);
      q && q.addEventListener('input', apply);
      apply();
    }

    attachFilter('#trades-list',   '#trades-team',   '#trades-q');
    attachFilter('#signings-list', '#signings-team', '#signings-q');
    attachFilter('#moves-list',    '#moves-team',    '#moves-q');
  });
})();
