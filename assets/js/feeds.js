// feeds.js — homepage headlines aggregator (API → team file feeds → UHA.headlines)
(() => {
  const $ = s => document.querySelector(s);
  const MAX = 6;

  const grid = $('#topHeadlinesGrid');
  const wrap = grid?.closest('.top-headlines');
  if (!grid || !wrap) return;

  function timeAgo(iso){
    if (!iso) return '';
    const then = new Date(iso).getTime();
    if (!isFinite(then)) return '';
    const diff = Math.max(0, Date.now() - then);
    const m = Math.floor(diff / 60000);
    if (m < 60)  return `${m}m`;
    const h = Math.floor(m / 60);
    if (h < 48)  return `${h}h`;
    const d = Math.floor(h / 24);
    return `${d}d`;
  }

  function cardHTML(h){
    const img = h.image ? `<div class="th-thumb" style="background-image:url('${h.image}')"></div>` : `<div class="th-thumb empty">IMAGE</div>`;
    const team = h.team ? `<span class="th-tag">${h.team}</span>` : '';
    const ago  = h.published_at ? `<span class="th-ago">${timeAgo(h.published_at)}</span>` : (h.timeAgo ? `<span class="th-ago">${h.timeAgo}</span>` : '');
    const href = h.link || `news-article.php?id=${encodeURIComponent(h.id||'')}`;
    return `<a class="th-card" href="${href}">
      ${img}
      <div class="th-body">
        <div class="th-title">${h.title || ''}</div>
        <div class="th-meta">${team}${ago}</div>
      </div>
    </a>`;
  }

  function render(items){
    if (!items || !items.length) { wrap.style.display = 'none'; return; }
    grid.innerHTML = items.slice(0, MAX).map(cardHTML).join('');
  }

  async function tryAPI(){
    try {
      const base = (window.UHA && (UHA.apiBase ?? '')) || '';
      const url  = `${base}api/news.php?limit=${MAX}`;
      const res  = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) return null;
      const j = await res.json();
      if (j && j.ok && Array.isArray(j.items) && j.items.length) return j.items;
      return null;
    } catch { return null; }
  }

  async function tryTeamFeeds(){
    try {
      const feeds = (window.UHA && Array.isArray(UHA.teamsFeed) ? UHA.teamsFeed : []);
      if (!feeds.length) return null;
      const settled = await Promise.allSettled(
        feeds.map(f => fetch(f.feed, {credentials:'same-origin'}).then(r=>r.json()))
      );
      let items = [];
      settled.forEach((r, idx) => {
        if (r.status !== 'fulfilled' || !r.value) return;
        const team = feeds[idx].abbr || r.value.team || null;
        const arr = Array.isArray(r.value.items) ? r.value.items : [];
        arr.forEach(it => items.push({
          id: it.id, title: it.title, team: it.team || team,
          image: it.image || null, published_at: it.published_at || null,
          link: it.link || `news-article.php?id=${encodeURIComponent(it.id||'')}`
        }));
      });
      if (!items.length) return null;
      items.sort((a,b) => new Date(b.published_at||0) - new Date(a.published_at||0));
      return items.slice(0, MAX);
    } catch { return null; }
  }

  (async function init(){
    // 1) API (DB-backed)
    let items = await tryAPI();

    // 2) File feeds (per-team news.json)
    if (!items || !items.length) items = await tryTeamFeeds();

    // 3) Fallback to any existing UHA.headlines
    if (!items || !items.length) {
      const fallback = (window.UHA && (UHA.headlines || [])).slice(0, MAX);
      if (fallback.length) { render(fallback); return; }
      wrap.style.display = 'none'; return;
    }
    render(items);
  })();
})();
