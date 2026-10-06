(() => {
  const links = Array.from(document.querySelectorAll('[data-review-gallery] [data-gallery-image]'));
  if (!links.length) return;
  const box = document.createElement('div');
  box.className = 'gallery-lightbox';
  box.hidden = true;
  box.innerHTML = '<div class="gallery-lightbox__bar"><span data-count></span><a data-open target="_blank" rel="noopener">Open original</a><button type="button" data-close aria-label="Close">×</button></div><div class="gallery-lightbox__stage"><img alt=""></div><button type="button" class="gallery-lightbox__nav is-prev" data-prev aria-label="Previous page">‹</button><button type="button" class="gallery-lightbox__nav is-next" data-next aria-label="Next page">›</button>';
  document.body.append(box);
  const img = box.querySelector('img'), stage = box.querySelector('.gallery-lightbox__stage');
  let group = [], index = 0, zoom = false;
  const show = i => {
    index = (i + group.length) % group.length;
    img.src = group[index].href;
    img.alt = group[index].querySelector('img')?.alt || '';
    zoom = false; stage.classList.remove('is-zoomed');
    box.querySelector('[data-count]').textContent = 'Page ' + (index + 1) + ' of ' + group.length;
    box.querySelector('[data-open]').href = group[index].href;
    box.querySelectorAll('.gallery-lightbox__nav').forEach(b => b.hidden = group.length < 2);
  };
  const close = () => { box.hidden = true; document.documentElement.style.overflow = ''; };
  links.forEach(link => link.addEventListener('click', e => {
    e.preventDefault();
    group = Array.from(link.closest('[data-review-gallery]').querySelectorAll('[data-gallery-image]'));
    box.hidden = false; document.documentElement.style.overflow = 'hidden';
    show(group.indexOf(link));
  }));
  box.querySelector('[data-close]').addEventListener('click', close);
  box.querySelector('[data-prev]').addEventListener('click', () => show(index - 1));
  box.querySelector('[data-next]').addEventListener('click', () => show(index + 1));
  img.addEventListener('click', () => { zoom = !zoom; stage.classList.toggle('is-zoomed', zoom); });
  document.addEventListener('keydown', e => {
    if (box.hidden) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') show(index - 1);
    if (e.key === 'ArrowRight') show(index + 1);
  });
  let x = null;
  stage.addEventListener('touchstart', e => { x = e.touches.length === 1 && !zoom ? e.touches[0].clientX : null; }, { passive: true });
  stage.addEventListener('touchend', e => { if (x === null) return; const d = e.changedTouches[0].clientX - x; if (Math.abs(d) > 50) show(index + (d < 0 ? 1 : -1)); x = null; });
})();
