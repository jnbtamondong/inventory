(() => {
 const dialog=document.querySelector('#ledgerPrintPicker');if(!dialog)return;
 const form=dialog.querySelector('form'),checks=[...form.querySelectorAll('[name="selected_items[]"]')],error=form.querySelector('[data-ledger-picker-error]');
 let opener;
 function update(){const count=checks.filter(item=>item.checked).length;form.querySelector('[data-ledger-selection-count]').textContent=count+' item'+(count===1?'':'s')+' selected';form.querySelectorAll('[data-ledger-print-submit]').forEach(button=>button.disabled=!count);error.hidden=true;}
 document.querySelectorAll('[data-ledger-picker-open]').forEach(button=>button.addEventListener('click',()=>{opener=button;const filters=document.getElementById(dialog.dataset.ledgerFilterForm);for(const name of ['from','to'])form.elements.namedItem(name).value=filters?.elements.namedItem(name)?.value||'';update();dialog.showModal();}));
 dialog.querySelectorAll('[data-ledger-picker-close]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
 dialog.addEventListener('close',()=>opener?.focus());
 checks.forEach(item=>item.addEventListener('change',update));
 form.querySelector('[data-ledger-select-all]').addEventListener('click',()=>{checks.forEach(item=>item.checked=true);update();});
 form.querySelector('[data-ledger-clear-all]').addEventListener('click',()=>{checks.forEach(item=>item.checked=false);update();});
 form.querySelector('[data-ledger-picker-search]').addEventListener('input',event=>{const query=event.target.value.trim().toLocaleLowerCase();checks.forEach(item=>item.closest('label').hidden=!item.closest('label').textContent.toLocaleLowerCase().includes(query));});
 form.addEventListener('submit',event=>{const from=form.elements.namedItem('from').value,to=form.elements.namedItem('to').value;const message=!checks.some(item=>item.checked)?'Select at least one item to print.':from&&to&&from>to?'The From date must be on or before the To date.':'';if(message){event.preventDefault();error.textContent=message;error.hidden=false;}});
 update();
})();
