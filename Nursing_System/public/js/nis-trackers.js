(() => {
  function loading() {
    const status=document.querySelector('[data-tracker-status]');
    if(status)status.textContent='Loading current information…';
    document.querySelector('main')?.setAttribute('aria-busy','true');
  }
  document.querySelector('[data-tracker-filters]')?.addEventListener('submit',loading);
  document.querySelector('[data-tracker-refresh]')?.addEventListener('click',loading);
  window.addEventListener('pageshow',()=>document.querySelector('main')?.removeAttribute('aria-busy'));
})();
