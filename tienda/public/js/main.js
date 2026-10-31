document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-checkout-form]');
  if (form) {
    const block = document.getElementById('password-block');
    const password = document.getElementById('checkout-password');
    const email = document.getElementById('checkout-email');
    const button = form.querySelector('[data-show-password]');
    const updateAccount = () => {
      const account = form.querySelector('input[name="tipo"]:checked')?.value === 'registrado';
      block.hidden = !account;
      password.disabled = !account;
      password.required = account && (!form.dataset.accountEmail ||
        email.value.trim().toLowerCase() !== form.dataset.accountEmail.toLowerCase());
      if (!account) { password.type = 'password'; button.textContent = 'Mostrar'; button.setAttribute('aria-pressed', 'false'); }
    };
    form.querySelectorAll('input[name="tipo"]').forEach(input => input.addEventListener('change', updateAccount));
    email.addEventListener('input', updateAccount);
    button.hidden = false;
    button.addEventListener('click', () => {
      const reveal = password.type === 'password';
      password.type = reveal ? 'text' : 'password';
      button.textContent = reveal ? 'Ocultar' : 'Mostrar';
      button.setAttribute('aria-pressed', String(reveal));
    });
    updateAccount();
  }
  document.querySelector('[data-focus-on-load]')?.focus();
});
