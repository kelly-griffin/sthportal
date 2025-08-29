// assets/js/statistics.js — v1.8.4 (minimal patch: stacked names, colon scrub, solid team-colour avatar bg)
// Compatible with v1.8.1 markup/data attributes.
(function () {
  const pageDir = (location.pathname.replace(/\/[^\/]*$/, '/') || '/');
  const userBases = (window.UHA_HEADSHOTS_BASES && Array.isArray(window.UHA_HEADSHOTS_BASES))
    ? window.UHA_HEADSHOTS_BASES
    : (window.UHA_HEADSHOTS_BASE ? [window.UHA_HEADSHOTS_BASE] : []);

  const DEFAULT_BASES = [
    '/assets/img/mugs/',
    pageDir + 'assets/img/mugs/',
    '/data/uploads/headshots/',
    'data/uploads/headshots/',
    '../data/uploads/headshots/'
  ];
  const BASES = [...userBases, ...DEFAULT_BASES];

  // Resolve project asset paths relative to the current page (ignores <base>)
  const assetURL = (p) => {
    try {
      const s = String(p || '');
      if (/^https?:\/\//i.test(s)) return s; // already absolute
      const here = new URL(window.location.pathname, window.location.origin);
      return new URL(s.replace(/^\.?\/+/, ''), here).href;
    } catch { return p; }
  };

  const TEAM_COLORS = {
    FLA: '#C8102E', EDM: '#041E42', DET: '#CE1126', VGK: '#B4975A', PIT: '#FFB81C',
    SJS: '#006D75', TBL: '#002654', VAN: '#001F5B', COL: '#6F263D', BUF: '#003087',
    TOR: '#00205B', MTL: '#AF1E2D', NYR: '#0038A8', NJD: '#CE1126', BOS: '#FFB81C',
    OTT: '#D1202E', PHI: '#F74902', WSH: '#C8102E', CAR: '#CC0000', CGY: '#C8102E',
    WPG: '#041E42', STL: '#002F6C', DAL: '#006847', LAK: '#111111', ANA: '#F47A38',
    ARI: '#8C2633', MIN: '#154734', CBJ: '#002654', NSH: '#FFB81C', SEA: '#1F4E5F',
    UTA: '#000000', CHI: '#CF0A2C'
  };

// Pure string replacement; no DOM parsing or HTML interpretation.
// codeql[js/xss-through-dom]  lgtm[js/xss-through-dom]
const decodeEntities = (s) => String(s ?? '').replace(/&(amp|#38);/g, '&');
  const extractNhlId = raw => { const m = String(raw || '').match(/(\d{6,8})/); return m ? m[1] : ''; };
  function getSeasonSlug(d = new Date()) { const y = d.getFullYear(), m = d.getMonth() + 1; const start = (m >= 7) ? y : (y - 1), end = start + 1; return '' + start + end; }
  const mugsUrl = (team, id, season) => `https://assets.nhle.com/mugs/nhl/${season || getSeasonSlug()}/${(team || '').toUpperCase().replace(/[^A-Z]/g, '')}/${id}.png`;
  const cmsUrl = id => `https://cms.nhl.bamgrid.com/images/headshots/current/168x168/${id}.jpg`;

  function initials(name) {
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase() || '??';
  }
  function ensureAvatarChildren(av) {
    let img = av.querySelector('[data-avatar-img]');
    if (!img) { img = document.createElement('img'); img.setAttribute('data-avatar-img', ''); img.alt = ''; av.appendChild(img); }
    let init = av.querySelector('[data-avatar-initials]');
    if (!init) { init = document.createElement('span'); init.setAttribute('data-avatar-initials', ''); init.className = 'avatar-initials'; av.appendChild(init); }
    return { img, init };
  }
  function accentFromTeam(team) {
    const key = (team || '').toUpperCase();
    const COLOR = TEAM_COLORS[key];
    if (COLOR) return COLOR;
    let hash = 0; for (let i = 0; i < key.length; i++) hash = ((hash << 5) - hash) + key.charCodeAt(i);
    const hue = Math.abs(hash) % 360; return `hsl(${hue},65%,35%)`;
  }

  function setStackedName(feature, rawName) {
    const nameEl = feature.querySelector('[data-name]');
    if (!nameEl) return;
    const parts = String(rawName || '').trim().split(/\s+/);
    const first = parts.shift() || '';
    const last = parts.join(' ');
    // escape user text, keep your stacked <span> markup
    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
    nameEl.textContent = '';

    if (first && last) {
      const s1 = document.createElement('span');
      s1.className = 'first';
      s1.textContent = first;

      const s2 = document.createElement('span');
      s2.className = 'last';
      s2.textContent = last;

      nameEl.append(s1, s2);
    } else {
      const s = document.createElement('span');
      s.className = 'last';
      s.textContent = first || last;
      nameEl.append(s);
    }

  }

  function scrubColon(feature) {
    const box = feature.querySelector('.feat-metric');
    if (!box) return;
    // remove any lone ":" text nodes between label and value
    const nodes = Array.from(box.childNodes);
    nodes.forEach(n => {
      if (n.nodeType === 3 && n.nodeValue && n.nodeValue.trim() === ':') { box.removeChild(n); }
    });
  }

  function setFeature(card, li) {
    if (!li) return;
    const feature = card.querySelector('[data-card-feature]');
    if (!feature) return;
    const name = li.getAttribute('data-name') || '—';
    const team = li.getAttribute('data-team') || '';
    const teamNum = li.getAttribute('data-teamnum') || '';
    const metric = li.getAttribute('data-metric') || '';
    const val = li.getAttribute('data-valtext') || li.getAttribute('data-val') || '—';

    // Name (stacked)
    setStackedName(feature, name);

    // Basic fields
    const teamEl = feature.querySelector('[data-team]');
    if (teamEl) teamEl.textContent = team;
    const labEl = feature.querySelector('[data-metric-label]');
    if (labEl) labEl.textContent = metric;
    const valEl = feature.querySelector('[data-metric-val]');
    if (valEl) valEl.textContent = val;

    // Map metric label to human text (no colon)
    (function () {
      const map = { 'PTS': 'POINTS', 'G': 'GOALS', 'A': 'ASSISTS', 'GAA': 'GAA', 'SV%': 'SV%', 'SO': 'SHUTOUTS' };
      if (labEl) labEl.textContent = map[metric] || String(metric || '').toUpperCase();
      scrubColon(feature);
    })();

    // Position & info row
    (function () {
      let p = li.getAttribute('data-pos') || li.getAttribute('data-position') || li.getAttribute('data-poscode') || '';
      const up = s => (s || '').toString().toUpperCase();
      if (!p) {
        const truthy = v => /^(TRUE|1|Y|T)$/i.test(v || '');
        const flags = { C: false, L: false, R: false, D: false, G: false };
        for (const a of li.attributes) {
          if (!a.name.startsWith('data-')) continue;
          const n = a.name.toLowerCase(), v = up(a.value);
          if (!truthy(v)) continue;
          if (/posc$|_posc$|\bc$/.test(n)) flags.C = true;
          if (/poslw$|_poslw$|\blw$|\bl$/.test(n)) flags.L = true;
          if (/posrw$|_posrw$|\brw$|\br$/.test(n)) flags.R = true;
          if (/posd$|_posd$|\bd$/.test(n)) flags.D = true;
          if (/posg$|_posg$|\bg$/.test(n)) flags.G = true;
        }
        if (flags.G) p = 'G';
        else if (flags.D && !(flags.C || flags.L || flags.R)) p = 'D';
        else {
          const parts = []; if (flags.C) parts.push('C'); if (flags.L) parts.push('L'); if (flags.R) parts.push('R');
          if (parts.length === 3) p = 'F'; else if (parts.length) p = parts.join('/');
        }
      }
      const posEl = feature.querySelector('[data-pos]');
      if (posEl) posEl.textContent = p || '';
      const info = feature.querySelector('.feat-info');
      if (info) {
        const dots = info.querySelectorAll('.dot');
        const rawNum = li.getAttribute('data-number') || li.getAttribute('data-jersey') || '';
        const hasNum = !!(rawNum && rawNum !== '0');
        const displayNum = hasNum ? ('#' + rawNum) : '#';
        // always show the first dot so the bar doesn't look stubby
        if (dots[0]) dots[0].style.display = '';
        if (dots[1]) dots[1].style.display = (p && String(p).trim()) ? '' : 'none';
        const numEl = feature.querySelector('[data-number]');
        if (numEl) numEl.textContent = displayNum;
      }

      const codeEl = feature.querySelector('[data-team-code]');
      if (codeEl) codeEl.textContent = (team || '').toUpperCase();
      const logoEl = feature.querySelector('[data-team-logo]');
      if (logoEl) {
        const code = (team || '').toUpperCase().replace(/[^A-Z]/g, '');
        const path = code ? `assets/img/logos/${code}_dark.svg` : `assets/img/logos/_unknown.svg`;
        logoEl.src = assetURL(path);
        logoEl.alt = (code || 'Team') + ' logo';
        // graceful fallback if specific logo file is missing
        logoEl.onerror = () => { logoEl.onerror = null; logoEl.src = assetURL('assets/img/logos/_unknown.svg'); };
      }
    })();

    // Accent & avatar
    const accent = accentFromTeam(team);
    feature.style.setProperty('--team-accent', accent);
    feature.style.setProperty('--avatar-bg', accent);

    const av = feature.querySelector('[data-avatar]');
    if (!av) return;
    const { img, init } = ensureAvatarChildren(av);
    const id = extractNhlId(decodeEntities(li.getAttribute('data-photo') || ''));

    if (id) {
      const cleanTeam = (team || '').toUpperCase().replace(/[^A-Z]/g, '');
      const sources = [];
      const add = (p) => { if (!p) return; sources.push(/^https?:\/\//i.test(p) ? p : assetURL(p)); };
      for (const base of BASES) {
        add(`${base}${id}.png`);
        add(`${base}${id}.jpg`);
        if (teamNum) add(`${base}${teamNum}/${id}.png`);
        add(`${base}${cleanTeam}/${id}.png`);
      }
      const remote1 = mugsUrl(team, id);
      const remote2 = cmsUrl(id);
      let idx = 0;
      const tryNext = () => {
        if (idx < sources.length) {
          // codeql[js/xss-through-dom]: assigning a URL to an <img> attribute; not parsing HTML.
          img.src = String(sources[idx++]);
          img.onerror = tryNext;
        } else if (idx === sources.length) {
          idx++;
          // codeql[js/xss-through-dom]: league-controlled URL; still not HTML parsing.
          img.src = String(remote1);
          img.onerror = tryNext;
        } else {
          img.onerror = null;
          // codeql[js/xss-through-dom]: final fallback URL; attribute assignment only.
          img.src = String(remote2);
        }
      };
      tryNext();
      img.alt = name + ' headshot';
      av.classList.add('has-photo');
      init.textContent = '';
    } else {
      img.removeAttribute('src');
      av.classList.remove('has-photo');
      init.textContent = initials(name);
    }
  }

  document.querySelectorAll('.leaders-card').forEach(card => {
    const tabs = card.querySelectorAll('.metric-tab');
    const panes = card.querySelectorAll('.metric-panel');
    tabs.forEach(btn => btn.addEventListener('click', () => {
      const metric = btn.dataset.metric;
      tabs.forEach(b => { b.classList.toggle('active', b === btn); b.setAttribute('aria-selected', b === btn ? 'true' : 'false'); });
      panes.forEach(p => p.classList.toggle('active', p.dataset.metric === metric));
      const first = card.querySelector('.metric-panel.active li'); setFeature(card, first);
    }));
    panes.forEach(panel => {
      panel.addEventListener('mouseover', e => { const li = e.target.closest('li'); if (li) setFeature(card, li); });
      panel.addEventListener('focusin', e => { const li = e.target.closest('li'); if (li) setFeature(card, li); });
    });
    const first = card.querySelector('.metric-panel.active li'); setFeature(card, first);
  });
})();
// --- Main tabs: Home / Skaters / Goalies / Teams ---
(function () {
  function activate(tab) {
    const links = document.querySelectorAll('.stats-subnav .tab-link');
    const panels = document.querySelectorAll('.tab-panel');
    links.forEach(a => a.classList.toggle('active', a.dataset.tab === tab));
    panels.forEach(p => p.classList.toggle('active', p.id === ('tab-' + tab)));
  }
  document.addEventListener('DOMContentLoaded', function () {
    const links = document.querySelectorAll('.stats-subnav .tab-link');
    if (!links.length) return;

    // initial from hash (e.g., #skaters) or default to home
    const initial = (location.hash || '#home').slice(1);
    activate(initial);

    links.forEach(a => {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        const tab = a.dataset.tab || 'home';
        activate(tab);
        history.replaceState(null, '', '#' + tab);
      });
    });
  });
})();
// --- Skaters: client-side sort + top-N (header click enabled) ---
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('#tab-skaters .skaters-subpanel[data-subtab="summary"]');
    if (!panel) return;

    const tbody = panel.querySelector('.skaters-table tbody');
    const headers = panel.querySelectorAll('.skaters-table thead th[data-sort]');
    const sortSel = document.querySelector('#skaters-sort'); // optional existing dropdown
    const rowsSel = document.querySelector('#skaters-rows');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const numeric = new Set([
      'gp', 'g', 'a', 'p',
      'plusminus', 'pim', 'p_per_gp',
      'evg', 'ppg', 'ppp', 'shg', 'shp',
      'otg', 'gwg', 's', 's_pct',
      'toi_gp', 'fow_pct'
    ]);
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

    function renumber() {
      let n = 1;
      rows.forEach(r => {
        if (r.style.display === 'none') return;
        const cell = r.querySelector('.col-rank');
        if (cell) cell.textContent = n++;
      });
    }

    function setHeaderState(key, dir) {
      // header arrow state
      headers.forEach(h => {
        h.classList.remove('active', 'asc', 'desc');
        h.removeAttribute('aria-sort');
      });
      const active = panel.querySelector(`.skaters-table thead th[data-sort="${key}"]`);
      if (active) {
        active.classList.add('active', dir);
        active.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
      }

      // column highlight (NHL.com style)
      const table = panel.querySelector('.skaters-table');
      if (!table || !active) return;
      table.querySelectorAll('th,td').forEach(c => c.classList.remove('active-col'));
      const idx = Array.from(active.parentNode.children).indexOf(active) + 1;
      table.querySelectorAll('tr td:nth-child(' + idx + '), tr th:nth-child(' + idx + ')')
        .forEach(c => c.classList.add('active-col'));
    }

    function sortBy(key, dir) {
      const isNum = numeric.has(key);
      rows.sort((r1, r2) => {
        const a = isNum ? parseFloat(r1.dataset[key] || '0') || 0 : (r1.dataset[key] || '');
        const b = isNum ? parseFloat(r2.dataset[key] || '0') || 0 : (r2.dataset[key] || '');
        let cmp = isNum ? (a - b) : collator.compare(a, b);
        return dir === 'asc' ? cmp : -cmp;
      });
      rows.forEach(r => tbody.appendChild(r));
      setHeaderState(key, dir);
      renumber();
    }

    function showTop(val) {
      const text = String(val || '').trim().toLowerCase();
      let limit = Infinity;
      if (text && text !== 'all') {
        const n = parseInt(text, 10);
        if (Number.isFinite(n) && n > 0) limit = n;
      }
      rows.forEach((r, i) => { r.style.display = (i < limit) ? '' : 'none'; });
      renumber();
    }

    // Header clicks
    let current = { key: 'p', dir: 'desc' }; // default: Points desc
    headers.forEach(th => {
      th.addEventListener('click', () => {
        const key = th.dataset.sort;
        // Toggle dir if clicking same header, else default dir (num=desc, text=asc)
        const defaultDir = numeric.has(key) ? 'desc' : 'asc';
        const dir = (current.key === key)
          ? (current.dir === 'desc' ? 'asc' : 'desc')
          : defaultDir;
        current = { key, dir };
        sortBy(key, dir);
        if (sortSel) sortSel.value = key; // keep dropdown (if present) in sync
      });
    });

    // Dropdown hooks still work
    if (sortSel) sortSel.addEventListener('change', () => {
      const key = sortSel.value;
      const dir = numeric.has(key) ? 'desc' : 'asc';
      current = { key, dir };
      sortBy(key, dir);
    });
    if (rowsSel) rowsSel.addEventListener('change', () => showTop(rowsSel.value));

    // Defaults
    sortBy('p', 'desc');
    showTop(rowsSel ? rowsSel.value : 'all');
  });
})();


