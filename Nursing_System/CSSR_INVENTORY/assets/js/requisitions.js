(() => {
 'use strict';
 document.querySelectorAll('[data-rq-row]').forEach(row => row.addEventListener('click', event => {
   if (event.target.closest('a,button,input,select') || getSelection()?.toString()) return;
   row.querySelector('.rq-number').click();
 }));
 const notice=document.querySelector('[data-rq-notice]');
 if (notice) { window.cssrUI?.toast(notice.textContent.trim()); notice.hidden=true; }
 const detail=document.querySelector('#rq-detail'); if (!detail) return;
 const form=document.querySelector('#rq-action-form'), confirmation=document.querySelector('#rq-confirm');
 const error=document.querySelector('#rq-action-error'), remarks=document.querySelector('#rq-remarks');
 const confirmButton=document.querySelector('#rq-confirm-submit');
 let pendingAction='',busy=false;
 detail.showModal();
 document.addEventListener('keydown',event=>{
   if (event.key!=='Escape' || !detail.open) return;
   event.preventDefault();event.stopPropagation();
   if (!busy) (confirmation.open?confirmation:detail).close();
 },true);
 detail.addEventListener('close',()=>{
   const url=new URL(location.href);url.searchParams.delete('view');history.replaceState(null,'',url);
   document.querySelector(`[data-rq-row="${detail.dataset.requestId}"] .rq-number`)?.focus();
 });
 document.querySelector('#rq-print')?.addEventListener('click',()=>window.print());
 function showError(message) { error.textContent=message; error.hidden=false; error.scrollIntoView({block:'nearest'}); }
 form.addEventListener('submit',event=>event.preventDefault()); // All changes pass through confirmation.
 document.querySelectorAll('[data-rq-action]').forEach(button=>button.addEventListener('click',()=>{
   if (busy) return; const action=button.dataset.rqAction;error.hidden=true;
   const quantities=[...form.querySelectorAll('.rq-quantity')];
   if (action==='approve' || action==='partial') {
     if (!quantities.length) return;
     const desired=quantities.map(input=>action==='approve'?Number(input.dataset.requested):Number(input.value));
     if (desired.some((qty,i)=>!Number.isInteger(qty)||qty<0||qty>Number(quantities[i].dataset.requested))) return showError('Enter whole approved quantities from zero up to the requested quantity.');
     if (!desired.some(qty=>qty>0)) return showError('Approve at least one item, or reject the request with a reason.');
     if (action==='partial' && desired.every((qty,i)=>qty===Number(quantities[i].dataset.requested))) return showError('Reduce at least one quantity for partial approval, or choose Approve for the full request.');
     for (let i=0;i<quantities.length;i++) {
       const lot=document.getElementById(quantities[i].dataset.lot);
       if (desired[i]>0 && (!Number(lot.value) || desired[i]>Number(lot.selectedOptions[0].dataset.stock||0))) return showError('The selected lot cannot cover the approved quantity. Choose an available lot or use Partial Approve with a lower quantity.');
     }
   }
   if (action==='reject' && !remarks.value.trim()) { showError('Enter a reason in Remarks before rejecting this request.');remarks.focus();return; }
   pendingAction=action;
   const labels={approve:'Approve request',partial:'Partially approve request',reject:'Reject request',release:'Release items'};
   document.querySelector('#rq-confirm-title').textContent=labels[action];
   document.querySelector('#rq-confirm-message').textContent={
     approve:'Approve all requested quantities and reserve the selected stock? Inventory is deducted only when items are released.',
     partial:'Approve the quantities entered and reserve the selected stock? Unapproved quantities will not be issued.',
     reject:'Reject this requisition with the reason entered? Any reserved stock will be made available again.',
     release:'Release all approved quantities? This will deduct stock and record the issuance. This request cannot be released twice.'
   }[action];
   confirmButton.textContent=labels[action];confirmButton.classList.toggle('cu-btn-danger',action==='reject');confirmation.showModal();
 }));
 confirmButton.addEventListener('click',()=>{
   if (!pendingAction || busy) return;
   if (detail.dataset.preview==='true') {
     confirmation.close();detail.close();window.cssrUI.toast('Preview complete. No request or inventory records were changed.');pendingAction='';return;
   }
   if (pendingAction==='approve') form.querySelectorAll('.rq-quantity').forEach(input=>input.value=input.dataset.requested);
   document.querySelector('#rq-action').value=pendingAction==='partial'?'approve':pendingAction;
   document.querySelector('#rq-approval-mode').value=pendingAction;
   busy=true;confirmButton.disabled=true;confirmButton.textContent='Processing…';
   HTMLFormElement.prototype.submit.call(form);
 });
})();
