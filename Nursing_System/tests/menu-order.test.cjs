const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_PATH || 'C:/Users/Jeeve Aron/AppData/Local/npm-cache/_npx/e41f203b7505f1fb/node_modules/playwright');
(async () => {
    const browser = await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
    try {
        const page = await browser.newPage();
        const output = path.join(__dirname,'artifacts','menu-order');
        fs.mkdirSync(output,{recursive:true});
        for (const role of ['admin','staff']) {
            await page.route('http://localhost/Nursing_System/pages/__menu_order',route => route.fulfill({contentType:'text/html',body:execFileSync('C:/xampp/php/php.exe',['-n',path.join(__dirname,'menu-ui-fixture.php'),role],{encoding:'utf8'})}));
            for (const width of [1440,390,320]) {
                await page.setViewportSize({width,height:900});
                await page.goto('http://localhost/Nursing_System/pages/__menu_order',{waitUntil:'networkidle'});
                const cards = page.locator('.system-choice-grid > .system-choice-card');
                const names = await cards.locator('strong').allTextContents();
                assert.equal(names[0],'CSSR Inventory');
                assert.equal(names[1],role === 'admin' ? 'Procurement' : 'Nursing Schedule Management');
                const disabled = await cards.evaluateAll(nodes => nodes.map(node => node.getAttribute('aria-disabled') === 'true'));
                const firstDisabled = disabled.indexOf(true);
                if (role === 'staff') {
                    assert.equal(await page.locator('.system-choice-status').count(),3);
                    assert(firstDisabled >= 0 && disabled.slice(firstDisabled).every(Boolean));
                    assert.equal(await page.locator('.system-choice-unavailable[href]').count(),0);
                    assert.equal(await page.locator('.system-choice-unavailable').first().evaluate(node => getComputedStyle(node).opacity),'1');
                }
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
                await page.screenshot({path:path.join(output,role+'-'+width+'.png'),fullPage:true});
            }
            await page.unroute('http://localhost/Nursing_System/pages/__menu_order');
        }
        console.log('PASS: menu order, disabled construction cards and responsive layouts for admin/staff at 1440/390/320px.');
    } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exitCode=1;});
