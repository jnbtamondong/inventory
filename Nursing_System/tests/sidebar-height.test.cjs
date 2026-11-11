const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require('C:/Users/pc/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const root=path.resolve(__dirname,'..');let html=execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'nis-sidebar-fixture.php')],{encoding:'utf8'});html=html.replace('</head>','<link rel="stylesheet" href="/Nursing_System/public/css/nursync-overview.css?v=3"></head>').replace('<main class="main-content">','<main class="main-content" style="min-height:3000px">');
 const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:800},reducedMotion:'reduce'});
  await page.route('**/*',route=>{const url=new URL(route.request().url());if(url.origin!=='http://nis.test')return route.abort();if(url.pathname.startsWith('/Nursing_System/pages/'))return route.fulfill({contentType:'text/html',body:html});const relative=url.pathname.replace('/Nursing_System/','');if(!relative.startsWith('public/')||relative.includes('..'))return route.abort();const file=path.join(root,relative);if(!fs.existsSync(file))return route.abort();return route.fulfill({contentType:{'.css':'text/css','.js':'application/javascript','.png':'image/png','.svg':'image/svg+xml'}[path.extname(file)]||'application/octet-stream',body:fs.readFileSync(file)});});
  await page.goto('http://nis.test/Nursing_System/pages/dashboard.php');await page.waitForSelector('body.ns-unified-sidebar');await page.waitForTimeout(300);
  for(const y of [0,450,1400]){await page.evaluate(y=>scrollTo(0,y),y);await page.waitForTimeout(100);const geometry=await page.locator('.nursing-sidebar').evaluate(e=>{const r=e.getBoundingClientRect();return {top:r.top,bottom:r.bottom,height:r.height,position:getComputedStyle(e).position,ancestors:[e.parentElement,document.body,document.documentElement].map(a=>{const s=getComputedStyle(a);return [a.className,s.transform,s.filter,s.perspective,s.contain,s.willChange];})};});assert(Math.abs(geometry.top)<1,'sidebar stays at viewport top');assert(Math.abs(geometry.bottom-800)<1,'sidebar fills viewport after document scrolling');}
  console.log('PASS sidebar fills the viewport at document scroll positions 0, 450 and 1400.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
