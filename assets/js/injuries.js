// assets/js/injuries.js — Home Injuries rail (mini-rail markup, idempotent)
(function () {
  const Q = (sel, ctx = document) => ctx.querySelector(sel);

  // Accept {pro:{TEAM:[...]}}; gracefully handle legacy flat arrays (injuries.items)
  function group(payload) {
    if (!payload) return {};
    if (payload.pro && typeof payload.pro === 'object' && !Array.isArray(payload.pro)) return payload.pro;
    const items = Array.isArray(payload.items) ? payload.items : [];
    const g = {};
    for (const m of items) {
      const code = (m.teamCode || m.teamAbbr || '').toUpperCase();
      if (!code) continue;
      (g[code] = g[code] || []).push({
        player: m.player || '',
        detail: m.detail || '',
        timeline: m.timeline || m.term || '',
        date: m.date || ''
      });
    }
    return g;
  }

  // Heuristic badge from timeline
  function statusFrom(tl = '') {
    const t = tl.toLowerCase();
    const m = t.match(/(\d+)\s*(day|week|month)/);
    if (m) {
      const n = +m[1], u = m[2];
      if (u.startsWith('day'))   return n < 7 ? 'DTD' : 'IR';
      if (u.startsWith('week'))  return n >= 4 ? 'LTIR' : 'IR';
      if (u.startsWith('month')) return 'LTIR';
    }
    if (/indefinite|season/.test(t)) return 'LTIR';
    return '';
  }

  // HEX-safe text -> HTML
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => (
    {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
  ));

  // Skip “Exhaustion*”
  const isExhaustion = (m) =>
    /\bexhaust(?:ion|ed|ing)?\b/i.test(`${m?.detail || ''} ${m?.timeline || ''} ${m?.player || ''}`);

  function render() {
    const card = Q('#injuries-card');
    const list = Q('#inj-list');
    const stat = Q('#inj-status');
    if (!card || !list) return;

    const data = (window.UHA && window.UHA.injuries) || {};
    const teamsRaw = group(data);

    // Apply filter per team; drop teams that end up empty
    const teams = {};
    Object.keys(teamsRaw).forEach(code => {
      const kept = (teamsRaw[code] || []).filter(m => !isExhaustion(m));
      if (kept.length) teams[code] = kept;
    });

    const codes = Object.keys(teams);

    // Idempotent clear
    list.textContent = '';

    if (!codes.length) {
      // Hide rail cleanly when empty
      card.style.display = 'none';
      if (stat) stat.textContent = '';
      return;
    }

    // Show rail & clear “Loading…”
    card.style.display = '';
    if (stat) stat.textContent = '';

    // Render: one mini row per injury (no separate team header row)
    for (const code of codes) {
      const rowsHTML = teams[code].map(m => {
        const st = (m.status && String(m.status).toUpperCase()) || statusFrom(m.timeline);
        const badge  = st ? `<span class="inj-badge">${esc(st)}</span>` : '';
        const detail = m.detail ? ` <span class="inj-detail">(${esc(m.detail)})</span>` : '';
        const tl     = m.timeline ? ` — <span class="inj-timeline">${esc(m.timeline)}</span>` : '';
        const dt     = m.date ? `<span class="inj-date">${esc(m.date)}</span>` : '';

        return `<li class="inj-row inj-mini-row">
          <div class="who">
            <img class="inj-logo" src="assets/img/logos/${esc(code)}_dark.svg" alt="">
            <span class="inj-abbr">${esc(code)}</span>
          </div>
          <div class="desc">
            <strong class="inj-player">${esc(m.player || '')}</strong>
            ${badge}${detail}${tl}
          </div>
          <div class="date">${dt}</div>
        </li>`;
      }).join('');

      list.insertAdjacentHTML('beforeend', rowsHTML);
    }
  }

  function safeRender(){ try { render(); } catch(e){ console.error('Injuries render failed:', e); } }

  // Paint now and when payload announces itself
  safeRender();
  document.addEventListener('UHA:injuries-ready', safeRender);

  // Tiny probe
  window.UHA = window.UHA || {};
  window.UHA.debugInjuries = function () {
    const p = (window.UHA.injuries && window.UHA.injuries.pro) || {};
    const out = {}; for (const [k,v] of Object.entries(p)) out[k] = Array.isArray(v) ? v.length : 0;
    console.log('Injuries per team:', out);
    return out;
  };
})();
