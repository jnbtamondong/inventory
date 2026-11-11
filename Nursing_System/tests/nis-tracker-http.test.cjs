const assert=require('node:assert/strict'),path=require('node:path'),fs=require('node:fs'),net=require('node:net');
const {spawn}=require('node:child_process');
const {chromium}=require('C:/Users/pc/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const root=path.resolve(__dirname,'..'),token=process.env.NIS_TEST_TOKEN,port=33318;
 assert(/^nis_tracker_test_[a-f0-9]{10}$/.test(process.env.NIS_TEST_DATABASE||''));
 await new Promise((resolve,reject)=>{const test=net.createServer();test.once('error',reject);test.listen(port,'127.0.0.1',()=>test.close(resolve));});
 const server=spawn('C:/xampp/php/php.exe',['-d','session.save_path='+require('node:os').tmpdir(),'-S',`127.0.0.1:${port}`,'tests/nis-tracker-router.php'],{cwd:root,env:process.env,windowsHide:true,stdio:['ignore','pipe','pipe']});
 let logs='';server.stdout.on('data',data=>logs+=data);server.stderr.on('data',data=>logs+=data);
 const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const base=`http://127.0.0.1:${port}`;let ready=false;
  for(let i=0;i<30;i++){try{await fetch(base+'/__test_session');ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
  assert(ready,'test HTTP server starts');
  const context=await browser.newContext({reducedMotion:'reduce'}),page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route(/^https:\/\//,route=>route.abort());
  const login=await context.request.get(base+'/__test_login?id=1',{headers:{'X-Test-Token':token}});assert.equal(login.status(),200,'synthetic session bootstrap: '+await login.text()+' '+logs);const session=await login.json();
  const patients=await context.request.get(base+'/Nursing_System/api/nis_trackers.php?tracker=patients');assert.equal(patients.status(),200);const data=await patients.json();
  assert(data.rows.some(row=>row.patient_id===9)&&!data.rows.some(row=>row.patient_id===10),'HTTP API protects outgoing vs unrelated patients');
  const legacy=await context.request.get(base+'/Nursing_System/api/get_patients.php');assert.equal(legacy.status(),200);const queue=await legacy.json();assert(queue.some(row=>row.patient_id===1)&&!queue.some(row=>row.patient_id===6),'existing queue API retains its response shape with department filtering');
  const denied=await context.request.get(base+'/Nursing_System/pages/opd_patients.php',{maxRedirects:0});assert([302,403].includes(denied.status()),'direct unrelated department URL denied');
  const wardDenied=await context.request.get(base+'/Nursing_System/pages/ward_dashboard.php?module=ward',{maxRedirects:0});assert.equal(wardDenied.status(),403,'query parameter cannot select another unit');
  const chartDenied=await context.request.get(base+'/Nursing_System/pages/patient_detail.php?id=9',{maxRedirects:0});assert.equal(chartDenied.status(),403,'outgoing tracking cannot bypass receiving chart guard');
  const write=await context.request.post(base+'/Nursing_System/api/nis_trackers.php?tracker=beds',{headers:{'X-CSRF-Token':session.csrf_token},data:{}});assert.equal(write.status(),405,'trackers reject mutation requests');
  const beds=await (await context.request.get(base+'/Nursing_System/api/nis_trackers.php?tracker=beds')).json();assert(!JSON.stringify(beds).includes('HN-'),'HTTP bed data has no patient identifiers');
  for(const [width,height] of [[1440,1000],[768,1024],[390,844]]){
   await page.setViewportSize({width,height});
   for(const route of ['patient_live_tracker.php','bed_tracker.php']){
    const response=await page.goto(base+'/Nursing_System/pages/'+route);assert.equal(response.status(),200);
    await page.waitForSelector('body.ns-unified-sidebar');
    for(const theme of ['light','dark']){
     await page.evaluate(theme=>window.NursingTheme.set(theme),theme);
     assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'no document overflow at '+width);
     const sidebar=page.locator('.nursing-sidebar');assert.equal(await sidebar.locator('.has-submenu').count(),1);
     for(const title of ['ER Patient Tracker','Bed Tracker','Settings','Back to Main Menu'])assert.equal(await sidebar.locator(`.menu>li>a[title="${title}"]`).count(),1);
     if(width===390){await page.locator('.mobile-menu-toggle').click();assert(await sidebar.isVisible());await page.keyboard.press('Escape');}
     await page.screenshot({path:path.join(root,`outputs/nis-navigation-20261026/${route}-${width}-${theme}.png`),fullPage:true});
    }
   }
  }
  await page.goto(base+'/Nursing_System/pages/patient_live_tracker.php?patient_id=9');assert.equal(await page.locator('a[href="patient_detail.php?id=9"]').count(),0,'no clinical chart link for outgoing patient');
  assert.equal(await page.locator('.nis-panel').last().locator('tbody tr').count(),1,'authorized encounter-specific movement history');
  const sidebar=page.locator('.nursing-sidebar');assert.equal(await sidebar.getByTitle('Settings',{exact:true}).getAttribute('href'),'account_settings.php');assert.equal(await sidebar.getByTitle('Back to Main Menu',{exact:true}).getAttribute('href'),'system_menu.php');
  const still=await context.request.get(base+'/__test_session',{headers:{'X-Test-Token':token}});assert.equal((await still.json()).user_id,1,'tracking and navigation links preserve authenticated session');
  await context.request.get(base+'/__test_login?id=9',{headers:{'X-Test-Token':token}});
  const chief=await context.request.get(base+'/Nursing_System/api/nis_trackers.php?tracker=patients');assert.equal((await chief.json()).total,12,'leadership HTTP scope');
  await context.request.get(base+'/__test_login?id=10',{headers:{'X-Test-Token':token}});const refused=await context.request.get(base+'/Nursing_System/api/nis_trackers.php?tracker=patients');assert.equal(refused.status(),403,'ungranted API permission rejected');
  assert.deepEqual(errors,[]);assert(!/PHP (Fatal error|Warning|Parse error)/.test(logs),logs);
  console.log('PASS authenticated HTTP trackers, API denial/write protection, department URLs, session preservation, desktop/tablet/mobile, light/dark, no PHI in bed responses or JavaScript errors');
 }finally{await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
