// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const fixturePath=path.join(__dirname,'.request-add-review.html');
  fs.writeFileSync(fixturePath,execFileSync(process.env.CSSR_PHP_PATH||'C:/xampp/php/php.exe',[path.join(__dirname,'request-add-fixture.php'),'catalog-department']));
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-foundation-'));
  const chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
  const browserPath = process.env.CSSR_BROWSER_PATH || (fs.existsSync(chrome) ? chrome : 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe');
  const browser = spawn(browserPath, [
    '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--disable-extensions', '--disable-background-networking', '--disable-sync', '--remote-debugging-port=0',
    ...(process.env.CSSR_BROWSER_NO_SANDBOX === '1' ? ['--no-sandbox'] : []),
    '--user-data-dir=' + path.join(artifacts, 'profile'), 'about:blank',
  ], { windowsHide: true, stdio: 'ignore' });
  let launchError; browser.on('error', error => { launchError = error; });
  browser.on('exit', code => { if (code) launchError = Error('Browser exited: ' + code); });
  let socket; const pending = new Map(); let nextId = 1; const exceptions = [];
  try {
    const portFile = path.join(artifacts, 'profile', 'DevToolsActivePort');
    for (let i = 0; i < 300 && !fs.existsSync(portFile); i++) { if (launchError) throw launchError; await pause(100); }
    assert(fs.existsSync(portFile), 'Browser did not start');
    const port = fs.readFileSync(portFile, 'utf8').split('\n')[0];
    const pages = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    const websocketUrl = new URL(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
    websocketUrl.hostname = '127.0.0.1';
    socket = new WebSocket(websocketUrl);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    socket.addEventListener('message', event => {
      const message = JSON.parse(event.data);
      if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails.text);
      const waiter = pending.get(message.id);
      if (waiter) { clearTimeout(waiter.timer); pending.delete(message.id); message.error ? waiter.reject(Error(message.error.message)) : waiter.resolve(message.result); }
    });
    function call(method, params = {}) {
      return new Promise((resolve, reject) => {
        const id = nextId++; const timer = setTimeout(() => { pending.delete(id); reject(Error('Timed out: ' + method)); }, 20000);
        pending.set(id, { resolve, reject, timer }); socket.send(JSON.stringify({ id, method, params }));
      });
    }
    async function evaluate(expression) {
      const value = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
      if (value.exceptionDetails) throw Error(JSON.stringify(value.exceptionDetails));
      return value.result.value;
    }
    async function ready() {
      for (let i = 0; i < 80; i++) { if (await evaluate('document.readyState === "complete" && !!window.cssrUI')) return; await pause(100); }
      throw Error('Reference did not load');
    }
    async function viewport(width, height = 1000) {
      await call('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 }); await pause(100);
    }
    async function key(key, code = key, modifiers = 0) {
      const windowsVirtualKeyCode = key === 'Escape' ? 27 : key === 'Tab' ? 9 : 0;
      await call('Input.dispatchKeyEvent', { type: 'keyDown', key, code, modifiers, windowsVirtualKeyCode });
      await call('Input.dispatchKeyEvent', { type: 'keyUp', key, code, modifiers, windowsVirtualKeyCode });
      await pause(50);
    }
    async function screenshot(name) { fs.writeFileSync(path.join(artifacts, name + '.png'), Buffer.from((await call('Page.captureScreenshot')).data, 'base64')); }
    await call('Runtime.enable'); await call('Emulation.setFocusEmulationEnabled', { enabled: true }); await viewport(1440);
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/' });
    await ready();await call('Page.enable');

    await call('Page.addScriptToEvaluateOnNewDocument',{source:`window.catalogCalls=[];const realFetch=window.fetch;window.fetch=async(url,options)=>{
      const u=new URL(url,location.href);if(!u.searchParams.has('catalog'))return realFetch(url,options);
      window.catalogCalls.push(u.searchParams.toString());const room=u.searchParams.get('room')||'CSSR';
      let rows=Array.from({length:130},(_,n)=>({id:n+1,item_code:'CODE-'+(n+1),item_name:'Item '+String(n+1).padStart(3,'0'),current_stock:10,storage_location:room}));
      const ids=u.searchParams.get('ids');const q=(u.searchParams.get('q')||'').toLowerCase();
      rows=rows.filter(r=>ids?ids.split(',').includes(String(r.id)):!q||r.item_name.toLowerCase().includes(q)||r.item_code.toLowerCase().includes(q));
      const offset=Number(u.searchParams.get('offset')||0);return new Response(JSON.stringify({ok:true,items:rows.slice(offset,offset+50),has_more:rows.length>offset+50,next_offset:offset+Math.min(50,rows.length-offset)}),{headers:{'Content-Type':'application/json'}});
    };localStorage.setItem('cssr-request-cart-v2-1',JSON.stringify([{id:120,qty:3}]));`});
    for(const mode of ['department','storage']){
      fs.writeFileSync(fixturePath,execFileSync(process.env.CSSR_PHP_PATH||'C:/xampp/php/php.exe',[path.join(__dirname,'request-add-fixture.php'),'catalog-'+mode]));
      await call('Page.navigate',{url:'http://localhost/Nursing_System/CSSR_INVENTORY/tests/.request-add-review.html?mode='+mode});await ready();await pause(300);
      const storage=mode==='storage';const search=storage?'storageRequestSearch':'requestSearch';const list=storage?'storageRequestItemList':'requestItemList';
      assert.equal(await evaluate('document.getElementById('+JSON.stringify(list)+').children.length'),50,'Initial catalog bounded');
      if(!storage){assert.equal(await evaluate('requestCart.get(120).qty'),3,'Saved item beyond page 1 restored');assert.equal(await evaluate('JSON.parse(localStorage.getItem(requestCartStorageKey))[0].id'),120,'Cart storage retained');}
      await evaluate('document.getElementById('+JSON.stringify(list)+').nextElementSibling.nextElementSibling.click()');await pause(100);
      assert.equal(await evaluate('document.getElementById('+JSON.stringify(list)+').children.length'),100,'Load more appends next page');
      const before=await evaluate('catalogCalls.length');
      await evaluate('(()=>{const s=document.getElementById('+JSON.stringify(search)+');for(const text of ["Item 1","Item 12","Item 125"]){s.value=text;s.dispatchEvent(new Event("input"))}})()');await pause(450);
      assert.equal(await evaluate('catalogCalls.length'),before+1,'Typing is debounced');
      assert.equal(await evaluate('document.getElementById('+JSON.stringify(list)+').children.length'),1,'Server search narrows rows');
      assert.match(await evaluate('document.getElementById('+JSON.stringify(list)+').textContent'),/Item 125/);
      if(!storage){await evaluate('addRequestItem(125)');assert.equal(await evaluate('requestCart.size'),2,'Search preserves earlier cart item');}
      const picker=storage?'storageAddItemPicker':'addRequestItemPicker';
      await evaluate('(()=>{const i=document.getElementById('+JSON.stringify(picker)+');i.focus();i.value="Item 130";i.dispatchEvent(new Event("input"))})()');await pause(450);
      const choices=storage?'storageAddItemSuggestions':'addRequestItemSuggestions';
      assert.match(await evaluate('document.getElementById('+JSON.stringify(choices)+').textContent'),/Item 130/,'Add Item searches beyond first page');
      console.log('Catalog UI passed: '+mode);
    }
    assert.deepEqual(exceptions,[]);console.log('Passed paged catalog, debounced search, item lookup and saved-cart restoration. No database writes.');
    await call('Browser.close');
  } finally { fs.rmSync(fixturePath,{force:true}); socket?.close(); browser.kill(); for (const waiter of pending.values()) clearTimeout(waiter.timer); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });

