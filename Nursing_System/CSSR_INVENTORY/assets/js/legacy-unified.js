(() => {
  'use strict';
  const root=document.querySelector('.cu-legacy .main-content');if(!root)return;
  const statuses={available:'success',active:'success',released:'success',received:'success',completed:'success',posted:'success',approved:'teal',pending:'warning','for approval':'warning','low stock':'warning',low:'warning',quarantined:'warning','for release':'info',reserved:'info',draft:'info',rejected:'danger','out of stock':'danger',expired:'danger',critical:'danger',void:'danger',deleted:'danger',invalid:'invalid'};
  let fieldNumber=0;
  function sync(){
    root.querySelectorAll('label:not([for])').forEach(label=>{
      if(label.querySelector('input,select,textarea'))return;
      const field=label.nextElementSibling?.matches('input:not([type=hidden]),select,textarea')?label.nextElementSibling:label.nextElementSibling?.querySelector('input:not([type=hidden]),select,textarea');
      if(!field)return;
      if(!field.id){do{field.id='cssr-field-'+(++fieldNumber);}while(document.querySelectorAll('#'+field.id).length>1);}
      label.htmlFor=field.id;
    });
    root.querySelectorAll('.badge,.account-status,.reset-request-pending').forEach(badge=>{
    badge.classList.add('badge');
    const label=badge.textContent.trim().toLowerCase().replace(/[_-]/g,' ').replace(/\s+/g,' ');
    const status=statuses[label]||(badge.classList.contains('bg-success')?'success':badge.classList.contains('bg-danger')?'danger':badge.classList.contains('bg-warning')?'warning':badge.classList.contains('bg-info')?'info':'neutral');
    if(badge.dataset.cuStatus!==status)badge.dataset.cuStatus=status;
  });}
  let pending=false;new MutationObserver(()=>{if(pending)return;pending=true;requestAnimationFrame(()=>{pending=false;sync();});}).observe(root,{childList:true,subtree:true,characterData:true});sync();
})();
