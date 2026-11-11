(() => {
 'use strict';
 const dialogs=[...document.querySelectorAll('dialog.registration-modal')];
 if(!dialogs.length)return;
 function sync(){document.body.classList.toggle('registration-modal-open',dialogs.some(dialog=>dialog.open));}
 function open(dialog){
  for(const other of dialogs)if(other!==dialog&&other.open)other.close();
  if(!dialog.open)dialog.showModal();sync();
  const alert=dialog.querySelector('[role=alert]');
  if(alert){alert.tabIndex=-1;alert.focus({preventScroll:true});}
  else dialog.querySelector('input:not([type=hidden]):not([readonly]),select:not([disabled]),textarea')?.focus({preventScroll:true});
 }
 document.querySelectorAll('[data-registration-open]').forEach(link=>link.addEventListener('click',event=>{
  const dialog=document.getElementById(link.dataset.registrationOpen);if(!dialog)return;
  // A new identity must not reuse an existing patient's read-only encounter form.
  if(dialog.id==='registration-patient-modal'&&dialog.querySelector('[name=source_patient_id]')?.value!=='0')return;
  event.preventDefault();open(dialog);
 }));
 document.querySelectorAll('[data-registration-close]').forEach(button=>button.addEventListener('click',event=>{const dialog=button.closest('dialog');if(dialog){event.preventDefault();dialog.close();}}));
 for(const dialog of dialogs){
  dialog.addEventListener('close',sync);
  dialog.addEventListener('click',event=>{if(event.target!==dialog)return;const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();});
  if(dialog.hasAttribute('data-registration-auto-open')){if(dialog.open)dialog.close();open(dialog);}
 }
})();
