(() => {
 'use strict';
 document.querySelectorAll('[data-cs-row]').forEach(row=>row.addEventListener('click',event=>{if(event.target.closest('a,button')||getSelection()?.toString())return;row.querySelector('a').click();}));
 const dialog=document.querySelector('#cs-detail');if(!dialog)return;
 dialog.showModal();dialog.addEventListener('close',()=>{const url=new URL(location.href);url.searchParams.delete('view');history.replaceState(null,'',url);document.querySelector('[data-cs-row="'+dialog.dataset.itemId+'"] a')?.focus();});
})();
