// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const fixtures=['users','settings','settings-pending'].map(name=>({name,file:path.join(__dirname,'.account-'+name+'-review.html')}));
  for(const fixture of fixtures)fs.writeFileSync(fixture.file,execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'render-account-fixture.php'),fixture.name]));
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-theme-'));
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
    async function unreadableDarkText(scope='body',includeLight=false) {
      return evaluate(`(()=>{
        const luminance=c=>c.match(/[\\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4}).reduce((s,v,i)=>s+v*[.2126,.7152,.0722][i],0);
        return [...document.querySelectorAll(${JSON.stringify(scope+' *')})].filter(e=>e.getClientRects().length&&[...e.childNodes].some(n=>n.nodeType===3&&n.textContent.trim())).flatMap(e=>{
          const css=getComputedStyle(e);if(css.visibility==='hidden')return [];
          let parent=e,bg;do{bg=getComputedStyle(parent).backgroundColor;parent=parent.parentElement;}while(parent&&bg==='rgba(0, 0, 0, 0)');
          if(!bg.startsWith('rgb(')||(!${includeLight}&&luminance(bg)>.1))return [];
          const a=luminance(css.color),b=luminance(bg),ratio=(Math.max(a,b)+.05)/(Math.min(a,b)+.05);
          return ratio>=4.5?[]:[{tag:e.tagName,cl:e.className,text:e.textContent.trim().slice(0,50),color:css.color,bg,ratio}];
        });
      })()`);
    }
    await call('Page.enable'); await call('Runtime.enable'); await call('Emulation.setFocusEmulationEnabled',{enabled:true}); await viewport(1440);
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/reports.php' });
    await ready();









    const base='http://localhost/Nursing_System/CSSR_INVENTORY/design-system/';
    async function go(page){await call('Page.navigate',{url:base+page});await pause(300);await ready();}
    await evaluate('localStorage.removeItem("jvgh.cssr.theme");localStorage.removeItem("jvgh.theme")');await go('dashboard.php');
    assert.equal(await evaluate('document.documentElement.dataset.cuTheme'),'light','Default is light');
    const geometry=await evaluate('[...document.querySelectorAll(".cu-workspace,.cu-card")].map(e=>e.getBoundingClientRect().toJSON())');
    const lightChart=await evaluate('document.querySelector("#cu-issuance-chart text").getAttribute("fill")');
    const totals=await evaluate('[...document.querySelectorAll("[data-chart-total]")].map(e=>e.dataset.chartTotal)');
    await evaluate('NursingTheme.set(NursingTheme.get()==="dark"?"light":"dark")');await pause(250);
    assert.equal(await evaluate('localStorage.getItem("jvgh.cssr.theme")'),'dark','Persist selection');
    assert(await evaluate('!document.querySelector(".cu-theme-desktop,.cu-theme-mobile")'),"CSSR theme switches removed");
    assert.deepEqual(await evaluate('[...document.querySelectorAll(".cu-workspace,.cu-card")].map(e=>e.getBoundingClientRect().toJSON())'),geometry,'Theme does not change workspace geometry');
    assert.notEqual(await evaluate('document.querySelector("#cu-issuance-chart text").getAttribute("fill")'),lightChart,'SVG labels redraw');
    assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-chart-total]")].map(e=>e.dataset.chartTotal)'),totals,'Chart data preserved');
    await screenshot('dashboard-dark');
    for(const page of ['dashboard.php','inventory.php','inbound.php','storage_room_1.php','cssr_stock.php','requisitions.php','reports.php','mobile-legacy.php','consistency.php?screen=suppliers','consistency.php?screen=users','../tests/.account-users-review.html','../tests/.account-settings-review.html','../tests/.account-settings-pending-review.html']){
      console.log('Theme check '+page);await viewport(1440);await go(page);await pause(250);
      assert.equal(await evaluate('document.documentElement.dataset.cuTheme'),'dark','Theme retained across pages');
      assert.equal(await evaluate('getComputedStyle(document.body).backgroundColor'),'rgb(16, 28, 41)','Dark canvas '+page);
      const readability=await evaluate(`(()=>{const selectors=['.cu-card','.page-panel','.cu-topbar','.cu-dropdown-panel','input:not([type=hidden]):not([type=checkbox])','select','.cu-table th','.table th'];return selectors.map(s=>{const e=document.querySelector(s);if(!e)return null;const c=getComputedStyle(e);return {s,bg:c.backgroundColor,color:c.color}}).filter(Boolean)})()`);
      assert(readability.every(e=>e.bg!=='rgb(255, 255, 255)'),JSON.stringify(readability));
      const lightSurfaces=await evaluate('[...document.querySelectorAll(".cu-workspace *,body>.user-edit-popup *")].filter(e=>e.getClientRects().length&&getComputedStyle(e).backgroundColor==="rgb(255, 255, 255)").map(e=>e.className).filter((v,i,a)=>a.indexOf(v)===i)');assert.deepEqual(lightSurfaces,[],'No white panels '+page);
      const darkText=await evaluate('[...document.querySelectorAll(".cu-workspace *")].filter(e=>e.getClientRects().length&&[...e.childNodes].some(n=>n.nodeType===3&&n.textContent.trim())&&getComputedStyle(e).color.match(/\\d+/g).slice(0,3).map(Number).reduce((a,b)=>a+b)<260).map(e=>({tag:e.tagName,cl:e.className,parent:e.parentElement.className,text:e.textContent.slice(0,40)}))');if(darkText.length)console.log('  Dim text '+JSON.stringify(darkText));
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Desktop fits '+page);
      assert.deepEqual(await unreadableDarkText(),[],'Readable dark text '+page);
      if(page.includes('.account-settings')){
        assert.deepEqual(await unreadableDarkText('.forgot-password-settings',true),[],'Forgot Password contrast including background '+page);
        await evaluate('document.querySelector(".forgot-password-settings").scrollIntoView({behavior:"instant",block:"center"})');
        await screenshot(page.includes('pending')?'forgot-password-pending-dark':'forgot-password-dark');
      }
      if(page==='reports.php')await screenshot('reports-dark');
      await viewport(320,844);assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Mobile fits '+page);
      if(page.includes('.account-settings'))assert.deepEqual(await unreadableDarkText('.forgot-password-settings',true),[],'Mobile Forgot Password contrast '+page);
    }
    // Shared transaction labels appear throughout receiving, issuance, requests and histories.
    await viewport(1440);await go('mobile-legacy.php');
    // Use the real body-mounted toast function, including the duplicate-cart notice.
    for(const theme of ['light','dark']){
      await evaluate(`document.documentElement.dataset.cuTheme=${JSON.stringify(theme)}`);
      for(const width of [1440,390]){
        await viewport(width);
        await evaluate(`document.querySelectorAll('.app-toast').forEach(e=>e.remove());['info','success','warning','danger','error','notice'].forEach(type=>showToastMessage(type,type==='info'?'Item is already in the cart.':'Sample notification.'))`);
        await pause(250);
        assert.deepEqual(await unreadableDarkText('.toast-stack',true),[],'Toast contrast '+theme+' '+width);
        if(theme==='dark')await screenshot('notifications-dark-'+width);
      }
    }
    await evaluate('document.querySelectorAll(".app-toast").forEach(e=>e.remove())');await viewport(1440);
    const stockSource=fs.readFileSync(path.join(__dirname,'../pages/stock_pos.php'),'utf8');
    const cartCountStyle=stockSource.match(/\.inbound-cart-count\{[^}]+\}/)[0];
    const labelMarkup='<section id="badge-review" class="page-panel"><style>'+cartCountStyle+'</style><div class="pos-panel-title"><div><span class="eyebrow">Transaction Cart</span><h3>Stock Movement</h3></div><span class="pos-count">STOCK OUT</span></div><div class="d-flex gap-2 flex-wrap">'+
      ['STOCK IN','Multi-item','Selected','25 records','5 Pending','Audited','4 cards'].map(label=>'<span class="pos-count">'+label+'</span>').join('')+
      '<button class="btn btn-primary">View Cart <span class="inbound-cart-count">3</span></button><span class="status-normal">Available</span><span class="status-low">Low Stock</span><span class="status-out">Out of Stock</span><span class="badge bg-warning">Near Expiry</span><span class="badge request-status pending">Pending</span></div></section>';
    await evaluate(`document.querySelector('.cu-workspace').innerHTML=${JSON.stringify(labelMarkup)}`);
    for(const theme of ['light','dark']){
      await evaluate(`document.documentElement.dataset.cuTheme=${JSON.stringify(theme)}`);
      for(const width of [1440,390]){
        await viewport(width);assert.deepEqual(await unreadableDarkText('#badge-review',true),[],'All badge backgrounds have readable text: '+theme+' '+width);
      }
    }
    await viewport(1440);await screenshot('transaction-labels-dark');
    // Reproduce the actual expiry-removal modal with sample data; never submit a transaction.
    await viewport(1440);await go('mobile-legacy.php');
    const expirySource=fs.readFileSync(path.join(__dirname,'../pages/expiry_monitoring.php'),'utf8');
    const expiryStart=expirySource.indexOf('<div class="app-modal" id="expiryRemovalModal"');
    assert(expiryStart>=0,'Expiry modal found');
    const expiryMarkup=expirySource.slice(expiryStart,expirySource.indexOf('<script>',expiryStart)).replace(/<\?[\s\S]*?\?>/g,'');
    await evaluate(`(()=>{
      document.body.insertAdjacentHTML('beforeend',${JSON.stringify(expiryMarkup)});
      const modal=document.querySelector('#expiryRemovalModal');modal.classList.add('open');modal.setAttribute('aria-hidden','false');
      const data={expiryRemovalItem:'JP DRAIN 100ML',expiryRemovalLot:'CY24F06',expiryRemovalDate:'2027-05-01',expiryRemovalLocation:'STORAGE ROOM 1',expiryRemovalQty:'20'};
      for(const [id,value] of Object.entries(data))document.getElementById(id).textContent=value;
      document.querySelector('#expiryRemovalForm').addEventListener('submit',e=>e.preventDefault());
    })()`);
    for(const width of [1440,390]){
      await viewport(width);assert.deepEqual(await unreadableDarkText('#expiryRemovalModal'),[],'Expiry modal readable at '+width);
      assert.equal(await evaluate('getComputedStyle(document.querySelector(".expiry-summary-item strong")).color'),'rgb(229, 237, 245)','Readable lot values');
      assert.equal(await evaluate('getComputedStyle(document.querySelector(".expiry-summary-item span")).color'),'rgb(175, 192, 209)','Readable secondary labels');
      assert(await evaluate('getComputedStyle(document.querySelector("#expiryRemovalModal .alert-warning strong")).color===getComputedStyle(document.querySelector("#expiryRemovalModal .alert-warning")).color'),'Nested warning retains amber');
      await screenshot('expiry-modal-dark-'+width);
    }
    await viewport(1440);await evaluate('NursingTheme.set(NursingTheme.get()==="dark"?"light":"dark")');await pause(250);
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".expiry-summary-item strong")).color'),'rgb(18, 52, 77)','Original light-mode text restored');
    await evaluate('NursingTheme.set(NursingTheme.get()==="dark"?"light":"dark")');await pause(250);
    assert.deepEqual(await unreadableDarkText('#expiryRemovalModal'),[],'Open modal updates when switching back to dark');
    await viewport(320,844);
    for(const [page,open] of [['inbound.php?new=1',''],['storage_room_1.php','document.querySelector("[data-storage-transfer]").click()'],['cssr_stock.php?view=1',''],['requisitions.php?view=1','']]){
      await go(page);if(open)await evaluate(open);await pause(100);
      assert.equal(await evaluate('getComputedStyle(document.querySelector("dialog[open]")).backgroundColor'),'rgb(26, 43, 60)','Dark transaction dialog '+page);
      assert.deepEqual(await unreadableDarkText('dialog[open]'),[],'Readable dialog text '+page);
      const bright=await evaluate('[...document.querySelectorAll("dialog[open] *")].filter(e=>e.getClientRects().length).filter(e=>{let c=getComputedStyle(e).backgroundColor;if(c.startsWith("rgba"))return false;return c.match(/\\d+/g).slice(0,3).map(Number).reduce((a,b)=>a+b)>680}).map(e=>e.className)');assert.deepEqual(bright,[],'No light surfaces inside '+page);await key('Escape');
    }
    await go('inventory.php');await evaluate('document.querySelector(".cu-page-heading>.cu-btn").click()');await pause(100);
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".cu-item-editor")).backgroundColor'),'rgb(26, 43, 60)','Dark mobile sheet');
    assert.notEqual(await evaluate('getComputedStyle(document.querySelector(".cu-item-editor .cu-btn:disabled")).backgroundColor'),'rgb(8, 127, 131)','Disabled save distinct');
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".cu-item-editor .cu-btn-secondary")).backgroundColor'),'rgb(34, 54, 74)','Secondary action distinct');
    await screenshot('item-dark-mobile');await key('Escape');
    await evaluate('document.querySelector("#cu-nav-toggle").click()');await pause(100);
    assert(await evaluate('!document.querySelector(".cu-theme-desktop,.cu-theme-mobile")'),"CSSR mobile switch removed");
    await evaluate('NursingTheme.set(NursingTheme.get()==="dark"?"light":"dark")');await pause(250);
    assert.equal(await evaluate('document.documentElement.dataset.cuTheme'),'light','Drawer theme toggle');await key('Escape');
    await call('Page.reload');await pause(300);await ready();assert.equal(await evaluate('document.documentElement.dataset.cuTheme'),'light','Light persists on reload');
    await viewport(1440);await go('mobile-legacy.php');const canvasLight=await evaluate('document.querySelector("canvas").toDataURL()');await evaluate('NursingTheme.set(NursingTheme.get()==="dark"?"light":"dark")');await pause(250);
    assert.notEqual(await evaluate('document.querySelector("canvas").toDataURL()'),canvasLight,'Canvas labels redraw');
    await call('Emulation.setEmulatedMedia',{media:'print'});
    assert(await evaluate('!document.querySelector(".cu-theme-desktop,.cu-theme-mobile")'),"No duplicate print control");
    assert.notEqual(await evaluate('getComputedStyle(document.body).backgroundColor'),'rgb(16, 28, 41)','Print retains light presentation');
    await call('Emulation.setEmulatedMedia',{media:'',features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await evaluate('NursingTheme.set(NursingTheme.get()==="dark"?"light":"dark")');assert(!await evaluate('document.documentElement.classList.contains("cu-theme-changing")'),'Reduced motion respected');
    // Exercise the production ledger markup with sample text, without executing its database queries.
    for(const ledger of ['stock_card','bin_card']){
      await go('mobile-legacy.php');await viewport(1440);
      const source=fs.readFileSync(path.join(__dirname,'../pages/'+ledger+'.php'),'utf8');
      const start=source.indexOf('<div class="bin-card-sheet');
      assert(start>=0,'Ledger template found');
      const markup=source.slice(start,source.indexOf('</section>',start)).replace(/<\?php[\s\S]*?\?>/g,'').replace(/<\?=[\s\S]*?\?>/g,'Sample');
      await evaluate(`document.querySelector('.cu-workspace').innerHTML=${JSON.stringify('<section class="bin-workspace">'+markup+'</section>')}`);
      const colors=()=>evaluate(`(()=>{
        const sheet=document.querySelector('.bin-card-sheet');
        const selectors=['.bin-card-header','.facility-copy span','.facility-copy strong','.facility-copy small','.meta-label','.meta-value','.bin-card-table th','.bin-card-table td','.bin-footer-note','.bin-footer-code strong','.bin-footer-code th'];
        return selectors.map(selector=>{const el=sheet.querySelector(selector),css=getComputedStyle(el);let parent=el,bg;
          do{bg=getComputedStyle(parent).backgroundColor;parent=parent.parentElement;}while(parent&&bg==='rgba(0, 0, 0, 0)');
          return {selector,color:css.color,bg};});
      })()`);
      const contrast=entry=>{
        const luminance=value=>value.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4}).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
        const a=luminance(entry.color),b=luminance(entry.bg);return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
      };
      await evaluate('document.documentElement.dataset.cuTheme="light"');
      const light=await colors();
      await call('Emulation.setEmulatedMedia',{media:'print'});const lightPrint=await colors();
      await call('Emulation.setEmulatedMedia',{media:'screen'});
      await evaluate('document.documentElement.dataset.cuTheme="dark"');
      const dark=await colors();
      assert.equal(await evaluate('getComputedStyle(document.querySelector(".bin-card-sheet")).backgroundColor'),'rgb(255, 255, 255)',ledger+' stays white in dark mode');
      assert.equal(await evaluate('getComputedStyle(document.querySelector(".bin-card-table tbody tr:nth-child(even)")).backgroundColor'),'rgb(255, 255, 255)',ledger+' blank rows stay white');
      assert.equal(await evaluate('getComputedStyle(document.body).backgroundColor'),'rgb(16, 28, 41)','Surrounding workspace stays dark');
      for(const entry of dark){assert(contrast(entry)>=4.5,ledger+' contrast '+JSON.stringify(entry));}
      await evaluate('document.querySelector(".bin-card-sheet").scrollIntoView({behavior:"instant",block:"start"})');await pause(100);await screenshot(ledger+'-dark');
      await call('Emulation.setEmulatedMedia',{media:'print'});assert.deepEqual(await colors(),lightPrint,ledger+' print colors unaffected by dark mode');
      await call('Emulation.setEmulatedMedia',{media:'screen'});
      await evaluate('document.documentElement.dataset.cuTheme="light"');assert.deepEqual(await colors(),light,ledger+' light colors restored');
    }
    await call('Page.addScriptToEvaluateOnNewDocument',{source:'Storage.prototype.getItem=function(){throw Error("blocked")};Storage.prototype.setItem=function(){throw Error("blocked")};'});
    await go('dashboard.php');assert.equal(await evaluate('document.documentElement.dataset.cuTheme'),'light','Storage failure falls back to light');await evaluate('NursingTheme.set(NursingTheme.get()==="dark"?"light":"dark")');assert.equal(await evaluate('document.documentElement.dataset.cuTheme'),'dark','Toggle works without storage');
    assert.deepEqual(exceptions,[],'No browser exceptions');
    console.log('Passed: default light, persistence, unchanged geometry, all screen families, actual account templates, SVG/canvas chart redraw, shared theme controller and mobile sheets, print, reduced motion and blocked storage.');console.log('Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);for(const fixture of fixtures)fs.rmSync(fixture.file,{force:true});}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
