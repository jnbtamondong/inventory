/* Shell behavior only; procurement forms keep their existing controllers. */
document.addEventListener('DOMContentLoaded',()=>{
 const body=document.body,sidebar=document.getElementById('procSidebar'),main=document.querySelector('.proc-shell>main'),topbar=document.querySelector('.proc-top');
 if(!sidebar||!main||!topbar)return;
 if(body.dataset.procLayoutReady==='true')return;
 const mobile=matchMedia('(max-width:900px)');
 try{if(localStorage.getItem('procSidebarCollapsed')==='1')body.classList.add('proc-sidebar-collapsed');}catch{}
 const heading=topbar.firstElementChild;heading.classList.add('proc-top-copy');
 const left=document.createElement('div');left.className='proc-top-left';
 const toggle=document.createElement('button');toggle.type='button';toggle.className='proc-sidebar-toggle';toggle.dataset.procCollapse='';toggle.setAttribute('aria-controls','procSidebar');
 toggle.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4" width="17" height="16" rx="2.5"/><path d="M9 4v16"/></svg>';
 heading.before(left);left.append(toggle,heading);
 const brand=sidebar.querySelector('.proc-brand'),image=brand?.querySelector('img');
 const logo=document.createElement('button');logo.type='button';logo.className='ns-sidebar-logo';
 if(image){image.before(logo);logo.append(image);}
 sidebar.dataset.procSidebarManaged='true';body.classList.add('ns-unified-sidebar');
 main.id='procWorkspace';
 function sync(){
  const open=mobile.matches&&body.classList.contains('proc-nav-open');
  const expanded=mobile.matches?open:!body.classList.contains('proc-sidebar-collapsed');
  body.classList.toggle('ns-sidebar-rail',!mobile.matches&&!expanded);
  const target=mobile.matches?left:brand;if(target&&toggle.parentElement!==target){if(mobile.matches)left.prepend(toggle);else brand.append(toggle);}
  logo.disabled=mobile.matches||expanded;logo.setAttribute('aria-label',logo.disabled?'JVGH seal':'Expand navigation');logo.setAttribute('aria-expanded',String(expanded));logo.setAttribute('aria-controls','procSidebar');logo.title=logo.disabled?'':'Expand navigation';
  toggle.setAttribute('aria-expanded',String(expanded));toggle.setAttribute('aria-label',mobile.matches?(open?'Close navigation':'Open navigation'):expanded?'Collapse navigation':'Expand navigation');
  toggle.title=toggle.getAttribute('aria-label');
  toggle.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(mobile.matches?'<rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/>':'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16'+(expanded?'m8-12-4 4 4 4':'m4-12 4 4-4 4')+'"/>')+'</svg>';
  sidebar.inert=mobile.matches&&!open;sidebar.setAttribute('aria-hidden',String(sidebar.inert));main.inert=open;
  if(open){sidebar.setAttribute('role','dialog');sidebar.setAttribute('aria-modal','true');}else{sidebar.removeAttribute('role');sidebar.removeAttribute('aria-modal');}
 }
 function close(){body.classList.remove('proc-nav-open');sync();toggle.focus();}
 toggle.addEventListener('click',()=>{
  if(mobile.matches){if(body.classList.contains('proc-nav-open')){close();return;}body.classList.add('proc-nav-open');sync();sidebar.querySelector('[data-proc-close]').focus();}
  else{body.classList.toggle('proc-sidebar-collapsed');try{localStorage.setItem('procSidebarCollapsed',body.classList.contains('proc-sidebar-collapsed')?'1':'0');}catch{}sync();(body.classList.contains('proc-sidebar-collapsed')?logo:toggle).focus();}
 });
 logo.addEventListener('click',()=>{if(!mobile.matches&&body.classList.contains('proc-sidebar-collapsed'))toggle.click();});
 document.querySelectorAll('[data-proc-close]').forEach(button=>button.addEventListener('click',close));
 document.addEventListener('keydown',event=>{
  if(!mobile.matches||!body.classList.contains('proc-nav-open'))return;
  if(event.key==='Escape'){event.preventDefault();close();}
  if(event.key==='Tab'){
   const targets=[...sidebar.querySelectorAll('a[href],button')].filter(e=>e.getClientRects().length&&!e.disabled),first=targets[0],last=targets.at(-1);
   if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
  }
 });
 mobile.addEventListener('change',()=>{const focused=sidebar.contains(document.activeElement);body.classList.remove('proc-nav-open');sync();if(mobile.matches&&focused)toggle.focus();});sync();
 const oldUser=topbar.querySelector('.proc-user');
 const actions=document.createElement('div'),clock=document.createElement('span');actions.className='proc-top-actions';clock.className='proc-clock';actions.append(clock);topbar.append(actions);if(oldUser)actions.append(oldUser);
 const updateClock=()=>clock.textContent=new Intl.DateTimeFormat('en-PH',{timeZone:'Asia/Manila',month:'short',day:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}).format(new Date());updateClock();setInterval(updateClock,30000);
 body.dataset.procLayoutReady='true';document.dispatchEvent(new Event('proc-layout-ready'));
});
