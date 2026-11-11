// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-mobile-'));
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










    const base='http://localhost/Nursing_System/CSSR_INVENTORY/design-system/';
    async function go(page){await call('Page.navigate',{url:base+page});await ready();await pause(180);}
    const widths=[1920,1366,1280,1024,768,430,414,390,375,360];
    const modules=['dashboard.php','inventory.php','inbound.php','storage_room_1.php','cssr_stock.php','requisitions.php','reports.php','mobile-legacy.php'];
    let layouts=0;
    for(const page of modules){
      await go(page);
      for(const width of widths)for(const theme of ['light','dark']){
        await viewport(width,900);await evaluate('document.documentElement.dataset.cuTheme='+JSON.stringify(theme));await pause(70);
        assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),page+' overflows '+width+' '+theme);
        assert.equal(await evaluate('document.querySelector(".cm-bottom-nav")'),null,'Removed mobile navigation stays removed');
        if(width<768)assert(await evaluate('document.querySelector("#cu-nav-toggle").getBoundingClientRect().width>=44'),'Navigation touch target');
        if(width>=768)assert(await evaluate('(()=>{const bell=document.querySelector(".cu-notification-trigger").getBoundingClientRect(),profile=document.querySelector(".cu-profile").getBoundingClientRect();return profile.left-bell.right<=24})()'),'Header clock, bell, and profile stay together');
        if(width===390&&theme==='dark')await screenshot(page.replace('.php','')+'-mobile-dark');
        layouts++;
      }
      console.log('Responsive matrix passed: '+page);
    }
    await viewport(390,844);await go('inventory.php');
    assert.equal(await evaluate('document.querySelectorAll("#cu-nav-toggle rect").length'),4,'Mobile menu grid icon');
    await viewport(1366,900);
    await evaluate('document.querySelector(".cu-sidebar [data-cu-close-nav]").click()');
    await pause(350);
    assert(await evaluate('document.body.classList.contains("cu-collapsed") && document.querySelector("#cu-nav-toggle").getAttribute("aria-label")==="Expand navigation"'),'Desktop collapse state and label');
    assert(await evaluate('!document.querySelector(".cu-sidebar").inert && getComputedStyle(document.querySelector(".cu-sidebar")).visibility==="visible" && document.querySelector(".cu-sidebar").getBoundingClientRect().width===80 && document.querySelector(".cu-main").getBoundingClientRect().left===80'),'Collapsed desktop sidebar keeps the icon strip');
    assert(await evaluate('getComputedStyle(document.querySelector("#cu-nav-toggle")).display==="none"'),'Top-bar toggle hidden on desktop');
    await screenshot('desktop-sidebar-icon-strip');
    assert(await evaluate('getComputedStyle(document.querySelector(".cu-sidebar [data-cu-close-nav]")).display==="none"'),'Collapsed sidebar hides the expand button');
    await evaluate('document.querySelector(".cu-brand").click()');
    assert(await evaluate('!document.body.classList.contains("cu-collapsed")'),'Logo expands navigation');
    await viewport(390,844);
    await evaluate('document.querySelector("#cu-nav-toggle").click()');await pause(100);
    assert(await evaluate('document.body.classList.contains("cu-nav-open")&&document.querySelector(".cu-main").inert'),'Hamburger opens accessible drawer');
    await key('Escape');assert(await evaluate('document.activeElement.id==="cu-nav-toggle"'),'Drawer restores hamburger focus');
    await evaluate('document.querySelector("[data-item-editor=new]").click()');await pause(200);
    assert(await evaluate('document.querySelector(".cu-item-editor").getBoundingClientRect().height===innerHeight'),'Item editor is full screen');
    assert(await evaluate('document.querySelector(".cu-item-editor-footer").getBoundingClientRect().bottom<=innerHeight'),'Save actions visible');await key('Escape');
    await go('inbound.php?new=1');await pause(200);assert(await evaluate('document.querySelector("#in-editor").open'),'Inbound URL still opens editor');await key('Escape');
    await go('storage_room_1.php');await evaluate('document.querySelector("[data-storage-transfer]").click()');await pause(200);assert(await evaluate('document.querySelector("#st-transfer").getBoundingClientRect().height===innerHeight'),'Transfer sheet fits');await key('Escape');
    await go('mobile-legacy.php');
    assert(await evaluate('document.querySelector(".table.cm-card-table")!==null'),'Simple tables become mobile cards');
    assert(await evaluate('document.querySelector("#sample-merged").parentElement.classList.contains("cm-table-scroll")'),'Merged tables retain local scrolling');
    await go('reports.php');await call('Emulation.setEmulatedMedia',{media:'print'});
    assert(await evaluate('getComputedStyle(document.querySelector(".rp-table")).display==="table"'),'Print retains table layout');
    await call('Emulation.setEmulatedMedia',{media:''});
    assert.deepEqual(exceptions,[],'No browser exceptions');
    console.log('Passed '+layouts+' desktop/mobile/theme layouts, drawer focus, sheets, responsive tables and print layout.');
    console.log('Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
