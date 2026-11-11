const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'C:/Users/Jeeve Aron/AppData/Local/npm-cache/_npx/e41f203b7505f1fb/node_modules/playwright');
const base = 'http://localhost/Nursing_System/';
const artifacts = path.join(__dirname, 'artifacts', 'workspace-ui');
const requestedScreen = process.argv[2];

(async () => {
    fs.mkdirSync(artifacts, {recursive:true});
    const browser = await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
    try {
        const page = await browser.newPage();
        const errors=[];
        page.on('pageerror', error => errors.push(error.message));
        // Local copy keeps layout checks independent of CDN availability.
        await page.route('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', route => route.fulfill({contentType:'text/css',body:fs.readFileSync(path.join(__dirname,'artifacts','bootstrap-5.3.3.css'))}));
        for (const fixture of ['tests/workspace-ui-preview.html','CSSR_INVENTORY/tests/ui-preview.html','CSSR_INVENTORY/tests/sidebar-form-preview.html']) {
            await page.route(base+fixture,route=>route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(__dirname,'..',fixture))}));
        }
        await page.route(base+'pages/__menu_ui_preview',route=>route.fulfill({contentType:'text/html',body:execFileSync('C:/xampp/php/php.exe',['-n',path.join(__dirname,'menu-ui-fixture.php')],{encoding:'utf8'})}));
        for(const [url,file] of [['pages/__admin_ui_preview','admin-ui-fixture.php'],['PROCUREMENT/pages/__proc_ui_preview','procurement-ui-fixture.php']]) {
            await page.route(base+url,route=>route.fulfill({contentType:'text/html',body:execFileSync('C:/xampp/php/php.exe',['-n',path.join(__dirname,file)],{encoding:'utf8'})}));
        }
        for (const width of [1440,390,320]) {
            await page.setViewportSize({width,height:900});
            for (const screen of ['index.php','dashboard_preview.php','pages/login.php','pages/forgot_password.php','pages/__menu_ui_preview','pages/__admin_ui_preview','PROCUREMENT/pages/__proc_ui_preview','tests/workspace-ui-preview.html','CSSR_INVENTORY/tests/ui-preview.html','CSSR_INVENTORY/tests/sidebar-form-preview.html']) {
                if(requestedScreen && requestedScreen!==screen) continue;
                await page.goto(base+screen,{waitUntil:'networkidle'});
                await page.evaluate(()=>document.fonts.ready);
                assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Horizontal page overflow: '+screen+' at '+width);
                const canvas=page.locator('.ns-workspace-background,.cssr-ambient');
                assert.equal(await canvas.count(),1,'Missing or duplicate background: '+screen);
                assert(await canvas.evaluate(c=>{ const pixels=c.getContext('2d').getImageData(0,0,c.width,c.height).data; return pixels.some((value,i)=>i%4===3&&value>0); }),'Blank canvas');
                if(screen==='dashboard_preview.php') {
                    assert.equal(await page.locator('main').evaluate(el=>getComputedStyle(el).zIndex),'1','Background must sit behind dashboard content');
                    assert.equal(await page.locator('.overview-kpi').count(),4,'Bed summary cards missing');
                    assert.equal(await page.locator('.overview-kpi-icon .bi').count(),4,'Bed summary icons missing');
                    assert(await page.locator('.overview-census-card').count()>0,'Department census missing');
                    assert.equal(await page.locator('.report-table').count(),2,'ER and ward reports missing');
                    assert((await page.locator('.navbar .nav-link.active').innerText()).trim()==='Dashboard','Dashboard navigation lost its active state');
                }
                if(screen==='pages/login.php') {
                    await page.locator('#login-password').fill('preview-only');
                    await page.locator('.login-password-toggle').click();
                    assert.equal(await page.locator('#login-password').getAttribute('type'),'text');
                    await page.locator('.login-password-toggle').click();
                    assert.equal(await page.locator('#login-password').getAttribute('type'),'password');
                    assert.equal(await page.locator('form').evaluate(form=>form.checkValidity()),false,'Username must remain required');
                }
                if(screen==='pages/__admin_ui_preview') {
                    assert(await page.locator('.account-setup').isVisible(),'Account setup must remain visible');
                    for(const id of ['department-entry-modal','role-entry-modal','job-title-entry-modal']) {
                        await page.locator('[data-open-setup="'+id+'"]').click();
                        await page.waitForTimeout(200);
                        const bounds=await page.locator('#'+id+' .setup-entry-card').boundingBox();
                        assert(bounds.x>=0&&bounds.x+bounds.width<=width,'Setup dialog overflow');
                        await page.locator('#'+id+' [data-close-setup]').click();
                    }
                    await page.locator('#open-create-user').click();
                    await page.waitForTimeout(200);
                    await page.screenshot({path:path.join(artifacts,`create-user-${width}.png`)});
                    await page.locator('#close-create-user').click();
                    await page.locator('[data-open-setup="department-entry-modal"]').click();
                    await page.locator('#department-entry-modal [name="department_code"]').fill('PREVIEW');
                    await page.locator('#department-entry-modal [name="department_name"]').fill('Preview Department');
                    await page.locator('#department-entry-modal button[type="submit"]').click();
                    await page.waitForTimeout(200);
                    assert(await page.locator('#add-setup-modal').isVisible(),'Confirmation did not open');
                    await page.screenshot({path:path.join(artifacts,`confirmation-${width}.png`)});
                    await page.locator('#cancel-add-setup').click();
                    assert(!(await page.locator('#add-setup-modal').isVisible()),'Cancel did not close confirmation');
                    await page.locator('#department-entry-modal [data-close-setup]').click();
                }
                if(screen==='CSSR_INVENTORY/tests/sidebar-form-preview.html'&&width<650) {
                    await page.locator('#openCartPreview').click();
                    await page.waitForTimeout(250);
                    const drawer=page.locator('#cartPreview');
                    assert(await drawer.isVisible(),'Sidebar form did not open');
                    assert.equal(await drawer.evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(255, 255, 255)','Sidebar form must be opaque');
                    await page.screenshot({path:path.join(artifacts,`sidebar-form-${width}.png`)});
                    await page.locator('#closeCartPreview').click();
                    await page.waitForTimeout(250);
                    assert(!(await drawer.isVisible()),'Sidebar form did not close');
                }
                if(screen==='PROCUREMENT/pages/__proc_ui_preview') {
                    const semantic=await page.evaluate(()=>{
                        const colors={};
                        for(const name of ['green','red','light']) {
                            const button=document.createElement('button');button.type='submit';button.className='btn '+name;
                            document.body.appendChild(button);colors[name]=getComputedStyle(button).backgroundColor;button.remove();
                        }
                        return colors;
                    });
                    assert.equal(semantic.green,'rgb(22, 118, 96)','Approval action lost its green state');
                    assert.equal(semantic.red,'rgb(180, 35, 54)','Destructive action lost its red state');
                    assert.equal(semantic.light,'rgb(255, 255, 255)','Cancel action lost its neutral state');
                    if(width<900) {
                        await page.locator('.proc-sidebar-toggle').click();
                        await page.waitForTimeout(250);
                        assert(await page.locator('.proc-sidebar').isVisible(),'Procurement drawer did not open');
                        await page.keyboard.press('Escape');
                        await page.waitForTimeout(250);
                        assert(!(await page.locator('.proc-sidebar').isVisible()),'Procurement drawer did not close');
                    }
                    await page.locator('#openProcPreview').click();
                    await page.waitForTimeout(200);
                    const bounds=await page.locator('#procPreview').boundingBox();
                    assert(bounds.x>=0&&bounds.x+bounds.width<=width,'Procurement dialog overflow');
                    await page.locator('#closeProcPreview').click();
                }
                if(screen==='tests/workspace-ui-preview.html') {
                    if(width<900) {
                        await page.locator('.ed-mobile-toggle').click();
                        await page.waitForTimeout(250);
                        assert(await page.locator('.tc-sidebar').isVisible(),'Drawer did not open');
                        await page.keyboard.press('Escape');
                        await page.waitForTimeout(250);
                        assert(!(await page.locator('.tc-sidebar').isVisible()),'Escape did not close drawer');
                    }
                    await page.locator('#openPreview').click();
                    await page.waitForTimeout(200);
                    const bounds=await page.locator('dialog').boundingBox();
                    assert(bounds.x>=0&&bounds.x+bounds.width<=width,'Dialog overflow');
                    await page.screenshot({path:path.join(artifacts,`popup-${width}.png`)});
                    await page.locator('#closePreview').click();
                    assert(!(await page.locator('dialog').isVisible()),'Dialog did not close');
                }
                await page.screenshot({path:path.join(artifacts,screen.replaceAll('/','-')+'-'+width+'.png')});
            }
        }
        await page.goto(base+'pages/login.php',{waitUntil:'networkidle'});
        const canvas=page.locator('.ns-workspace-background');
        const first=await canvas.evaluate(c=>c.toDataURL());
        await page.waitForTimeout(300);
        assert.notEqual(first,await canvas.evaluate(c=>c.toDataURL()),'Background did not animate');
        await page.emulateMedia({reducedMotion:'reduce'});
        await page.waitForTimeout(100);
        const still=await canvas.evaluate(c=>c.toDataURL());
        await page.waitForTimeout(200);
        assert.equal(still,await canvas.evaluate(c=>c.toDataURL()),'Reduced motion ignored');
        await page.emulateMedia({media:'print'});
        assert(!(await canvas.isVisible()),'Animated background must not print');
        await page.goto(base+'CSSR_INVENTORY/tests/ui-preview.html',{waitUntil:'networkidle'});
        assert(!(await page.locator('.cssr-ambient').isVisible()),'CSSR background must not print');
        assert.deepEqual(errors,[],'Client errors');
        console.log(requestedScreen ? 'PASS: '+requestedScreen+' at 1440/390/320px, canvas motion, reduced motion and print visibility.' : 'PASS: home, public dashboard, login, recovery, launcher, user management, procurement, employee components and CSSR previews at 1440/390/320px; password reveal, required fields, drawer Escape, dialogs, nonblank moving canvas and reduced motion.');
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
