const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {chromium}=require('C:/Users/pc/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const root=path.resolve(__dirname,'..');const html=execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'nis-sidebar-fixture.php')],{encoding:'utf8'});
 const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',route=>{
   const url=new URL(route.request().url());if(url.origin!=='http://nis.test')return route.abort();
   if(url.pathname.startsWith('/Nursing_System/pages/'))return route.fulfill({contentType:'text/html',body:html});
   const relative=url.pathname.replace('/Nursing_System/','');if(!relative.startsWith('public/')||relative.includes('..'))return route.abort();
   const file=path.join(root,relative);if(!fs.existsSync(file))return route.abort();const ext=path.extname(file);
   return route.fulfill({contentType:{'.css':'text/css','.js':'text/javascript','.png':'image/png','.svg':'image/svg+xml'}[ext]||'application/octet-stream',body:fs.readFileSync(file)});
  });
  await page.addInitScript(()=>{if(!sessionStorage.getItem('sidebar-test-started')){localStorage.setItem('nursingSidebarCollapsed','1');localStorage.removeItem('nursingSidebarCollapsedV2');sessionStorage.setItem('sidebar-test-started','1');}});
  await page.goto('http://nis.test/Nursing_System/pages/dashboard.php');await page.waitForSelector('body.ns-unified-sidebar');
  const sidebar=page.locator('.nursing-sidebar'),toggle=sidebar.locator('.sidebar-toggle');
  assert.equal(await page.locator('body').evaluate(e=>e.classList.contains('sidebar-collapsed')),false,'old automatic collapse preference does not hide menu');
  assert.equal(Math.round((await sidebar.boundingBox()).width),248,'expanded desktop width');
  assert(await sidebar.locator('.menu a>span:not(.ns-icon-host)').first().isVisible(),'menu labels visible');
  for(const label of ['Patient Tracker','Bed Tracker','Settings','Back to Main Menu','Reports']){
   assert.equal(await sidebar.locator(`.menu>li>a[title="${label}"]`).count(),1,'single shared menu: '+label);
  }
  assert.equal(await sidebar.locator('a[title="Patient Care"]').count(),0,'obsolete patient-care menu removed');
  for(const href of await sidebar.locator('a[href]:not([href="#"])').evaluateAll(links=>links.map(a=>a.getAttribute('href')))){
   assert(fs.existsSync(path.join(root,'pages',href.split('?')[0])),'sidebar destination exists: '+href);
  }
  for(const list of await sidebar.locator('.submenu').all()){
   const hrefs=await list.locator('a[href]').evaluateAll(links=>links.map(a=>a.getAttribute('href')));
   assert.equal(new Set(hrefs).size,hrefs.length,'department submenu has no duplicate destinations');
  }
  assert.equal(await sidebar.locator('a[href^="nursing_notes.php"],a[href="newborn_record.php"],a[href="postpartum_monitoring.php"]').count(),0,'placeholder pages are not presented as functioning tools');
  for(const department of ['Emergency Room','Outpatient Department','OB/Pedia Ward','Medical/Surgery Ward','Operating Room','Labor Room / Delivery Room','MICU','NICU']){
   assert.equal(await sidebar.locator('.has-submenu>a').filter({hasText:department}).count(),1,'authorized department present: '+department);
  }
  const er=sidebar.locator('.has-submenu>a').filter({hasText:'Emergency Room'});
  await er.click();assert(await sidebar.locator('a[href="emergency_room_dashboard.php?module=er"]').isVisible(),'clinical submenu opens');
  await er.click();
  await toggle.click();assert(await toggle.isVisible(),'expand toggle remains visible in rail');
  assert.equal(Math.round((await sidebar.boundingBox()).width),80,'collapsed desktop width');
  await page.reload();await page.waitForSelector('body.ns-sidebar-rail');assert(await toggle.isVisible(),'manual collapsed preference retains accessible expansion');
  await toggle.click();await sidebar.getByTitle('Patient Tracker',{exact:true}).click();await page.waitForLoadState();
  assert.equal(await page.locator('body').evaluate(e=>e.classList.contains('sidebar-collapsed')),false,'navigation keeps labeled menu expanded');
  await page.waitForSelector('body.ns-unified-sidebar');
  assert.equal(await sidebar.locator('.menu a:not(.active)').first().evaluate(e=>getComputedStyle(e).color),'rgb(220, 234, 241)','inactive links have readable contrast');
  await page.screenshot({path:path.join(root,'outputs/nis-reengineering/sidebar-desktop.png'),fullPage:true});
  await page.setViewportSize({width:390,height:844});await page.locator('.mobile-menu-toggle').click();assert(await page.locator('body').evaluate(e=>e.classList.contains('sidebar-mobile-open')),'mobile menu opens');
  await page.keyboard.press('Escape');assert.equal(await page.locator('body').evaluate(e=>e.classList.contains('sidebar-mobile-open')),false,'mobile Escape closes drawer');
  assert.deepEqual(errors,[]);console.log('PASS visible desktop labels, old preference recovery, persistent manual toggle, visible expand button, navigation persistence, link contrast, mobile drawer and Escape');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
