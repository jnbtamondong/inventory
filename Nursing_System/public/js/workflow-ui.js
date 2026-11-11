(() => {
  if (window.nursingWorkflowUI) return;
  window.nursingWorkflowUI = true;
  const mobile = window.matchMedia('(max-width: 767px)');
  function initialize() {
    const selectors = '.cu-inventory-filter-grid,form.rq-filters,form.st-filters,form.cs-filters,.rp-filter-grid,form.document-filter,[data-mobile-filters]';
    document.querySelectorAll(selectors).forEach((group, index) => {
      if (group.dataset.nsFiltersReady) return;
      const fields = [...group.children].filter(child => {
        const controls = child.matches('input,select,textarea') ? [child] : [...child.querySelectorAll('input,select,textarea')];
        return controls.some(control => control.type !== 'hidden') && !controls.some(control => control.type === 'search' || ['q','report','from','to'].includes(control.name));
      });
      if (!fields.length) return;
      group.dataset.nsFiltersReady = 'true';
      group.dataset.nsFilterExpanded = 'false';
      group.id ||= `ns-filter-group-${index}`;
      fields.forEach(field => field.dataset.nsFilterExtra = '');
      const button = document.createElement('button');
      button.type = 'button';button.className = 'ns-filter-toggle no-print';
      button.setAttribute('aria-controls', group.id);
      group.before(button);
      let expanded = false;
      const refresh = () => {
        const controls = fields.flatMap(field => field.matches('input,select,textarea') ? [field] : [...field.querySelectorAll('input,select,textarea')]);
        const count = controls.filter(control => !control.disabled && control.type !== 'hidden' && !['','0','all'].includes(String(control.value).toLowerCase()) && (!['checkbox','radio'].includes(control.type) || control.checked)).length;
        button.textContent = `${expanded ? 'Hide' : 'Show'} filters${count ? ` (${count} active)` : ''}`;
        button.setAttribute('aria-expanded', String(expanded || !mobile.matches));
        group.dataset.nsFilterExpanded = String(expanded);
      };
      button.addEventListener('click', () => {expanded = !expanded;refresh();});
      group.addEventListener('change', refresh);
      group.addEventListener('input', refresh);
      group.addEventListener('invalid', () => {expanded = true;refresh();}, true);
      group.closest('form')?.addEventListener('reset', () => setTimeout(refresh));
      mobile.addEventListener('change', refresh);
      refresh();
    });
  }
  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded',initialize); else initialize();
})();
