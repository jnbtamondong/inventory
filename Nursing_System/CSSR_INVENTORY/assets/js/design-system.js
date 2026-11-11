/* Shared behavior. No business data or permissions are managed here. */
(() => {
  'use strict';
  const body = document.body;
  const toggle = document.querySelector('#cu-nav-toggle');
  const sidebar = document.querySelector('#cu-sidebar');
  const sidebarToggle = sidebar?.querySelector('[data-cu-close-nav]');
  const brand = sidebar?.querySelector('.cu-brand');
  const brandLabel = brand?.getAttribute('aria-label');
  const mobileCloseIcon = sidebarToggle?.innerHTML;
  const main = document.querySelector('.cu-main');
  const scrim = document.querySelector('.cu-scrim');
  const mobile = matchMedia('(max-width: 767px)');
  let collapsed = false;
  const menuIcon = '<svg class="cu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/></svg>';
  try { collapsed = localStorage.getItem('cssr.ui.sidebar.collapsed') === 'true'; } catch (_) { /* Storage may be unavailable. */ }
  function syncNavigation() {
    body.classList.toggle('cu-collapsed', !mobile.matches && collapsed);
    const open = mobile.matches ? body.classList.contains('cu-nav-open') : !collapsed;
    if (brand) {
      if (!mobile.matches && collapsed) {
        brand.setAttribute('aria-label', 'Expand navigation');
        brand.setAttribute('aria-expanded', 'false');
        brand.setAttribute('aria-controls', 'cu-sidebar');
        brand.title = 'Expand navigation';
      } else {
        if (brandLabel) brand.setAttribute('aria-label', brandLabel); else brand.removeAttribute('aria-label');
        brand.removeAttribute('aria-expanded'); brand.removeAttribute('aria-controls'); brand.removeAttribute('title');
      }
    }
    toggle?.setAttribute('aria-expanded', String(open));
    toggle?.setAttribute('aria-label', mobile.matches ? (open ? 'Close navigation' : 'Open navigation') : (open ? 'Collapse navigation' : 'Expand navigation'));
    if (toggle) {
      toggle.title = toggle.getAttribute('aria-label');
      toggle.innerHTML = mobile.matches ? menuIcon : '<svg class="cu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16' + (collapsed ? 'm4-12 4 4-4 4' : 'm8-12-4 4 4 4') + '"/></svg>';
    }
    if (sidebarToggle) {
      sidebarToggle.innerHTML = mobile.matches ? mobileCloseIcon : toggle.innerHTML;
      sidebarToggle.setAttribute('aria-label', mobile.matches ? 'Close navigation' : (collapsed ? 'Expand navigation' : 'Collapse navigation'));
      sidebarToggle.setAttribute('aria-controls', 'cu-sidebar');
      sidebarToggle.setAttribute('aria-expanded', String(open));
      sidebarToggle.title = sidebarToggle.getAttribute('aria-label');
    }
    if (scrim) scrim.hidden = !mobile.matches || !open;
    if (main) main.inert = mobile.matches && open;
    if (sidebar) {
      sidebar.inert = mobile.matches && !open;
      if (mobile.matches && open) { sidebar.setAttribute('role', 'dialog'); sidebar.setAttribute('aria-modal', 'true'); }
      else { sidebar.removeAttribute('role'); sidebar.removeAttribute('aria-modal'); }
    }
  }
  function closeNavigation(restoreFocus = true) {
    body.classList.remove('cu-nav-open'); syncNavigation();
    if (restoreFocus) toggle?.focus();
  }
  toggle?.addEventListener('click', () => {
    if (mobile.matches) {
      if (body.classList.contains('cu-nav-open')) { closeNavigation(); return; }
      body.classList.add('cu-nav-open'); syncNavigation();
      sidebar?.querySelector('[data-cu-close-nav]')?.focus();
    } else {
      collapsed = !collapsed;
      try { localStorage.setItem('cssr.ui.sidebar.collapsed', String(collapsed)); } catch (_) { /* Optional preference. */ }
      syncNavigation();
    }
  });
  document.querySelectorAll('[data-cu-close-nav]').forEach(button => button.addEventListener('click', () => {
    if (!mobile.matches) {
      collapsed = !collapsed;
      try { localStorage.setItem('cssr.ui.sidebar.collapsed', String(collapsed)); } catch (_) { /* Optional preference. */ }
      syncNavigation(); (collapsed ? brand : sidebarToggle)?.focus(); return;
    }
    closeNavigation();
  }));
  sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => { if (mobile.matches) closeNavigation(); }));
  brand?.addEventListener('click', event => {
    if (mobile.matches || !collapsed) return;
    event.preventDefault(); collapsed = false;
    try { localStorage.setItem('cssr.ui.sidebar.collapsed', 'false'); } catch (_) { /* Optional preference. */ }
    syncNavigation(); sidebarToggle?.focus();
  });
  sidebar?.querySelectorAll('.cu-nav-group > summary').forEach(summary => summary.addEventListener('click', event => {
    if (!mobile.matches && collapsed) {
      event.preventDefault(); collapsed = false; syncNavigation(); summary.parentElement.open = true;
      try { localStorage.setItem('cssr.ui.sidebar.collapsed', 'false'); } catch (_) { /* Optional preference. */ }
    }
  }));
  mobile.addEventListener('change', () => {
    const focusWasInSidebar = sidebar?.contains(document.activeElement);
    closeNavigation(false);
    if (mobile.matches && focusWasInSidebar) toggle?.focus();
  });
  syncNavigation();
  const dropdowns = [...document.querySelectorAll('.cu-dropdown')];
  dropdowns.forEach(dropdown => dropdown.addEventListener('toggle', () => {
    if (dropdown.open) dropdowns.forEach(other => { if (other !== dropdown) other.open = false; });
  }));
  document.addEventListener('click', event => dropdowns.forEach(dropdown => { if (!dropdown.contains(event.target)) dropdown.open = false; }));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      if (body.classList.contains('cu-nav-open')) closeNavigation();
      dropdowns.forEach(dropdown => { if (dropdown.open) { dropdown.open = false; dropdown.querySelector('summary').focus(); } });
    }
    if (event.key === 'Tab' && body.classList.contains('cu-nav-open')) {
      const focusable = [...sidebar.querySelectorAll('a,button,summary')].filter(el => el.getClientRects().length && !el.closest('details:not([open]) .cu-subnav'));
      const first = focusable[0], last = focusable.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  function updateClock() {
    const now = new Date();
    document.querySelectorAll('[data-cu-clock]').forEach(clock => {
      clock.dateTime = now.toISOString();
      clock.textContent = new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric', year: 'numeric' }).format(now) + '\n' + new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit' }).format(now) + ' PHT';
    });
  }
  updateClock(); setInterval(updateClock, 30000);
  document.querySelectorAll('[data-cu-dialog]').forEach(button => button.addEventListener('click', () => {
    const dialog = document.getElementById(button.dataset.cuDialog);
    if (dialog instanceof HTMLDialogElement && !dialog.open) dialog.showModal();
  }));
  document.querySelectorAll('[data-cu-dismiss]').forEach(button => button.addEventListener('click', () => button.closest('dialog')?.close()));
  let toastTimer;
  function toast(message) {
    const target = document.querySelector('#cu-toast');
    if (!target) return;
    clearTimeout(toastTimer); target.textContent = message; target.hidden = false;
    toastTimer = setTimeout(() => { target.hidden = true; }, 6000);
  }
  window.cssrUI = Object.freeze({ toast });
  // Reference-only interactions are activated by explicit data attributes.
  document.querySelectorAll('[data-cu-demo-toast]').forEach(button => button.addEventListener('click', () => toast(button.dataset.cuDemoToast)));
  document.querySelectorAll('[data-cu-demo-form]').forEach(form => form.addEventListener('submit', event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    form.closest('dialog')?.close(); toast('Preview complete. No records were saved.');
  }));
})();
