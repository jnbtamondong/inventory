// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const fixtures=['users','settings'].map(name=>({name,file:path.join(__dirname,'.account-'+name+'-review.html')}));
  for(const fixture of fixtures)fs.writeFileSync(fixture.file,execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'render-account-fixture.php'),fixture.name]));
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-consistency-'));
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
    async function go(page){await call('Page.navigate',{url:base+page});await pause(250);await ready();}
    await call('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await go('inventory.php');
    const reference=await evaluate(`(()=>{const styles=s=>{let e=document.querySelector(s),c=getComputedStyle(e);return {font:c.fontFamily,size:c.fontSize,bg:c.backgroundColor,radius:c.borderRadius}};return {sidebar:styles('.cu-sidebar'),header:styles('.cu-topbar'),button:styles('.cu-page-heading>.cu-btn'),field:styles('#inventory-filters .cu-input'),card:styles('.cu-card'),table:styles('.cu-table th'),nav:document.querySelector('.cu-sidebar').innerHTML}})()`);
    for(const screen of ['suppliers','departments','history','alerts','pricing','adjustments','backup','chat','users','settings']){
      console.log('Checking '+screen);await viewport(1440);await go('consistency.php?screen='+screen);
      const actual=await evaluate(`(()=>{const styles=s=>{let e=document.querySelector(s),c=getComputedStyle(e);return {font:c.fontFamily,size:c.fontSize,bg:c.backgroundColor,radius:c.borderRadius}};return {sidebar:styles('.cu-sidebar'),header:styles('.cu-topbar'),button:styles('.module-actions .btn-primary'),field:styles('#review-search'),card:styles('.page-panel'),table:styles('.table th')}})()`);
      assert.deepEqual(actual.sidebar,reference.sidebar,'Same sidebar '+screen);
      assert.deepEqual(actual.header,reference.header,'Same header '+screen);
      assert.deepEqual(actual.button,reference.button,'Same button '+screen);
      assert.deepEqual(actual.field,reference.field,'Same fields '+screen);
      assert.equal(actual.card.radius,reference.card.radius,'Same card radius '+screen);
      assert.equal(actual.table.font,reference.table.font,'Same table font '+screen);
      assert.equal(actual.table.size,reference.table.size,'Same table text '+screen);
      assert.equal(actual.table.bg,reference.table.bg,'Same table background '+screen);
      const badges=await evaluate('[...document.querySelectorAll("#consistency-table .badge")].map(e=>e.dataset.cuStatus)');
      assert.deepEqual(badges,['success','warning','danger','warning','teal','info','success','danger','invalid'],'Semantic statuses '+screen);
      if(screen==='suppliers'||screen==='users')await screenshot(screen+'-desktop');
      for(const width of [390,320]){
        await viewport(width,844);await pause(100);
        assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'No overflow '+screen+' '+width);
        assert(await evaluate('document.querySelector("#consistency-table").classList.contains("cm-card-table")'),'Card table '+screen);
      }
      await evaluate('document.querySelector("[data-modal-open]").click()');await pause(100);
      assert(await evaluate('document.querySelector(".app-modal-card").getBoundingClientRect().height===innerHeight'),'Full mobile sheet '+screen);
      await key('Escape');
      if(screen==='suppliers')await screenshot('suppliers-mobile');
    }
    for(const fixture of fixtures){
      console.log('Checking actual '+fixture.name+' template');
      await viewport(1440);await go('../tests/.account-'+fixture.name+'-review.html');
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Actual account desktop fits');await screenshot('actual-'+fixture.name+'-desktop');
      await viewport(390,844);
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Actual account mobile fits');
      if(fixture.name==='users'){
        await evaluate('document.querySelector("#open-create-user").click()');await pause(100);
        assert(await evaluate('document.querySelector(".create-user-dialog").getBoundingClientRect().height===innerHeight'),'Create account full screen');await key('Escape');
        await evaluate('document.querySelector("[data-edit-user]").click()');await pause(100);
        assert(await evaluate('document.querySelector(".user-edit-popup").getBoundingClientRect().height===innerHeight'),'Detached editor full screen');
        const fields=await evaluate('[...document.querySelectorAll(".user-edit-popup input,.user-edit-popup select")].filter(e=>e.type!=="hidden").map(e=>({id:e.id,size:getComputedStyle(e).fontSize}))');assert(fields.every(e=>parseFloat(e.size)>=16),'Detached editor fields legible '+JSON.stringify(fields));await screenshot('actual-user-editor-mobile');await key('Escape');
      }else{
        await evaluate('document.querySelector("#new-password").value="SampleUnsavedChange";document.querySelector("#cancel-account-settings").click()');await pause(100);
        await screenshot('actual-settings-mobile');assert(await evaluate('document.querySelector("#account-cancel-modal .account-confirm-card").getBoundingClientRect().height===innerHeight'),'Settings confirmation full screen '+JSON.stringify(await evaluate('(()=>{const e=document.querySelector("#account-cancel-modal .account-confirm-card");return {rect:e.getBoundingClientRect().toJSON(),height:innerHeight,parent:e.parentElement.className,main:!!e.closest(".main-content")}})()')));
      }
    }
    await viewport(1440);await go('mobile-legacy.php');await screenshot('returns-desktop');
    const links=await evaluate('[...document.querySelectorAll(".cu-sidebar a")].map(a=>a.getAttribute("href"))');
    for(const route of ['storage_requests.php','stock_adjustment.php','bin_card.php','price_list.php','suppliers.php','departments.php','chat.php','backup_restore.php'])assert(links.some(h=>h.endsWith(route)),'Preserved navigation '+route);
    await go('consistency.php?embed=1');assert(await evaluate('getComputedStyle(document.querySelector(".cu-sidebar")).display==="none"'),'Embedded content hides application shell');
    await call('Emulation.setEmulatedMedia',{media:'print'});assert(await evaluate('getComputedStyle(document.querySelector(".cu-sidebar")).display==="none"'),'Print hides shell');
    assert.deepEqual(exceptions,[],'No browser exceptions');
    console.log('Passed: consistent sidebar/header/fonts/buttons/fields/tables/cards/status colors across ten page families; mobile sheets/cards; embedded and print views; existing tool links.');console.log('Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);for(const fixture of fixtures)fs.rmSync(fixture.file,{force:true});}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
