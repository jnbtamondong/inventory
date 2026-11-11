const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'C:/Users/Jeeve Aron/AppData/Local/npm-cache/_npx/e41f203b7505f1fb/node_modules/playwright');
const E = require('../public/js/schedule-engine.js');
const base = 'http://localhost/Nursing_System/';
const artifact = name => path.join(__dirname, 'artifacts', name);
const employees = [
    {id:12,name:'Ana Santos',position:'Nurse II',pattern:'8',group:'A',preference:'ANY',active:true},
    {id:13,name:'Mikaela Reyes',position:'ER Clerk',pattern:'8',group:'B',preference:'ANY',active:true},
    {id:14,name:'Alexandria Delos Santos',position:'Nursing Attendant I',category:'NA',pattern:'8',group:'B',preference:'ANY',active:true}
];
const emptyPayload = () => ({staff:structuredClone(employees),assignments:[],requests:[],mode:'MANUAL',
    rules:{minRest:12,maxConsecutive:6,minOff:1,maxOff:31,offPer7:1,reviewed:true,staffingTargets:{TRIAGE:0,NURSE:0,NA:0},staffingShifts:[],coverage:Object.fromEntries(E.clinical.map(code=>[code,{min:0,max:3}]))}});
async function fixture(browser,personal=false) {
    const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce',acceptDownloads:true});
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    const html=cp.execFileSync('C:/xampp/php/php.exe',['-n',path.join(__dirname,'schedule-fixture.php'),...(personal?['--personal']:[])],{encoding:'utf8'});
    let saved={exists:false,status:'DRAFT',version:0,submitted_by:null,payload:emptyPayload(),master:structuredClone(employees),boundaries:[],summary:[]};
    if(personal){saved.exists=true;saved.status='PUBLISHED';saved.version=4;saved.master=[];saved.payload.staff=[employees[0]];saved.payload.assignments={12:{'2026-10-01':'AM','2026-10-02':'OFF'}};}
    const posts=[],gets=[];
    await page.route('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',route=>route.fulfill({contentType:'text/css',body:fs.readFileSync(artifact('bootstrap-5.3.3.css'))}));
    await page.route('https://cdnjs.cloudflare.com/**',route=>route.fulfill({contentType:'text/css',body:''}));
    await page.route('**/fontsource/**',route=>route.abort());
    const url=base+'pages/__schedule_ui_fixture';
    await page.route(url,route=>route.fulfill({contentType:'text/html',body:html}));
    await page.route('**/api/schedule_management.php**',async route=>{
        const request=route.request();
        if(request.method()==='POST'){
            const body=request.postDataJSON();posts.push(body);
            assert.equal(request.headers()['x-csrf-token'],'synthetic-test-token');
            assert(!Array.isArray(body.payload.assignments));
            saved={...saved,exists:true,version:saved.version+1,payload:body.payload,
                status:({submit:'FOR APPROVAL',approve:'APPROVED',publish:'PUBLISHED',return:'RETURNED FOR REVISION'})[body.action]||'DRAFT',submitted_by:body.action==='submit'?1:saved.submitted_by};
        } else gets.push(new URL(request.url()).searchParams);
        if(new URL(request.url()).searchParams.get('action')==='history'){
            await route.fulfill({json:{success:true,data:[{version:1,action:'save',changed_by_name:'Fixture Head',created_at:'2026-10-03 08:00:00',remarks:'Initial draft',changes:[]}]}});return;
        }
        await route.fulfill({json:{success:true,data:saved}});
    });
    await page.goto(url,{waitUntil:'networkidle'});
    await page.waitForFunction(()=>document.querySelector('#scheduleApp').getAttribute('aria-busy')==='false');
    return {page,errors,posts,gets,setSubmitter: id=>{saved.submitted_by=id;}};
}
(async()=>{
    const browser=await chromium.launch({executablePath:process.env.BROWSER_PATH||'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
    const checks=[];
    try{
        const {page,errors,posts,gets,setSubmitter}=await fixture(browser);
        assert.equal(await page.locator('.ns-workflow,.ns-workflow-placeholder').count(),0);
        assert.equal(await page.locator('.sidebar').count(),1,'Schedule must have only its own sidebar');
        assert.equal(await page.locator('.schedule-sidebar').count(),1);
        assert.equal(await page.locator('.schedule-sidebar nav a[href]:not([href^="#"])').count(),0,'Schedule navigation must remain within scheduling');
        assert.equal(await page.locator('.schedule-sidebar nav a').count(),1);
        assert((await page.locator('.schedule-sidebar nav').innerText()).includes('Schedule Management'));
        await page.evaluate(() => localStorage.setItem('nursingSidebarCollapsed','1'));
        await page.locator('.schedule-sidebar .sidebar-toggle').click();
        assert.equal(await page.evaluate(() => localStorage.getItem('nursingScheduleSidebarCollapsed')),'1');
        await page.locator('.schedule-sidebar .sidebar-toggle').click();
        assert.equal(await page.evaluate(() => localStorage.getItem('nursingScheduleSidebarCollapsed')),'0');
        assert.equal(await page.evaluate(() => localStorage.getItem('nursingSidebarCollapsed')),'1','Scheduling must not change the Nursing sidebar state');
        await page.locator('.schedule-sidebar a[href="#scheduleApp"]').click();
        assert(!(await page.locator('body').evaluate(node => node.classList.contains('sidebar-collapsed'))),'Schedule section navigation must not collapse the desktop sidebar');
        assert.equal(await page.locator('#scheduleSetupRoster .roster-person').count(),3);
        assert((await page.locator('#scheduleSetupRoster').innerText()).includes('ER Clerk'));
        assert.equal(await page.locator('#scheduleStaffing').count(),0);
        assert.equal(await page.locator('#scheduleRules').count(),0);
        assert(!await page.locator('#scheduleWorkspace').isVisible());
        for(const width of [1440,390,320]) {
            await page.setViewportSize({width,height:1000});
            for(const step of [1,2,3,4]) {
                await page.locator('[data-setup-step="'+step+'"]').click();
                await page.screenshot({path:artifact('schedule-setup-'+step+'-'+width+'.png'),fullPage:true});
                assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Setup overflow at '+width+' step '+step);
            }
        }
        await page.setViewportSize({width:1440,height:1000});
        await page.locator('[data-setup-step="1"]').click();
        await page.locator('#scheduleSetupNext').click();
        await page.locator('#scheduleAddRequest').click();
        await page.locator('#scheduleDialog [name=date]').fill('2026-10-04');
        await page.locator('#scheduleDialog [name=status]').selectOption('APPROVED');
        await page.locator('#scheduleDialog [name=remarks]').fill('Approved request off');
        await page.getByRole('button',{name:'Save request',exact:true}).click();
        assert.equal(await page.locator('#scheduleRequestsList tr').count(),1);
        await page.locator('#scheduleSetupNext').click();
        await page.locator('[name=schedulePattern][value="12"]').check();
        await page.locator('#scheduleSetupNext').click();
        await page.locator('#scheduleGenerate').click();
        await page.getByRole('button',{name:'Generate schedule',exact:true}).click();
        await page.waitForFunction(()=>document.querySelector('#scheduleNotice').textContent.startsWith('Generation complete'),{},{timeout:120000});
        assert.equal(await page.locator('#scheduleSummary .COMPLETE').count(),3);
        assert.equal(await page.locator('.schedule-day').count(),31);
        assert.equal(await page.locator('#scheduleStaffLists .staff-person').count(),3);
        assert.equal(await page.locator('[data-edit-duty="12"][data-date="2026-10-04"] .schedule-duty').getAttribute('data-duty'),'REQUEST_OFF');
        checks.push('four-step setup, all staff with positions, approved request and 176-hour generation');
        await page.locator('[data-edit-duty="12"][data-date="2026-10-04"]').click();
        await page.locator('#scheduleDialog [name=duty]').selectOption('DAY12');
        await page.getByRole('button',{name:'Apply assignment',exact:true}).click();
        assert((await page.locator('[data-error]').innerText()).includes('approved absence'));
        await page.locator('#scheduleDialogClose').click();
        const off=page.locator('[data-edit-duty="12"]').filter({has:page.locator('[data-duty=OFF]')}).first();
        const offDate=await off.getAttribute('data-date');
        await off.click();
        await page.locator('#scheduleDialog [name=duty]').selectOption('ADJUST8');
        assert((await page.locator('[data-duty-summary]').innerText()).includes('184 / 176'));
        await page.getByRole('button',{name:'Apply assignment',exact:true}).click();
        assert.equal(await page.locator('#scheduleSummary .EXCESS').count(),1);
        await page.locator('[data-edit-duty="12"][data-date="'+offDate+'"]').click();
        await page.locator('#scheduleDialog [name=duty]').selectOption('OFF');
        await page.getByRole('button',{name:'Apply assignment',exact:true}).click();
        assert.equal(await page.locator('#scheduleSummary .COMPLETE').count(),3);
        checks.push('manual editing after generation, live excess/complete and approved absence protection');
        await page.locator('[data-workflow=save]').click();
        await page.waitForFunction(()=>document.querySelector('#scheduleVersion').textContent==='Version 1');
        assert.equal(posts[0].payload.staff.length,3);
        assert.deepEqual(posts[0].payload.rules.staffingShifts,[]);
        assert(Object.values(posts[0].payload.rules.coverage).every(v=>v.min===0));
        const downloadEvent=page.waitForEvent('download');await page.locator('#scheduleExportSummary').click();
        const download=await downloadEvent;const csv=fs.readFileSync(await download.path(),'utf8');
        assert(csv.includes('Position')&&csv.includes('Target hours')&&csv.includes('"176"'));
        checks.push('draft persistence, CSRF, no quotas and complete hours export');
        for(const width of [1440,1024,768,390,320]){
            await page.setViewportSize({width,height:1000});
            await page.evaluate(()=>scrollTo(0,0));
            await page.screenshot({path:artifact('schedule-calendar-new-'+width+'.png'),fullPage:true});
            assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Page overflow at '+width);
            await page.locator('[data-edit-date="2026-10-01"]').click();
            const box=await page.locator('#scheduleDialog').boundingBox();
            assert(box.x>=0 && box.x+box.width<=width+1 && box.height<=1000,'Dialog bounded at '+width);
            await page.screenshot({path:artifact('schedule-duty-new-'+width+'.png')});
            await page.locator('#scheduleDialogClose').click();
        }
        checks.push('desktop/tablet/mobile layout and bounded duty forms');
        await page.setViewportSize({width:1440,height:1000});
        await page.locator('[data-workflow=submit]').click();
        await page.locator('#scheduleDialog button[type=submit]').click();
        await page.waitForFunction(()=>document.querySelector('#scheduleStatus').textContent==='FOR APPROVAL');
        assert(await page.locator('[data-workflow=approve]').isDisabled());
        setSubmitter(2);
        await page.locator('#scheduleReload').click();
        await page.waitForFunction(()=>document.querySelector('#scheduleApp').getAttribute('aria-busy')==='false');
        await page.locator('#scheduleResume').click();
        await page.locator('[data-workflow=approve]').click();
        await page.locator('#scheduleDialog [name=ack]').check();
        await page.locator('#scheduleDialog button[type=submit]').click();
        await page.waitForFunction(()=>document.querySelector('#scheduleStatus').textContent==='APPROVED');
        await page.locator('[data-workflow=publish]').click();
        await page.locator('#scheduleDialog button[type=submit]').click();
        await page.waitForFunction(()=>document.querySelector('#scheduleStatus').textContent==='PUBLISHED');
        assert(await page.locator('[data-edit-date]').first().isDisabled());
        checks.push('submit, independent approval, publication and final edit lock');
        await page.locator('#scheduleMonth').selectOption('02');
        await page.waitForFunction(()=>document.querySelector('#scheduleApp').getAttribute('aria-busy')==='false');
        assert.equal(gets.at(-1).get('month'),'2026-02');
        await page.locator('#scheduleDepartment').selectOption('WARD_3F');
        await page.waitForFunction(()=>document.querySelector('#scheduleApp').getAttribute('aria-busy')==='false');
        assert.equal(gets.at(-1).get('department'),'WARD_3F');
        assert.deepEqual(errors,[]);
        checks.push('department/month changes automatically load data without a staffing form');
        const manual=await fixture(browser);
        await manual.page.locator('[data-setup-step="4"]').click();
        await manual.page.locator('#scheduleManual').click();
        assert.equal(await manual.page.locator('#scheduleStaffLists .staff-person').count(),3);
        assert.equal(await manual.page.locator('#scheduleSummary .DEFICIT').count(),3);
        await manual.page.locator('[data-edit-date="2026-10-01"]').click();
        await manual.page.locator('#scheduleDialog [name=employee]').selectOption('14');
        await manual.page.locator('#scheduleDialog [name=duty]').selectOption('AM');
        await manual.page.getByRole('button',{name:'Apply assignment',exact:true}).click();
        assert((await manual.page.locator('#scheduleSummary tr').last().innerText()).includes('168'));
        await manual.page.locator('[data-edit-duty="14"]').click();
        await manual.page.locator('#scheduleDialog [name=duty]').selectOption('');
        await manual.page.getByRole('button',{name:'Apply assignment',exact:true}).click();
        assert.equal(await manual.page.locator('[data-edit-duty]').count(),0);
        assert.deepEqual(manual.errors,[]);
        checks.push('manual mode starts with all staff; assignment removal recalculates remaining hours');
        const personal=await fixture(browser,true);
        assert(await personal.page.locator('#scheduleWorkspace').isVisible());
        assert.equal(await personal.page.locator('#scheduleSetup').count(),0);
        assert.equal(await personal.page.locator('#scheduleSummary tr').count(),1);
        assert(await personal.page.locator('[data-edit-date]').first().isDisabled());
        assert.deepEqual(personal.errors,[]);
        checks.push('staff view remains personal and read-only');
        checks.forEach(c=>console.log('PASS '+c));
        console.log(checks.length+' browser workflow checks passed.');
    }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
