document.querySelectorAll('[data-password-toggle]').forEach(button => {
  button.addEventListener('click', () => {
    const field = document.getElementById(button.dataset.passwordToggle);
    const visible = field.type === 'password';
    field.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(visible));
    button.setAttribute('aria-label', (visible ? 'Hide ' : 'Show ') + document.querySelector('label[for="' + field.id + '"]').textContent.toLowerCase());
  });
});
document.querySelectorAll('.auth-form').forEach(form => form.addEventListener('submit', () => {
  if (!form.checkValidity()) return;
  const button = form.querySelector('.auth-submit');
  button.disabled = true;
  button.querySelector('span').textContent = form.classList.contains('signup-form') ? 'Creating your workspace…' : 'Signing in…';
}));

