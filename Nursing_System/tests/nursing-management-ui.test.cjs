// Standalone UI checks use disposable fixture databases and an isolated browser profile.
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
      if(message.method==='Page.javascriptDialogOpening')socket.send(JSON.stringify({id:nextId++,method:'Page.handleJavaScriptDialog',params:{accept:true}}));
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
    const forbidden=execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'nursing-management-ui-fixture.php'),'forbidden'],{encoding:'utf8'});assert(!forbidden.includes('PRIVATE APPLICATION MUST NOT LEAK')&&forbidden.includes('Request access denied'),'Unauthorized detail cannot leak private request');
    const archived=execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'nursing-management-ui-fixture.php'),'archived'],{encoding:'utf8'});assert(archived.includes('Fixture Employee 1')&&archived.includes('APPROVED_FOR_HR_FILING'),'Archived employee request keeps original identity');
    const managementViews=['off','leave','print','configuration','adminOff','adminLeave','adminOffDraft'];
    const fixtures=new Map(managementViews.map(view=>[base+'__management_'+view,execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'nursing-management-ui-fixture.php'),view],{encoding:'utf8'})]));
    fixtures.set(base+'nursing_requests.php?view=new&kind=LEAVE&leave_type=SL&department=ER&employee_id=7',execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'nursing-management-ui-fixture.php'),'adminLeave','leave_type=SL&department=ER&employee_id=7'],{encoding:'utf8'}));
    fixtures.set(base+'nursing_requests.php?view=new&kind=OFF&department=ER&employee_id=7',execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'nursing-management-ui-fixture.php'),'adminOff','department=ER&employee_id=7'],{encoding:'utf8'}));
    fixtures.set(base+'__management_schedule',execFileSync('C:/xampp/php/php.exe',['-n',path.join(__dirname,'theme-ui-fixture.php'),'schedule'],{encoding:'utf8'}));
    const employees=[{id:1,name:'Fixture Nurse',role:'STAFF_NURSE',department:'ER',position:'Nurse',category:'NURSE',staffingRole:'NURSE',pattern:'8',active:true,group:'',preference:'ANY',fixedShift:'8'},{id:2,name:'Fixture Nursing Attendant',role:'NURSING_ATTENDANT',department:'ER',position:'Nursing Attendant',category:'NA',staffingRole:'NA',pattern:'8',active:true,group:'',preference:'ANY',fixedShift:'8'}];
    const scheduleData={exists:false,status:'DRAFT',version:0,master:employees,boundaries:[],payload:{staff:structuredClone(employees),assignments:{},requests:[{employee_id:1,date:'2026-10-01',type:'REQUEST_OFF',status:'APPROVED',remarks:'Synthetic approved preference',service_request_id:44}],mode:'MANUAL',policy:{mode:'FIXED',department:'NSO',target:176,operatingDays:[1,2,3,4,5],requiredShifts:[]},rules:{minRest:12,maxConsecutive:6,minOff:1,maxOff:31,offPer7:1,reviewed:true,coverage:{},staffingTargets:{TRIAGE:0,NURSE:0,NA:0},staffingShifts:[]}}};
    socket.addEventListener('message',async event=>{const msg=JSON.parse(event.data);if(msg.method!=='Fetch.requestPaused')return;try{const url=msg.params.request.url,html=fixtures.get(url);if(msg.params.request.method==='POST'){const input=JSON.parse(msg.params.request.postData);if(input.action==='save')Object.assign(scheduleData,{exists:true,version:scheduleData.version+1,payload:input.payload});}const body=html||JSON.stringify({success:true,data:new URL(url).searchParams.get('action')==='availability'?{requests:[{employee_id:1,date:'2026-10-01',type:'REQUEST_OFF',status:'APPROVED',remarks:'Synthetic approved preference',service_request_id:44}],boundaries:[]}:structuredClone(scheduleData)});await call('Fetch.fulfillRequest',{requestId:msg.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:html?'text/html; charset=utf-8':'application/json'}],body:Buffer.from(body).toString('base64')});}catch(error){exceptions.push(error.message);}});
    await call('Fetch.enable',{patterns:[{urlPattern:'*__management_*'},{urlPattern:'*/api/schedule_management.php*'},{urlPattern:'*/pages/nursing_requests.php?*'}]});let layouts=0;
    for(const view of managementViews)for(const width of [1440,768,390,320])for(const theme of ['light','dark']){
        await viewport(width);await call('Page.navigate',{url:base+'__management_'+view});await ready();await evaluate('NursingTheme.set('+JSON.stringify(theme)+')');await pause(100);
        assert(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'No overflow '+view+' '+width);layouts++;
        if(view==='configuration')assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-schedule-modules]>[data-schedule-module]")].map(e=>e.dataset.scheduleModule)'),['monthly','off','exchange','leave'],'Four child modules under the existing schedule parent');
        if(view==='off'||view==='leave')assert(await evaluate('document.querySelector("h2").textContent==='+JSON.stringify(view==='off'?'Request-Off Management':'Request Leave')),'Separate request module heading');
        assert(await evaluate('document.querySelector(".ns-account-menu").getClientRects().length>0'),'Shared account menu stays visible '+view+' '+width);
        if(width<768){await evaluate('document.querySelector(".mobile-menu-toggle").click()');assert(await evaluate('document.body.classList.contains("sidebar-mobile-open")'),'Mobile navigation opens '+view);await key('Escape');assert(await evaluate('!document.body.classList.contains("sidebar-mobile-open")'),'Mobile navigation closes '+view);}
        else if(width===1440){await evaluate('if(document.body.classList.contains("sidebar-collapsed"))document.querySelector(".ns-sidebar-logo").click();document.querySelector(".sidebar-toggle").click()');assert(await evaluate('document.body.classList.contains("sidebar-collapsed")'),'Desktop navigation collapses '+view);assert(await evaluate('document.querySelector(".ns-sidebar-logo").getClientRects().length>0'),'Collapsed rail retains hospital logo');await evaluate('document.querySelector(".ns-sidebar-logo").click()');assert(await evaluate('!document.body.classList.contains("sidebar-collapsed")'),'Hospital logo expands navigation '+view);}
        if(view==='off'||view==='leave'){assert.equal(await evaluate('document.querySelectorAll("input[readonly]").length'),4,'Readonly authenticated employee information');if(view==='off')assert(await evaluate('!document.getElementById("requestAddDate")&&!document.getElementById("requestAddDateButton")'),'Calendar replaces the duplicate Add date controls');}
        if(['off','leave','adminOff','adminLeave','adminOffDraft'].includes(view)){
          assert.deepEqual(await evaluate('[...document.getElementById("requestType").options].filter(option=>option.value).map(option=>option.value).sort()'),['OFF','VL','SL','SPL','WL','FL','ML','PL','SOLO_PARENT','OTHER_LEAVE'].sort(),'Unified request dropdown contains OFF and all enabled leave categories');
          assert(await evaluate('[...document.getElementById("requestType").options].filter(option=>option.value).every(option=>!option.textContent.includes("?")&&!option.textContent.includes(option.value+" "))'),'Request types show full names without acronym prefixes');
          if(view==='leave'||view==='adminLeave'){await evaluate('document.getElementById("requestType").value="SL";document.getElementById("requestType").dispatchEvent(new Event("change"))');assert.equal(await evaluate('document.getElementById("requestLeaveType").value'),'SL','Changing leave category updates submitted leave type');}
        }
        if(view.startsWith('admin')){
          assert(await evaluate('document.querySelector("[data-schedule-module=off] a[href*=new]")&&document.querySelector("[data-schedule-module=leave] a[href*=new]")&&!document.querySelector(".schedule-sidebar .submenu,.schedule-sidebar .has-submenu")'),'Sidebar uses direct encoding links without dropdowns');
          assert.equal(await evaluate('document.querySelectorAll("input[readonly]").length'),4,'Admin encoding retains readonly applicant identity and assigned department');
          if(view==='adminOffDraft'){
            assert(await evaluate('!document.getElementById("requestEmployee")&&!document.getElementById("requestDepartment")'),'Resuming an admin-accessible draft cannot switch its applicant or department');
            assert.equal(await evaluate('document.querySelector("input[type=hidden][name=employee_id]").value'),'7','Draft retains permanent applicant id');
            assert.equal(await evaluate('document.getElementById("requestEmployeeId").value'),'F-7','Draft shows applicant official employee id');
            assert.equal(await evaluate('document.getElementById("requestEmployeeName").value'),'Fixture Employee 7','Draft shows Nursing Attendant applicant rather than System Admin');
            assert(await evaluate('document.getElementById("requestDates").value.includes("2035-01-10")&&document.querySelector("button[value=save_draft]")'),'Admin can resume the saved OFF dates and save the same draft');
          }else{
            const expected=view==='adminOff'?[1,2,7,10]:[1,2,3,4,5,7,10];
            assert.deepEqual(await evaluate('[...document.getElementById("requestEmployeeOptions").content.querySelectorAll("option")].filter(option=>option.value).map(option=>Number(option.value)).sort((a,b)=>a-b)'),expected,'Admin applicant source contains only active central nursing employees eligible for this request kind');
            assert(await evaluate('!!document.getElementById("requestDepartment")&&(document.getElementById("requestDepartment").compareDocumentPosition(document.getElementById("requestEmployee"))&Node.DOCUMENT_POSITION_FOLLOWING)!==0'),'Admin selects Department before Employee');
            assert.equal(await evaluate('document.getElementById("requestDepartment").name'),'request_department','Admin submits the department used to filter applicants');
            assert.deepEqual(await evaluate('[...document.querySelectorAll("#requestDepartment option")].filter(option=>option.value).map(option=>option.value).sort()'),view==='adminOff'?['ER','WARD']:['ER','NSO','WARD'],'Department choices derive from eligible active nursing applicants');
            assert(await evaluate('document.getElementById("requestDepartment").value===""&&document.getElementById("requestEmployee").disabled&&document.getElementById("requestEmployee").value===""&&[...document.querySelectorAll("#requestEmployee option")].filter(option=>option.value).length===0'),'Employee choices remain empty and disabled until Department is selected');
            assert.equal(await evaluate('document.getElementById("requestEmployee").name'),'employee_id','Admin posts the selected permanent applicant id');
            await evaluate('document.getElementById("requestDepartment").value="ER";document.getElementById("requestDepartment").dispatchEvent(new Event("change"))');
            assert(await evaluate('!document.getElementById("requestEmployee").disabled'),'Selecting Department enables Employee');
            assert.deepEqual(await evaluate('[...document.querySelectorAll("#requestEmployee option")].filter(option=>option.value).map(option=>Number(option.value))'),[1,2,7],'ER shows its active Nurse, Head Nurse and Nursing Attendant, with nurses before attendants');
            await evaluate('document.getElementById("requestEmployee").value="7";document.getElementById("requestEmployee").dispatchEvent(new Event("change"))');
            assert.deepEqual(await evaluate('(()=>{const option=document.getElementById("requestEmployee").selectedOptions[0];return ["Id","Name","Position","Department"].map((suffix,index)=>[document.getElementById("requestEmployee"+suffix).value,option.dataset[["employeeId","name","position","department"][index]]])})()'),[['F-7','F-7'],['Fixture Employee 7','Fixture Employee 7'],['NURSING ATTENDANT','NURSING ATTENDANT'],['ER','ER']],'Nursing Attendant selection updates all readonly applicant values');
            if(view==='adminOff'){
              const before=await evaluate('location.href');await evaluate('document.querySelector("[name=reason]").value="Keep entered reason";document.getElementById("requestType").value="SL";document.getElementById("requestType").dispatchEvent(new Event("change"))');
              for(let n=0;n<60;n++){if(await evaluate('document.querySelector("[data-request-kind]").dataset.requestKind==="LEAVE"'))break;await pause(100);}
              assert.equal(await evaluate('location.href'),before,'Request type switch does not reload or navigate the page');assert(await evaluate('document.getElementById("requestOffCalendar")&&document.getElementById("requestEmployee").value==="7"&&document.querySelector("[name=reason]").value==="Keep entered reason"'),'Leave form replaces encoding fields while preserving applicant and reason');
              await evaluate('document.getElementById("requestType").value="OFF";document.getElementById("requestType").dispatchEvent(new Event("change"))');for(let n=0;n<60;n++){if(await evaluate('document.querySelector("[data-request-kind]").dataset.requestKind==="OFF"'))break;await pause(100);}
              assert.equal(await evaluate('location.href'),before,'Returning to Request-Off does not blink through a page navigation');assert(await evaluate('document.querySelectorAll("[data-off-date]").length>=28&&document.getElementById("requestEmployee").value==="7"'),'Calendar and filtered employee selection work after switching back');
            }

            await evaluate('document.getElementById("requestDepartment").value="WARD";document.getElementById("requestDepartment").dispatchEvent(new Event("change"))');
            assert.deepEqual(await evaluate('[...document.querySelectorAll("#requestEmployee option")].filter(option=>option.value).map(option=>Number(option.value))'),[10],'Changing Department replaces applicant choices with only that department employees');
            assert(await evaluate('document.getElementById("requestEmployee").value===""&&["Id","Name","Position","Department"].every(suffix=>document.getElementById("requestEmployee"+suffix).value==="")'),'Changing Department clears the prior applicant and all readonly details');
            await evaluate('document.getElementById("requestEmployee").value="10";document.getElementById("requestEmployee").dispatchEvent(new Event("change"))');
            assert(await evaluate('document.getElementById("requestEmployeeDepartment").value===document.getElementById("requestEmployee").selectedOptions[0].dataset.department&&document.getElementById("requestEmployeeDepartment").value!=="NSO"'),'Applicant department follows the selected employee instead of admin department');
            if(view==='adminLeave'){
              await evaluate('document.getElementById("requestDepartment").value="NSO";document.getElementById("requestDepartment").dispatchEvent(new Event("change"))');
              assert.deepEqual(await evaluate('[...document.querySelectorAll("#requestEmployee option")].filter(option=>option.value).map(option=>Number(option.value))'),[3,4,5],'Leave retains Supervisor, Assistant Chief and Chief Nurse within NSO');
              await evaluate('document.getElementById("requestEmployee").value="5";document.getElementById("requestEmployee").dispatchEvent(new Event("change"))');
              assert.equal(await evaluate('document.getElementById("requestEmployeeName").value'),'Fixture Employee 5','Chief Nurse remains an eligible leave applicant');
            }
            assert(!await evaluate('[...document.querySelectorAll("input[readonly]")].some(input=>input.value==="Fixture Employee 6"||input.value==="F-6")'),'System Admin identity is not used as the nursing applicant');
            assert(await evaluate('!!document.querySelector("textarea[name=reason]")&&!!document.getElementById("requestDates")&&!!document.querySelector("button[name=action][value=submit]")'),'Admin selected applicant retains the independent request form');
            await evaluate('document.getElementById("requestDepartment").value="INVALID_DEPARTMENT";document.getElementById("requestDepartment").dispatchEvent(new Event("change"))');
            assert(await evaluate('document.getElementById("requestDepartment").value===""&&document.getElementById("requestEmployee").disabled&&document.getElementById("requestEmployee").value===""&&[...document.querySelectorAll("#requestEmployee option")].filter(option=>option.value).length===0&&["Id","Name","Position","Department"].every(suffix=>document.getElementById("requestEmployee"+suffix).value==="")'),'Unknown or empty Department resets Employee and clears applicant details');
          }
        }
        if(view==='print')assert(await evaluate('document.querySelector(".printable-leave").textContent.includes("APPROVED_FOR_HR_FILING")'),'Approved internal printable form');
        if(view==='off'){assert(await evaluate('document.querySelectorAll("[data-off-date]").length>=28'),'OFF multi-date calendar renders');await evaluate('document.querySelector("[data-off-date]").click()');assert(await evaluate('document.getElementById("requestDates").value.length>0'),'Calendar selection encodes preferred OFF date');assert(await evaluate('!document.querySelector("textarea[name=reason]").required&&document.querySelector("button[value=save_draft]")'),'OFF optional remarks and Save Draft control');}
        if(view==='off'){
          await evaluate('document.getElementById("requestOffCalendar").scrollIntoView({block:"center",behavior:"instant"})');
          async function point(day){return evaluate('(()=>{const button=[...document.querySelectorAll("[data-off-date]")].find(b=>Number(b.textContent)==='+day+');const r=button.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2};})()');}
          let first=await point(10),last=await point(14);first=await point(10);
          await call('Input.dispatchMouseEvent',{type:'mouseMoved',...first});await call('Input.dispatchMouseEvent',{type:'mousePressed',...first,button:'left',buttons:1,clickCount:1});await call('Input.dispatchMouseEvent',{type:'mouseMoved',...last,button:'left',buttons:1});await call('Input.dispatchMouseEvent',{type:'mouseReleased',...last,button:'left',buttons:0,clickCount:1});
          assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-off-date][aria-pressed=true]")].map(b=>Number(b.textContent))'),[1,10,11,12,13,14],'Dragging selects the complete OFF range and retains prior dates '+width+' '+theme);
          first=await point(14);last=await point(12);first=await point(14);
          await call('Input.dispatchMouseEvent',{type:'mousePressed',...first,button:'left',buttons:1,clickCount:1});await call('Input.dispatchMouseEvent',{type:'mouseMoved',...last,button:'left',buttons:1});await call('Input.dispatchMouseEvent',{type:'mouseReleased',...last,button:'left',buttons:0,clickCount:1});
          assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-off-date][aria-pressed=true]")].map(b=>Number(b.textContent))'),[1,10,11],'Reverse dragging selected OFF dates deselects that range');
          first=await point(20);last=await point(22);first=await point(20);
          await call('Emulation.setTouchEmulationEnabled',{enabled:true,maxTouchPoints:1});
          assert.equal(await evaluate('getComputedStyle(document.querySelector("[data-off-date]")).touchAction'),'none','Calendar drag prevents native touch scrolling');
          await call('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[first]});await call('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[last]});await call('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
          assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-off-date][aria-pressed=true]")].map(b=>Number(b.textContent))'),[1,10,11,20,21,22],'Touch dragging selects OFF dates');
        }
        if(view==='leave'){await evaluate('document.getElementById("requestMonth").value="2035-01";document.getElementById("requestMonth").dispatchEvent(new Event("change"));[10,11,12].forEach(day=>document.querySelector("[data-off-date=\\"2035-01-"+day+"\\"]").click())');assert(await evaluate('document.getElementById("leaveDuration").textContent.includes("3 days")&&document.getElementById("requestDates").value.includes("2035-01-11")'),'Leave range recalculates duration and dates');assert(await evaluate('!!document.querySelector("input[name=document]")'),'Application includes supporting-document upload');}
        if(theme==='dark')assert.deepEqual(await unreadableDarkText('main'),[],'Readable dark mode '+view);
        if(width===390)await screenshot('management-'+view+'-'+theme+'-390');
    }
    await viewport(1440);await call('Page.navigate',{url:base+'__management_schedule'});await ready();await pause(300);
    assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-form-section]")].map(e=>e.dataset.formSection)'),['header','configuration','rules','generation','summary','views','roster','validation','legend','actions'],'Approved form section order');
    assert(await evaluate('!document.getElementById("scheduleDutyList").hidden && document.getElementById("scheduleCalendar").hidden'),'Horizontal list is default');
    assert.equal(await evaluate('document.querySelectorAll("[data-setup-step]").length'),9,'Nine monthly scheduling steps');
    assert(await evaluate('document.getElementById("schedulePrevious").disabled&&document.getElementById("scheduleYear").min==="2026"'),'Current month disables previous navigation and past years');
    assert.deepEqual(await evaluate('[...document.getElementById("scheduleMonth").options].filter(option=>option.disabled).map(option=>option.value)'),['01','02','03','04','05','06','07','08','09'],'Past months disabled in current year');
    await evaluate('document.getElementById("schedulePrevious").onclick()');assert(await evaluate('document.getElementById("scheduleMonth").value==="10"&&document.getElementById("scheduleNotice").textContent.includes("current and future")'),'Past month navigation remains blocked if triggered programmatically');
    await evaluate('document.getElementById("scheduleNext").click()');await pause(300);assert(await evaluate('document.getElementById("scheduleMonth").value==="11"&&!document.getElementById("schedulePrevious").disabled'),'Future month remains selectable');
    await evaluate('document.getElementById("schedulePrevious").click()');await pause(300);assert(await evaluate('document.getElementById("scheduleMonth").value==="10"&&document.getElementById("schedulePrevious").disabled'),'Returning from future stops at current month');
    await evaluate('document.getElementById("scheduleYear").value="2027";document.getElementById("scheduleYear").dispatchEvent(new Event("change"))');await pause(300);assert(await evaluate('[...document.getElementById("scheduleMonth").options].every(option=>!option.disabled)'),'All months of future years are available');
    await evaluate('document.getElementById("scheduleMonth").value="01";document.getElementById("scheduleMonth").dispatchEvent(new Event("change"))');await pause(300);
    await evaluate('document.getElementById("scheduleYear").value="2026";document.getElementById("scheduleYear").dispatchEvent(new Event("change"))');await pause(300);assert(await evaluate('document.getElementById("scheduleMonth").value==="10"&&document.getElementById("scheduleYear").value==="2026"'),'Changing from future January to current year clamps to current month');
    assert(await evaluate('!document.getElementById("schedulePreviousHistory")&&!document.getElementById("scheduleSaveHistorical")'),'Past-month controls removed from Monthly Scheduling');

    await evaluate('document.getElementById("scheduleEmployeeSetup").open=true');assert.deepEqual(await evaluate('[...document.querySelectorAll("#scheduleSetupRoster .roster-person strong")].map(e=>e.textContent)'),employees.map(employee=>employee.name),'All selected-department employees load automatically, nurses before nursing attendants');
    assert(await evaluate('!document.getElementById("scheduleMasterPick")&&!document.getElementById("scheduleAddEmployee")&&!document.querySelector("[data-remove-employee]")'),'Automatic department roster has no manual employee selection or removal controls');
    assert.equal(await evaluate('[...document.querySelectorAll("[data-setup-step]")].find(button=>button.dataset.setupStep==="3").textContent.replace(/^3/,"").trim()'),'Load Department Employees','Third step describes the automatic department roster');
    await evaluate('document.getElementById("scheduleSetupNext").click();document.getElementById("scheduleSetupNext").click();document.getElementById("scheduleSetupNext").click()');assert(await evaluate('document.getElementById("scheduleRequestSetup").open'),'Step four opens independent request-off and leave review');await evaluate('document.getElementById("scheduleSetupBack").click()');assert.equal(await evaluate('document.querySelectorAll("#scheduleDutyList tbody tr").length'),employees.length,'Step navigation retains all department employees');
    assert(await evaluate('document.getElementById("schedulePolicyNote").textContent.includes("Fixed daytime")'),'Applicable fixed policy displayed');
    await evaluate('document.getElementById("scheduleMode").value="MANUAL";document.getElementById("scheduleMode").dispatchEvent(new Event("change"))');
    assert(await evaluate('document.getElementById("scheduleGenerate").hidden&&!document.getElementById("scheduleManual").hidden'),'Manual mode displays its control');
    await evaluate('document.getElementById("scheduleManual").click()');await pause(150);await evaluate('document.getElementById("scheduleListView").click()');
    assert.equal(await evaluate('document.querySelectorAll("[data-roster-cell]").length'),31*employees.length,'Monthly editable horizontal list includes all department employees');
    await evaluate('document.querySelector("[data-roster-cell]").click()');assert(await evaluate('document.getElementById("scheduleDialog").open'),'Touch/click cell opens duty editor');
    await evaluate('document.querySelector("#scheduleDialog [name=duty]").value="8";document.querySelector("#scheduleDialog [name=offReview]").value="Mandatory shift coverage reviewed";document.querySelector("#scheduleDialog form").dispatchEvent(new Event("submit",{cancelable:true}))');
    assert(await evaluate('document.querySelector("[data-roster-cell]").textContent.includes("8")'),'List updates immediately');assert(await evaluate('document.querySelector(".schedule-assignment").textContent.includes("8")'),'Calendar shares edited assignment');
    await evaluate('document.getElementById("scheduleHoursView").click()');assert(await evaluate('!document.getElementById("scheduleHoursPanel").hidden && document.getElementById("scheduleSummary").textContent.includes("8")'),'Hours view recalculates from cell edit');
    await evaluate('document.getElementById("scheduleCalendarView").click()');assert(await evaluate('!document.getElementById("scheduleCalendar").hidden'),'Calendar selector works');
    await evaluate('document.getElementById("scheduleListView").click();document.getElementById("scheduleValidate").click()');await pause(100);assert(await evaluate('document.getElementById("scheduleReview").open&&document.getElementById("scheduleConflicts").textContent.includes("no assignment")'),'Validation reports actual incomplete roster');
    await evaluate('document.querySelector("[data-roster-cell]").focus()');await key('ArrowRight');assert(await evaluate('document.activeElement.dataset.date.endsWith("02")'),'Keyboard navigates date cells');
    await evaluate('window.exported=[];HTMLAnchorElement.prototype.click=function(){window.exported.push({name:this.download,url:this.href});};document.getElementById("scheduleExport").click()');assert(await evaluate('exported[0].name.endsWith(".xlsx")'),'Excel exports actual XLSX');assert(await evaluate('(async()=>{const bytes=new Uint8Array(await(await fetch(exported[0].url)).arrayBuffer());return bytes[0]===80&&bytes[1]===75;})()'),'Excel export creates a ZIP workbook');
    await evaluate('document.getElementById("scheduleMode").value="AUTO";document.getElementById("scheduleMode").dispatchEvent(new Event("change"));document.getElementById("scheduleGenerate").click()');await pause(100);
    assert(await evaluate('document.getElementById("scheduleDialog").open'),'Generate opens generation controls');
    await evaluate('document.querySelector("#scheduleDialog [name=replace]").checked=true;document.querySelector("#scheduleDialog form").dispatchEvent(new Event("submit",{cancelable:true}))');
    for(let attempt=0;attempt<100;attempt++){if(await evaluate('document.getElementById("scheduleApp").getAttribute("aria-busy")==="false"'))break;await pause(100);}
    assert.equal(await evaluate('[...document.querySelectorAll("[data-roster-cell]")].filter(c=>c.querySelector(".schedule-duty")).length'),31*employees.length,'Real worker generates all monthly assignments');
    assert.equal(await evaluate('document.querySelector("#scheduleDutyList tbody tr td:last-child").textContent'), '176','Generated fixed roster respects reviewed OFF exception and exact paid hours');
    for(const width of [1440,768,390,320])for(const theme of ['light','dark']){await viewport(width);await evaluate('NursingTheme.set('+JSON.stringify(theme)+');document.getElementById("scheduleEmployeeSetup").open=false;document.getElementById("scheduleRequestSetup").open=false;window.scrollTo(0,0)');await pause(100);assert(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'Monthly form no page overflow '+width+' '+theme);assert.equal(await evaluate('document.querySelectorAll("#scheduleDutyList tbody tr").length'),employees.length,'All eligible department employees appear once');layouts++;await screenshot('monthly-form-'+width+'-'+theme);}
    for(const width of [1440,390,320]){await viewport(width);await evaluate('document.querySelector(".pattern-options").hidden=false;document.getElementById("scheduleFixedPattern").hidden=true;window.scrollTo(0,0)');assert(await evaluate('(()=>{const cards=[...document.querySelectorAll(".pattern-options-row .pattern-option")].map(e=>e.getBoundingClientRect());return cards.length===2&&Math.abs(cards[0].top-cards[1].top)<1&&cards[1].left>=cards[0].right&&cards.every(r=>r.width>100)})()'),'Duty choices stay aligned side by side '+width);await screenshot('shift-options-'+width);}
    await viewport(1440);await evaluate('NursingTheme.set("light");document.getElementById("scheduleReset").click()');await pause(100);await evaluate('document.querySelector("#scheduleDialog form").dispatchEvent(new Event("submit",{cancelable:true}))');await pause(100);
    assert.equal(await evaluate('document.querySelectorAll("#scheduleDutyList .schedule-duty").length'),0,'Reset clears editable draft assignments');
    await evaluate('document.getElementById("scheduleMode").value="MANUAL";document.getElementById("scheduleMode").dispatchEvent(new Event("change"));document.getElementById("scheduleManual").click()');await pause(100);assert.equal(await evaluate('document.querySelectorAll("[data-roster-cell]").length'),31*employees.length,'Manual blank roster keeps full department month matrix');
    await evaluate('document.querySelectorAll("[data-roster-cell]")[1].click();document.querySelector("#scheduleDialog [name=duty]").value="8";document.querySelector("#scheduleDialog form").dispatchEvent(new Event("submit",{cancelable:true}));document.querySelector("[data-workflow=save]").click()');await pause(200);
    assert.equal(scheduleData.payload.assignments[1]['2026-10-02'],'8','Save Draft sends edited state to existing API');assert.equal(scheduleData.version,1,'Save response retains its version');
    await evaluate('document.getElementById("scheduleReload").click()');await pause(200);assert(await evaluate('document.querySelectorAll("[data-roster-cell]")[1].textContent.includes("8")'),'Saved roster restores after reload');
    await evaluate('window.printCalls=0;window.print=()=>printCalls++;document.getElementById("schedulePdf").click();document.getElementById("schedulePrint").click();document.getElementById("schedulePrintSummary").click()');assert.equal(await evaluate('printCalls'),3,'PDF, roster and summary controls all open printing');await evaluate('window.dispatchEvent(new Event("afterprint"))');
    await evaluate('document.querySelector("[data-workflow=submit]").click()');await pause(100);assert(await evaluate('document.getElementById("scheduleDialog").textContent.includes("independent approval")'),'Finalization clearly preserves mandatory approval');await evaluate('document.getElementById("scheduleDialogClose").click()');
    scheduleData.payload.policy.mode='ROTATING';scheduleData.payload.assignments={1:{'2026-10-01':'PM'}};scheduleData.payload.rules.minRest=0;await evaluate('document.getElementById("scheduleReload").click()');await pause(200);await evaluate('document.querySelectorAll("[data-roster-cell]")[1].click();document.querySelector("#scheduleDialog [name=duty]").value="AM";document.querySelector("#scheduleDialog form").dispatchEvent(new Event("submit",{cancelable:true}))');assert(await evaluate('document.querySelector("#scheduleDialog [data-error]").textContent.includes("PM")'),'Manual PM-to-AM edit blocked before changing state');assert(!await evaluate('document.querySelectorAll("[data-roster-cell]")[1].textContent.includes("AM")'),'Prohibited assignment remains uncommitted');await evaluate('document.getElementById("scheduleDialogClose").click()');
    const activeAssignments=structuredClone(scheduleData.payload.assignments);
    scheduleData.payload.staff.push({...employees[0],id:99,name:'Fixture Former Nurse',department:'WARD',active:false});scheduleData.payload.assignments[99]={'2026-10-04':'8'};scheduleData.payload.locks={99:{'2026-10-04':true}};scheduleData.payload.requestOffOverrides={99:{'2026-10-04':{reason:'Former employee reviewed'}}};scheduleData.payload.requests.push({employee_id:99,date:'2026-10-05',type:'REQUEST_OFF',status:'APPROVED'});scheduleData.payload.internalLeaveIndicators=[{employee_id:99,date:'2026-10-06',leave_type:'VL'}];scheduleData.payload.exchange_refs={99:{'2026-10-04':'EX-PROTECTED'}};
    await evaluate('document.getElementById("scheduleReload").click()');await pause(200);assert.equal(await evaluate('document.querySelectorAll("[data-remove-inactive]").length'),1,'Only former inactive employee has a removal control');await evaluate('document.querySelector("[data-remove-inactive]").click()');assert(await evaluate('!document.getElementById("scheduleDialog").open&&document.getElementById("scheduleNotice").textContent.includes("protected exchange")'),'Inactive employee with approved exchange stays protected');
    delete scheduleData.payload.exchange_refs[99];scheduleData.status='PUBLISHED';await evaluate('document.getElementById("scheduleReload").click()');await pause(200);await evaluate('document.querySelector("[data-remove-inactive]").click()');assert(await evaluate('!document.getElementById("scheduleDialog").open&&document.querySelectorAll("#scheduleDutyList tbody tr").length===3'),'Published roster cannot remove former employees');
    scheduleData.status='DRAFT';await evaluate('document.getElementById("scheduleReload").click()');await pause(200);await evaluate('document.querySelector("[data-remove-inactive]").click()');assert(await evaluate('document.getElementById("scheduleDialog").open&&document.getElementById("scheduleDialog").textContent.includes("Fixture Former Nurse")'),'Inactive draft removal asks for confirmation');await evaluate('document.querySelector("#scheduleDialog [data-cancel]").click()');await pause(100);assert.equal(await evaluate('document.querySelectorAll("#scheduleDutyList tbody tr").length'),3,'Cancelling keeps the former employee and duties');
    await evaluate('document.querySelector("[data-remove-inactive]").click();document.querySelector("#scheduleDialog form").dispatchEvent(new Event("submit",{cancelable:true}))');await pause(100);assert.deepEqual(await evaluate('[...document.querySelectorAll("#scheduleSetupRoster .roster-person strong")].map(e=>e.textContent)'),employees.map(employee=>employee.name),'Removing former employee keeps every active department employee');assert(await evaluate('!document.querySelector("[data-remove-inactive]")&&!document.getElementById("scheduleRequestsList").textContent.includes("Fixture Former Nurse")'),'Removed former employee no longer appears in availability review');
    await evaluate('document.querySelector("[data-workflow=save]").click()');await pause(200);assert.deepEqual(scheduleData.payload.assignments,activeAssignments,'Removing former employee preserves all active assignments');for(const field of ['locks','requestOffOverrides','exchange_refs'])assert(!scheduleData.payload[field]?.[99],'Former employee '+field+' removed from draft');assert(!scheduleData.payload.staff.some(employee=>employee.id===99)&&!scheduleData.payload.requests.some(request=>request.employee_id===99)&&!scheduleData.payload.internalLeaveIndicators.some(request=>request.employee_id===99),'Only former employee and draft availability indicators removed');
    await call('Emulation.setEmulatedMedia',{media:'print'});assert(await evaluate('getComputedStyle(document.getElementById("scheduleLegend")).display!=="none"'),'Printed roster retains legend');assert(await evaluate('getComputedStyle(document.getElementById("scheduleCalendar")).display==="none"'),'List print respects selected view');fs.writeFileSync(path.join(artifacts,'monthly-roster.pdf'),Buffer.from((await call('Page.printToPDF',{printBackground:true,landscape:true})).data,'base64'));
    await call('Emulation.setEmulatedMedia',{media:'print'});await call('Page.navigate',{url:base+'__management_print'});await ready();assert(await evaluate('getComputedStyle(document.querySelector(".print-title")).display!=="none"'),'Printed hospital approval title visible');assert(await evaluate('getComputedStyle(document.querySelector(".request-controls")).display==="none"'),'Print omits interactive controls');
    fs.writeFileSync(path.join(artifacts,'internal-leave.pdf'),Buffer.from((await call('Page.printToPDF',{printBackground:true})).data,'base64'));
    assert.deepEqual(exceptions,[]);console.log('Passed '+layouts+' management layouts, System Admin department-first filtered applicant encoding and immutable draft resume, approved monthly section order, worker generation, reset, draft save/reload, editing, hours, validation, exports and printable internal approval. Artifacts: '+artifacts);await call('Browser.close');
  }finally{socket?.close();browser.kill();for(const waiter of pending.values())clearTimeout(waiter.timer);}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
