// Standalone visual-foundation checks: no database mutations or real credentials.
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function main() {
  const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'cssr-reports-'));
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
      if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
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








    async function go(query=''){await call('Page.navigate',{url:'http://localhost/Nursing_System/CSSR_INVENTORY/design-system/reports.php'+query});await pause(200);await ready();}
    assert.equal(await evaluate('document.querySelector("h1").textContent'),'Stock Movement');
    assert.equal(await evaluate('document.querySelectorAll("#rp-filters .cu-field").length'),6);
    assert.equal(await evaluate('document.querySelectorAll("#rp-report option").length'),15);
    assert(await evaluate('!document.querySelector("#rp-year,#rp-month")'),'Year and month filters removed');
    assert(await evaluate('[...document.querySelectorAll(".rp-exports a")].every(a=>!new URL(a.href).searchParams.has("year")&&!new URL(a.href).searchParams.has("month"))'),'Exports use dates without hidden year/month filters');
    assert.equal(await evaluate('document.querySelectorAll(".rp-chart-card").length'),1);
    assert.equal(await evaluate('document.querySelectorAll(".cu-navigation > .cu-nav-link[title=Reports]").length'),1,'One Reports entry');
    assert.equal(await evaluate('[...document.querySelectorAll(".cu-navigation > .cu-nav-link")].filter(a=>["Stock Card","Bin Card"].includes(a.title)).length'),2,'Separate stock and bin cards');
    assert.equal(await evaluate('document.querySelectorAll(".rp-tools").length'),0,'Report tools panel removed');
    assert(await evaluate('[...document.querySelectorAll(".cu-nav-group")].some(g=>g.querySelector("summary").textContent.trim()==="Report Tools"&&g.querySelectorAll(".cu-subnav a").length===3)'),'Report Tools submenu contains three links');
    assert.equal(await evaluate('document.querySelectorAll(".rp-table > tbody > tr").length'),25);
    assert.equal(await evaluate('document.querySelector(".cu-navigation .cu-nav-link[aria-current]").textContent.trim()'),'Reports');
    assert(await evaluate('document.querySelector(".rp-pagination").textContent.includes("Page 1 of")'),'Pagination');
    await screenshot('desktop-reports');
    await evaluate('document.querySelector("#rp-department").value="Emergency Room";document.querySelector("#rp-department").dispatchEvent(new Event("input",{bubbles:true}))');
    assert(await evaluate('!document.querySelector("#rp-pending").hidden'),'Dirty filters prompt regeneration');
    assert(await evaluate('!document.querySelector(".rp-exports a").href.includes("Emergency")'),'Exports retain generated filters before submit');
    await evaluate('document.querySelector("#rp-filters button[type=submit]").click()');await pause(300);await ready();
    assert(await evaluate('[...document.querySelectorAll(".rp-table > tbody > tr")].every(r=>r.textContent.includes("Emergency Room"))'),'Submitted department filters results');
    assert(await evaluate('document.querySelector(".rp-exports a").href.includes("department=Emergency+Room")'),'Exports retain selected department');
    await go('?report=inbound&from=2026-01-01&to=2026-01-31&procurement_type=Donation');
    assert.equal(await evaluate('document.querySelectorAll(".rp-table > tbody > tr").length'),1);
    assert(await evaluate('document.querySelector(".rp-table").textContent.includes("Donation")'),'Combined filters');
    const downloads=await evaluate(`Promise.all([...document.querySelectorAll('.rp-exports a')].slice(0,2).map(async a=>{const r=await fetch(a.href);const b=new Uint8Array(await r.arrayBuffer());return {type:r.headers.get('content-type'),disposition:r.headers.get('content-disposition'),signature:Array.from(b.slice(0,4)),length:b.length}}))`);
    assert.equal(downloads[0].type,'application/pdf');assert.deepEqual(downloads[0].signature,[37,80,68,70]);assert(downloads[0].length>1000);
    assert(downloads[1].type.includes('spreadsheetml'));assert.deepEqual(downloads[1].signature,[80,75,3,4]);assert(downloads[1].disposition.includes('.xlsx'));
    await go('?report=inventory_balance');
    assert(await evaluate('document.querySelector(".rp-table").textContent.includes("Current Balance")'),'Current balances labeled');
    await go('?report=out_of_stock');assert.equal(await evaluate('document.querySelectorAll(".rp-table tbody .cu-badge-danger").length'),3);
    for(const [type,label] of Object.entries({monthly:'Periodic Reports',weekly:'Periodic Reports',annual:'Periodic Reports',near_expiry:'Items Near Expiry',consumption:'Consumption Report',department_consumption:'Consumption Report',out_of_stock:'Out of Stock Items',low_stock:'Low Stock Items',available:'Available Items'})){
      await go('?report='+type);
      assert.equal(await evaluate('document.querySelector("#rp-report").value'),type,'Report selection '+type);
      assert(await evaluate('document.querySelector("h1").textContent===document.querySelector("#rp-report option:checked").textContent'),'Specific report heading '+type);
      assert(await evaluate('document.querySelector(".rp-filter-foot a").href.includes("report="+document.querySelector("#rp-report").value)'),'Reset keeps selected report '+type);
      assert.equal(await evaluate('document.querySelector(".cu-navigation .cu-nav-link[aria-current]").textContent.trim()'),'Reports','Active report link '+type);
      assert(await evaluate('!!document.querySelector(".rp-exports")'),'Export controls '+type);
      assert(await evaluate('document.querySelector(".cu-navigation").textContent.includes("PPMP")&&document.querySelector(".cu-navigation").textContent.includes("Purchase Request")'),'Original procurement links restored');
    }
    await go('?report=monthly&department=Operating+Room');
    assert.equal(await evaluate('document.querySelectorAll(".rp-period-tabs a").length'),3,'Three periods in one report page');
    await evaluate('document.querySelectorAll(".rp-period-tabs a")[1].click()');await pause(300);await ready();
    assert.equal(await evaluate('document.querySelector("#rp-report").value'),'weekly','Weekly switch');
    assert.equal(await evaluate('document.querySelector("#rp-department").value'),'Operating Room','Period switch retains department');
    assert.equal(await evaluate('Math.round((new Date(document.querySelector("#rp-to").value)-new Date(document.querySelector("#rp-from").value))/86400000)'),6,'Weekly shortcut sets a full week');
    await evaluate('document.querySelectorAll(".rp-period-tabs a")[2].click()');await pause(300);await ready();
    assert(await evaluate('document.querySelector("#rp-from").value.endsWith("-01-01")&&document.querySelector("#rp-to").value.endsWith("-12-31")'),'Annual switch sets full year');
    await go('?report=department_consumption&from=2026-02-01&to=2026-03-01&department=Operating+Room');
    assert(await evaluate('document.querySelector(".rp-table").textContent.includes("Operating Room")'),'Department report rows');
    assert(await evaluate('document.querySelector(".rp-exports a").href.includes("from=2026-02-01")&&document.querySelector(".rp-exports a").href.includes("to=2026-03-01")'),'Consumption export date range');
    assert.equal(await evaluate('document.querySelectorAll(".rp-usage-tabs a").length'),2,'Two usage views');
    await evaluate('document.querySelector(".rp-usage-tabs a").click()');await pause(300);await ready();
    assert.equal(await evaluate('document.querySelector("#rp-report").value'),'consumption','Usage date view');
    assert(await evaluate('document.querySelector("#rp-from").value==="2026-02-01"&&document.querySelector("#rp-to").value==="2026-03-01"&&document.querySelector("#rp-department").value==="Operating Room"'),'Usage switch preserves dates and department');
    await evaluate('document.querySelectorAll(".rp-usage-tabs a")[1].click()');await pause(300);await ready();
    assert.equal(await evaluate('document.querySelector("#rp-report").value'),'department_consumption','Usage department view');
    await go('?department=missing');assert(await evaluate('document.querySelector(".cu-empty h3").textContent.includes("No matching")'),'Empty result');
    await go('?from=2026-02-30');assert(await evaluate('!!document.querySelector("[aria-invalid=true]")'),'Invalid filters have field errors');
    assert.equal(await evaluate('document.querySelectorAll(".rp-exports").length'),0,'No export for invalid filters');
    await go();await evaluate('document.querySelector("#rp-to").value="2000-01-01";document.querySelector("#rp-to").dispatchEvent(new Event("change"))');
    assert(await evaluate('!document.querySelector("#rp-to").checkValidity()'),'Date order validation');
    for(const width of [1440,1024,768,390,320]){
      await viewport(width,width<768?844:1000);await go();
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Page fits '+width+' '+JSON.stringify(await evaluate('({width:innerWidth,scroll:document.documentElement.scrollWidth,elements:[...document.querySelectorAll("body *")].filter(el=>el.getBoundingClientRect().right>innerWidth).slice(0,20).map(el=>[el.className,el.getBoundingClientRect().right,getComputedStyle(el).position])})')));
      assert(await evaluate('[...document.querySelectorAll("#rp-filters input,#rp-filters select")].every(el=>el.getBoundingClientRect().right<=innerWidth)'),'Filters fit '+width);
      if(width===390)await screenshot('mobile-reports');
    }
    // CDP generates the PDFs below; suppress native print dialogs in headless checks.
    await call('Page.addScriptToEvaluateOnNewDocument',{source:'window.print=()=>{window.__reportPrintCalls=(window.__reportPrintCalls||0)+1;};'});
    await go('?report=out_of_stock');
    const printLocation=await evaluate('location.href');
    const tabsBefore=(await call('Target.getTargets')).targetInfos.filter(t=>t.type==='page').length;
    await evaluate('document.querySelector("[data-rp-print]").click()');
    for(let i=0;i<60;i++){if(await evaluate('document.querySelector("[data-rp-print-frame]")?.contentWindow?.__reportPrintCalls===1'))break;await pause(100);}
    assert(await evaluate('document.querySelector("[data-rp-print-frame]")?.contentWindow?.__reportPrintCalls===1'),'Print dialog opens in embedded report');
    assert.equal(await evaluate('location.href'),printLocation,'Print preserves current page and filters');
    assert.equal((await call('Target.getTargets')).targetInfos.filter(t=>t.type==='page').length,tabsBefore,'Print does not open another tab');
    await evaluate('document.querySelector("[data-rp-print-frame]").contentWindow.dispatchEvent(new Event("afterprint"))');
    assert(await evaluate('!document.querySelector("[data-rp-print-frame]")&&!document.querySelector("[data-rp-print]").hasAttribute("aria-busy")'),'Print frame cleaned up after dialog closes');
    await call('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});await viewport(1440);await go('?print=1');
    assert(await evaluate('getComputedStyle(document.querySelector(".rp-print-header")).display==="none"'),'Letterhead does not change screen layout');
    assert.equal(await evaluate('document.querySelectorAll(".rp-table > tbody > tr").length'),await evaluate('Number(document.querySelector(".rp-card-head")?.dataset.count || document.querySelector(".rp-totals .cu-stat-value").textContent.replaceAll(",",""))'),'Print contains every row');
    await call('Emulation.setEmulatedMedia',{media:'print',features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    assert(await evaluate('getComputedStyle(document.querySelector(".cu-sidebar")).display==="none"'),'Print hides navigation');
    assert(await evaluate('getComputedStyle(document.querySelector(".rp-filter-card")).display==="none"'),'Print hides filters');
    assert(await evaluate('[".rp-totals",".rp-chart-card",".rp-result-heading",".rp-scope",".rp-notes-row"].every(s=>getComputedStyle(document.querySelector(s)).display==="none")'),'Print contains report details only');
    assert(await evaluate('getComputedStyle(document.querySelector("[aria-labelledby=rp-table-title]")).display!=="none"'),'Report details remain visible');
    assert(await evaluate('getComputedStyle(document.querySelector(".rp-print-header")).display==="block"&&getComputedStyle(document.querySelector(".rp-print-footer")).display==="block"'),'Print shows hospital letterhead and footer');
    assert(await evaluate('[...document.querySelectorAll(".rp-print-header img")].length===2&&[...document.querySelectorAll(".rp-print-header img")].every(img=>img.complete&&img.naturalWidth>0)'),'Both letterhead logos load');
    assert(await evaluate('document.querySelector(".rp-print-footer").textContent.includes("JVGH-CSSR-XX-XXX")&&document.querySelector(".rp-print-footer").textContent.includes("No stamping is needed.")'),'Stock Card footer matches');
    const print=await call('Page.printToPDF',{landscape:false,printBackground:true,preferCSSPageSize:true});
    fs.writeFileSync(path.join(artifacts,'browser-print.pdf'),Buffer.from(print.data,'base64'));
    await go('?report=out_of_stock&print=1');await viewport(703);
    assert.equal(await evaluate('document.querySelector(".rp-print-title").textContent'),'Out of Stock Report','Printed title is the selected report name');
    assert(await evaluate('getComputedStyle(document.querySelector(".rp-print-title")).textAlign==="center"&&parseFloat(getComputedStyle(document.querySelector(".rp-print-title")).fontSize)===24'),'Centered 18pt report title');
    const typography=await evaluate(`(async()=>{
      const frame=document.createElement('iframe');frame.style.cssText='position:fixed;left:-3000px;width:800px;height:1100px';document.body.append(frame);
      const header=document.querySelector('.rp-print-header>div').cloneNode(true);header.className='facility-copy';
      const contact=document.createElement('small');contact.textContent=header.lastElementChild.textContent;header.lastElementChild.replaceWith(contact);
      const footer=document.querySelector('.rp-print-footer').cloneNode(true);footer.className='bin-card-footer';footer.firstElementChild.className='bin-footer-note';footer.lastElementChild.className='bin-footer-code';
      const styles=['bootstrap.min.css','app.css','forms.css','design-system.css','legacy-unified.css'].map(file=>'<link rel="stylesheet" href="'+new URL('../assets/css/'+file,location.href)+'">').join('');
      await new Promise(resolve=>{frame.onload=resolve;frame.srcdoc='<!doctype html><html><head>'+styles+'</head><body class="cu-app cssr-forms cu-legacy"><main class="main-content"><div class="bin-card-sheet stock-card-sheet"><div class="bin-card-header">'+header.outerHTML+'</div>'+footer.outerHTML+'</div></main></body></html>';});
      const props=['fontFamily','fontSize','fontWeight','fontStyle','lineHeight'];
      const pairs=[['.rp-print-header span','.facility-copy span'],['.rp-print-header strong','.facility-copy strong'],['.rp-print-header span:last-child','.facility-copy small'],['.rp-print-footer>div:first-child','.bin-footer-note'],['.rp-document-code strong','.bin-footer-code strong'],['.rp-document-code th','.bin-footer-code th'],['.rp-document-code td','.bin-footer-code td']];
      const headerStyle=getComputedStyle(document.querySelector('.rp-print-header span'));
      const result=pairs.map(([report,stock])=>{const a=getComputedStyle(document.querySelector(report)),b=frame.contentWindow.getComputedStyle(frame.contentDocument.querySelector(stock));return {report,actual:props.map(p=>a[p]),expected:props.map(p=>!report.startsWith('.rp-print-header')&&['fontFamily','fontStyle'].includes(p)?headerStyle[p]:b[p])};});frame.remove();return result;
    })()`);
    for(const row of typography)assert.deepEqual(row.actual,row.expected,'Header and footer typography: '+row.report);
    assert(await evaluate('[...document.querySelectorAll(".rp-table > tbody .cu-badge")].every(el=>{const cell=el.closest("td").getBoundingClientRect();const r=el.getBoundingClientRect();return getComputedStyle(el).whiteSpace==="nowrap"&&r.right<=cell.right&&el.scrollWidth<=el.clientWidth;})'),'Out of Stock fits on one line inside its column');
    assert(await evaluate('getComputedStyle(document.querySelector(".rp-print-footer")).position==="fixed"&&parseFloat(getComputedStyle(document.querySelector(".rp-print-footer")).bottom)===0'),'Footer anchored in the reserved bottom page margin');
    const stockPrint=await call('Page.printToPDF',{landscape:false,printBackground:true,preferCSSPageSize:true});fs.writeFileSync(path.join(artifacts,'out-of-stock-print.pdf'),Buffer.from(stockPrint.data,'base64'));
    const downloaded=Buffer.from(await evaluate('fetch(document.querySelector(".rp-exports a").href).then(async r=>{if(!r.ok)throw Error("PDF download failed");return Array.from(new Uint8Array(await r.arrayBuffer()));})'));
    fs.writeFileSync(path.join(artifacts,'out-of-stock-download.pdf'),downloaded);
    assert(downloaded.subarray(0,5).toString()==='%PDF-','Matching-layout PDF download');
    assert.equal([...downloaded.toString('latin1').matchAll(/\/Type\s*\/Page\b/g)].length,1,'Downloaded short report has the same page count as Print');
    assert.equal([...Buffer.from(stockPrint.data,'base64').toString('latin1').matchAll(/\/Type\s*\/Page\b/g)].length,1,'Short report and bottom footer share one page');
    assert(await evaluate('document.querySelector(".rp-print-footer").parentElement===document.body'),'Fixed footer sits outside the paginated table');
    await go('?report=inventory_balance&print=1');
    await evaluate(`(()=>{const cells=[...document.querySelectorAll('.rp-table tbody td strong')].filter(el=>!el.closest('.rp-number'));cells[0].textContent='BONE MARROW BIOPSY NEEDLE, 11G x 4 INCHES, WITH TREPHINE TIP AND REMOVABLE STYLET, STERILE, SINGLE-USE.';cells[1].textContent='LONGITEMNAME'.repeat(20);})()`);
    for(const width of [1100,740]){
      await viewport(width);
      assert(await evaluate(`[...document.querySelectorAll('.rp-table > tbody > tr > td strong')].every(el=>{const cell=el.closest('td').getBoundingClientRect();const range=document.createRange();range.selectNodeContents(el);return [...range.getClientRects()].every(r=>r.left>=cell.left&&r.right<=cell.right+1&&r.bottom<=cell.bottom+1);})`),'Printed names stay inside their cells '+width);
      assert(await evaluate('getComputedStyle(document.querySelector(".rp-table td strong")).whiteSpace==="normal"'),'Print name wrapping');
    }
    const longNamePrint=await call('Page.printToPDF',{landscape:false,printBackground:true,preferCSSPageSize:true});
    fs.writeFileSync(path.join(artifacts,'long-item-names-print.pdf'),Buffer.from(longNamePrint.data,'base64'));
    await call('Emulation.setEmulatedMedia',{media:'screen',features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    for(const theme of ['light','dark']){
      await evaluate('document.documentElement.setAttribute("data-cu-theme",'+JSON.stringify(theme)+')');
      for(const width of [1440,1024,390,320]){
        await viewport(width);
        assert(await evaluate(`[...document.querySelectorAll('.rp-table > tbody > tr > td strong')].every(el=>{const cell=el.closest('td').getBoundingClientRect();const range=document.createRange();range.selectNodeContents(el);return [...range.getClientRects()].every(r=>r.left>=cell.left&&r.right<=cell.right+1&&r.bottom<=cell.bottom+1);})`),'On-screen names stay inside cells '+theme+' '+width);
        assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Long names do not overflow page '+width);
        if(width===1440){await evaluate('document.querySelector(".rp-table").scrollIntoView({block:"center"})');await screenshot('long-names-'+theme);}
      }
    }
    assert.deepEqual(exceptions,[],'No browser errors');
    console.log('Passed: report filters, fifteen report types, totals/chart/table, pagination, filtered PDF/XLSX downloads, validation, empty state, mobile layouts and complete print output.');
    console.log('Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
