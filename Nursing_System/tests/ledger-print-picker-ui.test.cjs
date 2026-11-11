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
    const base='http://localhost/Nursing_System/CSSR_INVENTORY/pages/';
    const fixtures=new Map(['bin','stock'].map(kind=>[base+'__ledger_'+kind,execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'../CSSR_INVENTORY/tests/ledger-print-all.test.php'),'fixture',kind],{encoding:'utf8'})]));
    socket.addEventListener('message',async event=>{const message=JSON.parse(event.data);if(message.method!=='Fetch.requestPaused')return;try{await call('Fetch.fulfillRequest',{requestId:message.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(fixtures.get(message.params.request.url)).toString('base64')});}catch(error){exceptions.push(error.message);}});
    await call('Fetch.enable',{patterns:[{urlPattern:'*__ledger_*'}]});
    for(const kind of ['bin','stock'])for(const width of [1440,390,320]){
      await viewport(width);await call('Page.navigate',{url:base+'__ledger_'+kind});await ready();await pause(150);
      await evaluate('document.querySelector(".toolbar [name=from]").value="2026-01-01";document.querySelector(".toolbar [name=to]").value="2026-02-28";document.querySelector("[data-ledger-picker-open]").click()');
      assert(await evaluate('document.querySelector("#ledgerPrintPicker").open'),'Popup opens');
      assert(await evaluate('document.querySelector("#ledgerPrintPicker").getBoundingClientRect().width<=innerWidth'),'Popup fits viewport');
      assert.equal(await evaluate('document.querySelector("#ledgerPrintForm [name=from]").value'),'2026-01-01');
      assert(await evaluate('[...document.querySelectorAll("[data-ledger-print-submit]")].every(button=>button.disabled)'),'Empty selection disables printing');
      await evaluate('document.querySelector("[data-ledger-select-all]").click()');
      assert.equal(await evaluate('document.querySelectorAll("#ledgerPrintForm input[type=checkbox]:checked").length'),2);
      await evaluate('document.querySelector("[data-ledger-clear-all]").click()');
      assert.equal(await evaluate('document.querySelectorAll("#ledgerPrintForm input[type=checkbox]:checked").length'),0);
      await evaluate('const checkbox=document.querySelector("#ledgerPrintForm input[type=checkbox]");checkbox.checked=true;checkbox.dispatchEvent(new Event("change"));window.submissions=[];document.querySelector("#ledgerPrintForm").addEventListener("submit",event=>{if(!event.defaultPrevented)window.submissions.push([...new FormData(event.target,event.submitter)]);event.preventDefault();});');
      await evaluate('document.querySelector("[name=print_mode][value=preview]").click();document.querySelector("[name=print_mode][value=print]").click()');
      const submissions=await evaluate('window.submissions');assert.equal(submissions.length,2);
      assert.deepEqual(submissions[0].filter(pair=>pair[0]!=='print_mode'),submissions[1].filter(pair=>pair[0]!=='print_mode'),'Preview and Print send identical selected items and dates');
      await evaluate('document.querySelector("#ledgerPrintForm [name=to]").value="2025-01-01";document.querySelector("[name=print_mode][value=print]").click()');
      assert.equal(await evaluate('window.submissions.length'),2,'Reversed dates do not submit');
      assert(await evaluate('!document.querySelector("[data-ledger-picker-error]").hidden'),'Date error is shown');
      if(width===390)await screenshot('ledger-picker-'+kind);
      await key('Escape');assert(await evaluate('!document.querySelector("#ledgerPrintPicker").open && document.activeElement.matches("[data-ledger-picker-open]")'),'Close restores focus');
    }
    assert.deepEqual(exceptions,[]);console.log('Passed Bin Card and Stock Card popup selection, Select All, Clear All, date validation, identical print submissions, and mobile/desktop layout. Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