// --- Skaters subtab router (Summary / Bio / Faceoff % / Faceoff W&L)
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('#tab-skaters');
    if (!root) return;
    const select = root.querySelector('#skaters-subtab');
    const panels = root.querySelectorAll('.skaters-subpanel');
    const label = root.querySelector('.skaters-toolbar .label');

    function setPill(which) {
      if (!label) return;
      label.textContent =
        which === 'bio' ? 'Bio Info' :
          which === 'faceoffs' ? 'Face-off Percentages' :
            which === 'faceoffs-wl' ? 'Face-off Wins & Losses' :
              'Summary';
    }

    function show(which) {
      panels.forEach(p => {
        const on = p.getAttribute('data-subtab') === which;
        p.toggleAttribute('hidden', !on);
      });
      setPill(which);
      if (root && typeof root.__applyRows === "function") { root.__applyRows(); }
      try {
        history.replaceState(null, '', (
          which === 'bio' ? '#skaters-bio' :
            which === 'faceoffs' ? '#skaters-fo' :
              which === 'faceoffs-wl' ? '#skaters-fo-wl' :
                '#skaters'
        ));
      } catch { }
    }

    if (select) select.addEventListener('change', () => show(select.value));

    const wantsBio = /#skaters-bio$/i.test(location.hash);
    const wantsFO = /#skaters-fo$/i.test(location.hash);
    const wantsFOWL = /#skaters-fo-wl$/i.test(location.hash);

    if (select) {
      select.value = wantsFOWL ? 'faceoffs-wl' : (wantsFO ? 'faceoffs' : (wantsBio ? 'bio' : 'summary'));
      show(select.value);
    } else {
      show(wantsFOWL ? 'faceoffs-wl' : (wantsFO ? 'faceoffs' : (wantsBio ? 'bio' : 'summary')));
    }
  });
})();

