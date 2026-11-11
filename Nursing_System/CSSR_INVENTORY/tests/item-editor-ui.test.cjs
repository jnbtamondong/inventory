// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-item-editor-'));
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
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/inventory.php' });
    await ready();



    await evaluate('document.querySelector("[data-item-editor=new]").click()');
    assert(await evaluate('document.querySelector("#item-editor-dialog").open'));
    assert.equal(await evaluate('document.querySelector("#item-editor-title").textContent'),'Add New Item');
    assert(await evaluate('document.querySelector("#item-editor-save").disabled'));
    assert(await evaluate('document.querySelector("[name=item_code]").readOnly'));
    await screenshot('desktop-empty');
    await evaluate('document.querySelector("#item-editor-item_name").focus();document.querySelector("#item-editor-classification").focus()');
    assert(await evaluate('!document.querySelector("#item-error-item_name").hidden'),'Required error appears below field after blur: '+JSON.stringify(await evaluate('({active:document.activeElement.id,required:document.querySelector("#item-editor-item_name").required,value:document.querySelector("#item-editor-item_name").value,error:document.querySelector("#item-error-item_name").textContent})'))+' '+JSON.stringify(exceptions));
    await evaluate('(() => {const f=document.querySelector("#item-editor-form");for(const [key,value] of Object.entries({item_name:"New sterile dressing",classification:"MEDICAL SUPPLIES",unit:"Box",minimum_stock:"12",storage_location:"STORAGE ROOM 1",procurement_type:"Emergency Purchase",item_description:"Sterile dressing, individually packed."})){f.elements[key].value=value;f.elements[key].dispatchEvent(new Event("input"));}})()');
    assert(await evaluate('!document.querySelector("#item-editor-save").disabled'),'Complete required values enable Save');
    await evaluate('const a=document.querySelector("[name=auto_code]");a.checked=false;a.dispatchEvent(new Event("change"));');
    assert(await evaluate('document.querySelector("#item-editor-save").disabled'),'Manual code is required');
    await evaluate('const c=document.querySelector("#item-editor-item_code");c.value="CSSR-0001";c.dispatchEvent(new Event("input"));c.dispatchEvent(new Event("blur"));');
    assert(await evaluate('!document.querySelector("#item-error-item_code").hidden && document.querySelector("#item-editor-save").disabled'),'Duplicate visible code flagged');
    await evaluate('const c2=document.querySelector("#item-editor-item_code");c2.value="CUSTOM-NEW";c2.dispatchEvent(new Event("input"));');
    assert(await evaluate('!document.querySelector("#item-editor-save").disabled'));
    await evaluate('const n=document.querySelector("#item-editor-minimum_stock");n.value="-1";n.dispatchEvent(new Event("input"));n.dispatchEvent(new Event("blur"));');
    assert(await evaluate('!document.querySelector("#item-error-minimum_stock").hidden && document.querySelector("#item-editor-save").disabled'));
    await screenshot('desktop-validation');
    await evaluate('const n2=document.querySelector("#item-editor-minimum_stock");n2.value="12";n2.dispatchEvent(new Event("input"));document.querySelector("#item-editor-form").requestSubmit()');
    assert(await evaluate('!document.querySelector("#item-editor-dialog").open && !document.querySelector("#cu-toast").hidden'),'Successful preview save closes and shows toast');
    await evaluate('document.querySelector("[data-item-editor=edit]").click()');
    assert.equal(await evaluate('document.querySelector("#item-editor-title").textContent'),'Edit Item');
    assert.equal(await evaluate('document.querySelector("#item-editor-item_name").value'),'Alcohol 70%');
    assert.equal(await evaluate('document.querySelector("#item-editor-procurement_type").value'),'Procured');
    assert(await evaluate('!document.querySelector("[name=auto_code]").checked && !document.querySelector("#item-editor-save").disabled'));
    await screenshot('desktop-edit');
    await key('Tab'); assert(await evaluate('document.querySelector("#item-editor-dialog").contains(document.activeElement)'));
    await key('Escape'); assert(await evaluate('document.activeElement.hasAttribute("data-item-editor")'),'Focus returns to Edit');
    for(const width of [1024,768,390,320]) {
      await viewport(width,width<768?844:1000);
      await evaluate('document.querySelector("[data-item-editor=new]").click()');
      const rect=await evaluate('(() => {const r=document.querySelector("#item-editor-dialog").getBoundingClientRect();return {left:r.left,top:r.top,width:r.width,height:r.height,viewport:innerWidth};})()');
      assert(rect.left>=0 && rect.left+rect.width<=rect.viewport,'Modal fits '+width);
      if(width<768) { assert.equal(rect.top,0); assert.equal(rect.left,0); assert.equal(rect.width,width); assert.equal(rect.height,844); }
      assert(await evaluate('document.querySelector("#item-editor-save").getBoundingClientRect().bottom<=innerHeight'),'Footer remains reachable');
      await key('Escape');
    }
    await viewport(390,844);await evaluate('document.querySelector("[data-item-editor=new]").click()');
    await screenshot('mobile-sheet');
    await evaluate('document.querySelector(".cu-item-editor-body").scrollTop=10000');
    await screenshot('mobile-sheet-bottom'); await key('Escape');
    // Exercise server-error presentation without contacting the authenticated endpoint.
    await call('Page.addScriptToEvaluateOnNewDocument',{source:`
      const parse=JSON.parse;JSON.parse=function(text,...args){const value=parse.call(this,text,...args);if(value && value.preview===true && Array.isArray(value.items))value.preview=false;return value;};
      window.fetch=async()=>new Response(JSON.stringify({ok:false,errors:{item_code:"This item code is already in use."}}),{status:422,headers:{"Content-Type":"application/json"}});
    `});
    await call('Page.reload');await pause(200);await ready();
    await evaluate('document.querySelector("[data-item-editor=edit]").click();document.querySelector("#item-editor-form").requestSubmit()');
    await pause(100);
    assert(await evaluate('document.querySelector("#item-editor-dialog").open && !document.querySelector("#item-error-item_code").hidden'),'Server validation stays in modal: '+JSON.stringify(await evaluate('({open:document.querySelector("#item-editor-dialog").open,preview:JSON.parse(document.querySelector("#item-editor-data").textContent).preview,error:document.querySelector("#item-editor-error").textContent,fieldError:document.querySelector("#item-error-item_code").textContent,toast:document.querySelector("#cu-toast").textContent})'))+' '+JSON.stringify(exceptions));
    assert.equal(await evaluate('document.querySelector("#item-editor-item_name").value'),'Alcohol 70%','Input retained on server error');
    assert.deepEqual(exceptions,[],'No browser exceptions');
    console.log('Passed: Add/Edit opening, prefilled values, auto/manual codes, required and duplicate validation, disabled save, success toast, server errors, focus, desktop bounds, full-screen mobile sheet and fixed footer.');
    console.log('Screenshots: '+artifacts);await call('Browser.close');
  } finally { socket?.close(); browser.kill(); for(const waiter of pending.values()) clearTimeout(waiter.timer); }
}
main().catch(error=>{console.error(error);process.exitCode=1;});
