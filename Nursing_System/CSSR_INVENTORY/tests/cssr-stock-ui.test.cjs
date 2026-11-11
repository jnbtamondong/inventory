// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-stock-'));
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
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/cssr_stock.php' });
    await ready();







    async function go(query=''){await call('Page.navigate',{url:'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/cssr_stock.php'+query});await pause(150);await ready();}
    assert.equal(await evaluate('document.querySelector("h1").textContent'),'CSSR Stock');
    assert.equal(await evaluate('document.querySelectorAll(".cu-stats .cu-stat").length'),4);
    assert.equal(await evaluate('document.querySelector(".cu-nav-link[aria-current]").getAttribute("aria-label")'),'CSSR Stock');
    assert.equal(await evaluate('document.querySelectorAll(".cs-table tbody tr").length'),7);
    assert.equal(await evaluate('document.querySelector(".cs-stat-total .cu-stat-value").textContent'),'1,302');
    assert(await evaluate(`Number.parseFloat(getComputedStyle(document.querySelector('[data-cs-row="1"] .cs-stock strong')).fontSize)>=28`),'CSSR quantity prominent');
    await screenshot('desktop-stock');
    await evaluate(`document.querySelector('[data-cs-row="1"] td:nth-child(2)').click()`);await pause(200);await ready();
    assert(await evaluate('document.querySelector("#cs-detail").open'),'Row opens detail');
    assert.equal(await evaluate('document.querySelector("#cs-detail-title").textContent'),'Gauze Swab 4 × 4');
    assert.equal(await evaluate('document.querySelectorAll(".cs-batches tbody tr").length'),1,'No Storage Room batches in detail');
    assert.equal(await evaluate('document.querySelectorAll(".cs-issues tbody tr").length'),1,'CSSR-only monthly issuance');
    assert(await evaluate('document.querySelector(".cs-detail-balance strong").textContent.includes("400")'),'CSSR balance only');
    await screenshot('desktop-detail');await key('Tab');
    assert(await evaluate('document.querySelector("#cs-detail").contains(document.activeElement)'),'Detail traps keyboard focus');
    await key('Escape');assert(await evaluate('!document.querySelector("#cs-detail").open'),'Escape closes detail');
    assert(await evaluate('document.activeElement.closest("[data-cs-row]")?.dataset.csRow==="1"'),'Focus restored');
    await go('?status=Out%20of%20Stock');
    assert.equal(await evaluate('document.querySelectorAll(".cs-table tbody tr").length'),3);
    assert(await evaluate('[...document.querySelectorAll(".cs-stock strong")].every(el=>el.textContent==="0")'),'Out-of-stock filter');
    await go('?status=Fast-Moving');assert.equal(await evaluate('document.querySelectorAll(".cs-table tbody tr").length'),2);
    await go('?q=Gauze');assert.equal(await evaluate('document.querySelectorAll(".cs-table tbody tr").length'),1);
    await go('?q=absent');assert(await evaluate('!!document.querySelector(".cu-empty")'),'Empty state');
    await go('?view=2');assert(await evaluate('document.querySelector(".cs-legacy").textContent.includes("7 units")'),'Earlier CSSR stock verification explained');
    const previous=await evaluate('(()=>{const d=new Date();d.setDate(1);d.setMonth(d.getMonth()-1);return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")})()');
    await go('?month='+previous+'&view=1');
    assert(await evaluate('document.querySelector(".cs-detail-balance strong").textContent.includes("400")'),'Changing issue month preserves current stock');
    assert(await evaluate('document.querySelector(".cs-issues-heading strong").textContent.includes("40")'),'Previous month issuance');
    for(const width of [1440,1024,768,390,320]){
      await viewport(width,width<768?844:1000);await go();
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'List fits '+width);
      if(width===390)await screenshot('mobile-stock');
      await go('?view=1');
      const bounds=await evaluate('(()=>{const r=document.querySelector("#cs-detail").getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,height:r.height};})()');
      assert(bounds.left>=0&&bounds.right<=width,'Detail fits '+width);
      assert(await evaluate('document.querySelector("#cs-detail .cu-dialog-foot").getBoundingClientRect().bottom<=innerHeight'),'Footer reachable '+width);
      if(width<768){assert.equal(bounds.top,0);assert.equal(bounds.height,844);}
      if(width===390)await screenshot('mobile-detail');
      await key('Escape');
    }
    assert.deepEqual(exceptions,[],'No browser errors');
    console.log('Passed: CSSR stock totals, emphasized quantities, stock/status search, fast-moving filter, month switching, CSSR-only batch and issue details, legacy explanations, keyboard focus, and responsive layouts.');
    console.log('Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