// --- Bio Info: header sort (scoped inside #tab-skaters) ---
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('#tab-skaters .skaters-subpanel[data-subtab="bio"]');
    if (!panel) return;
    const tbody = panel.querySelector('.bio-table tbody');
    const headers = panel.querySelectorAll('.bio-table thead th[data-sort]');
    if (!tbody || !headers.length) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const numeric = new Set(['weight', 'draft_year', 'draft_round', 'draft_overall', 'gp', 'g', 'a', 'p']);
    const dateish = new Set(['dob']);
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
    function renumber() {
      let n = 1;
      rows.forEach(r => {
        if (r.style.display === 'none') return;
        const cell = r.querySelector('.col-rank');
        if (cell) cell.textContent = n++;
      });
    }
    function setHeaderState(key, dir) {
      headers.forEach(h => { h.classList.remove('active', 'asc', 'desc'); h.removeAttribute('aria-sort'); });
      const active = panel.querySelector('.bio-table thead th[data-sort="' + key + '"]');
      if (active) { active.classList.add('active', dir); active.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending'); }

      const table = panel.querySelector('.bio-table');
      if (!table || !active) return;
      table.querySelectorAll('th,td').forEach(c => c.classList.remove('active-col'));
      const idx = Array.from(active.parentNode.children).indexOf(active) + 1;
      table.querySelectorAll('tr td:nth-child(' + idx + '), tr th:nth-child(' + idx + ')')
        .forEach(c => c.classList.add('active-col'));
    }

    function sortBy(key, dir) {
      rows.sort((r1, r2) => {
        let a = r1.dataset[key] || '';
        let b = r2.dataset[key] || '';
        if (numeric.has(key)) { a = parseFloat(a) || 0; b = parseFloat(b) || 0; return dir === 'asc' ? a - b : b - a; }
        if (dateish.has(key)) { const ta = a ? Date.parse(a) : 0, tb = b ? Date.parse(b) : 0; return dir === 'asc' ? ta - tb : tb - ta; }
        return dir === 'asc' ? collator.compare(a, b) : collator.compare(b, a);
      });
      rows.forEach(r => tbody.appendChild(r));
      setHeaderState(key, dir);
      renumber();
    }
    sortBy('name', 'asc');
    headers.forEach(th => {
      th.addEventListener('click', () => {
        const key = th.dataset.sort;
        const isNum = numeric.has(key) || dateish.has(key);
        const current = th.classList.contains('active') ? (th.classList.contains('desc') ? 'desc' : 'asc') : null;
        const next = current ? (current === 'desc' ? 'asc' : 'desc') : (isNum ? 'desc' : 'asc');
        sortBy(key, next);
      });
    });
  });
})();


