/**
 * Keeps a subject dropdown in step with the class dropdown beside it.
 *
 * Markup contract:
 *   <select id="noteClass" ...>                       the class selector
 *   <select data-subject-filter="#noteClass" ...>     the subject selector
 *   <option value="7" data-class="3">Mathematics</option>
 *
 * Options without a data-class attribute are treated as placeholders and always kept.
 * Runs on load as well as on change, so a form redisplayed after a validation error is
 * already filtered correctly.
 */
(function () {
    'use strict';

    function initialise(subjectSelect) {
        var selector = subjectSelect.getAttribute('data-subject-filter');
        var classSelect = selector ? document.querySelector(selector) : null;
        if (!classSelect) {
            return;
        }

        // Keep the full list; filtering removes options from the DOM, so we cannot re-read it.
        var all = Array.prototype.slice.call(subjectSelect.options).map(function (option) {
            return {
                value: option.value,
                label: option.textContent,
                classId: option.getAttribute('data-class'),
                selected: option.selected
            };
        });

        function render() {
            var chosen = classSelect.value;
            var previous = subjectSelect.value;
            var matched = 0;

            subjectSelect.innerHTML = '';
            all.forEach(function (item) {
                // Placeholders (no data-class) always survive. With no class chosen we keep
                // every subject, so filter bars still offer "All subjects".
                if (item.classId !== null && chosen && item.classId !== chosen) {
                    return;
                }
                var option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.label;
                if (item.classId !== null) {
                    option.setAttribute('data-class', item.classId);
                    matched++;
                }
                subjectSelect.appendChild(option);
            });

            if (previous && subjectSelect.querySelector('option[value="' + previous.replace(/"/g, '\\"') + '"]')) {
                subjectSelect.value = previous;
            }

            var empty = chosen && matched === 0;
            subjectSelect.disabled = !chosen || empty;
            if (empty) {
                var none = document.createElement('option');
                none.value = '';
                none.textContent = 'No subjects assigned to this class';
                subjectSelect.appendChild(none);
            }
        }

        classSelect.addEventListener('change', render);
        render();
    }

    function boot() {
        var selects = document.querySelectorAll('[data-subject-filter]');
        Array.prototype.forEach.call(selects, initialise);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
