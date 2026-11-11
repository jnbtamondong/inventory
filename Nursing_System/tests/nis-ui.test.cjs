const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {chromium}=require('C:/Users/pc/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const root=path.resolve(__dirname,'..');const html=execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'nis-ui-fixture.php')],{encoding:'utf8'});
 const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://nis.test/**',route=>{const pathname=new URL(route.request().url()).pathname;
   if(pathname==='/Nursing_System/pages/fixture')return route.fulfill({contentType:'text/html',body:html});
   const relative=pathname.replace('/Nursing_System/','');if(!/^public\/(css|js)\/[a-z-]+\.(css|js)$/.test(relative))return route.abort();
   route.fulfill({contentType:relative.endsWith('.css')?'text/css':'text/javascript',body:fs.readFileSync(path.join(root,relative))});
  });
  for(const [width,height] of [[1440,1000],[768,1024],[390,844]]){
   await page.setViewportSize({width,height});await page.goto('http://nis.test/Nursing_System/pages/fixture');
   for(const theme of ['light','dark']){
    await page.evaluate(theme=>window.NursingTheme.set(theme),theme);
    const overflow=await page.evaluate(()=>[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1&&!e.closest('.nis-table-wrap')).map(e=>[e.tagName,e.className,e.getBoundingClientRect().width]));
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'no page overflow at '+width+': '+JSON.stringify({overflow,details:await page.evaluate(()=>({root:document.documentElement.scrollWidth,body:document.body.scrollWidth,wrap:document.querySelector('.nis-table-wrap').getBoundingClientRect().toJSON(),scroll:document.querySelector('.nis-table-wrap').scrollWidth}))}));
    assert.equal(await page.locator('.nis-metric').count(),4);assert.equal(await page.locator('.nis-table tbody tr').count(),2);
    await page.screenshot({path:path.join(root,`outputs/nis-reengineering/ui-${width}-${theme}.png`),fullPage:true});
   }
  }
  assert.equal(await page.locator('.nis-action').first().locator('select').first().locator('option').count(),3,'only permitted next states');
  await page.locator('[data-nis-patient]').selectOption('1');
  assert.equal(await page.locator('[data-nis-encounter]').inputValue(),'SYNTHETIC-ENC-001','patient selection preserves the encounter');
  assert(await page.locator('[data-nis-encounter]').getAttribute('readonly')!==null,'encounter cannot be mistyped');
  assert(await page.locator('.nis-action').first().evaluate(e=>e.getBoundingClientRect().right<=innerWidth),'mobile action is visible without horizontal scroll');
  await page.locator('[name="remarks"]').first().fill('Synthetic handover');
  page.once('dialog',d=>d.dismiss());await page.locator('.nis-action button').first().click();
  assert.equal(await page.locator('.nis-action button').first().isEnabled(),true,'cancel does not lock the form');
  assert.deepEqual(errors,[]);console.log('PASS desktop/tablet/mobile, light/dark, action policy, cancellation, no page overflow or JavaScript errors');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
