(() => {
  const form = document.querySelector('[data-test-attempt]');
  if (!form) return;
  // Pages, uploads and submitting are handled by submission-pages.js.
  const answered = () => {
    let count = 0;
    form.querySelectorAll('[data-answer-card]').forEach(card => {
      const text = card.querySelector('textarea');
      const box = card.querySelector('[data-page-upload]');
      const file = box?.querySelector('input[type="file"]');
      const radio = card.querySelector('input[type="radio"]:checked');
      const done = Boolean(radio || text?.value.trim() || Number(box?.dataset.pages) > 0 || file?.files?.length);
      card.classList.toggle('is-answered', done);
      if (done) count++;
    });
    const output = document.querySelector('[data-answered-count]');
    if (output) output.textContent = count;
  };
  form.addEventListener('input', answered);
  form.addEventListener('change', answered);
  answered();

  // Countdown from the student's own start time (server clock), so they know how long is left.
  const timer = document.querySelector('[data-test-deadline]');
  if (timer) {
    const offset = Number(timer.dataset.serverNow) * 1000 - Date.now();
    const deadline = Number(timer.dataset.testDeadline) * 1000;
    const label = timer.querySelector('[data-timer-label]');
    const value = timer.querySelector('[data-timer-value]');
    const pad = n => String(n).padStart(2, '0');
    const tick = () => {
      const left = Math.round((deadline - (Date.now() + offset)) / 1000);
      if (left <= 0) {
        timer.classList.add('is-over');
        label.textContent = 'Time is up';
        value.textContent = 'submit now — it will show as late';
        return;
      }
      const hh = Math.floor(left / 3600), mm = Math.floor(left % 3600 / 60), ss = left % 60;
      value.textContent = (hh ? hh + ':' + pad(mm) : mm) + ':' + pad(ss);
      timer.classList.toggle('is-low', left <= 300);
      setTimeout(tick, 1000);
    };
    tick();
  }
})();
