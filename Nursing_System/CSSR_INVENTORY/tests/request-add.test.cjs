// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const fixturePath=path.join(__dirname,'.request-add-review.html');
  fs.writeFileSync(fixturePath,execFileSync(process.env.CSSR_PHP_PATH||'C:/xampp/php/php.exe',[path.join(__dirname,'request-add-fixture.php')]));
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-foundation-'));
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
    await call('Runtime.enable'); await call('Emulation.setFocusEmulationEnabled', { enabled: true }); await viewport(1440);
    await call('Page.navigate', { url: process.env.CSSR_DESIGN_URL || 'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/' });
    await ready();await call('Page.enable');
    await call('Page.addScriptToEvaluateOnNewDocument',{source:`window.posts=[];window.saveFails=false;const origFetch=window.fetch;window.fetch=async(url,opts)=>{if(opts?.method==='POST'){window.posts.push(Object.fromEntries(opts.body.entries()));return new Response(JSON.stringify(window.saveFails?{ok:false,message:'Sample save error'}:{ok:true,message:'Item added to request.'}),{status:window.saveFails?422:200,headers:{'Content-Type':'application/json'}});}return origFetch(url,opts)};const originalTimeout=window.setTimeout;window.setTimeout=(fn,delay,...args)=>{if(delay===900||delay===700){window.reloadScheduled=true;return 0;}return originalTimeout(fn,delay,...args)};`});
    await call('Page.navigate',{url:'http://localhost/Nursing_System/CSSR_INVENTORY/tests/.request-add-review.html'});await ready();await pause(250);
    async function pointerClick(selector){
      const r=await evaluate('(()=>{const e=document.querySelector('+JSON.stringify(selector)+');e.scrollIntoView({block:"center"});const r=e.getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2,hit:e.contains(document.elementFromPoint(r.left+r.width/2,r.top+r.height/2)),visible:getComputedStyle(e).visibility}})()');
      assert(r.hit && r.visible==='visible', 'Control must be visible: '+selector);
      for(const type of ['mousePressed','mouseReleased'])await call('Input.dispatchMouseEvent',{type,x:r.x,y:r.y,button:'left',clickCount:1});await pause(250);
    }
    async function clickOption(id){
      const r=await evaluate(`(()=>{const e=document.querySelector('[data-item-id="${id}"]');e.scrollIntoView({block:'nearest'});const r=e.getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2,hit:document.elementFromPoint(r.left+r.width/2,r.top+r.height/2)?.closest('[data-item-id]')?.dataset.itemId}})()`);
      assert.equal(r.hit,String(id),'Option not clipped or covered');
      for(const type of ['mousePressed','mouseReleased'])await call('Input.dispatchMouseEvent',{type,x:r.x,y:r.y,button:'left',clickCount:1});
    }


    for(const mode of ['department','storage']){
      fs.writeFileSync(fixturePath,execFileSync(process.env.CSSR_PHP_PATH||'C:/xampp/php/php.exe',[path.join(__dirname,'request-add-fixture.php'),mode]));
      const prefix=mode==='storage'?'storage':'addRequest';
      const picker=mode==='storage'?'storageAddItemPicker':'addRequestItemPicker';
      const qty=mode==='storage'?'storageAddItemQty':'addRequestItemQty';
      const feedback=mode==='storage'?'storageAddItemFeedback':'addRequestItemFeedback';
      const form=mode==='storage'?'storageRequestAddItemForm':'addRequestItemForm';
      const button='button[form="'+form+'"][type="submit"]';
      for(const width of [1440,390,320])for(const theme of ['light','dark']){
        await viewport(width,700);await call('Page.navigate',{url:'http://localhost/Nursing_System/CSSR_INVENTORY/tests/.request-add-review.html?mode='+mode});await ready();await pause(250);
        await evaluate('document.documentElement.dataset.cuTheme='+JSON.stringify(theme));
        await pointerClick(button);
        assert.equal(await evaluate('document.getElementById('+JSON.stringify(feedback)+').hidden'),false,'Empty item gets visible feedback');
        await evaluate('document.getElementById('+JSON.stringify(picker)+').value="BIOPSY";document.getElementById('+JSON.stringify(picker)+').dispatchEvent(new Event("input"))');
        if(mode==='storage')await pointerClick('.storage-add-item-suggestion');else await clickOption(575);
        await evaluate('document.getElementById('+JSON.stringify(qty)+').value="0"');
        await pointerClick(button);
        assert.match(await evaluate('document.getElementById('+JSON.stringify(feedback)+').textContent'),/whole quantity/);
        await evaluate('document.getElementById('+JSON.stringify(picker)+').setCustomValidity("Choose an item from the dropdown.");document.getElementById('+JSON.stringify(qty)+').value="2";document.getElementById('+JSON.stringify(form)+').elements.namedItem("item_remarks").value="1";window.saveFails=true;');
        await pointerClick(button);
        assert.equal(await evaluate('document.getElementById("confirmModal").classList.contains("open")'),true,mode+' corrected entry opens confirmation');
        await pointerClick('[data-confirm-ok]');
        assert.equal(await evaluate('posts.length'),1);
        const payload=await evaluate('posts.at(-1)');
        assert.equal(payload.item_id,'575');assert.equal(payload.quantity_requested,'2');assert.equal(payload.item_remarks,'1');assert.equal(payload.csrf_token,'preview-token');assert.equal(payload.action,'add_item');
        assert.match(await evaluate('document.getElementById('+JSON.stringify(feedback)+').textContent'),/Sample save error/);
        assert.equal(await evaluate('document.querySelector('+JSON.stringify(button)+').disabled'),false);
        await screenshot(mode+'-add-error-'+theme+'-'+width);
        await evaluate('window.saveFails=false');await pointerClick(button);await pointerClick('[data-confirm-ok]');
        assert.equal(await evaluate('posts.length'),2);assert.equal(await evaluate('reloadScheduled'),true);
        assert.match(await evaluate('document.getElementById('+JSON.stringify(feedback)+').textContent'),/Item added/);
        assert.equal(await evaluate('document.querySelectorAll(".request-print-pages [form],.request-print-pages .no-print").length'),0);
      }
    }
    assert.deepEqual(exceptions,[]);
    console.log('Passed department and storage Add Item clicks, corrected validation, BIOPSY NEEDLE G14 qty 2, CSRF payload, errors, retry and refresh in both themes at desktop/mobile widths. POST responses mocked; no records changed. Artifacts: '+artifacts);
    await call('Browser.close');
  } finally { fs.rmSync(fixturePath,{force:true}); socket?.close(); browser.kill(); for (const waiter of pending.values()) clearTimeout(waiter.timer); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });

