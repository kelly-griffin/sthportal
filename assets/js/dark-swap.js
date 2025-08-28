
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.dark-row img').forEach(img => {
    const src = img.getAttribute('src') || '';
    if (/_light\.svg$/i.test(src)) {
      img.dataset.lightSrc = src;
      img.src = src.replace(/_light\.svg$/i, '_dark.svg');
    } else if (/\.svg$/i.test(src) && !/_dark\.svg$/i.test(src)) {
      img.dataset.lightSrc = src;
      img.src = src.replace(/\.svg$/i, '_dark.svg');
    }
  });
});
