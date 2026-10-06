/*
 * Live classes: three small progressive enhancements. Every one of them degrades to a working
 * page if scripting is off — the forms are ordinary POSTs and the audience fields simply all
 * stay visible.
 */
(function () {
  'use strict';

  /* Reveal the instant / schedule forms. */
  function toggle(triggerAttr, formAttr) {
    document.querySelectorAll('[' + triggerAttr + ']').forEach(function (button) {
      button.addEventListener('click', function () {
        var form = button.closest('.module-card').querySelector('[' + formAttr + ']');
        if (!form) { return; }
        form.hidden = !form.hidden;
        if (!form.hidden) {
          var first = form.querySelector('select, input');
          if (first) { first.focus(); }
        }
      });
    });
  }
  toggle('data-toggle-instant', 'data-instant-form');
  toggle('data-toggle-schedule', 'data-schedule-form');

  /* One shared Meet may target several classes; subjects remain single-class only. */
  document.querySelectorAll('[data-live-class-picker]').forEach(function (picker) {
    var form = picker.closest('form');
    var checks = Array.prototype.slice.call(picker.querySelectorAll('[data-live-class-check]'));
    var subject = form ? form.querySelector('[data-live-subject]') : null;
    var audience = form ? form.querySelector('[data-audience]') : null;
    var applyClasses = function () {
      var selected = checks.filter(function (check) { return check.checked; });
      var selectedId = selected.length === 1 ? selected[0].value : '';
      if (subject) {
        subject.disabled = selected.length !== 1;
        if (selected.length !== 1) { subject.value = ''; }
        Array.prototype.forEach.call(subject.options, function (option) {
          if (!option.value) { return; }
          option.hidden = option.getAttribute('data-class') !== selectedId;
          option.disabled = option.hidden;
        });
      }
      if (audience) {
        Array.prototype.forEach.call(audience.options, function (option) {
          var singleOnly = option.value === 'SEC' || option.value === 'SEL';
          option.disabled = singleOnly && selected.length !== 1;
        });
        if (selected.length !== 1) {
          audience.value = 'SC';
          audience.dispatchEvent(new Event('change'));
        }
      }
      if (form) {
        form.querySelectorAll('[data-audience-field="SEC"] option[data-class]').forEach(function (option) {
          option.hidden = option.getAttribute('data-class') !== selectedId;
          option.disabled = option.hidden;
        });
        form.querySelectorAll('.live-student-picker__item[data-class]').forEach(function (student) {
          student.hidden = student.getAttribute('data-class') !== selectedId;
          var input = student.querySelector('input');
          if (input) { input.disabled = student.hidden; }
        });
      }
    };
    checks.forEach(function (check) {
      check.addEventListener('change', function () {
        if (checks.length) { checks[0].setCustomValidity(''); }
        applyClasses();
      });
    });
    if (form) {
      form.addEventListener('submit', function (event) {
        if (!checks.some(function (check) { return check.checked; })) {
          event.preventDefault();
          checks[0].setCustomValidity('Choose at least one class.');
          checks[0].reportValidity();
        }
      });
    }
    applyClasses();
  });

  /*
   * Show only the audience field that applies. This is presentation: the server decides what a
   * given audience actually requires, so hiding a field here can never relax a rule.
   */
  var audience = document.querySelector('[data-audience]');
  if (audience) {
    var apply = function () {
      document.querySelectorAll('[data-audience-field]').forEach(function (field) {
        field.hidden = field.getAttribute('data-audience-field') !== audience.value;
      });
    };
    audience.addEventListener('change', apply);
    apply();
  }

  /*
   * Reload the form scoped to the chosen class so its sections, subjects and students match.
   * A round trip rather than a fetch: this codebase ships no XHR layer, and the page is small.
   */
  var classSource = document.querySelector('[data-class-source][data-reload-url]');
  if (classSource) {
    classSource.addEventListener('change', function () {
      var base = classSource.getAttribute('data-reload-url');
      window.location.href = base + (classSource.value ? '?class_id=' + encodeURIComponent(classSource.value) : '');
    });
  }

  /* Copy Meet link. */
  document.querySelectorAll('[data-copy-link]').forEach(function (button) {
    button.addEventListener('click', function () {
      var link = button.getAttribute('data-copy-link');
      if (!link || !navigator.clipboard) { return; }
      navigator.clipboard.writeText(link).then(function () {
        var original = button.innerHTML;
        button.textContent = 'Link copied';
        window.setTimeout(function () { button.innerHTML = original; }, 1600);
      });
    });
  });
}());
