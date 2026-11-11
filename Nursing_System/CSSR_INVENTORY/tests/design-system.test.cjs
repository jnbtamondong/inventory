// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
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
    await ready();
    assert.equal(await evaluate('document.querySelectorAll(".cu-nav-link, .cu-nav-group > summary").length'), 13);
    assert(await evaluate('document.querySelector("[data-cu-clock]").textContent.includes("PHT")'));
    assert(await evaluate('getComputedStyle(document.querySelector(".cu-sidebar")).width === "248px"'));
    await screenshot('desktop');
    await evaluate('document.querySelector("#cu-nav-toggle").click()');
    assert(await evaluate('document.body.classList.contains("cu-collapsed")'));
    await call('Page.reload'); await ready();
    assert(await evaluate('document.body.classList.contains("cu-collapsed")'), 'Collapse preference survives reload');
    await evaluate('document.querySelector(".cu-nav-group > summary").click()');
    assert(await evaluate('!document.body.classList.contains("cu-collapsed") && document.querySelector(".cu-nav-group").open'), 'Group opens from rail');
    await evaluate('document.querySelector("[data-cu-dialog=cu-example-dialog]").focus(); document.querySelector("[data-cu-dialog=cu-example-dialog]").click()');
    assert(await evaluate('document.querySelector("#cu-example-dialog").open && document.activeElement.id === "dialog-name"'));
    await key('Tab', 'Tab', 1);
    assert(await evaluate('document.querySelector("#cu-example-dialog").contains(document.activeElement)'), 'Modal keeps focus');
    await key('Escape');
    assert(await evaluate('!document.querySelector("#cu-example-dialog").open && document.activeElement.hasAttribute("data-cu-dialog")'), 'Escape returns focus');
    await evaluate('document.querySelector("[data-cu-dialog=cu-example-dialog]").click(); document.querySelector("#cu-example-dialog form").requestSubmit()');
    assert(await evaluate('document.querySelector("#cu-example-dialog").open'), 'Required field blocks submit');
    await evaluate('document.querySelector("#dialog-name").value="Gauze"; document.querySelector("#cu-example-dialog form").requestSubmit()');
    assert(await evaluate('!document.querySelector("#cu-example-dialog").open && !document.querySelector("#cu-toast").hidden'));
    assert(await evaluate('!document.querySelector(".cu-topbar [role=search]") && !document.querySelector("#cu-search-input")'), 'Header search removed');
    await evaluate('document.querySelector("#cu-toast").hidden=true');
    for (const width of [1440, 1024, 768, 390, 320]) {
      await viewport(width, width < 768 ? 844 : 1000);
      const dimensions = await evaluate('({scroll:document.documentElement.scrollWidth, viewport:innerWidth})');
      assert(dimensions.scroll <= dimensions.viewport, `Page overflows at ${width}: ${JSON.stringify(dimensions)}`);
    }
    await viewport(390, 844); await evaluate('scrollTo(0,0)'); await screenshot('mobile');
    assert(await evaluate('document.querySelector("#cu-sidebar").inert'), 'Hidden mobile navigation is inert');
    await evaluate('document.querySelector("#cu-nav-toggle").click()');
    assert(await evaluate('document.body.classList.contains("cu-nav-open") && document.querySelector(".cu-main").inert'));
    await pause(300);await evaluate('document.querySelector("#cu-sidebar .cu-theme-mobile").focus()'); await key('Tab');
    assert(await evaluate('document.querySelector("#cu-sidebar").contains(document.activeElement)'), 'Drawer traps focus: '+JSON.stringify(await evaluate('({active:document.activeElement.outerHTML.slice(0,160),inert:document.querySelector("#cu-sidebar").inert,classes:document.body.className})')));
    await screenshot('mobile-navigation'); await key('Escape');
    assert(await evaluate('!document.querySelector(".cu-main").inert && document.activeElement.id === "cu-nav-toggle"'));
    await evaluate('document.querySelector("[data-cu-dialog=cu-example-dialog]").click()');
    const bounds = await evaluate('(() => {const r=document.querySelector("#cu-example-dialog").getBoundingClientRect();return {left:r.left,right:r.right,bottom:r.bottom,top:r.top};})()');
    assert(bounds.left >= 0 && bounds.right <= 390 && bounds.top >= 0 && bounds.bottom <= 844, 'Mobile dialog fits');
    await screenshot('mobile-modal'); await key('Escape');
    await evaluate('document.querySelector(".cu-profile summary").click()'); await pause(50);
    assert(await evaluate('document.querySelector(".cu-profile").open'));
    await key('Escape'); assert(await evaluate('!document.querySelector(".cu-profile").open'));
    await call('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".cu-btn")).transitionDuration'), '0s');
    assert.deepEqual(exceptions, [], 'No browser exceptions');
    console.log('Passed: 13 reference modules, clock, navigation persistence/groups, modal focus and validation, header search removed, five viewport widths, mobile drawer, dropdown, reduced motion, no browser exceptions.');
    console.log('Screenshots: ' + artifacts);
    await call('Browser.close');
  } finally { socket?.close(); browser.kill(); for (const waiter of pending.values()) clearTimeout(waiter.timer); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
