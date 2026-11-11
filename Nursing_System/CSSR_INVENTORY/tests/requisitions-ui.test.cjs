// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-requisitions-'));
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
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/requisitions.php' });
    await ready();




    async function go(query='') {
      await call('Page.navigate',{url:'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/requisitions.php'+query});
      await pause(150);await ready();
    }
    assert.equal(await evaluate('document.querySelector("h1").textContent'),'Requisitions');
    assert.equal(await evaluate('document.querySelectorAll(".rq-tabs a").length'),6);
    assert.equal(await evaluate('document.querySelectorAll("[data-rq-row]").length'),10);
    assert.equal(await evaluate('document.querySelector(".cu-sidebar a[aria-current=page]").textContent.trim()'),'Requisitions');
    await screenshot('desktop-list');
    await evaluate(`document.querySelector('[data-rq-row="1"] td:nth-child(2)').click()`);
    await pause(250);await ready();
    assert(await evaluate('document.querySelector("#rq-detail").open'),'Clicking row opens detail');
    assert.equal(await evaluate('document.querySelectorAll(".rq-item-table tbody tr").length'),3);
    await screenshot('desktop-detail');
    await evaluate('document.querySelector("[data-rq-action=approve]").click()');
    assert(await evaluate('document.querySelector("#rq-confirm").open'),'Approval confirmation');
    assert(await evaluate('document.activeElement.textContent.trim()==="Cancel"'),'Safe initial confirmation focus');
    await key('Escape');
    assert(await evaluate('!document.querySelector("#rq-confirm").open && document.querySelector("#rq-detail").open'),'Escape closes only confirmation: '+JSON.stringify(await evaluate('({confirm:document.querySelector("#rq-confirm").open,detail:document.querySelector("#rq-detail").open})')));
    await evaluate('document.querySelector(".rq-quantity").value=20;document.querySelector("[data-rq-action=partial]").click()');
    assert(await evaluate('document.querySelector("#rq-confirm").open'),'Partial approval confirmation');
    await key('Escape');
    await evaluate('document.querySelector(".rq-quantity").value=0;document.querySelectorAll(".rq-quantity").forEach(input=>input.value=0);document.querySelector("[data-rq-action=partial]").click()');
    assert(await evaluate('!document.querySelector("#rq-action-error").hidden && !document.querySelector("#rq-confirm").open'),'Cannot approve zero quantities');
    await evaluate('document.querySelector("#rq-remarks").value="";document.querySelector("[data-rq-action=reject]").click()');
    assert(await evaluate('!document.querySelector("#rq-confirm").open'),'Reason required for rejection');
    await evaluate('document.querySelector("#rq-remarks").value="Duplicate request";document.querySelector("[data-rq-action=reject]").click()');
    assert(await evaluate('document.querySelector("#rq-confirm").open'),'Reject confirmation');
    await screenshot('reject-confirmation');
    await evaluate('document.querySelector("#rq-confirm-submit").click()');
    await pause(100); // Native dialog close events restore focus asynchronously.
    assert(await evaluate('!document.querySelector("#rq-detail").open && !document.querySelector("#cu-toast").hidden'),'Preview confirmation toast');
    assert(await evaluate('document.activeElement.classList.contains("rq-number")'),'Close restores row focus');
    await go('?view=2');
    assert(await evaluate('!!document.querySelector("[data-rq-action=release]")'),'Ready requests can release');
    await evaluate('document.querySelector("[data-rq-action=release]").click()');
    assert(await evaluate('document.querySelector("#rq-confirm-message").textContent.includes("deduct stock")'),'Release warns about stock deduction');
    await key('Escape');
    await evaluate('window.print=()=>window.printCalled=true;document.querySelector("#rq-print").click()');
    assert(await evaluate('window.printCalled'),'Print button');
    await call('Emulation.setEmulatedMedia',{media:'print'});
    assert(await evaluate('getComputedStyle(document.querySelector(".cu-sidebar")).display==="none"'),'Print hides sidebar');
    await screenshot('print-details');
    await call('Emulation.setEmulatedMedia',{media:''});
    await go('?view=3');
    assert(await evaluate('!document.querySelector("[data-rq-action=release]")'),'Unreserved approval cannot release');
    await go('?status=Rejected');
    assert.equal(await evaluate('document.querySelectorAll("[data-rq-row]").length'),2);
    assert(await evaluate('[...document.querySelectorAll(".rq-table .cu-badge")].every(b=>b.textContent==="Rejected")'),'Status filter');
    await go('?q=0099');
    assert.equal(await evaluate('document.querySelectorAll("[data-rq-row]").length'),1);
    await go('?q=no-match');
    assert(await evaluate('!!document.querySelector(".cu-empty")'),'Empty state');
    for (const width of [1440,1024,768,390,320]) {
      await viewport(width,width<768?844:1000);await go();
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'No page overflow at '+width);
      await go('?view=1');
      const bounds=await evaluate('(()=>{const r=document.querySelector("#rq-detail").getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:innerWidth,height:innerHeight}})()');
      assert(bounds.left>=0 && bounds.right<=bounds.width && bounds.top===0 && bounds.bottom<=bounds.height,'Detail fits '+width);
      assert(await evaluate('document.querySelector(".rq-detail-foot").getBoundingClientRect().bottom<=innerHeight'),'Actions reachable at '+width);
      await key('Tab'); assert(await evaluate('document.querySelector("#rq-detail").contains(document.activeElement)'),'Detail contains keyboard focus');
      if(width===390) {await screenshot('mobile-detail');await key('Escape');await screenshot('mobile-list');}
      else await key('Escape');
    }
    assert.deepEqual(exceptions,[],'No browser errors');
    console.log('Passed: tabs, filters, row details, approval/rejection/release confirmations, partial validation, keyboard focus, printing, preview toast, and responsive bounds at 1440/1024/768/390/320.');
    console.log('Screenshots: '+artifacts);await call('Browser.close');
  } finally {socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
