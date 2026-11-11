(() => {
  'use strict';
  let dirty = false;
  document.querySelectorAll('[data-nis-patient]').forEach(select => {
    const sync = () => { select.form.querySelector('[data-nis-encounter]').value = select.selectedOptions[0]?.dataset.encounter || ''; };
    select.addEventListener('change', sync); sync();
  });
  document.querySelectorAll('form[data-nis-confirm]').forEach(form => {
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', event => {
      if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
      if (!window.confirm('Confirm this movement for the displayed patient and encounter? Verify the receiving unit and handover before continuing.')) { event.preventDefault(); return; }
      dirty = false;
      form.dataset.submitting = 'true';
      form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => { button.disabled = true; button.textContent = 'Saving…'; });
    });
  });
  window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
  window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach(form => { delete form.dataset.submitting; form.querySelectorAll('button').forEach(button => { button.disabled = false; button.textContent = form.querySelector('[name="create_movement"]') ? 'Send transfer request' : 'Confirm action'; }); }));
})();
