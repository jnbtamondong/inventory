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
    const {execFileSync}=require('node:child_process'),base='http://localhost/Nursing_System/pages/';
    const fixtures=new Map(['dashboard','new','detail','configuration'].map(view=>[base+'__exchange_'+view,execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'duty-exchange-ui-fixture.php'),view],{encoding:'utf8'})]));
    fixtures.set(base+'__exchange_schedule',execFileSync('C:/xampp/php/php.exe',['-n',path.join(__dirname,'theme-ui-fixture.php'),'schedule'],{encoding:'utf8'}));
    const scheduleStaff=[{id:1,name:'Fixture Alpha',position:'Nurse',pattern:'8',group:'',preference:'ANY',active:true},{id:2,name:'Fixture Beta',position:'Nurse',pattern:'8',group:'',preference:'ANY',active:true}];
    const scheduleData={exists:true,status:'PUBLISHED',version:11,submitted_by:3,master:scheduleStaff,boundaries:[],summary:[],payload:{staff:scheduleStaff,assignments:{1:{'2026-10-01':'PM'},2:{'2026-10-01':'AM'}},exchange_refs:{1:{'2026-10-01':'EX-2035-0012'},2:{'2026-10-01':'EX-2035-0012'}},requests:[],mode:'MANUAL',rules:{minRest:12,maxConsecutive:6,minOff:1,maxOff:31,offPer7:1,reviewed:true,staffingTargets:{TRIAGE:0,NURSE:0,NA:0},staffingShifts:[],coverage:{}}}};
    const originals=[{assignment_id:'11:1:2035-01-10:10',schedule_id:11,employee_id:1,name:'Fixture Alpha',employee_number:'A',department:'ER',date:'2035-01-10',shift:'AM',version:10,published_version:10},{assignment_id:'11:2:2035-01-10:10',schedule_id:11,employee_id:2,name:'Fixture Beta',employee_number:'B',department:'ER',date:'2035-01-10',shift:'PM',version:10,published_version:10}];
    const request={id:12,request_no:'EX-2035-0012',requester_id:1,partner_id:2,requester_name:'Fixture Alpha',partner_name:'Fixture Beta',department:'ER',status:'PENDING_HEAD_NURSE_APPROVAL',reason:'Synthetic browser review',round_no:1,version:2,original:originals,submitted_at:'2035-01-01 09:00:00',created_at:'2035-01-01 09:00:00',can_approve:true};
    const events=[{round_no:1,stage:'SUBMITTED',decision:'SUBMITTED',actor_name:'Fixture Alpha',actor_role:'STAFF_NURSE',remarks:'Synthetic browser review',created_at:'2035-01-01 09:00:00'},{round_no:1,stage:'PARTNER',decision:'ACCEPT',actor_name:'Fixture Beta',actor_role:'STAFF_NURSE',remarks:'Confirmed',created_at:'2035-01-01 10:00:00'}];
    request.validation={minimum_rest:12,validated_at:'2035-01-01T10:00:00+08:00',periods:{11:'2035-01'},monthly_hours:{11:[{employee_id:1,hours:8,target:176,status:'DEFICIT'},{employee_id:2,hours:8,target:176,status:'DEFICIT'}]},before_monthly_hours:{11:[{employee_id:1,hours:8},{employee_id:2,hours:8}]}};
    let posts=[];
    socket.addEventListener('message',async event=>{
      const msg=JSON.parse(event.data);if(msg.method!=='Fetch.requestPaused')return;
      try{
        const req=msg.params.request,url=new URL(req.url);let body,status=200;
        if(fixtures.has(req.url))body=fixtures.get(req.url);
        else if(req.method==='POST'){posts.push(JSON.parse(req.postData));status=422;body=JSON.stringify({success:false,error:'Synthetic submission captured; no records changed.'});}
        else if(url.pathname.endsWith('/api/schedule_management.php'))body=JSON.stringify({success:true,data:scheduleData});
        else{
          let data=[];const action=url.searchParams.get('action');
          if(action==='list')data=[request];if(action==='detail')data={request,events,amendments:[],can_approve:true};if(action==='configuration')data={approvers:[],permissions:[],audit:[]};
          if(action==='assignments'){const id=Number(url.searchParams.get('employee_id')||1);const cell=originals.find(row=>row.employee_id===id)||originals[0];data=[{value:cell.assignment_id,date:cell.date,shift:cell.shift,department:'ER',hours:8,selectable:true,exchange_ref:null}];}
          body=JSON.stringify({success:true,data});
        }
        await call('Fetch.fulfillRequest',{requestId:msg.params.requestId,responseCode:status,responseHeaders:[{name:'Content-Type',value:fixtures.has(req.url)?'text/html; charset=utf-8':'application/json'}],body:Buffer.from(body).toString('base64')});
      }catch(error){exceptions.push(error.message);}
    });
    await call('Fetch.enable',{patterns:[{urlPattern:'*__exchange_*'},{urlPattern:'*/api/duty_exchange.php*'},{urlPattern:'*/api/schedule_management.php*'}]});
    let layouts=0;for(const view of ['dashboard','new','detail','configuration'])for(const width of [1440,768,390,320])for(const theme of ['light','dark']){
      await viewport(width);await call('Page.navigate',{url:base+'__exchange_'+view});await ready();
      for(let i=0;i<50;i++){if(await evaluate('!document.querySelector("#exchangeContent").textContent.includes("Loading exchange requests")'))break;await pause(100);}await evaluate('NursingTheme.set('+JSON.stringify(theme)+')');await pause(150);
      assert(await evaluate('!document.querySelector("#exchangeMessage").classList.contains("error")'),'View loads '+view);
      assert(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'No overflow '+view+' '+width);layouts++;
      assert(await evaluate('document.querySelectorAll(".sidebar").length===1&&!document.querySelector(".schedule-sidebar .has-submenu,.schedule-sidebar .submenu")'),'Scheduling sidebar has direct links without dropdowns');
      assert(await evaluate('document.querySelector("[data-schedule-module=exchange] a[aria-current=page]").getAttribute("href")==="duty_exchange.php?view=dashboard"'),'Exchange module stays highlighted for all its pages');
      assert(await evaluate('[...document.querySelectorAll(".schedule-sidebar [data-schedule-module] a")].every(a=>{const label=a.querySelector(":scope>span:not(.ns-icon-host)");return label&&label.getBoundingClientRect().width>=90;})'),'Direct navigation labels remain readable');
      if(view==='detail')assert.equal(await evaluate('document.querySelectorAll(".exchange-track li").length'),6,'Six mandatory stages');
      if(view==='detail')assert(await evaluate('document.querySelector("#exchangeContent").textContent.includes("Scheduling validation and monthly hours")'),'Approvers can review recalculated hours');
      if(view==='configuration'){assert.equal(await evaluate('document.querySelector("#exchangeApproverForm [name=approver_id]").value'),'3','Head stage lists only Head Nurses');await evaluate('const stage=document.querySelector("#exchangeApproverForm [name=stage]");stage.value="SUPERVISOR";stage.dispatchEvent(new Event("change"));');assert.equal(await evaluate('document.querySelector("#exchangeApproverForm [name=approver_id]").value'),'4','Supervisor stage lists only Supervisors');}
      if(theme==='dark')assert.deepEqual(await unreadableDarkText('#exchangeContent'),[],'Readable dark theme '+view);
      if(view==='new'){
        assert.equal(await evaluate('document.querySelectorAll("#exchangeRequestForm input[readonly]").length'),5,'Authenticated identity and exchange type are read-only');
        await evaluate('document.querySelector("[name=assignment_a]").value="11:1:2035-01-10:10";const partner=document.querySelector("#exchangePartnerSearch");partner.value="Fixture Beta · B · ER";partner.dispatchEvent(new Event("change"));');await pause(150);
        await evaluate('document.querySelector("[name=assignment_b]").value="11:2:2035-01-10:10";document.querySelector("[name=assignment_b]").dispatchEvent(new Event("change"));document.querySelector("[name=reason]").value="Synthetic browser request";');
        assert.equal(await evaluate('document.querySelectorAll("#exchangePreview>div").length'),2,'Original versus proposed assignments');
        const count=posts.length;await evaluate('document.querySelector("#exchangeRequestForm button[value=submit]").click()');await pause(150);assert.equal(posts.length,count+1,'Form submits once');assert.equal(posts.at(-1).partner_id,2);assert.equal(posts.at(-1).assignment_a,'11:1:2035-01-10:10');assert.equal(posts.at(-1).action,'submit');
      }
      if(width===390)await screenshot('exchange-'+view+'-'+theme+'-390');
    }
    await viewport(1440);await call('Page.navigate',{url:base+'__exchange_schedule'});await ready();
    for(let i=0;i<50;i++){if(await evaluate('document.querySelectorAll(".schedule-assignment").length===2'))break;await pause(100);}
    await evaluate('document.querySelector("#scheduleCalendarView").click()');
    assert.equal(await evaluate('document.querySelectorAll(".schedule-exchange-ref").length'),2,'Calendar marks both exchange amendments');
    await evaluate('document.querySelector("#scheduleListView").click()');
    assert(await evaluate('document.querySelector("#scheduleCalendar").hidden&&!document.querySelector("#scheduleDutyList").hidden'),'List View switches from Calendar');
    assert(await evaluate('document.querySelector("#scheduleDutyList").textContent.includes("EX-2035-0012")'),'List View uses the same exchange references');
    await evaluate('document.querySelector("#scheduleCalendarView").click()');
    assert(await evaluate('!document.querySelector("#scheduleCalendar").hidden'),'Calendar View restores');
    assert.deepEqual(exceptions,[]);console.log('Passed '+layouts+' exchange layouts, form and approval controls, dark contrast, and Calendar/List amendment synchronization. Screenshots: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
