// Use native labels so taps focus fields in the same user gesture on mobile.
(() => {
  'use strict';
  let seq = 0;
  const groupSelector = '.module-field,.form-field,.form-group,.builder-field,.settings-field,.field';
  const fieldSelector = 'input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=reset]),select,textarea';
  const named = el => el.labels?.length || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.title || el.closest('label');
  const within = (root, selector) => [...(root.matches?.(selector) ? [root] : []), ...root.querySelectorAll(selector)];
  function run(root = document) {
    const groups = within(root, groupSelector);
    const parentGroup = root.parentElement?.closest(groupSelector);
    if (parentGroup && !groups.includes(parentGroup)) groups.unshift(parentGroup);
    groups.forEach(group => {
      group.querySelectorAll(':scope > label').forEach(label => {
        if (label.control || label.querySelector('input,select,textarea')) return;
        // An ARIA name does not make a visible label tappable.
        const fields = [...group.querySelectorAll(fieldSelector)].filter(f => !f.labels?.length && f.closest(groupSelector) === group);
        if (fields.length !== 1) return;
        const field = fields[0];
        if (!field.id || document.getElementById(field.id) !== field) {
          let id;
          do { id = 'fld-' + (field.name || 'x').replace(/[^a-z0-9]+/gi, '-') + '-' + (++seq); } while (document.getElementById(id));
          field.id = id;
        }
        label.htmlFor = field.id;
      });
    });
    within(root, 'table').forEach(table => {
      const heads = [...(table.tHead?.rows[0]?.cells || [])].map(th => th.textContent.trim());
      table.querySelectorAll('tbody tr').forEach(tr => {
        const who = (tr.cells[0]?.textContent || tr.cells[1]?.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 60);
        tr.querySelectorAll(fieldSelector).forEach(f => {
          if (!named(f)) f.setAttribute('aria-label', [heads[f.closest('td')?.cellIndex] || f.name || 'Field', who].filter(Boolean).join(' — '));
        });
      });
    });
    within(root, fieldSelector).forEach(f => {
      if (!named(f) && (f.placeholder || f.name)) f.setAttribute('aria-label', f.placeholder || f.name.replace(/[_\[\]]+/g, ' ').trim());
      if (!f.hasAttribute('inputmode')) {
        if (f.type === 'number') f.setAttribute('inputmode', f.step === '1' ? 'numeric' : 'decimal');
        if (f.type === 'tel') f.setAttribute('inputmode', 'tel');
      }
    });
  }
  function start() {
    run();
    new MutationObserver(ms => ms.forEach(m => m.addedNodes.forEach(n => { if (n.nodeType === 1) run(n); }))).observe(document.body, {childList:true, subtree:true});
    // Keep the focused field reachable when the Android keyboard reduces the viewport.
    window.visualViewport?.addEventListener('resize', () => {
      const field = document.activeElement;
      if (!field?.matches('input:not([type=hidden]),textarea,[contenteditable=true]')) return;
      requestAnimationFrame(() => {
        if (document.activeElement !== field) return;
        const rect = field.getBoundingClientRect();
        const viewport = window.visualViewport;
        const bottom = viewport.offsetTop + viewport.height - 24;
        const top = viewport.offsetTop + 84;
        if (rect.bottom > bottom) window.scrollBy({top: rect.bottom - bottom, behavior:'instant'});
        else if (rect.top < top) window.scrollBy({top: rect.top - top, behavior:'instant'});
      });
    });
  }
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
})();
