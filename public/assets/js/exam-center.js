/**
 * Exam Center page behaviour (no inline handlers: the site's CSP blocks them).
 * - table filters, "only unmarked"
 * - marks sheet: Enter → next student, paste a column from Excel, live % and validation,
 *   absent toggles, unsaved-changes warning
 * - "students included" counter on class checklists
 * - add-papers form: show only the chosen class's subjects
 */
(function () {
  'use strict';

  // ---------------------------------------------------------------- table filter
  document.querySelectorAll('[data-ec-filter]').forEach(function (input) {
    var table = document.querySelector(input.getAttribute('data-ec-filter'));
    if (!table) return;
    input.addEventListener('input', apply);
    var unmarked = document.querySelector('[data-ec-unmarked]');
    if (unmarked) unmarked.addEventListener('change', apply);
    function apply() {
      var q = input.value.trim().toLowerCase();
      var onlyUnmarked = unmarked && unmarked.checked;
      table.querySelectorAll('tbody tr[data-ec-row]').forEach(function (row) {
        var show = (!q || row.getAttribute('data-ec-row').indexOf(q) !== -1) && (!onlyUnmarked || row.classList.contains('is-unmarked'));
        row.hidden = !show;
      });
    }
  });

  // ---------------------------------------------------------------- class checklists: student count
  document.querySelectorAll('[data-ec-class-total]').forEach(function (out) {
    var box = out.closest('fieldset');
    if (!box) return;
    function update() {
      var n = 0, c = 0;
      box.querySelectorAll('input[type=checkbox][data-count]:checked').forEach(function (cb) { n += parseInt(cb.getAttribute('data-count'), 10) || 0; c++; });
      out.hidden = c === 0;
      out.textContent = c + ' class(es) selected · ' + n + ' active student(s) will be included';
    }
    box.addEventListener('change', update);
    update();
  });

  // ---------------------------------------------------------------- add papers: subjects of the chosen class
  document.querySelectorAll('[data-ec-papers]').forEach(function (form) {
    var select = form.querySelector('[data-ec-class-select]');
    var hint = form.querySelector('[data-ec-subjects-hint]');
    function show() {
      var id = select.value;
      form.querySelectorAll('[data-ec-subjects]').forEach(function (grid) {
        var on = grid.getAttribute('data-ec-subjects') === id;
        grid.hidden = !on;
        grid.querySelectorAll('input').forEach(function (cb) { if (!on) cb.checked = false; });
      });
      if (hint) hint.hidden = !!id;
    }
    select.addEventListener('change', show);
    show();
    var all = form.querySelector('[data-ec-all]');
    if (all) all.addEventListener('click', function () {
      var grid = form.querySelector('[data-ec-subjects="' + select.value + '"]');
      if (!grid) return;
      var boxes = grid.querySelectorAll('input:not(:disabled)');
      var allOn = Array.prototype.every.call(boxes, function (b) { return b.checked; });
      boxes.forEach(function (b) { b.checked = !allOn; });
    });
    form.addEventListener('submit', function (e) {
      if (!form.querySelector('[data-ec-subjects]:not([hidden]) input:checked')) {
        e.preventDefault();
        window.alert('Tick at least one subject.');
      }
    });
  });

  // ---------------------------------------------------------------- marks sheet
  var sheet = document.querySelector('[data-ec-sheet]');
  if (!sheet) return;
  var total = parseFloat(sheet.getAttribute('data-total')) || 0;
  var dirty = false;
  var error = sheet.querySelector('[data-ec-error]');
  var status = sheet.querySelector('[data-ec-status]');

  function marks() { return Array.prototype.slice.call(sheet.querySelectorAll('.ec-mark')); }
  function rowOf(el) { return el.closest('tr'); }

  function check(input) {
    var row = rowOf(input);
    var pct = row.querySelector('[data-ec-pct]');
    var abs = row.querySelector('[data-ec-absent]');
    if (abs && abs.checked) { input.classList.remove('is-invalid'); if (pct) pct.textContent = '—'; return true; }
    var v = input.value.trim();
    if (v === '') { input.classList.remove('is-invalid'); if (pct) pct.textContent = ''; return true; }
    var n = Number(v);
    var ok = v !== '' && !isNaN(n) && n >= 0 && n <= total;
    input.classList.toggle('is-invalid', !ok);
    input.title = ok ? '' : 'Enter a number from 0 to ' + total;
    if (pct) pct.textContent = ok && total > 0 ? (Math.round(n / total * 1000) / 10) + '%' : '';
    return ok;
  }

  function refresh() {
    var rows = sheet.querySelectorAll('tbody tr[data-ec-row]');
    var marked = 0, absent = 0;
    rows.forEach(function (row) {
      var abs = row.querySelector('[data-ec-absent]');
      var input = row.querySelector('.ec-mark');
      var has = (abs && abs.checked) || (input && input.value.trim() !== '');
      if (abs && abs.checked) absent++;
      if (has) marked++;
      row.classList.toggle('is-unmarked', !has);
    });
    if (status) status.textContent = marked + ' of ' + rows.length + ' marked' + (absent ? ' · ' + absent + ' absent' : '') + (dirty ? ' · unsaved changes' : '');
    var top = document.querySelector('[data-ec-marked]');
    if (top) top.textContent = marked + '/' + rows.length;
  }

  function setAbsent(row, on) {
    var input = row.querySelector('.ec-mark');
    var abs = row.querySelector('[data-ec-absent]');
    if (!input || !abs) return;
    abs.checked = on;
    var shadow = row.querySelector('[data-ec-absent-shadow]');
    if (on) {
      input.disabled = true;
      input.value = '';
      if (!shadow) {
        shadow = document.createElement('input');
        shadow.type = 'hidden';
        shadow.name = input.name;
        shadow.value = '';
        shadow.setAttribute('data-ec-absent-shadow', '');
        input.parentNode.appendChild(shadow);
      }
    } else {
      input.disabled = false;
      if (shadow) shadow.remove();
    }
    check(input);
  }

  sheet.addEventListener('input', function (e) {
    if (e.target.classList.contains('ec-mark')) check(e.target);
    dirty = true;
    refresh();
  });
  sheet.addEventListener('change', function (e) {
    if (e.target.hasAttribute('data-ec-absent')) {
      setAbsent(rowOf(e.target), e.target.checked);
      if (!e.target.checked) { var i = rowOf(e.target).querySelector('.ec-mark'); if (i) i.focus(); }
    }
    dirty = true;
    refresh();
  });

  // Enter (or ↓) moves to the next student's mark; ↑ to the previous one.
  sheet.addEventListener('keydown', function (e) {
    if (!e.target.classList.contains('ec-mark')) return;
    if (e.key !== 'Enter' && e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
    e.preventDefault();
    var list = marks().filter(function (m) { return !m.disabled && !rowOf(m).hidden; });
    var at = list.indexOf(e.target);
    var next = list[e.key === 'ArrowUp' ? at - 1 : at + 1];
    if (next) { next.focus(); next.select(); }
  });

  // Pasting several lines (a column copied from Excel) fills this student and the ones below.
  sheet.addEventListener('paste', function (e) {
    if (!e.target.classList.contains('ec-mark')) return;
    var text = (e.clipboardData || window.clipboardData).getData('text');
    if (!/[\r\n\t]/.test(text.trim())) return;
    e.preventDefault();
    var values = text.trim().split(/\r?\n/).map(function (line) { return line.split('\t')[0].trim(); });
    var list = marks().filter(function (m) { return !rowOf(m).hidden; });
    var at = list.indexOf(e.target);
    values.forEach(function (v, k) {
      var input = list[at + k];
      if (!input) return;
      var row = rowOf(input);
      if (/^(a|ab|abs|absent)$/i.test(v)) { setAbsent(row, true); return; }
      if (input.disabled) setAbsent(row, false);
      input.value = v;
      check(input);
    });
    dirty = true;
    refresh();
  });

  var blank = document.querySelector('[data-ec-absent-blank]');
  if (blank) blank.addEventListener('click', function () {
    var n = 0;
    sheet.querySelectorAll('tbody tr[data-ec-row]').forEach(function (row) {
      var input = row.querySelector('.ec-mark');
      var abs = row.querySelector('[data-ec-absent]');
      if (input && abs && !abs.checked && input.value.trim() === '') { setAbsent(row, true); n++; }
    });
    if (n) { dirty = true; refresh(); }
    if (error) { error.hidden = false; error.textContent = n ? n + ' student(s) marked absent. Save to keep it.' : 'Every student already has marks or is absent.'; }
  });

  sheet.addEventListener('submit', function (e) {
    var bad = marks().filter(function (m) { return !m.disabled && !check(m); });
    if (bad.length) {
      e.preventDefault();
      e.stopImmediatePropagation();
      if (error) { error.hidden = false; error.textContent = bad.length + ' mark(s) are not valid (0 to ' + total + '). Fix the red boxes.'; }
      bad[0].focus();
      return;
    }
    var final = e.submitter && e.submitter.hasAttribute('data-ec-final');
    if (final) {
      var left = sheet.querySelectorAll('tbody tr.is-unmarked').length;
      if (left && !window.confirm(left + ' student(s) still have no marks. Mark them absent or enter their marks first. Continue anyway?')) { e.preventDefault(); return; }
    }
    dirty = false;
  });

  window.addEventListener('beforeunload', function (e) {
    if (!dirty) return;
    e.preventDefault();
    e.returnValue = '';
  });

  refresh();
})();
