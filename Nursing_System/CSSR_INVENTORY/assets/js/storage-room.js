(() => {
 'use strict';
 document.querySelector('[data-storage-location]')?.addEventListener('change',event=>event.target.form.requestSubmit());
 const notice=document.querySelector('[data-storage-notice]');if(notice){window.cssrUI.toast(notice.textContent.trim());notice.hidden=true;}
 const configElement=document.querySelector('#st-config');if(!configElement)return;
 const config=JSON.parse(configElement.textContent),lots=new Map(config.lots.map(lot=>[String(lot.id),lot]));
 const modal=document.querySelector('#st-transfer'),confirmation=document.querySelector('#st-confirm'),form=document.querySelector('#st-form');
 const select=document.querySelector('#st-lot'),quantity=document.querySelector('#st-quantity'),review=document.querySelector('#st-review'),submit=document.querySelector('#st-submit');
 let opener=null,busy=false;
 function clearErrors(){form.querySelectorAll('.cu-error').forEach(error=>error.hidden=true);form.querySelectorAll('[aria-invalid]').forEach(input=>input.removeAttribute('aria-invalid'));document.querySelector('#st-form-error').hidden=true;}
 function error(field,message){const el=document.getElementById('st-error-'+field)||document.querySelector('#st-form-error');el.textContent=message;el.hidden=false;form.elements.namedItem(field)?.setAttribute('aria-invalid','true');}
 function update(){const lot=lots.get(select.value),qty=Number(quantity.value),valid=!!lot&&quantity.value!==''&&Number.isInteger(qty)&&qty>0&&qty<=lot.available;
   quantity.max=lot?String(lot.available):'0';document.querySelector('#st-available').value=lot?lot.available.toLocaleString()+' '+lot.unit:'—';
   document.querySelector('#st-batch').hidden=!lot;if(lot){document.querySelector('#st-batch-number').textContent=lot.lot_number;document.querySelector('#st-batch-expiry').textContent=lot.expiry_date||'No expiry';}
   document.querySelector('#st-remaining').textContent=valid?(lot.available-qty).toLocaleString()+' '+lot.unit+' available in this batch':'—';
   review.disabled=busy||!valid||!form.elements.date.checkValidity();
   if(quantity.value!==''&&!valid){error('quantity',!lot?'Select an item first.':qty>lot.available?'Transfer quantity cannot exceed '+lot.available.toLocaleString()+' '+lot.unit+'.':'Enter a positive whole quantity.');}
   else{document.querySelector('#st-error-quantity').hidden=true;quantity.removeAttribute('aria-invalid');}
 }
 document.querySelectorAll('[data-storage-transfer]').forEach(button=>button.addEventListener('click',()=>{if(busy)return;opener=button;form.reset();clearErrors();select.value=button.dataset.storageTransfer||'';update();modal.showModal();(select.value?quantity:select).focus();}));
 document.querySelectorAll('[data-st-close]').forEach(button=>button.addEventListener('click',()=>{if(!busy)modal.close();}));
 modal.addEventListener('close',()=>opener?.focus());modal.addEventListener('cancel',event=>{if(busy)event.preventDefault();});
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&modal.open){event.preventDefault();event.stopPropagation();if(!busy)(confirmation.open?confirmation:modal).close();}},true);
 form.addEventListener('input',update);select.addEventListener('change',update);form.addEventListener('submit',event=>{event.preventDefault();if(!review.disabled)review.click();});
 review.addEventListener('click',()=>{clearErrors();if(!form.reportValidity())return;const lot=lots.get(select.value),qty=Number(quantity.value);if(!lot||!Number.isInteger(qty)||qty<=0||qty>lot.available){update();return;}document.querySelector('#st-confirm-description').textContent='Transfer '+qty.toLocaleString()+' '+lot.unit+' of '+lot.item_name+' (batch '+lot.lot_number+') from '+config.locationLabel+' to CSSR on '+form.elements.date.value+'?';confirmation.showModal();});
 function setBusy(value){busy=value;window.cssrMotion?.busy(form,value,'Recording stock transfer…');window.cssrMotion?.busy(confirmation,value&&confirmation.open,'Recording stock transfer…');modal.querySelectorAll('button').forEach(button=>button.disabled=value);submit.disabled=value;confirmation.querySelector('[data-cu-dismiss]').disabled=value;form.inert=value;update();}
 submit.addEventListener('click',async()=>{if(busy)return;const body=new FormData(form);body.set('csrf_token',config.csrf);body.set('request_key',config.request_key);setBusy(true);clearErrors();
   try{
     if(config.preview){confirmation.close();modal.close();window.cssrUI.toast('Preview transfer complete. No hospital stock was changed.');return;}
     body.set('source_location',config.location);
     const response=await fetch(config.save,{method:'POST',body,credentials:'same-origin',headers:{Accept:'application/json'}});
     let result;try{result=await response.json();}catch(_){throw Error('The transfer could not be confirmed. Check your session, then retry this form.');}
     if(!response.ok||!result.ok){confirmation.close();setBusy(false);if(Number.isInteger(result.available)){const lot=lots.get(select.value);if(lot)lot.available=result.available;update();}Object.entries(result.errors||{_form:'Transfer failed. Your entries are still here.'}).forEach(([key,message])=>error(key,message));form.querySelector('[aria-invalid=true]')?.focus();return;}
     location.reload();
   }catch(failure){confirmation.close();setBusy(false);error('_form',failure.message||'Connection interrupted. Your entries are preserved.');}
   finally{setBusy(false);}
 });
})();
