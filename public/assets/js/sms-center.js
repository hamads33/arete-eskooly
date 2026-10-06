/*
 * SMS Center front end: character/segment counter, template + variable insertion, live
 * preview, recipient autocomplete, schedule toggle, bulk filter fields and copy buttons.
 * Segment maths mirrors App\Services\Sms\SmsText.
 */
(function () {
  'use strict';

  var GSM = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
  var GSM_EXT = '^{}\\[~]|€\f';

  function analyse(text) {
    var chars = Array.from(text);
    var units = 0;
    var unicode = false;
    for (var i = 0; i < chars.length; i++) {
      if (GSM.indexOf(chars[i]) !== -1) units += 1;
      else if (GSM_EXT.indexOf(chars[i]) !== -1) units += 2;
      else { unicode = true; break; }
    }
    if (unicode) units = text.length; // UTF-16 code units, as UCS-2 SMS counts them
    var single = unicode ? 70 : 160;
    var multi = unicode ? 67 : 153;
    var segments = units === 0 ? 0 : (units <= single ? 1 : Math.ceil(units / multi));
    return { characters: chars.length, units: units, segments: segments, unicode: unicode, perPart: segments > 1 ? multi : single };
  }

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function insertAtCursor(field, value) {
    var start = field.selectionStart || 0;
    var end = field.selectionEnd || 0;
    field.value = field.value.slice(0, start) + value + field.value.slice(end);
    field.selectionStart = field.selectionEnd = start + value.length;
    field.focus();
    field.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function renderCounter(box, text) {
    var a = analyse(text);
    box.textContent = '';
    box.appendChild(el('span', '', 'Characters: ' + a.characters));
    var seg = el('span', a.segments > 3 ? 'is-over' : '', 'Estimated SMS segments: ' + a.segments);
    box.appendChild(seg);
    if (a.unicode) box.appendChild(el('span', 'is-unicode', 'Unicode (e.g. Urdu): ' + a.perPart + ' characters per SMS, so it may use more segments.'));
  }

  function setupForm(form) {
    var message = form.querySelector('[data-sms-message]');
    var counter = form.querySelector('[data-sms-counter]');
    var preview = form.querySelector('[data-sms-preview-text]');
    var template = form.querySelector('[data-sms-template]');
    var varError = form.querySelector('[data-sms-var-error]');
    var sample = null;
    try { sample = form.dataset.sample ? JSON.parse(form.dataset.sample) : null; } catch (e) { sample = null; }
    var known = sample ? Object.keys(sample) : null;

    function refresh() {
      if (!message) return;
      if (counter) renderCounter(counter, message.value);
      if (varError && known) {
        var bad = [];
        message.value.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (m, name) { if (known.indexOf(name) === -1 && bad.indexOf(name) === -1) bad.push(name); return m; });
        varError.hidden = bad.length === 0;
        varError.textContent = bad.length ? 'Unknown variable' + (bad.length > 1 ? 's' : '') + ': {{' + bad.join('}}, {{') + '}}. It will not be accepted.' : '';
      }
      if (preview) {
        var text = message.value.trim();
        if (sample) text = text.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (m, name) { return Object.prototype.hasOwnProperty.call(sample, name) ? sample[name] : m; });
        preview.textContent = text || 'Your message will appear here.';
        preview.classList.toggle('sms-muted', !text);
      }
    }

    if (message) message.addEventListener('input', refresh);
    form.querySelectorAll('[data-sms-insert]').forEach(function (btn) {
      btn.addEventListener('click', function () { if (message) insertAtCursor(message, btn.dataset.smsInsert); });
    });
    if (template && message) {
      template.addEventListener('change', function () {
        var opt = template.options[template.selectedIndex];
        if (!opt || !opt.dataset.body) return;
        if (message.value.trim() && message.value !== opt.dataset.body && !window.confirm('Replace the current message with this template?')) return;
        message.value = opt.dataset.body;
        refresh();
      });
    }

    var modes = form.querySelectorAll('[data-sms-mode]');
    var when = form.querySelector('[data-sms-schedule]');
    var submitLabel = form.querySelector('[data-sms-submit-label]');
    function syncMode() {
      var scheduled = Array.prototype.some.call(modes, function (m) { return m.checked && m.value === 'schedule'; });
      if (when) { when.hidden = !scheduled; when.required = scheduled; }
      if (submitLabel) submitLabel.textContent = scheduled ? 'Schedule SMS' : 'Send to Queue';
    }
    modes.forEach(function (m) { m.addEventListener('change', syncMode); });
    syncMode();
    refresh();
    return refresh;
  }

  function setupCompose(form, refresh) {
    var url = form.dataset.searchUrl;
    if (!url) return;
    var types = form.querySelectorAll('[data-sms-type]');
    var picker = form.querySelector('[data-sms-picker]');
    var manualName = form.querySelector('[data-sms-manual-name]');
    var search = form.querySelector('[data-sms-search]');
    var hiddenId = form.querySelector('[data-sms-recipient-id]');
    var list = form.querySelector('[data-sms-suggest]');
    var phone = form.querySelector('[data-sms-phone]');
    var hint = form.querySelector('[data-sms-picker-hint]');
    var toOut = form.querySelector('[data-sms-preview-to]');
    var phoneOut = form.querySelector('[data-sms-preview-phone]');
    var timer = null;
    var controller = null;
    var active = -1;
    var results = [];

    function type() {
      var t = 'guardian';
      types.forEach(function (r) { if (r.checked) t = r.value; });
      return t;
    }
    function syncPreview() {
      var manual = type() === 'manual';
      var nameField = form.querySelector('[name="recipient_name"]');
      if (toOut) toOut.textContent = manual ? ((nameField && nameField.value.trim()) || 'Manual number') : ((hiddenId.value !== '0' && search.value) || '—');
      if (phoneOut) phoneOut.textContent = phone.value.trim() || '—';
    }
    function close() { list.hidden = true; list.textContent = ''; active = -1; search.setAttribute('aria-expanded', 'false'); }
    function choose(item) {
      hiddenId.value = item.id;
      search.value = item.label;
      phone.value = item.raw_phone || '';
      close();
      syncPreview();
    }
    function highlight(index) {
      var items = list.querySelectorAll('li[role="option"]');
      items.forEach(function (li, i) { li.setAttribute('aria-selected', i === index ? 'true' : 'false'); });
      active = index;
    }
    function show(items) {
      results = items;
      list.textContent = '';
      if (!items.length) {
        list.appendChild(el('li', 'sms-suggest__empty', 'No matches found.'));
      }
      items.forEach(function (item, i) {
        var li = el('li');
        li.setAttribute('role', 'option');
        li.appendChild(el('strong', '', item.label));
        li.appendChild(el('small', '', item.detail || ''));
        li.appendChild(el('small', item.phone ? '' : 'sms-nophone', item.phone || (item.raw_phone ? 'Invalid number on record: ' + item.raw_phone : 'No phone number on record')));
        li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(item); });
        li.addEventListener('mouseenter', function () { highlight(i); });
        list.appendChild(li);
      });
      list.hidden = false;
      search.setAttribute('aria-expanded', 'true');
    }
    function lookup() {
      var q = search.value.trim();
      hiddenId.value = '0';
      syncPreview();
      if (q.length < 2) { close(); return; }
      if (controller) controller.abort();
      controller = new AbortController();
      fetch(url + '?type=' + encodeURIComponent(type()) + '&q=' + encodeURIComponent(q), { credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : { results: [] }; })
        .then(function (data) { show(data.results || []); })
        .catch(function () {});
    }
    function syncType() {
      var t = type();
      picker.hidden = t === 'manual';
      manualName.hidden = t !== 'manual';
      if (hint) hint.textContent = t === 'guardian' ? "Parents are found by their child's name." : (t === 'staff' ? 'Search by name or employee number.' : 'Search by name or registration number.');
      search.placeholder = t === 'staff' ? 'Type a staff name or employee no.' : "Type a student's name or registration no.";
      syncPreview();
    }

    types.forEach(function (r) {
      r.addEventListener('change', function () { hiddenId.value = '0'; search.value = ''; phone.value = ''; close(); syncType(); });
    });
    search.setAttribute('role', 'combobox');
    search.setAttribute('aria-expanded', 'false');
    search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(lookup, 250); });
    search.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(active + 1, results.length - 1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(active - 1, 0)); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); choose(results[active]); }
      else if (e.key === 'Escape') { close(); }
    });
    search.addEventListener('blur', function () { setTimeout(close, 150); });
    phone.addEventListener('input', syncPreview);
    var nameField = form.querySelector('[name="recipient_name"]');
    if (nameField) nameField.addEventListener('input', syncPreview);
    form.addEventListener('reset', function () {
      setTimeout(function () { hiddenId.value = '0'; syncType(); refresh(); }, 0);
    });
    form.addEventListener('submit', function (e) {
      if (type() !== 'manual' && hiddenId.value === '0') {
        e.preventDefault();
        search.focus();
        search.setCustomValidity('Choose a recipient from the list.');
        search.reportValidity();
        search.addEventListener('input', function clear() { search.setCustomValidity(''); search.removeEventListener('input', clear); });
      }
    });
    syncType();
  }

  function setupBulk(form) {
    var audience = form.querySelector('[data-bulk-audience]');
    var filter = form.querySelector('[data-bulk-filter]');
    var feeHint = form.querySelector('[data-bulk-hint-fee]');
    function sync() {
      var staff = audience.value === 'staff';
      filter.querySelectorAll('[data-student-only]').forEach(function (o) { o.disabled = staff; });
      if (staff && filter.value !== 'all') filter.value = 'all';
      form.querySelectorAll('[data-bulk-for]').forEach(function (field) {
        var on = field.dataset.bulkFor === filter.value;
        field.hidden = !on;
        field.querySelectorAll('select,input').forEach(function (input) { input.disabled = !on; });
      });
      if (feeHint) feeHint.hidden = filter.value !== 'fee_outstanding';
    }
    audience.addEventListener('change', sync);
    filter.addEventListener('change', sync);
    sync();
  }

  function setupAnnouncement(form) {
    var box = form.querySelector('[data-sms-announce]');
    if (!box) return;
    var toggle = box.querySelector('[data-sms-announce-toggle]');
    var audience = box.querySelector('[data-sms-announce-audience]');
    var fields = box.querySelector('[data-sms-announce-fields]');
    var count = box.querySelector('[data-sms-announce-count]');
    function load() {
      fields.hidden = !toggle.checked;
      if (!toggle.checked) return;
      count.textContent = 'Counting recipients…';
      fetch(box.dataset.countUrl + '?audience=' + encodeURIComponent(audience.value), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          count.textContent = d.error ? d.error : ('Selected recipients: ' + d.total + ' · Valid phone numbers: ' + d.valid + ' · Missing or invalid: ' + d.skipped);
        })
        .catch(function () { count.textContent = 'Could not count recipients right now.'; });
    }
    toggle.addEventListener('change', load);
    audience.addEventListener('change', load);
    load();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-sms-compose]').forEach(function (form) {
      var refresh = setupForm(form);
      if (form.dataset.searchUrl) setupCompose(form, refresh);
      if (form.hasAttribute('data-sms-bulk')) setupBulk(form);
    });
    document.querySelectorAll('form').forEach(setupAnnouncement);
    document.querySelectorAll('[data-copy-button]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var source = btn.parentElement.querySelector('[data-copy-source]');
        if (!source || !navigator.clipboard) return;
        navigator.clipboard.writeText(source.textContent.trim()).then(function () {
          var label = btn.lastChild;
          label.textContent = 'Copied';
          setTimeout(function () { label.textContent = 'Copy'; }, 2000);
        });
      });
    });
    if (window.location.search.indexOf('preview=1') !== -1) {
      var review = document.getElementById('review');
      if (review) review.scrollIntoView({ block: 'start' });
    }
  });
}());
