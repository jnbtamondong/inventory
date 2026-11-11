// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-inbound-'));
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
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/inbound.php' });
    await ready();





    async function go(query='') {await call('Page.navigate',{url:'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/inbound.php'+query});await pause(150);await ready();}
    async function fill() {await evaluate(`(()=>{
      const f=document.querySelector('#in-form');f.elements.supplier.value='Sample Medical Trading';f.elements.procurement_type.value='REGULAR';
      const rows=document.querySelectorAll('.in-line');
      rows.forEach((row,i)=>{row.querySelector('[data-field=item_id]').value=String(i+1);row.querySelector('[data-field=quantity]').value=i?'20':'100';row.querySelector('[data-field=unit_cost]').value=i?'220.00':'45.50';row.querySelector('[data-field=lot_number]').value=i?'GL-TEST':'GZ-TEST';row.querySelector('[data-field=expiry_date]').value='2099-12-31';});
      f.dispatchEvent(new Event('input',{bubbles:true}));
    })()`);}
    assert.equal(await evaluate('document.querySelector("h1").textContent'),'Inbound Supplies');
    assert.equal(await evaluate('document.querySelectorAll(".in-table tbody tr").length'),10);
    assert.equal(await evaluate('document.querySelector(".cu-nav-link[aria-current]").getAttribute("aria-label")'),'Inbound');
    await screenshot('desktop-list');
    await evaluate('document.querySelector("[data-in-new]").click()');
    assert(await evaluate('document.querySelector("#in-editor").open'),'New form opens');
    assert.equal(await evaluate('document.querySelector("[name=storage_location]").value'),'STORAGE ROOM 1');
    await evaluate('document.querySelector("#in-complete").click()');
    assert(await evaluate('!document.querySelector("#in-confirm").open && document.querySelectorAll("[aria-invalid=true]").length>0'),'Inline required validation');
    await evaluate('document.querySelector("#in-add-line").click()');await fill();
    assert.equal(await evaluate('document.querySelector("#in-total-items").textContent'),'2');
    assert.equal(await evaluate('document.querySelector("#in-total-quantity").textContent'),'120');
    assert(await evaluate('document.querySelector("#in-total-value").textContent.includes("8,950.00")'),'Live total value');
    await screenshot('desktop-form');
    await evaluate('document.querySelector("#in-complete").click()');
    assert(await evaluate('document.querySelector("#in-confirm").open'),'Complete confirmation');
    assert(await evaluate('document.querySelector("#in-confirm-text").textContent.includes("Storage Room 1")'),'Destination reviewed');
    await key('Escape');
    assert(await evaluate('document.querySelector("#in-editor").open && !document.querySelector("#in-confirm").open'),'Escape closes top dialog only');
    await evaluate('document.querySelector("#in-complete").click();document.querySelector("#in-confirm-submit").click()');
    assert(await evaluate('!document.querySelector("#in-editor").open && !document.querySelector("#cu-toast").hidden'),'Preview completion');
    await evaluate('document.querySelector("[data-in-new]").click();document.querySelector("#in-save-draft").click()');
    assert(await evaluate('!document.querySelector("#in-editor").open'),'Incomplete draft can save');
    await go('?draft=9');
    assert(await evaluate('document.querySelector("#in-editor").open'),'Draft reopens automatically');
    assert.equal(await evaluate('document.querySelector("#in-field-supplier").value'),'Sample Medical Trading');
    assert.equal(await evaluate('document.querySelectorAll(".in-line").length'),2);
    await evaluate('document.querySelector("[data-remove-line]").click()');
    assert.equal(await evaluate('document.querySelectorAll(".in-line").length'),1);
    await key('Escape');
    assert(await evaluate('document.querySelector("#in-confirm").open && document.querySelector("#in-confirm-title").textContent.includes("Discard")'),'Unsaved edits protected');
    await evaluate('document.querySelector("#in-confirm-submit").click()');
    await go('?procurement_type=DONATION');
    assert.equal(await evaluate('document.querySelectorAll(".in-table tbody tr").length'),6);
    await go('?q=DR-2026-0001');
    assert.equal(await evaluate('document.querySelectorAll(".in-table tbody tr").length'),2);
    await evaluate('document.querySelector(".in-table a").click()');await pause(200);await ready();
    assert(await evaluate('document.querySelector("#in-view").open'),'Receipt details');
    assert.equal(await evaluate('document.querySelectorAll("#in-view tbody tr").length'),2);
    await key('Escape');
    await go('?q=no-match');assert(await evaluate('!!document.querySelector(".cu-empty")'),'Empty state');
    for(const width of [1440,1024,768,390,320]){
      await viewport(width,width<768?844:1000);await go();
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'List fits '+width);
      await evaluate('document.querySelector("[data-in-new]").click();document.querySelector("#in-add-line").click()');await fill();
      const bounds=await evaluate('(()=>{const r=document.querySelector("#in-editor").getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,height:r.height}})()');
      assert(bounds.left>=0&&bounds.right<=width,'Modal width '+width);
      assert(await evaluate('document.querySelector(".in-editor-foot").getBoundingClientRect().bottom<=innerHeight'),'Footer reachable '+width);
      assert(await evaluate('document.querySelector("#in-form").scrollWidth<=document.querySelector("#in-form").clientWidth'),'Form does not scroll horizontally '+width);
      if(width<768){assert.equal(bounds.top,0);assert.equal(bounds.height,844);}
      if(width===390){await screenshot('mobile-form');await evaluate('document.querySelector("#in-form").scrollTop=10000');await screenshot('mobile-items');}
      await key('Tab');assert(await evaluate('document.querySelector("#in-editor").contains(document.activeElement)'),'Focus stays in modal');
      await evaluate('document.querySelector("#in-save-draft").click()');
    }
    // Inject a failing response to verify error handling without touching hospital records.
    await call('Page.addScriptToEvaluateOnNewDocument',{source:`
      const parse=JSON.parse;JSON.parse=function(text,...args){const value=parse.call(this,text,...args);if(value&&value.preview===true&&value.request_key)value.preview=false;return value;};
      window.fetch=async()=>new Response(JSON.stringify({ok:false,errors:{"lines.0.lot_number":"This lot is held for inspection."}}),{status:422,headers:{"Content-Type":"application/json"}});
    `});
    await go();await evaluate('document.querySelector("[data-in-new]").click()');await fill();
    await evaluate('document.querySelector("#in-complete").click();document.querySelector("#in-confirm-submit").click()');await pause(150);
    assert(await evaluate('document.querySelector("#in-editor").open && !document.querySelector("[data-line-error=lot_number]").hidden'),'Server errors stay in form');
    assert.equal(await evaluate('document.querySelector("[data-field=quantity]").value'),'100','Entries preserved');
    await evaluate('document.querySelector("[data-in-close]").click();document.querySelector("#in-confirm-submit").click()');
    assert.deepEqual(exceptions,[],'No browser exceptions');
    console.log('Passed: inbound filters, details, new form, default storage, multi-item totals, incomplete drafts, reopening, validation, confirmation, error preservation, unsaved changes, focus, responsive desktop/tablet/mobile.');
    console.log('Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
