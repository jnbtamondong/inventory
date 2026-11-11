(() => {
  'use strict';
  const form = document.querySelector('#rp-filters');
  const pending = document.querySelector('#rp-pending');
  if (form) {
    const original = new URLSearchParams(new FormData(form)).toString();
    form.addEventListener('input', () => {
      pending.hidden = original === new URLSearchParams(new FormData(form)).toString();
    });
    const from = form.elements.from, to = form.elements.to;
    function validate() { to.setCustomValidity(from.value && to.value && from.value > to.value ? 'To Date must be on or after From Date.' : ''); }
    from.addEventListener('change', validate); to.addEventListener('change', validate);
    validate();
  }
  document.querySelector('[data-rp-print]')?.addEventListener('click', event => {
    event.preventDefault();
    if (document.querySelector('[data-rp-print-frame]')) return;
    const link = event.currentTarget;
    const frame = document.createElement('iframe');
    frame.dataset.rpPrintFrame = '';
    frame.title = 'Printable report';
    frame.setAttribute('aria-hidden', 'true');
    frame.tabIndex = -1;
    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1024px;height:1000px;border:0;';
    link.setAttribute('aria-busy', 'true');
    const cleanup = () => { frame.remove(); link.removeAttribute('aria-busy'); };
    frame.addEventListener('load', () => {
      if (!frame.contentDocument?.querySelector('[data-print-report]')) {
        cleanup();window.cssrUI?.toast('Unable to prepare the report. Please refresh and try again.');return;
      }
      frame.contentWindow.addEventListener('afterprint', cleanup, { once: true });
      frame.contentWindow.focus();
      frame.contentWindow.print();
    }, { once: true });
    frame.addEventListener('error', cleanup, { once: true });
    frame.src = link.href;
    document.body.append(frame);
  });
  if (document.querySelector('[data-print-report]')) {
    document.querySelector('.rp-notes')?.setAttribute('open', '');
    // Embedded reports are printed by their parent after loading is complete.
    if (!window.frameElement) window.addEventListener('load', () => window.print(), { once: true });
  }
})();
