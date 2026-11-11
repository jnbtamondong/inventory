// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-dashboard-'));
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
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/dashboard.php' });
    await ready();

    for (let i=0; i<50; i++) {
      if (await evaluate('document.querySelector("#cu-stock-chart")?.dataset.chartTotal !== undefined')) break;
      await pause(100);
    }
    assert.equal(await evaluate('document.querySelectorAll("[data-kpi]").length'), 4);
    assert.equal(await evaluate('document.querySelectorAll(".cu-nav-link, .cu-nav-group > summary").length'), 14);
    assert(await evaluate('Number(document.querySelector("#cu-stock-chart").dataset.chartTotal) === Number(document.querySelector("[data-kpi=total]").textContent.replaceAll(",",""))'));
    assert.equal(await evaluate('document.querySelectorAll("#cu-issuance-chart [data-month]").length'), 12);
    const firstTotal = await evaluate('document.querySelector("#cu-issuance-chart").dataset.chartTotal');
    await evaluate('document.querySelector("#issuance-year").selectedIndex=1; document.querySelector("#issuance-year").dispatchEvent(new Event("change"))');
    await pause(250); await ready();
    assert(await evaluate('location.search.includes("year=")'));
    assert.notEqual(await evaluate('document.querySelector("#cu-issuance-chart").dataset.chartTotal'), firstTotal, 'Year selector changes monthly values');
    await screenshot('desktop');
    await evaluate('document.querySelector(".cu-dashboard-tables").scrollIntoView()');
    await screenshot('desktop-tables');
    await evaluate('scrollTo(0,0)');
    for (const width of [1440,1024,768,390,320]) {
      await viewport(width, width < 768 ? 844 : 1000);
      const sizes = await evaluate('({page:document.documentElement.scrollWidth,view:innerWidth})');
      assert(sizes.page <= sizes.view, 'No page overflow at ' + width);
      assert(await evaluate('document.querySelector("#cu-issuance-chart").getBoundingClientRect().width > 100'), 'Chart renders at ' + width);
      if (width < 768) {
        assert.equal(await evaluate('getComputedStyle(document.querySelector(".cu-dashboard-kpis")).gridTemplateColumns.split(" ").length'), 2);
        assert.equal(await evaluate('getComputedStyle(document.querySelector(".cu-dashboard-charts")).gridTemplateColumns.split(" ").length'), 1);
      }
    }
    await viewport(390,844); await screenshot('mobile');
    await evaluate('document.querySelector(".cu-dashboard-charts").scrollIntoView()');
    await screenshot('mobile-charts');
    await evaluate('document.querySelector("[data-cu-dialog]").focus();document.querySelector("[data-cu-dialog]").click()');
    assert(await evaluate('document.getElementById(document.querySelector("[data-cu-dialog]").dataset.cuDialog).open'));
    await key('Escape'); assert(await evaluate('!document.getElementById(document.querySelector("[data-cu-dialog]").dataset.cuDialog).open'));
    await evaluate('document.querySelector("#cu-nav-toggle").click()');
    assert(await evaluate('document.body.classList.contains("cu-nav-open") && document.querySelector(".cu-main").inert'));
    await key('Escape');
    await call('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await call('Page.reload'); await pause(250); await ready();
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".cu-btn")).transitionDuration'), '0s');
    // Zero totals must show a neutral donut and truthful zero values.
    await evaluate('document.querySelector("#cu-dashboard-data").textContent=JSON.stringify({monthly:Array(12).fill(0),stock:[0,0,0]}); new Promise(resolve=>{const s=document.createElement("script");s.src="../assets/js/dashboard.js?empty-check";s.onload=resolve;document.body.append(s);})');
    assert.equal(await evaluate('document.querySelector("#cu-stock-chart").dataset.empty'), 'true');
    assert.equal(await evaluate('document.querySelectorAll("#cu-stock-chart [data-stock-value]").length'), 0);
    assert.deepEqual(exceptions, [], 'No browser exceptions');
    console.log('Passed: dashboard KPIs, chart totals, working year selection, sample request modal, five viewport widths, 2-column mobile KPIs, stacked charts, shared navigation, reduced motion, empty charts.');
    console.log('Screenshots: ' + artifacts);
    await call('Browser.close');
  } finally { socket?.close(); browser.kill(); for(const waiter of pending.values()) clearTimeout(waiter.timer); }
}
main().catch(error=>{console.error(error);process.exitCode=1;});
