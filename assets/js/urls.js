// URL builders for STHS-style routes: ProTeam.php?Team=21, ProBoxScore.php?Game=123
(() => {
  const UHA = (window.UHA ||= {});

  // -------- Routes (overridable via UHA.routes in home.php) ----------
  const routes = (UHA.routes ||= {
    mode: 'params',           // 'params' now; 'pretty' later if you want
    proTeam: 'ProTeam.php',
    farmTeam: 'FarmTeam.php',
    proBox:  'ProBoxScore.php',
    proLog:  'ProGameLog.php',
    farmBox: 'FarmBoxScore.php',
    farmLog: 'FarmGameLog.php'
  });

  // -------- Team index (abbr -> numeric ID) ----------
  // Accepts either an object map { TOR:21, ... } or an array [{id,abbr}, ...]
  const idx = (UHA.teamIndex ||= {});
  const abbrToId = (() => {
    if (Array.isArray(idx)) {
      const m = {}; idx.forEach(t => { if (t && t.abbr != null) m[t.abbr] = t.id; });
      return m;
    }
    return idx;
  })();

  function teamIdFor(abbr) {
    return abbrToId?.[abbr] ?? null;
  }

  function scopeKey(scope) {
    const s = String(scope || 'pro').toLowerCase();
    if (['farm','ahl','minors','echl'].includes(s)) return 'farm';
    return 'pro';
  }

  // -------- Helpers to extract a numeric Game # for STHS --------
  function toNum(val) {
    const n = Number.parseInt(String(val).replace(/[^\d]/g, ''), 10);
    return Number.isFinite(n) ? n : null;
  }
  function gameNoFrom(gameLike) {
    if (gameLike == null) return null;
    if (typeof gameLike === 'number') return Number.isFinite(gameLike) ? gameLike : null;
    if (typeof gameLike === 'string') return toNum(gameLike);
    // object: try common fields
    const g = gameLike;
    return toNum(g.gameNo ?? g.Game ?? g.number ?? g.id ?? '');
  }

  // -------- Public URL builders ----------
  function team(abbr, scope = 'pro') {
    const id = teamIdFor(abbr);
    const mode = routes.mode || 'params';
    if (mode === 'params') {
      const base = scopeKey(scope) === 'farm' ? routes.farmTeam : routes.proTeam;
      // Fallback: if no numeric ID yet, use ?TeamAbbr=XXX so links still work
      return id != null
        ? `${base}?Team=${encodeURIComponent(id)}`
        : `${base}?TeamAbbr=${encodeURIComponent(abbr)}`;
    }
    // pretty URL (future)
    const base = scopeKey(scope) === 'farm' ? 'team/farm' : 'team/pro';
    return id != null ? `${base}/${encodeURIComponent(id)}` : `${base}/abbr/${encodeURIComponent(abbr)}`;
  }

  function gameBox(gameLike, scope = 'pro') {
    const kind = scopeKey(scope);
    const base = kind === 'farm' ? routes.farmBox : routes.proBox;
    const num  = gameNoFrom(gameLike);
    if (base && num != null) return `${base}?Game=${encodeURIComponent(num)}`;
    // Fallbacks if we don't have numeric Game # or custom routes yet
    const id = (typeof gameLike === 'object' && gameLike) ? (gameLike.id ?? num) : gameLike;
    return `boxscore.php?id=${encodeURIComponent(id ?? '')}`;
  }

  function gameLog(gameLike, scope = 'pro') {
    const kind = scopeKey(scope);
    const base = kind === 'farm' ? routes.farmLog : routes.proLog;
    const num  = gameNoFrom(gameLike);
    if (base && num != null) return `${base}?Game=${encodeURIComponent(num)}`;
    const id = (typeof gameLike === 'object' && gameLike) ? (gameLike.id ?? num) : gameLike;
    return `gamelog.php?id=${encodeURIComponent(id ?? '')}`;
  }

  window.URLS = { team, gameBox, gameLog, teamIdFor };
})();
