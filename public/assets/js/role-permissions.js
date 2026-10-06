/* Role permission editor: collapsible module cards, per-module select-all, search and live counts. */
(function () {
  'use strict';

  var form = document.querySelector('[data-permission-editor]');
  if (!form) return;

  var modules = Array.prototype.slice.call(form.querySelectorAll('[data-perm-module]'));
  var totalCount = form.querySelector('[data-perm-count]');
  var noResults = form.querySelector('[data-perm-noresults]');
  var search = form.querySelector('[data-perm-search]');
  var expandBtn = form.querySelector('[data-perm-expand]');
  var clearBtn = form.querySelector('[data-perm-clear]');

  function checksIn(module) {
    return Array.prototype.slice.call(module.querySelectorAll('[data-perm-check]'));
  }

  function refresh(module) {
    var checks = checksIn(module);
    var on = checks.filter(function (c) { return c.checked; }).length;
    var count = module.querySelector('[data-perm-module-count]');
    if (count) count.textContent = String(on);

    var all = module.querySelector('[data-perm-all]');
    if (all) {
      all.checked = on === checks.length && checks.length > 0;
      // Partial grants read as indeterminate so an admin can tell "some" from "none" at a glance.
      all.indeterminate = on > 0 && on < checks.length;
    }
  }

  function refreshTotal() {
    if (!totalCount) return;
    var on = form.querySelectorAll('[data-perm-check]:checked').length;
    totalCount.textContent = String(on);
  }

  function setOpen(module, open) {
    module.classList.toggle('is-open', open);
    var toggle = module.querySelector('[data-perm-toggle]');
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  modules.forEach(function (module) {
    refresh(module);

    var toggle = module.querySelector('[data-perm-toggle]');
    if (toggle) {
      toggle.addEventListener('click', function () {
        setOpen(module, !module.classList.contains('is-open'));
      });
    }

    var all = module.querySelector('[data-perm-all]');
    if (all) {
      all.addEventListener('change', function () {
        checksIn(module).forEach(function (c) { c.checked = all.checked; });
        refresh(module);
        refreshTotal();
        if (all.checked) setOpen(module, true);
      });
    }

    checksIn(module).forEach(function (check) {
      check.addEventListener('change', function () {
        refresh(module);
        refreshTotal();
      });
    });
  });

  refreshTotal();

  if (expandBtn) {
    expandBtn.addEventListener('click', function () {
      var anyClosed = modules.some(function (m) { return !m.classList.contains('is-open'); });
      modules.forEach(function (m) { setOpen(m, anyClosed); });
      expandBtn.textContent = anyClosed ? 'Collapse all' : 'Expand all';
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      form.querySelectorAll('[data-perm-check]').forEach(function (c) { c.checked = false; });
      modules.forEach(refresh);
      refreshTotal();
    });
  }

  if (search) {
    search.addEventListener('input', function () {
      var term = search.value.trim().toLowerCase();
      var visible = 0;

      modules.forEach(function (module) {
        var rows = Array.prototype.slice.call(module.querySelectorAll('[data-perm-row]'));
        var moduleMatches = term === '' || (module.getAttribute('data-perm-terms') || '').indexOf(term) !== -1;
        var rowMatches = 0;

        rows.forEach(function (row) {
          var hit = term === '' || moduleMatches || (row.getAttribute('data-perm-terms') || '').indexOf(term) !== -1;
          row.hidden = !hit;
          if (hit) rowMatches++;
        });

        var show = term === '' || moduleMatches || rowMatches > 0;
        module.hidden = !show;
        if (show) visible++;
        // A search should reveal what it matched, not leave the hit hidden inside a closed card.
        if (term !== '' && show) setOpen(module, true);
      });

      if (noResults) noResults.hidden = visible > 0;
    });
  }
})();