// --- Face-off %: header sort (scoped inside #tab-skaters) ---
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('#tab-skaters .skaters-subpanel[data-subtab="faceoffs"]');
    if (!panel) return;

    const table = panel.querySelector('.bio-table');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const headers = table.querySelectorAll('thead th[data-sort]');
    if (!tbody || !headers.length) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const numeric = new Set([
      'gp', 'fo', 'ev_fo', 'pp_fo', 'sh_fo', 'oz_fo', 'nz_fo', 'dz_fo',
      'fow_pct', 'ev_fow_pct', 'pp_fow_pct', 'sh_fow_pct', 'oz_fow_pct', 'nz_fow_pct', 'dz_fow_pct'
    ]);
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

    function renumber() {
      let n = 1;
      rows.forEach(r => {
        if (r.style.display === 'none') return;
        const cell = r.querySelector('.col-rank');
        if (cell) cell.textContent = n++;
      });
    }

    function setHeaderState(key, dir) {
      headers.forEach(h => { h.classList.remove('active', 'asc', 'desc'); h.removeAttribute('aria-sort'); });
      const active = table.querySelector('thead th[data-sort="' + key + '"]');
      if (active) {
        active.classList.add('active', dir);
        active.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
      }
      if (!active) return;

      table.querySelectorAll('th,td').forEach(c => c.classList.remove('active-col'));
      const idx = Array.from(active.parentNode.children).indexOf(active) + 1;
      table.querySelectorAll('tr td:nth-child(' + idx + '), tr th:nth-child(' + idx + ')')
        .forEach(c => c.classList.add('active-col'));
    }

    // define sortBy INSIDE this IIFE
    function sortBy(key, dir) {
      const isNum = numeric.has(key);
      rows.sort((r1, r2) => {
        const a = isNum ? parseFloat(r1.dataset[key] || '0') || 0 : (r1.dataset[key] || '');
        const b = isNum ? parseFloat(r2.dataset[key] || '0') || 0 : (r2.dataset[key] || '');
        const cmp = isNum ? (a - b) : collator.compare(a, b);
        return dir === 'asc' ? cmp : -cmp;
      });
      rows.forEach(r => tbody.appendChild(r));
      setHeaderState(key, dir);
      renumber();
    }

    // Default sort + header wiring
    sortBy('fow_pct', 'desc');
    headers.forEach(th => {
      th.addEventListener('click', () => {
        const key = th.dataset.sort;
        const isNum = numeric.has(key);
        const current = th.classList.contains('active')
          ? (th.classList.contains('desc') ? 'desc' : 'asc') : null;
        const next = current ? (current === 'desc' ? 'asc' : 'desc')
          : (isNum ? 'desc' : 'asc');
        sortBy(key, next);
      });
    });
  });
})();

