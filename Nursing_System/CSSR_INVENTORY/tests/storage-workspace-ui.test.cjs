// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const fixtures=['masterlist','requests'].map(name=>({name,file:path.join(__dirname,'.storage-'+name+'-review.html')}));
  for(const fixture of fixtures)fs.writeFileSync(fixture.file,execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'render-storage-workspace.php'),fixture.name]));
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-storage-workspace-'));
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
    await call('Page.enable'); await call('Runtime.enable'); await call('Emulation.setFocusEmulationEnabled',{enabled:true}); await viewport(1440);
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/reports.php' });
    await ready();









    for(const name of ['masterlist','requests']){
      await call('Page.navigate',{url:'http://localhost/Nursing_System/CSSR_INVENTORY/tests/.storage-'+name+'-review.html'});await pause(250);await ready();
      if(name==='requests')await evaluate('addStorageRequestItem(1);addStorageRequestItem(2)');
      for(const width of [1440,1024,390,320]){
        await viewport(width,1000);
        assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'No overflow '+name+' '+width);
        assert.equal(await evaluate('getComputedStyle(document.querySelector(".cu-card")).borderRadius'),'16px','Shared cards');
        if(name==='requests'){
          assert(await evaluate('[...document.querySelectorAll("#storageRequestCart .pos-cart-row")].every(row=>{const r=row.getBoundingClientRect();return [...row.children].every(e=>{const c=e.getBoundingClientRect();return c.bottom<=r.bottom&&c.right<=r.right})})'),'Request cart bounds '+width);
          assert.equal(await evaluate('document.querySelector("#storageRequestCartCount").textContent'),'2');
        }
        if(width===1440||width===390)await screenshot(name+'-'+width);
      }
      if(name==='requests'){
        await evaluate('document.querySelector("#storageRequestSearch").value="MEDIUM";document.querySelector("#storageRequestSearch").dispatchEvent(new Event("input"))');
        assert.equal(await evaluate('document.querySelectorAll("#storageRequestItemList .pos-item-card").length'),1,'Search still works');
        await evaluate('updateStorageRequestQty(2,3);removeStorageRequestItem(1)');assert.equal(await evaluate('document.querySelector("#storageRequestQty").textContent'),'3','Cart controls retained');
      }else{
        assert(await evaluate('!!document.querySelector(".data-search input")&&!!document.querySelector(".data-pager")'),'Search and pagination retained');
        await evaluate('window.print=()=>{window.printed=true};printStorageRoomInventory()');assert(await evaluate('window.printed'),'Print action retained');
        await call('Emulation.setEmulatedMedia',{media:'print'});assert.equal(await evaluate('getComputedStyle(document.querySelector(".cu-sidebar")).display'),'none','Print hides shell');await call('Emulation.setEmulatedMedia',{media:''});
      }
      await viewport(1440);await evaluate('NursingTheme.set(NursingTheme.get()==="dark"?"light":"dark")');await pause(250);await screenshot(name+'-dark');
    }
    assert.deepEqual(exceptions,[],'No script errors');console.log('Passed storage screen layouts, long-name carts, search, quantities, removal, pagination and printing. Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);for(const fixture of fixtures)fs.rmSync(fixture.file,{force:true});}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
