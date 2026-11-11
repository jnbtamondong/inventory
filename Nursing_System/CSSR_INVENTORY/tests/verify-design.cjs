const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const assert = require('node:assert/strict');

async function main() {
    const port = 9337;
    const artifacts = path.join(__dirname, 'design-artifacts');
    fs.mkdirSync(artifacts, { recursive: true });
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', [
        '--headless', '--disable-gpu', '--no-first-run', '--remote-debugging-port=' + port,
        '--user-data-dir=' + path.join(artifacts, 'browser-profile'), 'about:blank'
    ], { windowsHide: true, stdio: 'ignore' });
    const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
    let socket;
    let nextId = 1;
    const pending = new Map();
    try {
        let pages;
        for (let attempt = 0; attempt < 50; attempt++) {
            try { pages = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json(); break; }
            catch { await pause(100); }
        }
        assert(pages, 'Browser did not start');
        socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
        socket.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            const waiter = pending.get(message.id);
            if (waiter) {
                pending.delete(message.id);
                message.error ? waiter.reject(Error(message.error.message)) : waiter.resolve(message.result);
            }
        });
        function call(method, params = {}) {
            return new Promise((resolve, reject) => {
                const id = nextId++;
                pending.set(id, { resolve, reject });
                socket.send(JSON.stringify({ id, method, params }));
            });
        }
        async function evaluate(expression) {
            const result = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
            if (result.exceptionDetails) throw Error(result.exceptionDetails.text);
            return result.result.value;
        }
        await call('Page.enable');
        const url = pathToFileURL(path.join(__dirname, 'ui-preview.html')).href;
        await call('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
        await call('Page.navigate', { url });
        for (let attempt = 0; attempt < 60; attempt++) {
            if (await evaluate("document.body?.dataset.previewCheck === 'passed'")) break;
            await pause(100);
        }
        assert(await evaluate("document.body.dataset.previewCheck === 'passed'"), 'Popup open/close failed');
        await evaluate('document.fonts.ready.then(() => true)');
        const firstFrame = await evaluate("document.querySelector('.cssr-ambient').toDataURL()");
        await pause(600);
        const secondFrame = await evaluate("document.querySelector('.cssr-ambient').toDataURL()");
        assert.notEqual(firstFrame, secondFrame, 'Background is not moving');
        const pixels = await evaluate("(() => { const c=document.querySelector('.cssr-ambient'); const p=c.getContext('2d').getImageData(0,0,c.width,c.height).data; let n=0; for(let i=3;i<p.length;i+=4)if(p[i])n++; return n; })()");
        assert(pixels > 100, 'Background canvas is blank');
        fs.writeFileSync(path.join(artifacts, 'desktop.png'), Buffer.from((await call('Page.captureScreenshot')).data, 'base64'));
        await call('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        await evaluate("document.querySelector('[data-modal-open]').click()");
        await pause(150);
        const bounds = await evaluate("(() => {const r=document.querySelector('.app-modal-card').getBoundingClientRect();return {left:r.left,right:r.right,width:innerWidth,height:r.height};})()");
        assert(bounds.left >= 0 && bounds.right <= bounds.width, 'Mobile dialog exceeds viewport: ' + JSON.stringify(bounds));
        fs.writeFileSync(path.join(artifacts, 'mobile-popup.png'), Buffer.from((await call('Page.captureScreenshot')).data, 'base64'));
        await call('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
        await pause(100);
        const staticFrame = await evaluate("document.querySelector('.cssr-ambient').toDataURL()");
        await pause(200);
        assert.equal(await evaluate("document.querySelector('.cssr-ambient').toDataURL()"), staticFrame, 'Reduced motion is not respected');
        console.log('Passed: popup open/close, moving nonblank canvas, mobile dialog bounds, reduced motion. Screenshots: ' + artifacts);
        await call('Browser.close');
    } finally {
        socket?.close();
        browser.kill();
    }
}
main().catch(error => { console.error(error.message); process.exitCode = 1; });
