// assets/js/auto-logos.js
(function (win) {
  // find luminance of the nearest non-transparent background
  function isDarkBg(el){
    let node = el;
    while (node && node !== document.documentElement){
      const bg = getComputedStyle(node).backgroundColor;
      // skip transparent / rgba(0,0,0,0)
      if (bg && bg !== 'transparent' && !/rgba?\(\s*0\s*,\s*0\s*,\s*0\s*(?:,\s*0\s*)?\)/i.test(bg)){
        const m = /rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i.exec(bg);
        if (!m) break;
        const [r,g,b] = [m[1],m[2],m[3]].map(Number);
        const lum = (0.2126*r + 0.7152*g + 0.0722*b) / 255;
        return lum < 0.5; // true => dark background
      }
      node = node.parentElement;
    }
    return true; // assume dark if unknown
  }

  // derive abbr from data-abbr, src ".../logos/XXX_dark.svg", or short alt text
  function abbrFromImg(img){
    if (img.dataset && img.dataset.abbr) return img.dataset.abbr.toUpperCase();
    const m = /\/logos\/([A-Za-z0-9_-]+)_(?:dark|light)\.svg(?:\?.*)?$/i.exec(img.src || '');
    if (m) return m[1].toUpperCase();
    const alt = (img.alt || '').trim();
    if (alt.length >= 2 && alt.length <= 5) return alt.toUpperCase();
    return '';
  }

  function applyLogoVariants(root){
    const scope = root || document;
    // match both patterns used across the site
    scope.querySelectorAll('img.logo, .logo > img').forEach(img => {
      const host =
        img.closest('.game-card, .leadersCard, .st-row, .standings-box, .box') ||
        img.parentElement || document.body;

      // your convention: DARK bg => *_dark.svg ; LIGHT bg => *_light.svg
      const variant = isDarkBg(host) ? 'dark' : 'light';
      const abbr = abbrFromImg(img);
      if (!abbr) return;

      const next = `assets/img/logos/${abbr}_${variant}.svg`;
      if (img.getAttribute('src') !== next) img.setAttribute('src', next);
    });
  }

  // expose global
  win.UHA_applyLogoVariants = applyLogoVariants;

  // initial pass
  if (document.readyState !== 'loading') applyLogoVariants(document);
  else document.addEventListener('DOMContentLoaded', () => applyLogoVariants(document));

  // observe DOM for late-rendered content (standings, scores, etc.)
  const mo = new MutationObserver(muts => {
    let target = null;
    for (const m of muts){
      if (m.type === 'childList' && m.addedNodes && m.addedNodes.length) { target = m.target; break; }
      if (m.type === 'attributes' && m.attributeName === 'src' && m.target.tagName === 'IMG') { target = m.target.parentElement; break; }
    }
    if (target) applyLogoVariants(target.nodeType === 1 ? target : document);
  });
  mo.observe(document.documentElement, {
    subtree: true,
    childList: true,
    attributes: true,
    attributeFilter: ['src']
  });
})(window);
