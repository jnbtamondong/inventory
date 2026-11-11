// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-motion-'));
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
    async function go(page){await call('Page.navigate',{url:base+page});await pause(100);await ready();}
    await call('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'no-preference'}]});
    await go('inventory.php');
    assert(await evaluate('!!window.cssrMotion'),'Shared motion API');
    assert(await evaluate('getComputedStyle(document.querySelector(".cu-sidebar")).transitionDuration.includes("0.18s")'),'Fast sidebar transition');
    const before=await evaluate('document.querySelector(".cu-sidebar").getBoundingClientRect().width');
    await evaluate('document.querySelector("#cu-nav-toggle").click()');await pause(60);
    const middle=await evaluate('document.querySelector(".cu-sidebar").getBoundingClientRect().width');
    assert(middle<before&&middle>80,'Sidebar animates between widths');await pause(200);
    assert.equal(await evaluate('document.querySelector(".cu-sidebar").getBoundingClientRect().width'),80);
    await evaluate('document.querySelector("#cu-nav-toggle").click()');await pause(200);
    await evaluate('document.querySelector("[data-item-editor=new]").click()');
    assert(await evaluate('document.querySelector(".cu-item-editor").open'),'Modal opens immediately');
    await pause(170);
    await evaluate('document.querySelector(".cu-item-editor").close()');
    assert(await evaluate('!document.querySelector(".cu-item-editor").open'),'Close is immediate for business logic');
    await pause(170);
    assert(await evaluate('getComputedStyle(document.querySelector(".cu-item-editor")).display==="none"'),'Modal finishes close transition');
    await evaluate('window.cssrUI.toast("Saved successfully")');
    assert(await evaluate('document.querySelector("#cu-toast").getAttribute("role")==="status"'),'Toast remains announced');
    await pause(220);assert(await evaluate('getComputedStyle(document.querySelector("#cu-toast")).opacity==="1"'),'Toast settles fully visible');
    await evaluate('document.querySelector("#cu-toast").hidden=true');await pause(150);
    assert(await evaluate('getComputedStyle(document.querySelector("#cu-toast")).display==="none"'),'Toast exits cleanly');
    await evaluate('document.querySelector(".cu-profile summary").click()');await pause(30);
    assert(await evaluate('document.querySelector(".cu-profile").open'),'Dropdown opens immediately');await key('Escape');
    await evaluate('document.querySelector("[data-item-editor=new]").click()');await pause(150);
    const beforeValue=await evaluate('document.querySelector("[name=item_name]").value');
    await evaluate('window.cssrMotion.busy(document.querySelector(".cu-item-editor form"),true,"Saving item…")');
    assert.equal(await evaluate('document.querySelectorAll(".cx-loading").length'),0,'No flashing skeleton for quick requests');
    await pause(230);
    assert.equal(await evaluate('document.querySelectorAll(".cx-loading .cx-skeleton-line").length'),2,'Slow operation shows skeleton');
    assert.equal(await evaluate('document.querySelector("[name=item_name]").value'),beforeValue,'Skeleton does not replace fields');
    await screenshot('loading-skeleton');
    await evaluate('window.cssrMotion.busy(document.querySelector(".cu-item-editor form"),false)');
    assert.equal(await evaluate('document.querySelectorAll(".cx-loading").length'),0,'Skeleton removed after completion');
    await evaluate('window.cssrMotion.busy(document.querySelector(".cu-item-editor form"),true);window.cssrMotion.busy(document.querySelector(".cu-item-editor form"),false)');await pause(230);
    assert.equal(await evaluate('document.querySelectorAll(".cx-loading").length'),0,'Completed quick request never shows skeleton');
    await key('Escape');
    await viewport(390,844);await go('reports.php');
    const exact=await evaluate('document.querySelector(".rp-totals .cu-stat-value").textContent');
    await evaluate('document.querySelector(".rp-totals").scrollIntoView({block:"center"})');await pause(50);
    assert.equal(await evaluate('document.querySelector(".rp-totals .cu-stat-value").textContent'),exact,'Counter preserves exact server value');
    assert(await evaluate('!!document.querySelector(".cx-counter-overlay[aria-hidden=true]")'),'Only aria-hidden overlay counts');
    await pause(310);assert.equal(await evaluate('document.querySelectorAll(".cx-counter-shell").length'),0,'Counter cleans up and shows exact value');
    await evaluate('document.querySelector(".rp-chart").scrollIntoView({block:"center"})');await pause(30);
    assert(await evaluate('document.querySelector(".rp-chart").getAnimations().some(a=>a.effect.getTiming().duration===220)'),'Chart entrance animation');
    await pause(260);
    assert.equal(await evaluate('document.querySelector(".rp-chart").getAnimations().length'),0,'Chart does not loop');
    await call('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await go('dashboard.php');await pause(80);
    assert.equal(await evaluate('document.querySelectorAll(".cx-counter-overlay").length'),0,'Reduced motion skips counters');
    assert(await evaluate('getComputedStyle(document.querySelector(".cu-sidebar")).transitionDuration==="0s"'),'Reduced motion disables sidebar tween');
    assert.equal(await evaluate('document.getAnimations().filter(a=>a.playState==="running").length'),0,'Reduced motion has no running decoration');
    await go('inventory.php');await evaluate('document.querySelector("[data-item-editor=new]").click()');
    await evaluate('window.cssrMotion.busy(document.querySelector(".cu-item-editor form"),true)');await pause(220);
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".cx-skeleton-line"),"::after").animationName'),'none','Reduced-motion skeleton stays static');
    await evaluate('window.cssrMotion.busy(document.querySelector(".cu-item-editor form"),false)');await key('Escape');
    // Real item-editor busy/error path using an isolated slow response, never the hospital endpoint.
    await call('Page.addScriptToEvaluateOnNewDocument',{source:`
      const original=JSON.parse;JSON.parse=function(text,...args){const value=original.call(this,text,...args);if(value&&value.preview===true&&Array.isArray(value.items))value.preview=false;return value};
      window.fetch=async()=>{await new Promise(r=>setTimeout(r,650));return new Response(JSON.stringify({ok:false,errors:{_form:"Sample connection error. Entries preserved."}}),{status:422,headers:{"Content-Type":"application/json"}})};
    `});
    await go('inventory.php');await evaluate('document.querySelector("[data-item-editor=edit]").click()');
    const itemName=await evaluate('document.querySelector("[name=item_name]").value');
    await evaluate('document.querySelector("#item-editor-save").click()');await pause(240);
    assert(await evaluate('document.querySelector(".cu-item-editor form").getAttribute("aria-busy")==="true"&&!!document.querySelector(".cx-loading")'),'Actual save pending shows skeleton');
    assert(await evaluate('document.querySelector("#item-editor-save").disabled'),'Pending cannot double-submit');
    await pause(500);
    assert.equal(await evaluate('document.querySelectorAll(".cx-loading").length'),0,'Error removes loading skeleton');
    assert.equal(await evaluate('document.querySelector("[name=item_name]").value'),itemName,'Error preserves entered data');
    assert(await evaluate('!document.querySelector("#item-editor-error").hidden'),'Error remains visible');
    await call('Emulation.setEmulatedMedia',{media:'print',features:[{name:'prefers-reduced-motion',value:'no-preference'}]});
    assert(await evaluate('getComputedStyle(document.querySelector(".cu-workspace")).animationName==="none"'),'Print disables entrance motion');
    await call('Emulation.setEmulatedMedia',{media:'',features:[{name:'prefers-reduced-motion',value:'no-preference'}]});
    await call('Page.navigate',{url:base+'mobile-legacy.php'});
    for(let i=0;i<80;i++){if(await evaluate('document.readyState==="complete"&&!!window.cssrMotion'))break;await pause(100);}await pause(150);
    await evaluate('document.querySelector("#sample-chart").scrollIntoView({block:"center",behavior:"instant"})');await pause(30);
    assert(await evaluate('document.querySelector("#sample-chart").getAnimations().some(a=>a.effect.getTiming().duration===220)'),'Legacy canvas has short entrance '+JSON.stringify(await evaluate('({ready:document.readyState,motion:!!window.cssrMotion,reduced:matchMedia("(prefers-reduced-motion: reduce)").matches,rect:document.querySelector("#sample-chart").getBoundingClientRect().toJSON(),animations:document.querySelector("#sample-chart").getAnimations().map(a=>a.effect.getTiming())})')));await pause(240);
    await evaluate('startLoading("Saving sample transaction…")');await pause(230);
    assert(await evaluate('!!document.querySelector("#loadingOverlay .cx-loading")'),'Actual legacy loading hook shows skeleton');
    assert(await evaluate('document.querySelector("#loadingOverlay .cx-skeleton-line").getBoundingClientRect().width>100'),'Legacy loading skeleton is visible');
    await evaluate('document.querySelector("#loadingOverlay").classList.remove("open")');await pause(20);
    assert.equal(await evaluate('document.querySelectorAll(".cx-loading").length'),0,'Legacy loading cleanup');
    await call('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});await pause(40);
    assert.equal(await evaluate('document.querySelector("#sample-chart").getAnimations().length'),0,'Legacy chart has no continuing animation');
    assert.deepEqual(exceptions,[],'No browser exceptions');
    console.log('Passed: sidebar transition, immediate modal logic, modal/toast/dropdown effects, delayed skeletons, real save/error cleanup, exact accessible counters, one-time chart entrance, reduced motion, mobile sheets and print safety.');
    console.log('Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
