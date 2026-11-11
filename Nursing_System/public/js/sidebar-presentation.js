/* Use each module's existing controller and saved preference. */
(() => {
 function initialize(){
  const body=document.body;if(body.classList.contains('cu-app'))return;
  const sidebar=document.querySelector('.nursing-sidebar,.tc-sidebar,.proc-sidebar');if(!sidebar)return;
  if(sidebar.dataset.procSidebarManaged==='true')return;
  const procurement=sidebar.matches('.proc-sidebar'),documents=sidebar.matches('.tc-sidebar');
  const state=procurement?'proc-sidebar-collapsed':documents?'ns-document-collapsed':'sidebar-collapsed';
  const brand=sidebar.querySelector('.sidebar-brand,.tc-brand,.proc-brand');
  const control=procurement?document.querySelector('.proc-sidebar-toggle'):sidebar.querySelector(documents?'.ns-document-collapse':'.sidebar-toggle');
  const image=brand?.querySelector('img');if(!control||!image)return;
  body.classList.add('ns-unified-sidebar');
  const marker=document.createComment('Navigation toggle location');control.before(marker);
  const logo=document.createElement('button');logo.type='button';logo.className='ns-sidebar-logo';image.before(logo);logo.append(image);
  const mobile=matchMedia('(max-width:900px)');
  function sync(){
   const collapsed=!mobile.matches&&body.classList.contains(state);
   body.classList.toggle('ns-sidebar-rail',collapsed);
   logo.disabled=!collapsed;logo.setAttribute('aria-label',collapsed?'Expand navigation':'JVGH seal');
   logo.setAttribute('aria-expanded',String(!collapsed));
   if(!sidebar.id)sidebar.id='ns-shared-sidebar';logo.setAttribute('aria-controls',sidebar.id);
   logo.title=collapsed?'Expand navigation':'';
   if(procurement){if(mobile.matches)marker.after(control);else brand.append(control);}
  }
  logo.addEventListener('click',()=>{if(mobile.matches||!body.classList.contains(state))return;control.click();sync();control.focus();});
  control.addEventListener('click',()=>{if(!mobile.matches&&body.classList.contains(state)){sync();logo.focus();}});
  // Open a submenu from its icon without losing the navigation labels.
  sidebar.querySelectorAll('.has-submenu>a').forEach(link=>link.addEventListener('click',()=>{if(!mobile.matches&&body.classList.contains(state)){control.click();sync();}}));
  new MutationObserver(sync).observe(body,{attributes:true,attributeFilter:['class']});mobile.addEventListener('change',sync);sync();
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(initialize,0),{once:true});else setTimeout(initialize,0);
})();
