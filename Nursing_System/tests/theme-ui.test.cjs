// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'nursing-global-theme-'));
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
      if (message.method === 'Runtime.exceptionThrown') exceptions.push(JSON.stringify(message.params.exceptionDetails));
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
      for (let i = 0; i < 80; i++) { if (await evaluate('document.readyState === "complete" && !!window.NursingTheme')) return; await pause(100); }
      throw Error('Reference did not load');
    }
    async function viewport(width, height = 1000) {
      await call('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 }); await pause(300);
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
    await call('Page.enable');await call('Runtime.enable');
    const {execFileSync}=require('node:child_process');
    const base='http://localhost/Nursing_System/';
    const fixtures=new Map();
    for(const name of ['menu','admin','procurement','schedule','employee','dashboard','reports','backup','settings']){
      const url=base+(name==='procurement'?'PROCUREMENT/pages/':'pages/')+'__theme_'+name;
      const html=execFileSync('C:/xampp/php/php.exe',['-n',path.join(__dirname,'theme-ui-fixture.php'),name],{encoding:'utf8'});
      assert(!/Warning:|Fatal error:/.test(html),'Clean fixture '+name);
      fixtures.set(url,html);
    }
    const employee={id:12,name:'Preview Employee',position:'Nurse II',pattern:'8',group:'A',preference:'ANY',active:true};
    const schedule={exists:false,status:'DRAFT',version:0,submitted_by:null,master:[employee],boundaries:[],summary:[],payload:{staff:[employee],assignments:{},requests:[],mode:'MANUAL',rules:{minRest:12,maxConsecutive:6,minOff:1,maxOff:31,offPer7:1,reviewed:true,staffingTargets:{TRIAGE:0,NURSE:0,NA:0},staffingShifts:[],coverage:{}}}};
    socket.addEventListener('message',async event=>{
      const message=JSON.parse(event.data);if(message.method!=='Fetch.requestPaused')return;
      try{
        const req=message.params.request;const api=req.url.includes('/api/schedule_management.php');
        assert(req.method==='GET','Presentation test never submits records');
        const body=api?JSON.stringify({success:true,data:schedule}):fixtures.get(req.url);
        await call('Fetch.fulfillRequest',{requestId:message.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:api?'application/json':'text/html; charset=utf-8'}],body:Buffer.from(body).toString('base64')});
      }catch(error){exceptions.push(error.message);await call('Fetch.failRequest',{requestId:message.params.requestId,errorReason:'Aborted'});}
    });
    await call('Fetch.enable',{patterns:[{urlPattern:'*__theme_*'},{urlPattern:'*/api/schedule_management.php*'}]});
    const menu=base+'pages/__theme_menu';
    async function go(url){await call('Page.navigate',{url});await pause(150);await ready();await pause(200);}
    await viewport(1440);await go(menu);
    assert.equal(await evaluate('NursingTheme.get()'),'light');
    assert.equal(await evaluate('document.querySelectorAll("[data-nursing-theme-toggle]").length'),1);
    let checks=2;
    for(const width of [1440,390,320]){
      await viewport(width);
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'Menu fits '+width);
      assert(await evaluate('document.querySelector("[data-nursing-theme-toggle]").getBoundingClientRect().height>=44'),'Touch target');checks+=2;
      assert(await evaluate('(()=>{const a=document.querySelector("[data-nursing-theme-toggle]").getBoundingClientRect(),b=document.querySelector(".menu-role").getBoundingClientRect();return Math.abs(a.top+a.height/2-b.top-b.height/2)<1})()'),'Switch aligned with role badge '+width);checks++;
    }
    await evaluate('document.querySelector("[data-nursing-theme-toggle]").click()');
    assert.equal(await evaluate('localStorage.getItem("jvgh.theme")'),'dark');checks++;
    assert.equal(await evaluate('document.querySelector("[data-nursing-theme-toggle]").getAttribute("aria-checked")'),'true');checks++;
    assert.equal(await evaluate('document.querySelector("[data-theme-state]").textContent'),'On');checks++;
    const routes=[...fixtures.keys(),base+'CSSR_INVENTORY/design-system/dashboard.php',base+'CSSR_INVENTORY/design-system/inventory.php'];
    for(const theme of ['dark','light']){
      if(theme==='light'){await go(menu);await evaluate('document.querySelector("[data-nursing-theme-toggle]").click()');}
      for(const url of routes){
        await go(url);
        assert.equal(await evaluate('NursingTheme.get()'),theme,'Shared preference '+url);checks++;
        assert.equal(await evaluate('document.documentElement.dataset.cuTheme'),theme);checks++;
        assert.equal(await evaluate('document.querySelectorAll(".cu-theme-desktop,.cu-theme-mobile").length'),0);checks++;
        if(await evaluate('!!document.querySelector(".navbar,.proc-top,.ed-head,.cu-topbar,.standalone-user-header,.backup-header")')){
          assert.equal(await evaluate('document.querySelectorAll(".ns-account-menu").length'),1,'One shared account menu '+url);checks++;
          await evaluate('document.querySelector(".ns-account-menu>summary").click()');
          assert.deepEqual(await evaluate('[...document.querySelectorAll(".ns-account-menu .ns-account-panel>a,.ns-account-menu .cu-dropdown-panel>a")].map(a=>a.textContent.trim())'),['Account settings','Switch workspace','Sign out'],'Shared account actions');checks++;
          await key('Escape');
          assert(await evaluate('!document.querySelector(".ns-account-menu").open'),'Account menu closes with Escape');checks++;
        }
        for(const width of [1440,768,390,320]){
          await viewport(width);
          assert(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'No overflow '+url+' '+width);checks++;
          assert(await evaluate('[...document.querySelectorAll(".ns-control-icon")].every(host=>host.querySelector("svg") && !host.querySelector(".bi"))'),'Navigation controls render without icon fonts');checks++;
          if(await evaluate('!!document.querySelector(".ns-account-menu")')){
            await evaluate('document.querySelector(".ns-account-menu>summary").click()');
            assert(await evaluate('(()=>{const r=document.querySelector(".ns-account-menu .ns-account-panel,.ns-account-menu .cu-dropdown-panel").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth+1})()'),'Account menu fits viewport '+url+' '+width);checks++;
            if(url.includes('__theme_procurement')&&width===390)await screenshot('account-menu-'+theme+'-390');
            await key('Escape');
          }
          if(url.includes('__theme_schedule')||url.includes('__theme_employee')){
            if(width>900){
              assert.equal(await evaluate('document.querySelector(".sidebar,.tc-sidebar").getBoundingClientRect().width'),248,'CSSR sidebar width');checks++;
              await evaluate('document.querySelector(".ns-document-collapse,.sidebar-toggle").click()');await pause(250);
              assert.equal(await evaluate('document.querySelector(".sidebar,.tc-sidebar").getBoundingClientRect().width'),80,'Collapsed shared sidebar');checks++;
              assert(await evaluate('getComputedStyle(document.querySelector(".ns-document-collapse,.sidebar-toggle")).display==="none" && document.querySelector(".ns-sidebar-logo img").getBoundingClientRect().width===38'),'Collapsed navigation shows logo and hides button');checks++;
              await evaluate('document.querySelector(".ns-sidebar-logo").click()');await pause(250);
              assert.equal(await evaluate('document.querySelector(".sidebar,.tc-sidebar").getBoundingClientRect().width'),248,'Logo expands navigation');checks++;
            }else{
              await evaluate('document.querySelector(".ed-mobile-toggle,.mobile-menu-toggle").click()');await pause(250);
              assert(await evaluate('(()=>{const r=document.querySelector(".sidebar,.tc-sidebar").getBoundingClientRect();return Math.abs(r.left)<1&&r.right<=innerWidth})()'),'Drawer fits '+url+' '+width);checks++;
              assert(await evaluate('(()=>{const a=document.querySelector(".sidebar nav a,.tc-sidebar nav a"),r=a.getBoundingClientRect();return a.contains(document.elementFromPoint(r.left+r.width/2,r.top+r.height/2))})()'),'Drawer links above backdrop');checks++;
              if(theme==='dark')assert.deepEqual(await unreadableDarkText('.sidebar,.tc-sidebar',true),[],'Drawer contrast');
              await screenshot((url.includes('schedule')?'schedule':'documents')+'-drawer-'+theme+'-'+width);
              await key('Escape');
            }
          }
          if(url.includes('__theme_procurement')){
            assert.equal(await evaluate('document.querySelectorAll(".proc-top .proc-user,.proc-top .proc-profile-menu").length'),0,'No duplicate Procurement profile');checks++;
            if(width>900)assert(await evaluate('document.querySelector(".proc-sidebar-toggle").closest(".proc-brand")!==null'),'Desktop toggle belongs to sidebar');checks++;
            assert(await evaluate('document.querySelector(".proc-sidebar-toggle").getBoundingClientRect().width>=44'),'Navigation button visible');checks++;
            await evaluate('document.querySelector(".proc-sidebar-toggle").click()');await pause(250);
            if(width>900){
              assert.equal(await evaluate('document.querySelector(".proc-sidebar").getBoundingClientRect().width'),80,'Collapsed Procurement rail');checks++;
              assert(await evaluate('getComputedStyle(document.querySelector(".proc-sidebar-toggle")).display==="none" && document.querySelector(".ns-sidebar-logo img").getBoundingClientRect().width===38'),'Procurement collapsed logo and hidden button');checks++;
              await evaluate('document.querySelector(".ns-sidebar-logo").click()');await pause(250);
              assert.equal(await evaluate('document.querySelector(".proc-sidebar").getBoundingClientRect().width'),248,'Procurement logo expands sidebar');checks++;
            }else{
              assert(await evaluate('document.body.classList.contains("proc-nav-open")&&!document.querySelector(".proc-sidebar").inert&&document.querySelector("main").inert'),'Mobile navigation isolates focus');checks++;
              assert(await evaluate('(()=>{const logo=document.querySelector(".ns-sidebar-logo"),r=logo.getBoundingClientRect();return logo.contains(document.elementFromPoint(r.left+r.width/2,r.top+r.height/2))})()'),'Mobile drawer logo remains above the page header');checks++;
              await screenshot('procurement-drawer-'+theme+'-'+width);
              await key('Escape');
              assert(await evaluate('!document.querySelector("main").inert&&document.querySelector(".proc-sidebar").inert'),'Drawer closes safely');checks++;
            }
            await evaluate('document.querySelector("#ppmpDialog").showModal()');
            assert(await evaluate('document.querySelector("#ppmpDialog").scrollWidth<=document.querySelector("#ppmpDialog").clientWidth+1'),'PPMP form fits '+width);checks++;
            assert(await evaluate('(()=>{const r=document.querySelector("#ppmpDialog").getBoundingClientRect();return Math.abs(r.left+r.width/2-innerWidth/2)<2})()'),'PPMP form centered');checks++;
            if(theme==='dark')assert.deepEqual(await unreadableDarkText('#ppmpDialog',true),[],'Real PPMP form contrast');
            await screenshot('ppmp-form-'+theme+'-'+width);
            await evaluate('document.querySelector("#ppmpDialog").close()');
          }
          if(theme==='dark'){
            assert.equal(await evaluate('getComputedStyle(document.body).backgroundColor'),'rgb(16, 28, 41)','Dark canvas '+url);checks++;
            const bright=await evaluate('[...document.querySelectorAll(".panel,.card,.system-choice-card,.proc-top,.admin-panel,.navbar,.setup-panel,dialog[open]")].filter(e=>e.getClientRects().length&&getComputedStyle(e).backgroundColor==="rgb(255, 255, 255)").map(e=>e.className)');
            assert.deepEqual(bright,[],'No white content panels '+url);checks++;
            assert.deepEqual(await unreadableDarkText(url.includes('CSSR_INVENTORY')?'.cu-workspace':'body',true),[],'Readable text '+url+' '+width);checks++;
          }
          await screenshot(url.split('/').pop().replaceAll('.php','')+'-'+theme+'-'+width);
        }
      }
    }
    await go(base+'pages/__theme_schedule');await evaluate('NursingTheme.set("dark")');
    for(const step of [1,2,3,4]){
      await evaluate(`document.querySelector('[data-setup-step="${step}"]').click()`);await pause(100);
      assert.deepEqual(await unreadableDarkText('body',true),[],'Schedule step '+step+' contrast');checks++;
    }
    await screenshot('schedule-dark-step4');
    await evaluate('document.querySelector("#scheduleManual").click()');await pause(200);
    assert.deepEqual(await unreadableDarkText('body',true),[],'Schedule calendar contrast');checks++;
    await evaluate('document.querySelector(".schedule-day-add").click()');await pause(100);
    assert.deepEqual(await unreadableDarkText('dialog[open]',true),[],'Schedule dialog contrast');checks++;
    await go(menu);await evaluate('NursingTheme.set("light")');
    await go(menu);await call('Page.reload');await pause(200);await ready();
    assert.equal(await evaluate('NursingTheme.get()'),'light','Reload preserves preference');checks++;
    // Same-origin second document creates a real storage event, as another tab does.
    await evaluate('new Promise(resolve=>{const frame=document.createElement("iframe");frame.src="about:blank";frame.onload=()=>{frame.contentWindow.localStorage.setItem("jvgh.theme","dark");resolve()};document.body.append(frame)})');await pause(100);
    assert.equal(await evaluate('NursingTheme.get()'),'dark','Other open document synchronizes');checks++;
    assert.equal(await evaluate('document.querySelector("[data-theme-label]").textContent'),'Dark mode');checks++;
    await call('Emulation.setEmulatedMedia',{media:'print'});
    assert.equal(await evaluate('getComputedStyle(document.querySelector("[data-nursing-theme-toggle]")).display'),'none','Switch hidden in print');checks++;
    assert.notEqual(await evaluate('getComputedStyle(document.body).backgroundColor'),'rgb(16, 28, 41)','Print unaffected');checks++;
    await call('Emulation.setEmulatedMedia',{media:''});
    await evaluate('localStorage.removeItem("jvgh.theme");localStorage.setItem("jvgh.cssr.theme","dark")');await go(menu);
    assert.equal(await evaluate('NursingTheme.get()'),'dark','Existing CSSR preference inherited');checks++;
    await call('Page.addScriptToEvaluateOnNewDocument',{source:'Storage.prototype.getItem=function(){throw Error("blocked")};Storage.prototype.setItem=function(){throw Error("blocked")};'});
    await go(menu);assert.equal(await evaluate('NursingTheme.get()'),'light','Storage unavailable fallback');
    await evaluate('document.querySelector("[data-nursing-theme-toggle]").click()');assert.equal(await evaluate('NursingTheme.get()'),'dark','Toggle with blocked storage');checks+=2;
    assert.deepEqual(exceptions,[],'No browser exceptions');
    console.log('Passed '+checks+' shared theme checks. Screenshots: '+artifacts);
    await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