// --- Face-off Wins & Losses: header sort (scoped inside #tab-skaters) ---
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('#tab-skaters .skaters-subpanel[data-subtab="faceoffs-wl"]');
    if (!panel) return;
    const table = panel.querySelector('.bio-table');
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const headers = table.querySelectorAll('thead th[data-sort]');
    if (!tbody || !headers.length) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const numeric = new Set(['gp', 'fo', 'fow', 'fol', 'fow_pct', 'ev_fo', 'ev_fow', 'ev_fol', 'pp_fo', 'pp_fow', 'pp_fol', 'sh_fo', 'sh_fow', 'sh_fol', 'oz_fo', 'oz_fow', 'oz_fol', 'nz_fo', 'nz_fow', 'nz_fol', 'dz_fo', 'dz_fow', 'dz_fol']);
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
    function renumber() {
      let n = 1;
      rows.forEach(r => {
        if (r.style.display === 'none') return;
        const cell = r.querySelector('.col-rank');
        if (cell) cell.textContent = n++;
      });
    }
    function setHeaderState(key, dir) {
      headers.forEach(h => { h.classList.remove('active', 'asc', 'desc'); h.removeAttribute('aria-sort'); });
      const active = table.querySelector('thead th[data-sort="' + key + '"]');
      if (active) { active.classList.add('active', dir); active.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending'); }

      if (!active) return;
      table.querySelectorAll('th,td').forEach(c => c.classList.remove('active-col'));
      const idx = Array.from(active.parentNode.children).indexOf(active) + 1;
      table.querySelectorAll('tr td:nth-child(' + idx + '), tr th:nth-child(' + idx + ')')
        .forEach(c => c.classList.add('active-col'));
    }

    function sortBy(key, dir) {
      rows.sort((r1, r2) => {
        const isNum = numeric.has(key);
        const a = isNum ? parseFloat(r1.dataset[key] || '0') || 0 : (r1.dataset[key] || '');
        const b = isNum ? parseFloat(r2.dataset[key] || '0') || 0 : (r2.dataset[key] || '');
        const cmp = isNum ? (a - b) : collator.compare(a, b);
        return dir === 'asc' ? cmp : -cmp;
      });
      rows.forEach(r => tbody.appendChild(r));
      setHeaderState(key, dir);
      renumber();
    }
    sortBy('fow', 'desc');
    headers.forEach(th => {
      th.addEventListener('click', () => {
        const key = th.dataset.sort;
        const isNum = numeric.has(key);
        const current = th.classList.contains('active') ? (th.classList.contains('desc') ? 'desc' : 'asc') : null;
        const next = current ? (current === 'desc' ? 'asc' : 'desc') : (isNum ? 'desc' : 'asc');
        sortBy(key, next);
      });
    });
  });
})();
// --- Skaters: make #skaters-rows apply to the ACTIVE subtab (Summary/Bio/FO/FO-WL) ---
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('#tab-skaters');
    const rowsSel = document.querySelector('#skaters-rows');
    if (!root || !rowsSel) return;

    function renumber(tbody) {
      let n = 1;
      Array.from(tbody.querySelectorAll('tr')).forEach(r => {
        if (r.style.display === 'none') return;
        const cell = r.querySelector('.col-rank');
        if (cell) cell.textContent = n++;
      });
    }

    function applyRows() {
      // parse "All" or numeric values
      const t = String(rowsSel.value || '').trim().toLowerCase();
      let limit = Infinity;
      if (t && t !== 'all') {
        const n = parseInt(t, 10);
        if (Number.isFinite(n) && n > 0) limit = n;
      }

      // find the visible subpanel and its tbody
      const active = root.querySelector('.skaters-subpanel:not([hidden])');
      if (!active) return;
      const tbody =
        active.querySelector('.skaters-table tbody') ||
        active.querySelector('.bio-table tbody');
      if (!tbody) return;

      const rows = Array.from(tbody.querySelectorAll('tr'));
      rows.forEach((r, i) => { r.style.display = (i < limit) ? '' : 'none'; });
      renumber(tbody);
    }

    // run when the dropdown changes
    rowsSel.addEventListener('change', applyRows);

    // run on load
    applyRows();

    // expose so the subtab router can re-apply after switching
    root.__applyRows = applyRows;
  });
})();
// --- GOALIES TAB — unified sorter (default: W desc, tiebreaker SV% desc) ---
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('#tab-goalies .goalies-subpanel[data-subtab="summary"]');
    if (!panel) return;

    const table = panel.querySelector('.bio-table');
    const tbody = table?.querySelector('tbody');
    const headers = table?.querySelectorAll('thead th[data-sort]');
    if (!table || !tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const numeric = new Set(['gp', 'gs', 'w', 'l', 'ot', 'sa', 'svs', 'ga', 'sv_pct', 'gaa', 'toi_sec', 'so', 'a', 'pim']);
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

    function renumber() {
      let n = 1;
      rows.forEach(r => {
        if (r.style.display === 'none') return;
        const cell = r.querySelector('.col-rank');
        if (cell) cell.textContent = n++;
      });
    }

    function setHeaderState(key, dir) {
      if (!headers) return;
      headers.forEach(h => { h.classList.remove('active', 'asc', 'desc'); h.removeAttribute('aria-sort'); });
      const active = table.querySelector('thead th[data-sort="' + key + '"]');
      if (active) { active.classList.add('active', dir); active.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending'); }

      if (!active) return;
      table.querySelectorAll('th,td').forEach(c => c.classList.remove('active-col'));
      const idx = Array.from(active.parentNode.children).indexOf(active) + 1;
      table.querySelectorAll('tr td:nth-child(' + idx + '), tr th:nth-child(' + idx + ')')
        .forEach(c => c.classList.add('active-col'));
    }


    function val(r, k) {
      const v = r.dataset[k] ?? '';
      return numeric.has(k) ? (parseFloat(v) || 0) : v;
    }

    function sortBy(key, dir) {
      rows.sort((r1, r2) => {
        // primary compare
        const a1 = val(r1, key), b1 = val(r2, key);
        if (a1 !== b1) {
          const cmp = numeric.has(key) ? (a1 - b1) : collator.compare(a1, b1);
          return dir === 'asc' ? cmp : -cmp;
        }
        // tie-breaker when sorting by Wins: SV% desc
        if (key === 'w') {
          const a2 = val(r1, 'sv_pct'), b2 = val(r2, 'sv_pct');
          if (a2 !== b2) return b2 - a2;
        }
        return 0;
      });

      rows.forEach((r, i) => {
        tbody.appendChild(r);                       // apply new order
        const cell = r.querySelector('.col-rank'); // keep Rank = 1..N
        if (cell) cell.textContent = i + 1;
      });

      setHeaderState(key, dir);
      if (window.__applyGoaliesRows) window.__applyGoaliesRows();
    }

    // Header clicks (ignore Rank if it accidentally has data-sort)
    headers?.forEach(th => {
      th.addEventListener('click', () => {
        const key = th.dataset.sort;
        if (!key || key === 'rank') return;
        const defaultDir = numeric.has(key) ? 'desc' : 'asc';
        const isActive = th.classList.contains('active');
        const dir = isActive ? (th.classList.contains('desc') ? 'asc' : 'desc') : defaultDir;
        sortBy(key, dir);
      });
    });

    // Default on load: Wins ↓ (then SV% ↓)
    sortBy('w', 'desc');
  });
})();



