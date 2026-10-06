// Edit test: add new questions (written or MCQ) before any student has opened the test.
(() => {
  const form = document.querySelector('[data-test-edit]');
  const template = document.getElementById('newQuestionTemplate');
  const list = form?.querySelector('[data-new-questions]');
  const add = form?.querySelector('[data-add-edit-question]');
  if (!form || !template || !list || !add) return;
  let key = 0;
  add.addEventListener('click', () => {
    const k = key++;
    const card = template.content.firstElementChild.cloneNode(true);
    card.querySelector('[data-new-file]').name = `new_question_attachment[${k}]`;
    card.querySelectorAll('[data-new-option]').forEach(input => { input.name = `new_question_options[${k}][]`; });
    card.querySelectorAll('[data-new-correct]').forEach(radio => { radio.name = `new_correct_option[${k}]`; });
    const type = card.querySelector('[data-new-type]');
    const options = card.querySelector('[data-new-options]');
    type.addEventListener('change', () => { options.hidden = type.value !== 'mcq'; });
    card.querySelector('[data-discard-new]').addEventListener('click', () => card.remove());
    list.append(card);
    window.lucide?.createIcons();
    card.querySelector('textarea').focus();
  });
  // A question marked for removal is dimmed so it is obvious what will be deleted on save.
  form.querySelectorAll('input[name="remove_question[]"]').forEach(box => box.addEventListener('change', () => {
    box.closest('[data-edit-question]').classList.toggle('is-removed', box.checked);
  }));
})();
