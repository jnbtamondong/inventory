/* Mobile enhancement only. Keeps existing controls, validation and permission checks. */
(() => {
  'use strict';
  const mobile = matchMedia('(max-width: 767px)');
  const body = document.body;
  const bottom = document.querySelector('.cm-bottom-nav');
  const more = document.querySelector('[data-cm-more]');
  const transaction = document.querySelector('#cm-transactions');
  const transactionButton = document.querySelector('[data-cm-transaction]');
  const sidebar = document.querySelector('#cu-sidebar,#appSidebar');
  const toggle = document.querySelector('#cu-nav-toggle,[data-sidebar-toggle]');
  const legacy = !body.classList.contains('cu-app');
  const main = document.querySelector('.cu-main,.main-content');
  let opener = toggle;
  let openingFromMore = false;
  const originalToggleIcon = toggle?.innerHTML;
  const hamburger = '<svg class="cu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
  function navigationOpen() { return body.classList.contains(legacy ? 'sidebar-open' : 'cu-nav-open'); }
  function syncNavigation() {
    const open = mobile.matches && navigationOpen();
    if (bottom) bottom.inert = open;
    more?.setAttribute('aria-expanded', String(open));
    if (legacy && sidebar) {
      sidebar.inert = mobile.matches && !open;
      if (main) main.inert = open;
      if (open) { sidebar.setAttribute('role','dialog'); sidebar.setAttribute('aria-modal','true'); sidebar.setAttribute('aria-label','Main navigation'); }
      else { sidebar.removeAttribute('role'); sidebar.removeAttribute('aria-modal'); }
      toggle?.setAttribute('aria-expanded',String(mobile.matches ? open : !body.classList.contains('sidebar-collapsed')));
      toggle?.setAttribute('aria-controls','appSidebar');
    }
  }
  function openNavigation(source) {
    opener = source;
    if (legacy) { body.classList.add('sidebar-open'); syncNavigation(); sidebar?.querySelector('[data-sidebar-close],a')?.focus(); }
    else { openingFromMore = true; toggle?.click(); openingFromMore = false; }
  }
  function closeNavigation() {
    if (legacy) { body.classList.remove('sidebar-open'); syncNavigation(); }
    else { sidebar?.querySelector('[data-cu-close-nav]')?.click(); syncNavigation(); }
    opener?.focus();
  }
  more?.addEventListener('click', () => openNavigation(more));
  toggle?.addEventListener('click', () => { if (!openingFromMore) opener = toggle; });
  document.addEventListener('click', event => {
    if (mobile.matches && !legacy && event.target.closest('[data-cu-close-nav]')) { syncNavigation(); opener?.focus(); }
  });
  // Capture the old mobile toggle so its 900px legacy behavior stays intact on tablets/desktop.
  document.addEventListener('click', event => {
    if (!mobile.matches || !legacy) return;
    if (event.target.closest('[data-sidebar-toggle]')) { event.preventDefault(); event.stopImmediatePropagation(); openNavigation(toggle); }
    else if (event.target.closest('[data-sidebar-close]')) { event.preventDefault(); event.stopImmediatePropagation(); closeNavigation(); }
  }, true);
  document.addEventListener('keydown', event => {
    if (!mobile.matches || !navigationOpen()) return;
    if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); closeNavigation(); }
    if (event.key === 'Tab' && legacy) {
      const controls = [...sidebar.querySelectorAll('a,button,summary,input')].filter(el => !el.disabled && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
      const first = controls[0], last = controls.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
  }, true);
  new MutationObserver(syncNavigation).observe(body,{attributes:true,attributeFilter:['class']});
  transactionButton?.addEventListener('click', () => { if (mobile.matches && !transaction.open) transaction.showModal(); });
  transaction?.querySelectorAll('[data-cm-dismiss]').forEach(button => button.addEventListener('click', () => transaction.close()));
  transaction?.addEventListener('close', () => { if (mobile.matches) transactionButton?.focus(); });

  function enhanceTables() {
    if (!mobile.matches) return;
    document.querySelectorAll('.cu-workspace table,.main-content table').forEach(table => {
      if (table.closest('[data-mobile-table="scroll"],.print-page,.ris-print-pages') || table.dataset.mobileTable === 'scroll') return;
      const heads = [...(table.tHead?.rows || [])];
      if (!heads.length) return;
      const headers = [...heads[0].cells];
      const simple = heads.length === 1 && headers.every(cell => cell.colSpan === 1 && cell.rowSpan === 1) && [...table.tBodies].every(section => [...section.rows].every(row => [...row.cells].every(cell => cell.rowSpan === 1 && (cell.colSpan === 1 || row.cells.length === 1))));
      if (!simple) {
        // Preserve merged financial/official forms rather than mislabel their cells.
        if (!table.parentElement.classList.contains('cm-table-scroll')) {
          const wrapper = document.createElement('div'); wrapper.className='cm-table-scroll'; wrapper.tabIndex=0;
          wrapper.setAttribute('role','region'); wrapper.setAttribute('aria-label','Detailed table; scroll to see all columns');
          const hint=document.createElement('span');hint.className='cm-table-hint';hint.textContent='Swipe the table to see all columns.';
          table.before(hint,wrapper);wrapper.append(table);
        }
        return;
      }
      table.classList.add('cm-card-table'); table.setAttribute('role','table');
      table.tHead.setAttribute('role','rowgroup');heads[0].setAttribute('role','row');headers.forEach(th=>th.setAttribute('role','columnheader'));
      [...table.tBodies].forEach(section => { section.setAttribute('role','rowgroup'); [...section.rows].forEach(row => {
        row.setAttribute('role','row');
        [...row.cells].forEach((cell,index) => {
          cell.setAttribute('role','cell');
          const label=headers[index]?.textContent.trim().replace(/\s+/g,' ') || '';
          cell.dataset.label=label;
          if (/item name|^item$|description|action|remarks|supplier|department/i.test(label))cell.dataset.cmWide='';
        });
      }); });
    });
  }
  let scheduled = false;
  const schedule = () => { if(scheduled || !mobile.matches)return; scheduled=true; requestAnimationFrame(()=>{scheduled=false;enhanceTables();}); };
  new MutationObserver(schedule).observe(document.querySelector('.cu-workspace,.main-content') || body,{childList:true,subtree:true});

  const primary = document.querySelector('.cu-page-heading > .cu-btn,.cu-page-heading > .cu-actions > .cu-btn:not(.cu-btn-secondary):not(.cu-btn-ghost)');
  let sticky;
  if (primary) {
    const container=document.createElement('div');container.className='cm-quick-action';container.setAttribute('aria-label','Page action');
    sticky=document.createElement('button');sticky.type='button';sticky.textContent=primary.textContent.trim();sticky.disabled=primary.disabled || false;
    sticky.addEventListener('click',()=>primary.click());container.append(sticky);body.append(container);
    new IntersectionObserver(entries=>{const show=!entries[0].isIntersecting;container.classList.toggle('cm-show',show);body.classList.toggle('cm-has-quick-action',show && mobile.matches);},{threshold:0}).observe(primary);
    new MutationObserver(()=>{sticky.disabled=primary.disabled || false;sticky.textContent=primary.textContent.trim();}).observe(primary,{attributes:true,attributeFilter:['disabled'],childList:true,subtree:true});
  }
  function viewportChanged() {
    // The shared navigation controller owns the icon and its expanded state.
    if (toggle && legacy && !window.nursyncPresentationReady) { toggle.innerHTML=mobile.matches?hamburger:originalToggleIcon; }
    if (!mobile.matches) {
      transaction?.close();body.classList.remove('cm-keyboard','cm-has-quick-action');
      if (legacy) { body.classList.remove('sidebar-open'); if(main)main.inert=false; if(sidebar)sidebar.inert=false; }
    }
    syncNavigation();enhanceTables();
  }
  mobile.addEventListener('change',viewportChanged);viewportChanged();
  function keyboardChanged() {
    const viewport=window.visualViewport;
    const editing=document.activeElement?.matches('input,textarea,select,[contenteditable=true]');
    body.classList.toggle('cm-keyboard',Boolean(mobile.matches && editing && viewport && window.innerHeight-viewport.height>140));
  }
  window.visualViewport?.addEventListener('resize',keyboardChanged);document.addEventListener('focusout',()=>setTimeout(keyboardChanged,80));
  // A bottom-menu shortcut opens the existing editor; it never submits a transaction.
  if (new URLSearchParams(location.search).get('new')==='1') {
    const button=document.querySelector('[data-in-new]');if(button)button.click();
  }
})();
