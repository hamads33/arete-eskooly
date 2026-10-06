// Salary Sheet: live net pay per row (salary + bonus - deduction - advance), selection totals,
// absence-deduction suggestions, and inline editing of salaries already paid.
(() => {
  const form = document.querySelector('[data-ss-form]');
  if (!form) return;
  const cur = form.dataset.currency || 'Rs';
  const num = v => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
  const fmt = n => cur + ' ' + Math.round(n).toLocaleString('en');
  const rows = [...document.querySelectorAll('[data-ss-row]')];
  const paybar = document.querySelector('[data-ss-paybar]');
  const submit = form.querySelector('[data-ss-submit]');

  const netOf = row => {
    const base = num(row.dataset.base);
    const bonus = Math.max(0, num(row.querySelector('[data-ss-bonus]')?.value));
    const deduction = Math.max(0, num(row.querySelector('[data-ss-deduction]')?.value));
    const recInput = row.querySelector('[data-ss-recovery]');
    const recovery = row.dataset.paid === '1' ? num(row.dataset.balance) : Math.max(0, num(recInput?.value));
    return base + bonus - deduction - recovery;
  };
  const refresh = row => {
    const net = netOf(row);
    const out = row.querySelector('[data-ss-net]');
    out.textContent = fmt(net);
    row.classList.toggle('is-invalid', net <= 0);
    const rec = row.querySelector('input[data-ss-recovery][type=number]');
    if (rec) rec.setCustomValidity(num(rec.value) > num(rec.max) + 0.001 ? 'Cannot recover more than ' + fmt(num(rec.max)) : '');
    const pick = row.querySelector('[data-ss-pick]');
    if (pick && net <= 0) pick.checked = false;
    return net;
  };
  const totals = () => {
    let count = 0, total = 0, grand = 0;
    const sum = { bonus: 0, deduction: 0, recovery: 0 };
    rows.forEach(row => {
      const net = refresh(row);
      grand += Math.max(0, net);
      sum.bonus += Math.max(0, num(row.querySelector('[data-ss-bonus]')?.value));
      sum.deduction += Math.max(0, num(row.querySelector('[data-ss-deduction]')?.value));
      sum.recovery += row.dataset.paid === '1' ? num(row.dataset.balance) : Math.max(0, num(row.querySelector('input[data-ss-recovery]')?.value));
      const pick = row.querySelector('[data-ss-pick]');
      if (pick && pick.checked) { count++; total += net; }
      row.classList.toggle('is-picked', !!(pick && pick.checked));
    });
    const c = document.querySelector('[data-ss-count]'); if (c) c.textContent = count;
    const t = document.querySelector('[data-ss-total]'); if (t) t.textContent = fmt(total);
    const g = document.querySelector('[data-ss-grand]'); if (g) g.textContent = fmt(grand);
    document.querySelectorAll('[data-ss-sum]').forEach(el => { el.textContent = fmt(sum[el.dataset.ssSum] || 0); });
    if (submit) { submit.disabled = count === 0; submit.innerHTML = count ? `Pay ${count} selected` : 'Pay selected'; }
    if (paybar) paybar.classList.toggle('is-active', count > 0);
  };

  rows.forEach(row => {
    const pick = row.querySelector('[data-ss-pick]');
    row.querySelectorAll('input.ss-input, .ss-note').forEach(input => {
      input.addEventListener('focus', () => input.select && input.type === 'number' && input.select());
      input.addEventListener('input', () => { if (pick && !input.disabled && netOf(row) > 0) pick.checked = true; totals(); });
    });
    pick?.addEventListener('change', totals);
    row.querySelector('[data-ss-suggest]')?.addEventListener('click', e => {
      const d = row.querySelector('[data-ss-deduction]');
      if (d.disabled) return;
      d.value = e.currentTarget.dataset.ssSuggest;
      d.dispatchEvent(new Event('input', { bubbles: true }));
      d.focus();
    });
    // Paid rows: Edit unlocks bonus / deduction for an on-the-spot correction.
    const edit = row.querySelector('[data-ss-edit]'), save = row.querySelector('[data-ss-save]'), cancel = row.querySelector('[data-ss-cancel]');
    if (edit) {
      const inputs = [row.querySelector('[data-ss-bonus]'), row.querySelector('[data-ss-deduction]')];
      const original = inputs.map(i => i.value);
      const toggle = on => {
        inputs.forEach(i => { i.disabled = !on; });
        edit.hidden = on; save.hidden = !on; cancel.hidden = !on;
        const sug = row.querySelector('[data-ss-suggest]'); if (sug) sug.hidden = !on;
        row.classList.toggle('is-editing', on);
        if (on) inputs[1].focus();
      };
      edit.addEventListener('click', () => toggle(true));
      cancel.addEventListener('click', () => { inputs.forEach((i, k) => { i.value = original[k]; }); toggle(false); totals(); });
    }
  });

  document.querySelector('[data-ss-all]')?.addEventListener('change', e => {
    rows.forEach(row => { const p = row.querySelector('[data-ss-pick]'); if (p && netOf(row) > 0) p.checked = e.target.checked; });
    totals();
  });

  form.addEventListener('submit', e => {
    const picked = rows.filter(r => r.querySelector('[data-ss-pick]')?.checked);
    if (!picked.length) { e.preventDefault(); return; }
    if (!form.checkValidity()) return;
    const total = picked.reduce((s, r) => s + netOf(r), 0);
    const date = form.querySelector('[name=payment_date]')?.value || '';
    if (!confirm(`Pay ${picked.length} salar${picked.length === 1 ? 'y' : 'ies'} — total ${fmt(total)} — dated ${date}?`)) { e.preventDefault(); return; }
    if (submit) { submit.disabled = true; submit.textContent = 'Paying…'; }
  });

  document.querySelectorAll('[data-ss-adjust]').forEach(adj => adj.addEventListener('submit', e => {
    const row = document.querySelector(`[form="${adj.id}"]`)?.closest('[data-ss-row]');
    const net = row ? netOf(row) : 1;
    if (net <= 0) { e.preventDefault(); alert('Net pay must stay above zero.'); return; }
    if (!confirm(`Update ${adj.dataset.name}'s paid salary to ${fmt(net)}? The change is recorded in the audit log.`)) e.preventDefault();
  }));

  window.addEventListener('pageshow', () => { if (submit) totals(); });
  totals();
})();
