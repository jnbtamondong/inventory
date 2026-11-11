// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-storage-'));
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
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/storage_room_1.php' });
    await ready();






    async function go(query=''){await call('Page.navigate',{url:'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/storage_room_1.php'+query});await pause(150);await ready();}
    async function qty(value){await evaluate(`document.querySelector('#st-quantity').value='${value}';document.querySelector('#st-quantity').dispatchEvent(new Event('input',{bubbles:true}));`);}
    assert.equal(await evaluate('document.querySelector("h1").textContent'),'Storage Room 1');
    assert.equal(await evaluate('document.querySelectorAll(".cu-stats .cu-stat").length'),4);
    assert.equal(await evaluate('document.querySelectorAll(".st-table tbody tr").length'),8);
    assert.equal(await evaluate('document.querySelector(".cu-sidebar a[aria-current=page]").textContent.trim()'),'Item Name');
    assert.deepEqual(await evaluate('[...document.querySelector("#st-location").options].map(o=>o.value)'),['STORAGE ROOM 1','STORAGE ROOM 2','STORAGE ROOM 3','CSSR']);
    await evaluate('const locationSelect=document.querySelector("#st-location");locationSelect.value="STORAGE ROOM 2";locationSelect.dispatchEvent(new Event("change",{bubbles:true}))');await pause(250);await ready();
    assert.equal(await evaluate('document.querySelector("h1").textContent'),'Storage Room 2');
    assert.equal(await evaluate('document.querySelectorAll(".st-table tbody tr").length'),1);
    await evaluate(`document.querySelector('[data-storage-transfer="9"]').click()`);await qty(10);await evaluate('document.querySelector("#st-review").click()');
    assert(await evaluate('document.querySelector("#st-confirm-description").textContent.includes("from Storage Room 2 to CSSR")'),'Selected source in confirmation');await key('Escape');await key('Escape');
    await go('?location=CSSR');assert.equal(await evaluate('document.querySelector("h1").textContent'),'CSSR');
    assert(await evaluate('!document.querySelector("[data-storage-transfer]")'),'No CSSR self-transfer');
    await go('?location=STORAGE%20ROOM%203');assert(await evaluate('document.querySelector(".cu-empty")!==null'),'Empty Room 3');
    await go();
    await screenshot('desktop-stock');
    await evaluate('document.querySelector(".cu-page-heading [data-storage-transfer]").click()');
    assert(await evaluate('document.querySelector("#st-transfer").open'),'Global transfer opens');
    assert(await evaluate('document.querySelector("#st-review").disabled'),'No item selected');
    assert.equal(await evaluate('document.querySelectorAll("#st-lot option").length'),5);
    assert(await evaluate('document.querySelector("#st-destination").readOnly && document.querySelector("#st-destination").value==="CSSR"'),'Fixed destination');
    await key('Escape');
    await evaluate(`document.querySelector('[data-storage-transfer="1"]').click()`);
    assert.equal(await evaluate('document.querySelector("#st-lot").value'),'1');
    assert.equal(await evaluate('document.querySelector("#st-available").value'),'500 Pack');
    await qty(501);
    assert(await evaluate('document.querySelector("#st-review").disabled && !document.querySelector("#st-error-quantity").hidden'),'Cannot exceed stock');
    await qty(-1);assert(await evaluate('document.querySelector("#st-review").disabled'),'Negative blocked');
    await qty('1.5');assert(await evaluate('document.querySelector("#st-review").disabled'),'Fraction blocked');
    await qty(100);assert(await evaluate('!document.querySelector("#st-review").disabled'),'Valid quantity enabled');
    assert(await evaluate('document.querySelector("#st-remaining").textContent.includes("400")'),'Remaining balance preview');
    await screenshot('desktop-transfer');
    await evaluate('document.querySelector("#st-review").click()');
    assert(await evaluate('document.querySelector("#st-confirm").open'),'Confirmation shown');
    assert(await evaluate('document.querySelector("#st-confirm-description").textContent.includes("100 Pack")'),'Confirmation quantity and unit');
    await key('Escape');
    assert(await evaluate('document.querySelector("#st-transfer").open && !document.querySelector("#st-confirm").open'),'Escape closes top only');
    await evaluate('document.querySelector("#st-review").click();document.querySelector("#st-submit").click()');
    assert(await evaluate('!document.querySelector("#st-transfer").open && !document.querySelector("#cu-toast").hidden'),'Success preview feedback');
    assert(await evaluate('document.activeElement.hasAttribute("data-storage-transfer")'),'Focus restored');
    await go('?status=Expired');assert.equal(await evaluate('document.querySelectorAll(".st-table tbody tr").length'),1);
    assert(await evaluate('document.querySelector(".st-table [data-storage-transfer]").disabled'),'Expired stock disabled');
    await go('?q=GZ-2026-B');assert(await evaluate('document.querySelector(".st-table [data-storage-transfer]").disabled'),'Later batch disabled');
    await go('?q=not-found');assert(await evaluate('!!document.querySelector(".cu-empty")'),'Empty state');
    for(const width of [1440,1024,768,390,320]){
      await viewport(width,width<768?844:1000);await go();
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Page fits '+width);
      await evaluate(`document.querySelector('[data-storage-transfer="1"]').click()`);await qty(100);
      const bounds=await evaluate('(()=>{const r=document.querySelector("#st-transfer").getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,height:r.height};})()');
      assert(bounds.left>=0&&bounds.right<=width,'Modal fits '+width);
      assert(await evaluate('document.querySelector("#st-transfer .cu-dialog-foot").getBoundingClientRect().bottom<=innerHeight'),'Footer reachable '+width);
      assert(await evaluate('document.querySelector("#st-form").scrollWidth<=document.querySelector("#st-form").clientWidth'),'No form overflow '+width);
      if(width<768){assert.equal(bounds.top,0);assert.equal(bounds.height,844);}
      if(width===390)await screenshot('mobile-transfer');
      await key('Tab');assert(await evaluate('document.querySelector("#st-transfer").contains(document.activeElement)'),'Keyboard focus contained');
      await key('Escape');
      if(width===390)await screenshot('mobile-stock');
    }
    await call('Page.addScriptToEvaluateOnNewDocument',{source:`
      const parse=JSON.parse;JSON.parse=function(text,...args){const v=parse.call(this,text,...args);if(v&&v.preview===true&&Array.isArray(v.lots))v.preview=false;return v;};
      window.fetch=async()=>new Response(JSON.stringify({ok:false,available:30,errors:{quantity:'Only 30 units are currently available from this batch.'}}),{status:422,headers:{'Content-Type':'application/json'}});
    `});
    await go();await evaluate(`document.querySelector('[data-storage-transfer="1"]').click()`);await qty(100);
    await evaluate('document.querySelector("#st-review").click();document.querySelector("#st-submit").click()');await pause(100);
    assert(await evaluate('document.querySelector("#st-transfer").open && !document.querySelector("#st-error-quantity").hidden'),'Server errors remain in modal');
    assert.equal(await evaluate('document.querySelector("#st-quantity").value'),'100','Quantity preserved');
    assert.equal(await evaluate('document.querySelector("#st-available").value'),'30 Pack','Stale availability refreshed');
    assert(await evaluate('document.querySelector("#st-review").disabled'),'Stale transfer blocked');
    assert.deepEqual(exceptions,[],'No browser exceptions');
    console.log('Passed: storage KPIs, search, status filters, batch selection, quantity limits, fixed destination, confirmations, preview feedback, expired/FEFO restrictions, stale-stock errors, keyboard focus, responsive layouts.');
    console.log('Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
