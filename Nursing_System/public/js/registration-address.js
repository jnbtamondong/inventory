(() => {
 'use strict';
 const province=document.getElementById('reg-province_name'),city=document.getElementById('reg-municipality_name'),barangay=document.getElementById('reg-barangay_name');
 if(!province||!city||!barangay)return;
 const status=document.getElementById('registration-address-status'),retry=document.getElementById('registration-address-retry');
 let generation=0,controller=null,retryAction=null;
 function fill(select,items,label,selected=''){
  const options=[new Option(label,'')];
  for(const item of items){const option=new Option(item.name,item.name);option.dataset.locationId=String(item.id);options.push(option);}
  // Preserve entries after a server-side validation error, including older catalog names.
  if(selected&&!items.some(item=>item.name===selected))options.push(new Option(selected,selected));
  select.replaceChildren(...options);select.value=selected;select.disabled=!items.length&&!selected;select.removeAttribute('aria-busy');
 }
 function reset(select,label){fill(select,[],label);}
 function begin(){generation++;controller?.abort();controller=new AbortController();retry.hidden=true;status.textContent='';return generation;}
 async function load(select,type,id,label,selected,version){
  select.setAttribute('aria-busy','true');select.disabled=true;status.textContent='Loading '+label.toLowerCase()+'…';
  const url=new URL('../api/get_locations.php',location.href);url.searchParams.set('type',type);if(id)url.searchParams.set('id',id);
  const response=await fetch(url,{credentials:'same-origin',signal:controller.signal});
  if(!response.ok||response.redirected)throw new Error('Location list is unavailable.');
  const items=await response.json();if(!Array.isArray(items)||items.some(item=>!item.id||typeof item.name!=='string'))throw new Error('Invalid location list.');
  if(version!==generation)return false;
  fill(select,items,'Select '+label.toLowerCase(),selected);status.textContent=items.length?'':`No ${label.toLowerCase()} are available for this selection.`;return true;
 }
 const id=select=>select.selectedOptions[0]?.dataset.locationId||'';
 function run(action){retryAction=action;const job=action(),version=generation;job.catch(error=>{if(error.name==='AbortError'||version!==generation)return;status.textContent='Unable to load locations. Retry to load the address dropdowns.';retry.hidden=false;for(const select of [province,city,barangay]){select.removeAttribute('aria-busy');if(select.value)select.disabled=false;}});}
 function loadCities(){const version=begin();reset(city,'Select city / municipality');reset(barangay,'Select city / municipality first');const parent=id(province);if(!parent)return Promise.resolve();return load(city,'municipalities',parent,'City / Municipality','',version);}
 function loadBarangays(){const version=begin();reset(barangay,'Select barangay');const parent=id(city);if(!parent)return Promise.resolve();return load(barangay,'barangays',parent,'Barangay','',version);}
 province.addEventListener('change',()=>run(loadCities));city.addEventListener('change',()=>run(loadBarangays));barangay.addEventListener('change',()=>{status.textContent='';});
 retry.addEventListener('click',()=>run(retryAction||initialize));
 async function initialize(){
  const version=begin(),savedProvince=province.dataset.selected||'',savedCity=city.dataset.selected||'',savedBarangay=barangay.dataset.selected||'';
  if(!savedCity)reset(city,'Select province first');if(!savedBarangay)reset(barangay,'Select city / municipality first');
  if(!await load(province,'provinces','','Province',savedProvince,version))return;
  if(id(province)&&await load(city,'municipalities',id(province),'City / Municipality',savedCity,version)&&id(city))await load(barangay,'barangays',id(city),'Barangay',savedBarangay,version);
 }
 run(initialize);
})();
