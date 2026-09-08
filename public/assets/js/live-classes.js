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
