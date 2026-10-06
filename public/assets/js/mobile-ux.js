// Presentation only: preserve the original nodes, form fields, listeners and routes.
(() => {
  'use strict';
  const phone = matchMedia('(max-width: 767px)');
  const undo = [];
  function enhance() {
    while (undo.length) undo.pop()();
    if (!phone.matches) return;

    // Alerts belong in the student's content flow, above the next actionable work.
    const page = document.querySelector('.portal-shell .module-page');
    if (page && !document.querySelector('[data-test-attempt]')) {
      document.querySelectorAll('[data-fee-reminder],[data-student-notify]').forEach(alert => {
        const marker = document.createComment('mobile alert original position');
        alert.before(marker); page.prepend(alert);
        const card = alert.querySelector('[role=dialog]');
        if (card) { card.setAttribute('role', 'region'); card.removeAttribute('aria-modal'); }
        undo.push(() => {
          marker.replaceWith(alert);
          if (card) { card.setAttribute('role', 'dialog'); card.setAttribute('aria-modal', 'true'); }
        });
      });
    }

    function groupFollowing(list, keep, label) {
      if (!list || list.children.length <= keep) return;
      const nodes = [...list.children];
      const details = document.createElement('details'); details.className = 'mobile-following';
      const summary = document.createElement('summary'); summary.textContent = (nodes.length - keep) + ' ' + label;
      const content = document.createElement('div'); content.className = 'mobile-following__content';
      details.append(summary, content); nodes.slice(keep).forEach(node => content.append(node)); list.append(details);
      undo.push(() => { nodes.forEach(node => list.append(node)); details.remove(); });
    }
    groupFollowing(document.querySelector('.student-dashboard .live-tile__list'), 1, 'more upcoming classes');
    groupFollowing(document.querySelector('.student-dashboard .dashboard-test-list'), 2, 'more available tests');
    const conversationList = document.querySelector('.chat-layout:has(.chat-pane__header) > .chat-list');
    if (conversationList) {
      const switcher = document.createElement('details'); switcher.className = 'mobile-secondary-section';
      const summary = document.createElement('summary'); summary.textContent = 'Conversations · switch conversation';
      conversationList.before(switcher); switcher.append(summary, conversationList);
      undo.push(() => switcher.replaceWith(conversationList));
    }

    // Keep analytics available without pushing daily work down several screens.
    document.querySelectorAll('#dashboard .income-card,#dashboard .class-strength-card,.student-dashboard .portal-dashboard-grid > .module-card:not(.live-tile):not(.dashboard-tests):not(.student-homework-tile)').forEach(card => {
      const details = document.createElement('details'); details.className = 'mobile-secondary-section';
      const summary = document.createElement('summary'); summary.textContent = card.querySelector('h2')?.textContent || 'More information';
      card.before(details); details.append(summary, card);
      undo.push(() => details.replaceWith(card));
    });

    // Collapse long GET toolbars; POST workflows always keep their fields visible.
    document.querySelectorAll('form.filter-bar,form.fd-filters').forEach((form, index) => {
      if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get' || form.querySelectorAll('input:not([type=hidden]),select').length < 3) return;
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'btn btn--secondary mobile-filter-toggle';
      const originalId = form.getAttribute('id');
      const filterId = originalId || 'mobile-filters-' + index;
      form.setAttribute('id', filterId);
      button.setAttribute('aria-controls', filterId);
      const set = open => {
        form.classList.toggle('is-mobile-filter-closed', !open);
        button.setAttribute('aria-expanded', String(open));
        button.textContent = open ? 'Close filters' : 'Filters · refine results';
      };
      button.addEventListener('click', () => set(button.getAttribute('aria-expanded') !== 'true'));
      form.before(button); set(false);
      undo.push(() => { button.remove(); form.classList.remove('is-mobile-filter-closed'); if (!originalId) form.removeAttribute('id'); });
    });

    // The first action stays visible. Secondary controls keep their original handlers.
    document.querySelectorAll('.data-table td:last-child').forEach(cell => {
      if (cell.classList.contains('row-actions') || [...cell.children].filter(node => node.matches('a,button,form')).length < 3) return;
      cell.classList.add('row-actions');
      undo.push(() => cell.classList.remove('row-actions'));
    });
    document.querySelectorAll('.row-actions').forEach(row => {
      const controls = [...row.children];
      if (controls.length < 3) return;
      const menu = document.createElement('details'); menu.className = 'mobile-action-menu';
      const summary = document.createElement('summary'); summary.textContent = 'More';
      summary.setAttribute('aria-label', 'More record actions');
      const panel = document.createElement('div'); panel.className = 'mobile-action-menu__panel';
      menu.append(summary, panel); row.append(menu);
      controls.slice(1).forEach(node => panel.append(node));
      undo.push(() => { controls.forEach(node => row.append(node)); menu.remove(); });
    });

    // A local scrolling region is explicit and keyboard accessible, never page-wide.
    document.querySelectorAll('table.data-table').forEach(table => {
      if (table.matches('.mobile-records,.mobile-details,.attendance-table') || table.closest('.table-scroll,.report-table-wrap,.class-table-wrap,.fd-table-wrap,.ss-table-wrap,.ek-table-wrap')) return;
      const wrap = document.createElement('div'); wrap.className = 'mobile-table-scroll';
      table.before(wrap); wrap.append(table);
      undo.push(() => wrap.replaceWith(table));
    });
    document.querySelectorAll('.table-scroll,.mobile-table-scroll,.report-table-wrap,.fd-table-wrap,.ss-table-wrap,.ek-table-wrap').forEach(wrap => {
      if (wrap.querySelector('.mobile-records,.mobile-details,.attendance-table,.class-table,.sms-table,.status-rules-table')) return;
      const hint = document.createElement('p'); hint.className = 'mobile-table-hint';
      hint.textContent = 'Swipe across to see all columns';
      const original = { tabindex: wrap.getAttribute('tabindex'), label: wrap.getAttribute('aria-label') };
      const refresh = () => {
        const wide = wrap.scrollWidth > wrap.clientWidth + 1;
        hint.hidden = !wide;
        if (wide) { wrap.setAttribute('tabindex', '0'); if (!original.label) wrap.setAttribute('aria-label', 'Scrollable table'); }
      };
      wrap.before(hint);
      const observer = new ResizeObserver(refresh); observer.observe(wrap); refresh();
      undo.push(() => {
        observer.disconnect(); hint.remove();
        for (const [name, value] of [['tabindex', original.tabindex], ['aria-label', original.label]]) {
          if (value === null) wrap.removeAttribute(name); else wrap.setAttribute(name, value);
        }
      });
    });
  }
  enhance();
  phone.addEventListener('change', enhance);
  let printSections = [];
  window.addEventListener('beforeprint', () => {
    printSections = [...document.querySelectorAll('.mobile-following:not([open]),.mobile-secondary-section:not([open]),.mobile-action-menu:not([open])')];
    printSections.forEach(section => { section.open = true; });
  });
  window.addEventListener('afterprint', () => {
    printSections.forEach(section => { section.open = false; }); printSections = [];
  });
  document.addEventListener('click', event => {
    document.querySelectorAll('.mobile-action-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') document.querySelectorAll('.mobile-action-menu[open]').forEach(menu => { menu.open = false; menu.querySelector('summary').focus(); });
  });
})();
