/* Paginated item search. Keeps selected cart records when result pages change. */
window.cssrCatalogSearch = function (options) {
  const {input, list, items, render, room = () => 'CSSR'} = options;
  if (!input || !list) return;
  options.visible = items.slice();
  let timer, controller, generation = 0, offset = 0, more = false;
  const status = document.createElement('div');
  status.className = 'catalog-search-status'; status.setAttribute('role', 'status');
  const next = document.createElement('button'); next.type = 'button'; next.className = 'btn btn-light'; next.textContent = 'Load more items'; next.hidden = true;
  list.after(status, next);
  function merge(rows) {
    for (const row of rows) {
      const existing = items.findIndex(item => Number(item.id) === Number(row.id) && (item.storage_location || 'CSSR') === (row.storage_location || 'CSSR'));
      if (existing < 0) items.push(row); else items[existing] = row;
    }
  }
  async function request(extra = {}, signal) {
    const url = new URL(options.endpoint, location.href);
    url.search = ''; url.hash = '';
    for (const [key,value] of Object.entries({catalog:'1',q:input.value,room:room(),...extra})) url.searchParams.set(key,value);
    const response = await fetch(url, {credentials:'same-origin', signal, headers:{'X-Requested-With':'XMLHttpRequest'}});
    if (response.redirected || !response.ok) throw Error('Unable to load items. Please retry or refresh the page.');
    const data = await response.json(); if (!data.ok) throw Error(data.message || 'Unable to load items.');
    return data;
  }
  async function load(append = false) {
    const current = ++generation; controller?.abort(); controller = new AbortController();
    next.disabled = true; status.textContent = 'Loading items…'; list.setAttribute('aria-busy','true');
    const abort = controller;
    const timeout = setTimeout(() => abort.abort(), 15000);
    try {
      const data = await request({offset:append ? offset : 0}, abort.signal);
      if (current !== generation) return;
      merge(data.items); offset = data.next_offset; more = data.has_more;
      options.visible = append ? [...(options.visible || []), ...data.items] : data.items;
      render(options.visible); next.hidden = !more;
      next.textContent = 'Load more items';
      status.textContent = data.items.length ? (more ? 'More items are available.' : '') : 'No matching items found.';
    } catch (error) {
      if (current !== generation) return;
      status.textContent = error.name === 'AbortError' ? 'Search timed out. Change the search or retry.' : error.message;
      next.hidden = false; next.textContent = 'Retry search'; more = false;
    } finally {
      clearTimeout(timeout);
      if (current === generation) { next.disabled = false; list.removeAttribute('aria-busy'); }
    }
  }
  input.addEventListener('input', () => { clearTimeout(timer); controller?.abort(); ++generation; timer = setTimeout(() => { next.textContent='Load more items'; load(); }, 300); });
  next.addEventListener('click', () => load(more));
  if (options.roomInput) options.roomInput.addEventListener('change', () => load());
  more = !!options.initial?.has_more; offset = options.initial?.next_offset || items.length; next.hidden = !more;
  // Restoring a cart must also resolve items outside the first result page.
  if (options.restoreIds?.length) (async () => {
    const abort = new AbortController();
    const timeout = setTimeout(() => abort.abort(), 15000);
    try {
      for (let i=0;i<options.restoreIds.length;i+=50) {
        const data = await request({ids:options.restoreIds.slice(i,i+50).join(','),q:'',offset:0}, abort.signal); merge(data.items);
      }
      options.restored?.();
    } catch (error) { status.textContent='Saved cart items could not be loaded. Refresh to restore them.'; }
    finally { clearTimeout(timeout); }
  })();
  return {load, merge};
};

window.cssrCatalogLookup = function ({input, endpoint, room = () => 'CSSR', onItems, onError}) {
  let timer, controller, generation=0;
  async function search() {
    const current=++generation; controller?.abort(); controller=new AbortController();
    const abort=controller; const timeout=setTimeout(()=>abort.abort(),15000);
    input.setAttribute('aria-busy','true');
    try {
      const url=new URL(endpoint,location.href);url.search='';url.hash='';
      url.searchParams.set('catalog','1');url.searchParams.set('q',input.value);url.searchParams.set('room',room());
      const response=await fetch(url,{credentials:'same-origin',signal:abort.signal});
      if(response.redirected||!response.ok)throw Error('Unable to search items. Please retry.');
      const data=await response.json();if(!data.ok)throw Error(data.message||'Unable to search items.');
      if(current===generation)onItems(data.items);
    }catch(error){if(current===generation)onError(error.name==='AbortError'?'Item search timed out. Please retry.':error.message);}
    finally{clearTimeout(timeout);if(current===generation)input.removeAttribute('aria-busy');}
  }
  input.addEventListener('input',()=>{clearTimeout(timer);controller?.abort();++generation;timer=setTimeout(search,300);});
  input.addEventListener('focus',search);
};