// --- Rows limiter: GOALIES (#goalies-rows) ---
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('#tab-goalies');
    const rowsSel = document.querySelector('#goalies-rows');
    if (!root || !rowsSel) return;

    function renumber(tbody) {
      let n = 1;
      Array.from(tbody.querySelectorAll('tr')).forEach(r => {
        if (r.style.display === 'none') return;
        const cell = r.querySelector('.col-rank');
        if (cell) cell.textContent = n++;
      });
    }

    function applyRows() {
      const t = String(rowsSel.value || '').trim().toLowerCase();
      let limit = Infinity;
      if (t && t !== 'all') {
        const n = parseInt(t, 10);
        if (Number.isFinite(n) && n > 0) limit = n;
      }

      const panel = root.querySelector('.goalies-subpanel:not([hidden])') || root;
      const tbody = panel.querySelector('tbody');
      if (!tbody) return;
      const rows = Array.from(tbody.querySelectorAll('tr'));
      rows.forEach((r, i) => { r.style.display = (i < limit) ? '' : 'none'; });
      renumber(tbody);
    }

    rowsSel.addEventListener('change', applyRows);
    // expose so the sorters can re-apply after sorting
    window.__applyGoaliesRows = applyRows;
    applyRows();
  });
})();
// --- TEAMS: sorter (default: Points desc, tie-break RW desc then ROW desc) ---
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('#tab-teams .teams-subpanel[data-subtab="summary"]');
    if (!panel) return;
    const table = panel.querySelector('.teams-table');
    const tbody = table?.querySelector('tbody');
    const heads = table?.querySelectorAll('thead th[data-sort]');
    if (!table || !tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const numeric = new Set(['gp', 'w', 'l', 'ot', 'p', 'p_pct', 'rw', 'row', 'sow', 'gf', 'ga', 'gf_gp', 'ga_gp', 'pp_pct', 'pk_pct', 'net_pp_pct', 'net_pk_pct', 'shots_gp', 'sa_gp', 'fow_pct']);
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

    function renumber() {
      let n = 1;
      rows.forEach(r => {
        if (r.style.display === 'none') return;
        const cell = r.querySelector('.col-rank');
        if (cell) cell.textContent = n++;
      });
    }

    function setHead(key, dir) {
      heads?.forEach(h => { h.classList.remove('active', 'asc', 'desc'); h.removeAttribute('aria-sort'); });
      const th = table.querySelector('thead th[data-sort="' + key + '"]');
      if (th) { th.classList.add('active', dir); th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending'); }

      if (!th) return;
      table.querySelectorAll('th,td').forEach(c => c.classList.remove('active-col'));
      const idx = Array.from(th.parentNode.children).indexOf(th) + 1;
      table.querySelectorAll('tr td:nth-child(' + idx + '), tr th:nth-child(' + idx + ')')
        .forEach(c => c.classList.add('active-col'));
    }


    function val(r, k) { const v = r.dataset[k] ?? ''; return numeric.has(k) ? (parseFloat(v) || 0) : v; }

    function sortBy(key, dir) {
      rows.sort((r1, r2) => {
        const a1 = val(r1, key), b1 = val(r2, key);
        if (a1 !== b1) {
          const cmp = numeric.has(key) ? (a1 - b1) : collator.compare(a1, b1);
          return dir === 'asc' ? cmp : -cmp;
        }
        if (key === 'p') { // tie-breakers for Points
          const a2 = val(r1, 'rw'), b2 = val(r2, 'rw'); if (a2 !== b2) return b2 - a2;
          const a3 = val(r1, 'row'), b3 = val(r2, 'row'); if (a3 !== b3) return b3 - a3;
        }
        return 0;
      });
      rows.forEach((r, i) => { tbody.appendChild(r); const c = r.querySelector('.col-rank'); if (c) c.textContent = i + 1; });
      setHead(key, dir);
    }

    heads?.forEach(th => {
      th.addEventListener('click', () => {
        const key = th.dataset.sort;
        if (!key || key === 'rank') return;
        const def = numeric.has(key) ? 'desc' : 'asc';
        const isActive = th.classList.contains('active');
        const dir = isActive ? (th.classList.contains('desc') ? 'asc' : 'desc') : def;
        sortBy(key, dir);
      });
    });

    sortBy('p', 'desc'); // default Points ↓
  });
})();
