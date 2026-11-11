// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'nursing-workflow-'));
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

    await call('Page.enable');await call('Runtime.enable');
    const base='http://localhost/Nursing_System/CSSR_INVENTORY/design-system/';
    async function go(page){await call('Page.navigate',{url:base+page});await ready();for(let i=0;i<40;i++){if(await evaluate('!!window.nursingWorkflowUI'))return;await pause(100);}throw Error('Shared filter script missing');}
    let checks=0;
    for(const width of [320,390,768,1440]) for(const theme of ['light','dark']){
      await viewport(width,900);await go('inventory.php');
      await evaluate('document.documentElement.dataset.cuTheme='+JSON.stringify(theme));
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Inventory overflow');checks++;
      const hidden=await evaluate('getComputedStyle(document.querySelector("[data-ns-filter-extra]")).display === "none"');
      assert.equal(hidden,width<768,'Responsive filter visibility');checks++;
      assert(await evaluate('document.querySelector("#inventory-search").getBoundingClientRect().height>0'),'Search stays visible');checks++;
      if(width<768){
        assert(await evaluate('document.querySelector(".ns-filter-toggle").getBoundingClientRect().height>=44'),'Touch target');
        await evaluate('document.querySelector(".ns-filter-toggle").click()');
        assert(await evaluate('getComputedStyle(document.querySelector("[data-ns-filter-extra]")).display!=="none"'),'Expand filters');checks++;
        await evaluate('(()=>{const select=document.querySelector("#filter-classification");select.selectedIndex=1;select.dispatchEvent(new Event("input",{bubbles:true}));document.querySelector(".ns-filter-toggle").click();})()');
        assert(await evaluate('new FormData(document.querySelector("#inventory-filters")).get("classification")===document.querySelector("#filter-classification").value'),'Hidden values retained for submit');checks++;
        assert(await evaluate('document.querySelector(".ns-filter-toggle").textContent.includes("1 active")'),'Active-filter count');checks++;
        if(width===390&&theme==='dark')await screenshot('inventory-filters-dark-390');
      }
    }
    await viewport(390);await go('reports.php');
    assert(await evaluate('[...document.querySelectorAll("#rp-filters input[type=date]")].every(x=>x.getBoundingClientRect().height>0)'),'Report date range stays visible');checks++;
    for(const page of ['requisitions.php','storage_room_1.php','cssr_stock.php']){
      await go(page);assert(await evaluate('!!document.querySelector(".ns-filter-toggle")'),'Filter control '+page);checks++;
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'No mobile overflow '+page);checks++;
    }
    await go('requisitions.php?view=1');
    const workflow=await evaluate('document.querySelector(".ns-workflow")?.textContent||""');
    assert(workflow.includes('Next:'),'Request detail stage guidance');checks++;
    await call('Emulation.setEmulatedMedia',{media:'print'});
    assert(await evaluate('getComputedStyle(document.querySelector(".ns-workflow")).display==="none"'),'Workflow excluded from print');checks++;
    await call('Emulation.setEmulatedMedia',{media:''});
    await screenshot('request-progress-390');

    // Templates are fulfilled in the browser only; application authentication is not bypassed.
    const {execFileSync}=require('node:child_process');
    const root=path.resolve(__dirname,'../..');
    const fixtures=new Map();
    for(const [route,file] of [['pages/__admin_ui_preview','admin-ui-fixture.php'],['PROCUREMENT/pages/__proc_ui_preview','procurement-ui-fixture.php']]){
      fixtures.set('http://localhost/Nursing_System/'+route,execFileSync('C:/xampp/php/php.exe',['-n',path.join(root,'tests',file)],{encoding:'utf8'}));
    }
    socket.addEventListener('message',async event=>{const message=JSON.parse(event.data);if(message.method!=='Fetch.requestPaused')return;try{const html=fixtures.get(message.params.request.url);await call('Fetch.fulfillRequest',{requestId:message.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(html).toString('base64')});}catch(error){exceptions.push(error.message);}});
    await call('Fetch.enable',{patterns:[{urlPattern:'*__admin_ui_preview'},{urlPattern:'*__proc_ui_preview'}]});
    for(const width of [320,390,1440])for(const url of fixtures.keys()){
      await viewport(width);await call('Page.navigate',{url});await pause(600);
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'Cross-module overflow '+url+' '+width);checks++;
      assert(await evaluate('getComputedStyle(document.body).fontFamily.includes("Segoe UI")'),'Consistent Nursing/Procurement font');checks++;
      if(width===390)await screenshot(url.includes('PROCUREMENT')?'procurement-390':'nursing-390');
    }
    await call('Fetch.disable');
    assert.deepEqual(exceptions,[],'No browser exceptions');
    console.log('Passed '+checks+' workflow/filter checks. No hospital records changed. Screenshots: '+artifacts);
    await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
