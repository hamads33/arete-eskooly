(() => {
  // Generate Fees Invoice (eSkooly layout): tabs switch scope; the student list filters as you type.
  const selection = document.querySelector('[data-voucher-selection]');
  if (selection) {
    const scope = selection.elements.scope;
    const titles = {student: 'Student Invoice', class: 'Class Invoices', all: 'All Students Invoices'};
    const steps = {student: '1', class: '2', all: '3'};
    const sync = () => {
      selection.querySelectorAll('[data-voucher-scope]').forEach(b => { const on = b.dataset.voucherScope === scope.value; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', String(on)); });
      selection.querySelectorAll('[data-scope-field]').forEach(f => { const on = f.dataset.scopeField === scope.value; f.hidden = !on; f.querySelectorAll('select').forEach(s => s.required = on); });
      const t = selection.querySelector('[data-voucher-title]'); if (t) t.textContent = titles[scope.value] || 'Invoices';
      const n = selection.querySelector('[data-voucher-step]'); if (n) n.textContent = steps[scope.value] || '1';
    };
    selection.querySelector('[data-voucher-modes]').addEventListener('click', e => { const b = e.target.closest('[data-voucher-scope]'); if (!b) return; scope.value = b.dataset.voucherScope; sync(); });
    const filter = selection.querySelector('[data-student-filter]');
    const list = selection.querySelector('[data-student-select]');
    if (filter && list) {
      filter.addEventListener('input', () => {
        const q = filter.value.trim().toLowerCase(); let first = null;
        [...list.options].forEach((o, i) => { if (!i) return; const hit = !q || o.text.toLowerCase().includes(q); o.hidden = !hit; if (hit && !first) first = o; });
        if (q && first && (list.selectedOptions[0]?.hidden || list.value === '0')) list.value = first.value;
      });
      filter.addEventListener('keydown', e => { if (e.key === 'ArrowDown') { e.preventDefault(); list.focus(); } if (e.key === 'Enter') e.preventDefault(); });
      list.addEventListener('change', () => { if (list.value !== '0') filter.value = list.selectedOptions[0].text; });
    }
    selection.addEventListener('submit', e => {
      if (scope.value === 'student' && (!list || list.value === '0')) { e.preventDefault(); alert('Choose a student first.'); return; }
      if (scope.value === 'class' && selection.elements.class.value === '0') { e.preventDefault(); alert('Choose a class first.'); return; }
      if (!selection.querySelector('input[name="copy[]"]:checked')) { e.preventDefault(); alert('Choose at least one copy (Bank, Student or Institute).'); }
    });
    const orient = selection.querySelector('[data-orientation-field]');
    const copySync = () => { if (orient) orient.hidden = selection.querySelectorAll('input[name="copy[]"]:checked').length !== 1; };
    selection.querySelectorAll('input[name="copy[]"]').forEach(c => c.addEventListener('change', copySync)); copySync();
    selection.querySelector('[data-add-charge]')?.addEventListener('click', () => {
      const rows = selection.querySelector('[data-charge-rows]');
      if (rows.children.length >= 10) return alert('You can add up to 10 charges at a time.');
      const row = rows.firstElementChild.cloneNode(true); row.querySelectorAll('input').forEach(i => i.value = ''); rows.append(row); row.querySelector('input').focus();
    });
    selection.addEventListener('submit', e => {
      const bad = [...selection.querySelectorAll('.ek-charge')].find(r => { const [l, a] = r.querySelectorAll('input'); return (l.value.trim() === '') !== (a.value.trim() === ''); });
      if (bad) { e.preventDefault(); alert('Each extra charge needs both a name and an amount.'); bad.querySelector('input').focus(); return; }
      const charges = [...selection.querySelectorAll('.ek-charge')].filter(r => r.querySelector('input').value.trim()).length;
      if (charges && !e.defaultPrevented && !confirm(`Add ${charges} extra charge${charges > 1 ? 's' : ''} to every invoice in this selection?`)) e.preventDefault();
    });
    selection.querySelector('[data-voucher-preview]')?.addEventListener('click', () => {
      const u = new URL(location.href); u.search = '';
      for (const k of ['scope', 'month', 'class', 'student_id']) if (selection.elements[k]) u.searchParams.set(k, selection.elements[k].value);
      location.href = u.toString();
    });
    sync();
  }
  const form = document.querySelector('[data-fee-search]');
  if (form) {
    const results = document.querySelector('[data-fee-results]');
    const status = document.querySelector('[data-fee-search-status]');
    const query = form.elements.student_query;
    let timer, request, revision = 0;
    const search = async () => {
      clearTimeout(timer);
      request?.abort();
      const current = ++revision;
      if (!form.reportValidity()) return;
      request = new AbortController();
      const url = new URL(form.action, location.href);
      url.search = new URLSearchParams(new FormData(form)).toString();
      status.textContent = 'Finding students…';
      results.hidden = true;
      results.setAttribute('aria-busy', 'true');
      try {
        const response = await fetch(url, {signal: request.signal, credentials: 'same-origin'});
        if (!response.ok) throw new Error('Search failed');
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const updated = doc.querySelector('[data-fee-results]');
        if (!updated) throw new Error('Search unavailable');
        if (current !== revision) return;
        results.replaceChildren(...updated.childNodes);
        results.hidden = false;
        window.lucide?.createIcons();
        status.textContent = results.querySelector('.fee-results-count')?.textContent.trim() || 'No students found. Try another search.';
      } catch (error) {
        if (current !== revision || error.name === 'AbortError') return;
        status.textContent = 'Live search is unavailable. Press Find Student again to open the results.';
        form.dataset.searchFallback = 'true';
      } finally {
        if (current === revision) results.removeAttribute('aria-busy');
      }
    };
    form.addEventListener('submit', event => {
      if (form.dataset.searchFallback) return;
      event.preventDefault();
      search();
    });
    query.addEventListener('input', () => {
      clearTimeout(timer);
      request?.abort();
      ++revision;
      results.hidden = true;
      status.textContent = query.value.trim() ? 'Searching…' : 'Start typing a name or registration number, or press Find Student to browse.';
      if (query.value.trim()) timer = setTimeout(search, 200);
    });
    for (const name of ['class', 'month', 'only_due']) form.elements[name]?.addEventListener('change', search);
    query.addEventListener('keydown', event => {
      if (event.key === 'ArrowDown' && !results.hidden) {
        const first = results.querySelector('a');
        if (first) { event.preventDefault(); first.focus(); }
      }
    });
  }
  // Fees Collection (eSkooly layout): Total = particulars + fine - discount + previous balance - already paid;
  // Due Balance = Total - Deposit, all live. Locked invoices keep the server's remaining balance.
  const payment = document.querySelector('[data-ek-collect]');
  if (payment) {
    const deposit = payment.elements.payment_amount;
    const totalOut = payment.querySelector('[data-ek-total]');
    const dueOut = payment.querySelector('[data-ek-due]');
    const editable = payment.dataset.editable === '1';
    const num = v => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
    const fmt = n => n.toLocaleString('en', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const total = () => {
      if (!editable) return num(payment.dataset.fixedRemaining) + 0;
      let sum = 0;
      payment.querySelectorAll('[data-ek-line]').forEach(i => sum += Math.max(0, num(i.value)));
      const disc = payment.querySelector('[data-ek-discount]');
      const mode = payment.querySelector('[data-ek-discount-mode]')?.value || 'amount';
      const fineBox = payment.querySelector('[name=fine_amount]');
      const base = sum - (fineBox ? Math.max(0, num(fineBox.value)) : 0);
      const raw = Math.max(0, num(disc?.value));
      const d = Math.min(sum, mode === 'percent' ? Math.round(base * Math.min(raw, 100)) / 100 : raw);
      const calc = payment.querySelector('[data-ek-discount-calc]');
      if (calc) calc.textContent = mode === 'percent' ? '= ' + payment.dataset.currency + ' ' + fmt(d) : '';
      const prevInput = payment.querySelector('[data-ek-previous]');
      const prev = prevInput ? Math.max(0, num(prevInput.value)) : num(payment.dataset.previous);
      const gross = Math.max(0, sum - d + prev);
      if (totalOut) totalOut.dataset.gross = gross.toFixed(2);
      return Math.max(0, gross - num(payment.dataset.paid));
    };
    const update = () => {
      const t = total();
      if (editable) totalOut.textContent = fmt(num(totalOut.dataset.gross));
      const paidNow = num(payment.dataset.paid);
      if (editable && paidNow > 0 && num(totalOut.dataset.gross) + 0.004 < paidNow) { deposit?.setCustomValidity('The total cannot be less than the ' + fmt(paidNow) + ' already paid.'); }
      if (deposit) deposit.max = t.toFixed(2);
      const dep = deposit ? num(deposit.value) : 0;
      dueOut.textContent = fmt(Math.max(0, t - dep));
      dueOut.classList.toggle('is-clear', t - dep <= 0.004);
      if (deposit && !(editable && paidNow > 0 && num(totalOut.dataset.gross) + 0.004 < paidNow)) deposit.setCustomValidity(dep > t + 0.004 ? 'Deposit cannot be more than the balance of ' + fmt(t) + '.' : '');
    };
    payment.addEventListener('input', update);
    const modeSel = payment.querySelector('[data-ek-discount-mode]');
    modeSel?.addEventListener('change', () => {
      // Convert what is in the box so switching Rs <-> % keeps the same discount.
      const disc = payment.querySelector('[data-ek-discount]'); let sum = 0;
      payment.querySelectorAll('[data-ek-line]').forEach(i => { if (i.name !== 'fine_amount') sum += Math.max(0, num(i.value)); });
      const v = num(disc.value);
      disc.value = modeSel.value === 'percent' ? (sum ? +(v / sum * 100).toFixed(2) : 0) : +(sum * Math.min(v, 100) / 100).toFixed(2);
      disc.max = modeSel.value === 'percent' ? '100' : '';
      update();
    });
    payment.querySelector('[data-fee-pay-full]')?.addEventListener('click', () => { deposit.value = total().toFixed(2); update(); deposit.focus(); });
    payment.querySelectorAll('.ek-amount').forEach(i => i.addEventListener('focus', () => i.select()));
    payment.addEventListener('submit', event => {
      if (!payment.checkValidity()) return;
      const dep = num(deposit?.value), t = total();
      if (!confirm(`Collect ${payment.dataset.currency} ${fmt(dep)} of ${fmt(t)}?` + (t - dep > 0.004 ? `\nRemaining ${fmt(t - dep)} stays due.` : ''))) { event.preventDefault(); return; }
      const button = payment.querySelector('.ek-submit button');
      if (button) { button.disabled = true; button.textContent = 'Saving…'; }
    });
    window.addEventListener('pageshow', () => { const button = payment.querySelector('.ek-submit button'); if (button) { button.disabled = false; button.innerHTML = '<i data-lucide="check"></i>Submit Fees'; window.lucide?.createIcons(); } });
    update();
  }
})();
