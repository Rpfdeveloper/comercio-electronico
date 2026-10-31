// Mejora progresiva: el formulario GET completo sigue funcionando sin JavaScript.
(() => {
  const form = document.querySelector('[data-guide-form]');
  if (!form) return;
  const steps = [...form.querySelectorAll('[data-guide-step]')];
  const progress = form.querySelector('[data-guide-progress]');
  const status = form.querySelector('[data-guide-status]');
  const mode = form.querySelector('[data-guide-mode]');
  const back = form.querySelector('[data-guide-back]');
  const next = form.querySelector('[data-guide-next]');
  const submit = form.querySelector('[data-guide-submit]');
  const summary = form.querySelector('[data-guide-summary]');
  const category = form.elements.c;
  const query = form.elements.q;
  const budget = form.elements.max;
  let current = 0;
  let all = form.dataset.guideStart === 'all';

  const updateSummary = () => {
    form.querySelector('[data-guide-value="c"]').textContent = category.selectedOptions[0]?.textContent || 'Todas las colecciones';
    form.querySelector('[data-guide-value="q"]').textContent = query.value.trim() || 'Sin palabra elegida';
    form.querySelector('[data-guide-value="max"]').textContent = budget.value.trim() ? `Hasta ${budget.value.trim()} €` : 'Sin límite';
  };
  const show = (focus = true) => {
    budget.setCustomValidity('');
    steps.forEach((step, index) => { step.hidden = !all && index !== current; });
    progress.hidden = all;
    [...progress.children].forEach((item, index) => {
      if (index === current) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
      item.classList.toggle('is-complete', index < current);
    });
    status.textContent = all ? 'A tu ritmo · Todas las preferencias' : `Paso ${current + 1} de ${steps.length}`;
    mode.textContent = all ? 'Ir paso a paso' : 'Ver todas las preferencias';
    back.hidden = all || current === 0;
    next.hidden = all || current === steps.length - 1;
    submit.hidden = !all && current !== steps.length - 1;
    summary.hidden = !all && current !== steps.length - 1;
    form.classList.toggle('guide-all', all);
    updateSummary();
    if (focus) steps[all ? 0 : current].querySelector('h2').focus();
  };
  const advance = () => {
    const field = current === 0 ? category : query;
    if (!field.reportValidity()) return;
    current = Math.min(current + 1, steps.length - 1);
    show();
  };
  mode.addEventListener('click', () => { all = !all; show(); });
  next.addEventListener('click', advance);
  back.addEventListener('click', () => { current = Math.max(0, current - 1); show(); });
  form.querySelectorAll('[data-guide-example]').forEach(button => {
    button.addEventListener('click', () => {
      query.value = button.dataset.guideExample;
      updateSummary();
      query.focus();
    });
  });
  form.addEventListener('input', () => { budget.setCustomValidity(''); updateSummary(); });
  form.addEventListener('change', updateSummary);
  form.addEventListener('submit', event => {
    if (!all && current < steps.length - 1) { event.preventDefault(); advance(); return; }
    const value = budget.value.trim();
    const valid = value === '' || /^\d{1,8}(?:[.,]\d{1,2})?$/.test(value);
    budget.setCustomValidity(valid ? '' : 'Introduce un importe desde 0 €, con un máximo de dos decimales.');
    if (!valid) { event.preventDefault(); budget.reportValidity(); }
  });
  form.querySelector('[data-guide-toolbar]').hidden = false;
  form.querySelector('[data-guide-examples]').hidden = false;
  show(false);
})();
