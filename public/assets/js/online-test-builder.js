(() => {
  document.querySelectorAll('.saved-tests tbody tr').forEach(row => {
    const link = row.querySelector('a[href$="/submissions"]');
    if (!link) return;
    const cells = row.querySelectorAll('td');
    const count = cells[4]?.textContent.trim() || '0';
    link.classList.add('submission-review-button');
    link.textContent = Number(count) > 0 ? `Review & mark (${count})` : 'View student attempts';
  });

  const builder = document.querySelector('#questionBuilder');
  const template = document.querySelector('#questionTemplate');
  const addButton = document.querySelector('#addQuestion');
  if (!builder || !template || !addButton) return;

  // Two entirely separate question-source modes: type each question, or hand over one PDF
  // question paper. Each lives in its own <fieldset>; disabling the inactive one keeps its
  // inputs out of the submitted form without any manual name/value bookkeeping.
  const form = document.querySelector('.test-builder');
  const modeInputs = document.querySelectorAll('input[name="answer_mode"]');
  const typedSection = document.querySelector('#typedQuestionsSection');
  const paperSection = document.querySelector('#paperUploadSection');
  const paperFile = document.querySelector('#paperUploadFile');
  const paperFilenameRow = document.querySelector('[data-paper-upload-filename]');
  const paperFilenameText = document.querySelector('[data-paper-upload-filename-text]');
  const paperClearButton = document.querySelector('[data-paper-upload-clear]');

  const applyMode = () => {
    const uploadOnly = [...modeInputs].some(input => input.checked && input.value === 'upload_only');
    if (form) form.classList.toggle('is-upload-only', uploadOnly);
    if (typedSection) { typedSection.hidden = uploadOnly; typedSection.disabled = uploadOnly; }
    if (paperSection) { paperSection.hidden = !uploadOnly; paperSection.disabled = !uploadOnly; }
  };
  modeInputs.forEach(input => input.addEventListener('change', applyMode));

  if (paperFile) {
    paperFile.addEventListener('change', () => {
      const name = paperFile.files && paperFile.files[0] ? paperFile.files[0].name : '';
      if (paperFilenameText) paperFilenameText.textContent = name;
      if (paperFilenameRow) paperFilenameRow.hidden = !name;
    });
  }
  if (paperClearButton) {
    paperClearButton.addEventListener('click', () => {
      if (paperFile) paperFile.value = '';
      if (paperFilenameRow) paperFilenameRow.hidden = true;
    });
  }

  let sequence = 0;
  const refresh = () => {
    const cards = [...builder.querySelectorAll('.question-editor')];
    cards.forEach((card, index) => card.querySelector('[data-number]').textContent = index + 1);
    const count = document.querySelector('[data-question-count]');
    if (count) count.textContent = cards.length;
    cards.forEach(card => card.querySelector('[data-remove]').disabled = cards.length === 1);
    if (window.lucide) window.lucide.createIcons();
  };

  const setKind = (card, kind) => {
    card.dataset.kind = kind;
    card.querySelector('[data-type]').value = kind;
    card.querySelector('[data-options]').hidden = kind !== 'mcq';
    card.querySelector('[data-written-preview]').hidden = kind === 'mcq';
    card.querySelectorAll('[data-kind]').forEach(button => button.classList.toggle('is-active', button.dataset.kind === kind));
    card.querySelectorAll('[data-option]').forEach(input => input.required = kind === 'mcq');
  };

  const addQuestion = () => {
    const key = sequence++;
    const fragment = template.content.cloneNode(true);
    const card = fragment.querySelector('.question-editor');
    card.querySelector('[data-type]').name = `question_type[${key}]`;
    card.querySelector('[name="question_text[]"]').name = `question_text[${key}]`;
    card.querySelector('[name="question_marks[]"]').name = `question_marks[${key}]`;
    card.querySelector('[data-question-file]').name = `question_attachment[${key}]`;
    card.querySelectorAll('[data-option]').forEach(input => input.name = `question_options[${key}][]`);
    card.querySelector('[data-correct]').name = `correct_option[${key}]`;
    card.querySelectorAll('[data-kind]').forEach(button => button.addEventListener('click', () => setKind(card, button.dataset.kind)));
    card.querySelectorAll('[data-correct-radio]').forEach(radio => radio.addEventListener('change', () => card.querySelector('[data-correct]').value = radio.value));
    card.querySelector('[data-correct-radio]').checked = true;
    card.querySelector('[data-remove]').addEventListener('click', () => { card.remove(); refresh(); });
    builder.append(fragment);
    setKind(card, 'written');
    refresh();
    applyMode();
    card.querySelector('textarea').focus();
  };

  addButton.addEventListener('click', addQuestion);
  addQuestion();
  applyMode();
})();
