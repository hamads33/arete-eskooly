// Adds sort arrows to every .data-table header and a search + column filter bar above larger tables.
// Opt out with class="no-table-tools". Works on whatever rows the server rendered (current page).
(() => {
  const text = cell => (cell ? (cell.querySelector('select')?.selectedOptions[0]?.text ?? cell.querySelector('input:not([type=hidden])')?.value ?? cell.textContent) : '').replace(/\s+/g, ' ').trim();
  const num = v => { const s = v.replace(/[,\s]|Rs\.?|PKR/gi, ''); return /^-?\d+(\.\d+)?%?$/.test(s) ? parseFloat(s) : null; };
  const date = v => /^\d{4}-\d{2}-\d{2}/.test(v) || /^\d{1,2}[ -][A-Za-z]{3}[ -]\d{4}/.test(v) ? Date.parse(v) : null;
  const blank = v => v === '' || v === '—' || v === '-';
  const cmp = (a, b) => {
    if (blank(a) || blank(b)) return blank(a) - blank(b);
    const na = num(a), nb = num(b); if (na !== null && nb !== null) return na - nb;
    const da = date(a), db = date(b); if (da && db) return da - db;
    return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
  };
  const enhance = table => {
    if (table.dataset.tableTools || table.classList.contains('no-table-tools') || table.closest('.no-table-tools')) return;
    const head = table.tHead?.rows[table.tHead.rows.length - 1], body = table.tBodies[0];
    if (!head || !body) return;
    table.dataset.tableTools = '1';
    const rows = () => Array.from(body.rows).filter(r => !r.querySelector('td[colspan]'));
    const all = rows();
    if (all.length < 2) return;
    const cols = Array.from(head.cells);
    const sortable = i => !/^(actions?|select|)$/i.test(cols[i].textContent.trim()) && !cols[i].querySelector('input');
    let state = { i: -1, dir: 1 };
    cols.forEach((th, i) => {
      if (th.colSpan > 1 || !sortable(i)) return;
      th.classList.add('is-sortable'); th.tabIndex = 0; th.setAttribute('aria-sort', 'none');
      th.insertAdjacentHTML('beforeend', '<span class="sort-arrow" aria-hidden="true"></span>');
      const go = () => {
        state = { i, dir: state.i === i ? -state.dir : 1 };
        cols.forEach(c => c.setAttribute('aria-sort', 'none'));
        th.setAttribute('aria-sort', state.dir > 0 ? 'ascending' : 'descending');
        const list = rows(), tail = Array.from(body.rows).filter(r => !list.includes(r));
        list.sort((a, b) => cmp(text(a.cells[i]), text(b.cells[i])) * state.dir).concat(tail).forEach(r => body.append(r));
      };
      th.addEventListener('click', e => { if (!e.target.closest('a,button,input,select')) go(); });
      th.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });
    if (all.length < 6 || table.classList.contains('attendance-table') || table.closest('form[method=post]')) return;
    const bar = document.createElement('div');
    bar.className = 'table-filter-bar';
    bar.innerHTML = '<label class="table-filter-search"><input type="search" placeholder="Search this table…" aria-label="Search this table"></label>';
    const filters = [];
    cols.forEach((th, i) => {
      if (th.colSpan > 1 || !sortable(i)) return;
      const values = [...new Set(all.map(r => text(r.cells[i])).filter(v => v))];
      if (values.length < 2 || values.length > 20 || values.length > all.length * 0.6) return;
      const sel = document.createElement('select');
      sel.setAttribute('aria-label', 'Filter by ' + th.textContent.trim());
      sel.innerHTML = '<option value="">All ' + th.textContent.trim().toLowerCase() + '</option>';
      values.sort(cmp).forEach(v => sel.add(new Option(v, v)));
      filters.push({ i, sel }); bar.append(sel);
    });
    const count = document.createElement('span'); count.className = 'table-filter-count'; bar.append(count);
    const q = bar.querySelector('input');
    const apply = () => {
      const term = q.value.trim().toLowerCase(); let shown = 0;
      rows().forEach(r => {
        const ok = (!term || r.textContent.toLowerCase().includes(term)) && filters.every(f => !f.sel.value || text(r.cells[f.i]) === f.sel.value);
        r.hidden = !ok; if (ok) shown++;
      });
      count.textContent = shown === all.length ? all.length + ' rows' : shown + ' of ' + all.length + ' rows';
    };
    bar.addEventListener('input', apply); bar.addEventListener('change', apply); apply();
    (table.closest('.table-scroll, .class-table-wrap, .report-table-wrap') || table).before(bar);
  };
  const run = () => document.querySelectorAll('table.data-table').forEach(enhance);
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', run) : run();
})();
