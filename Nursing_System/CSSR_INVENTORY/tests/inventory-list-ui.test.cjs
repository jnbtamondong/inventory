// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-inventory-'));
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
    await call('Runtime.enable'); await viewport(1440);
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/inventory.php' });
    await ready();


    assert.equal(await evaluate('document.querySelector("h1").textContent'), 'Inventory');
    assert.equal(await evaluate('document.querySelectorAll("[data-inventory-row]").length'), 10);
    assert.equal(await evaluate('document.querySelectorAll(".cu-nav-link, .cu-nav-group > summary").length'), 14);
    assert.equal(await evaluate('document.querySelector(".cu-subnav [aria-current]").textContent'), 'Item masterlist');
    const sidebarColor = await evaluate('getComputedStyle(document.querySelector(".cu-sidebar")).backgroundColor');
    assert.equal(sidebarColor, 'rgb(16, 47, 68)');
    await screenshot('desktop');
    await evaluate('document.querySelector(".cu-inventory-table").scrollIntoView({block:"center"})');
    await screenshot('desktop-table');
    await evaluate('document.querySelector("[data-inventory-row] td").click()');
    assert(await evaluate('document.querySelector("#inventory-preview-dialog").open'));
    assert.equal(await evaluate('document.querySelector("#inventory-preview-title").textContent'), 'Item Details');
    await key('Escape');
    await evaluate('document.querySelector("[data-inventory-row]").focus()'); await key('Enter');
    assert(await evaluate('document.querySelector("#inventory-preview-dialog").open'), 'Enter activates row'); await key('Escape');
    await evaluate('document.querySelector("[data-item-editor=edit]").click()');
    assert(await evaluate('document.querySelector("#item-editor-dialog").open && document.querySelector("#item-editor-title").textContent === "Edit Item"'), 'Edit is not swallowed by row click');
    await key('Escape');
    await evaluate('document.querySelector("[data-preview-action=history]").click()');
    assert(await evaluate('!document.querySelector("[data-preview-history]").hidden'), 'Stock history opens separately'); await key('Escape');
    await evaluate('document.querySelector("[data-item-editor=new]").click()');
    assert(await evaluate('document.querySelector("#item-editor-dialog").open')); await key('Escape');
    await evaluate('document.querySelector(".cu-inventory-pagination [rel=next]").click()');
    await pause(200); await ready();
    assert(await evaluate('location.search.includes("page=2")'));
    assert.equal(await evaluate('document.querySelector(".cu-inventory-pagination [aria-current]").textContent'), '2');
    await evaluate('document.querySelector("#inventory-search").value="gloves";document.querySelector("#inventory-filters").requestSubmit()');
    await pause(200); await ready();
    assert.equal(await evaluate('document.querySelectorAll("[data-inventory-row]").length'),1);
    assert.equal(await evaluate('document.querySelector("[data-inventory-summary=total]").textContent'),'1');
    assert(await evaluate('!location.search.includes("page=2")'), 'Search resets pagination');
    await evaluate('document.querySelector("#inventory-search").value="";document.querySelector("#filter-stock_status").value="INVALID";document.querySelector("#inventory-filters").requestSubmit()');
    await pause(200); await ready();
    assert.equal(await evaluate('document.querySelectorAll("[data-inventory-row]").length'),1);
    assert.equal(await evaluate('document.querySelector(".cu-inventory-table .cu-badge").textContent'),'INVALID');
    await evaluate('document.querySelector("#filter-stock_status").value="";document.querySelector("#inventory-search").value="no matching record";document.querySelector("#inventory-filters").requestSubmit()');
    await pause(200); await ready();
    assert.equal(await evaluate('document.querySelectorAll("[data-inventory-row]").length'),0);
    assert.equal(await evaluate('document.querySelector(".cu-empty h3").textContent'),'No matching items');
    await evaluate('document.querySelector(".cu-empty a").click()'); await pause(200); await ready();
    await evaluate('document.querySelector("#inventory-per-page").value="25";document.querySelector("#inventory-per-page").dispatchEvent(new Event("change"))');
    await pause(200); await ready();
    assert.equal(await evaluate('document.querySelectorAll("[data-inventory-row]").length'),25);
    for (const width of [1440,1024,768,390,320]) {
      await viewport(width,width<768?844:1000);
      assert(await evaluate('document.documentElement.scrollWidth <= innerWidth'),'No page overflow at '+width);
      if(width<768) assert.equal(await evaluate('getComputedStyle(document.querySelector(".cu-inventory-summary")).gridTemplateColumns.split(" ").length'),2);
    }
    await viewport(390,844); await evaluate('scrollTo(0,0)'); await screenshot('mobile');
    await evaluate('document.querySelector("#cu-nav-toggle").click()');
    assert(await evaluate('document.body.classList.contains("cu-nav-open")')); await key('Escape');
    await evaluate('document.querySelector(".cu-inventory-table").scrollIntoView();document.querySelector(".cu-table-wrap").scrollLeft=2000');
    await screenshot('mobile-actions');
    await evaluate('document.querySelector("[data-item-editor=edit]").click()');
    assert(await evaluate('(() => {const r=document.querySelector("#item-editor-dialog").getBoundingClientRect();return r.left>=0 && r.right<=innerWidth;})()'));
    await key('Escape');
    assert.deepEqual(exceptions,[],'No browser exceptions');
    console.log('Passed: inventory layout, shared navigation, row click/keyboard, action isolation, add preview, search, filtering, pagination, page size, empty state, five responsive widths.');
    console.log('Screenshots: '+artifacts);
    await call('Browser.close');
  } finally { socket?.close(); browser.kill(); for(const waiter of pending.values()) clearTimeout(waiter.timer); }
}
main().catch(error=>{console.error(error);process.exitCode=1;});
