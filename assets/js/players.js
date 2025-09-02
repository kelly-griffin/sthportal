// UHA Portal — Players live-search (scaffold)
// Base behavior: waits for 3+ chars, shows dropdown with results; click row → player page.
// Data wiring is stubbed — replace searchPlayers() with a real fetch.

(() => {
  const q = document.getElementById('pl-q');
  const activeOnly = document.getElementById('pl-active');
  const box = document.querySelector('.pl-searchbox');
  const results = document.getElementById('pl-results');
  const tbody = document.getElementById('pl-results-body');
  const urlBase = box?.dataset.playerUrl || 'player.php?id=';

  if (!q || !results || !tbody) return;

  let lastSeq = 0;

  const debounce = (fn, ms) => {
    let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  };

  function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function render(list){
    if (!Array.isArray(list)) list = [];
    if (!list.length){
      tbody.innerHTML = '<tr class="empty"><td colspan="8">No results</td></tr>';
    } else {
      tbody.innerHTML = list.map(p => `
        <tr class="pl-row" data-id="${escapeHtml(p.id)}">
          <td class="pl-player">
            <img class="avatar" src="${escapeHtml(p.avatar || 'assets/img/players/default.png')}" alt="">
            <a href="${urlBase}${encodeURIComponent(p.id)}" class="name">${escapeHtml(p.name)}</a>
          </td>
          <td>${escapeHtml(p.pos || '')}</td>
          <td><img class="team-logo" src="assets/img/logos/${escapeHtml(p.team || '')}_dark.svg" alt=""></td>
          <td>${p.number ?? ''}</td>
          <td>${escapeHtml(p.status || '')}</td>
          <td>${escapeHtml(p.ht || '')}</td>
          <td>${escapeHtml(p.wt || '')}</td>
          <td>${escapeHtml(p.birthplace || '')}</td>
        </tr>`).join('');
    }
    results.hidden = false;
  }

  async function doSearch(query){
    const seq = ++lastSeq;
    const list = await searchPlayers(query, activeOnly.checked);
    if (seq !== lastSeq) return; // stale
    render(list);
  }

  const onInput = debounce(() => {
    const v = q.value.trim();
    if (v.length < 3){
      tbody.innerHTML = '<tr class="empty"><td colspan="8">Type at least 3 characters…</td></tr>';
      results.hidden = v.length === 0; // hide when fully empty
      return;
    }
    doSearch(v);
  }, 200);

  q.addEventListener('input', onInput);
  activeOnly.addEventListener('change', () => {
    if (q.value.trim().length >= 3) doSearch(q.value.trim());
  });

  // Close on outside click / Esc
  document.addEventListener('click', (e) => {
    if (!box.contains(e.target)) results.hidden = true;
  });
  q.addEventListener('keydown', (e) => { if (e.key === 'Escape') results.hidden = true; });

  // Row navigation (for whole-row click, not just the anchor)
  tbody.addEventListener('click', (e) => {
    const tr = e.target.closest('tr.pl-row');
    if (!tr) return;
    const id = tr.getAttribute('data-id');
    if (id) window.location.href = `${urlBase}${encodeURIComponent(id)}`;
  });

  // --- Data stub ---
  async function searchPlayers(query, activeOnly){
    // TODO: replace with real API call, e.g.:
    // const res = await fetch(`/api/players?q=${encodeURIComponent(query)}&active=${activeOnly?1:0}`);
    // return await res.json();
    return [];
  }
})();
