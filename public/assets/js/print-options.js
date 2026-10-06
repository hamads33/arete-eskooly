// Print layout chooser for reports. Pressing any Print button (window.print) or a list-report PDF button asks
// the admin which layout to use, remembers it per report, then prints:
//   Portrait  — the classic detailed report (letterhead, summary tiles, roomy rows)
//   Landscape — the compact list (one tight line per record, most rows per page)
// Ctrl+P uses the remembered layout. Only active in the admin layout (it provides #printPageSize).
(() => {
  const size = document.getElementById('printPageSize');
  if (!size) return;
  const html = document.documentElement;
  const PAGE = {
    portrait: '@page{size:A4 portrait;margin:14mm 12mm 16mm}',
    landscape: '@page{size:A4 landscape;margin:9mm 8mm 10mm}',
  };
  const key = 'arete-print-layout:' + location.pathname;
  const fallback = PAGE[html.dataset.printDefault] ? html.dataset.printDefault : 'portrait';
  const remembered = () => { try { const v = localStorage.getItem(key); return PAGE[v] ? v : null; } catch (e) { return null; } };
  const remember = m => { try { localStorage.setItem(key, m); } catch (e) { /* private mode */ } };
  const apply = m => { m = PAGE[m] ? m : fallback; html.dataset.printMode = m; size.textContent = '@media print{' + PAGE[m] + '}'; return m; };
  apply(remembered() || fallback);

  // List reports whose PDFs come in both layouts.
  const LIST_PDF = /\/admin\/reports\/(fee-defaulters|students\/basic-list|admissions|contacts|attendance\/(students|employees)|accounts\/statement|salary-sheet|class-tests\/\d+\/marksheet|exams\/\d+\/classes\/\d+\/gazette)\/pdf/;

  let open = null;
  const choose = (what) => new Promise(resolve => {
    if (open) open(null);
    const current = remembered() || fallback;
    const box = document.createElement('div');
    box.className = 'print-choice';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-labelledby', 'printChoiceTitle');
    box.innerHTML = `<div class="print-choice__card">
      <h2 id="printChoiceTitle">${what === 'pdf' ? 'PDF layout' : 'Print layout'}</h2>
      <p>Choose how this report should look on paper.</p>
      <div class="print-choice__options">
        <button type="button" data-mode="portrait" class="${current === 'portrait' ? 'is-current' : ''}"><span class="print-choice__page print-choice__page--p"><i></i><i></i><i></i></span><strong>Portrait</strong><small>Detailed report — summary boxes, sections and roomy rows (classic format)</small></button>
        <button type="button" data-mode="landscape" class="${current === 'landscape' ? 'is-current' : ''}"><span class="print-choice__page print-choice__page--l"><i></i><i></i><i></i><i></i><i></i></span><strong>Landscape</strong><small>Compact list — one line per record, fits the most on each page</small></button>
      </div>
      <button type="button" class="print-choice__cancel" data-mode="">Cancel</button>
    </div>`;
    const done = m => { open = null; document.removeEventListener('keydown', onKey, true); box.remove(); resolve(m || null); };
    const onKey = e => { if (e.key === 'Escape') { e.preventDefault(); done(null); } };
    open = done;
    box.addEventListener('click', e => { const b = e.target.closest('[data-mode]'); if (b) done(b.dataset.mode); else if (e.target === box) done(null); });
    document.addEventListener('keydown', onKey, true);
    document.body.appendChild(box);
    (box.querySelector('.is-current') || box.querySelector('[data-mode]')).focus();
  });

  const nativePrint = window.print.bind(window);
  window.print = () => {
    // The mini receipt prints its own fixed slip layout immediately.
    if (document.body.classList.contains('ek-print-mini')) { nativePrint(); return; }
    choose('print').then(m => {
      if (!m) return;
      remember(m); apply(m);
      requestAnimationFrame(() => requestAnimationFrame(nativePrint));
    });
  };
  window.addEventListener('beforeprint', () => apply(html.dataset.printMode));

  document.addEventListener('click', e => {
    const a = e.target.closest('a[href]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey || !LIST_PDF.test(a.getAttribute('href'))) return;
    e.preventDefault();
    choose('pdf').then(m => {
      if (!m) return;
      remember(m); apply(m);
      const u = new URL(a.href, location.href);
      u.searchParams.set('layout', m);
      if (a.target === '_blank') window.open(u.toString(), '_blank', 'noopener'); else location.href = u.toString();
    });
  }, true);
})();
