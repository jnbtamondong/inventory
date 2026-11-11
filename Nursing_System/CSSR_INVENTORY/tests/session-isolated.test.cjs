// Real HTTP login/logout/CSRF/idle checks against the VERIFIED isolated restore only.
// No live credentials, live login attempts, or live inventory mutations.
const {spawn,execFileSync}=require('node:child_process');
const fs=require('node:fs');const path=require('node:path');const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..');const php='C:/xampp/php/php.exe';
const recovery=fs.realpathSync(fs.readFileSync(path.join(root,'storage/reviews/recovery-current.txt'),'utf8').trim());
assert(recovery.startsWith(fs.realpathSync(path.join(root,'storage/reviews'))+path.sep+'recovery-'));
const password=fs.readFileSync(path.join(recovery,'isolated-password.txt'),'utf8');
const sessionDir=path.join(recovery,'http-test-sessions');fs.mkdirSync(sessionDir,{recursive:true});
const base='http://127.0.0.1:8089/Nursing_System/';let server;let prepared=false;let checks=0;
const pause=ms=>new Promise(r=>setTimeout(r,ms));
function check(value,message){assert(value,message);checks++;}
async function main(){
 try{
  // Reject an occupied endpoint instead of testing an unrelated server.
  const net=require('node:net');await new Promise((resolve,reject)=>{const probe=net.createServer();probe.once('error',reject);probe.listen(8089,'127.0.0.1',()=>probe.close(resolve));});
  const credentials=JSON.parse(execFileSync(php,[path.join(root,'tools/prepare-isolated-auth-test.php')],{encoding:'utf8'}));prepared=true;
  const env={...process.env,NURSING_APP_ENV:'test',NURSING_DB_HOST:'127.0.0.1',NURSING_DB_PORT:'33317',NURSING_DB_USER:'root',NURSING_DB_PASSWORD:password,CSSR_DB_HOST:'127.0.0.1',CSSR_DB_PORT:'33317',CSSR_DB_USER:'root',CSSR_DB_PASSWORD:password};
  delete env.NURSING_RUN_SCHEMA_SETUP;delete env.NURSING_ENABLE_SETUP;
  server=spawn(php,['-d','session.save_path='+sessionDir,'-S','127.0.0.1:8089','-t',path.dirname(root)],{cwd:root,env,windowsHide:true,stdio:'ignore'});
  let ready=false;for(let i=0;i<60;i++){try{await fetch(base+'pages/login.php');ready=true;break;}catch{await pause(100);}}
  check(ready,'Isolated HTTP server ready');
  let cookie='';
  async function request(route,form){
   const response=await fetch(base+route,{method:form?'POST':'GET',headers:cookie?{Cookie:cookie}:{},...(form?{body:new URLSearchParams(form)}:{}),redirect:'manual'});
   for(const value of response.headers.getSetCookie()){if(value.startsWith('PHPSESSID='))cookie=value.split(';')[0];}
   const text=await response.text();return {status:response.status,location:response.headers.get('location'),text,headers:response.headers};
  }
  const token=text=>{const match=text.match(/name="csrf_token"\s+value="([^"]+)"/);assert(match,'CSRF field');return match[1];};
  async function login(){
   const form=await request('pages/login.php');const before=cookie;
   check(form.status===200,'Login form loads');
   check(/HttpOnly/i.test(form.headers.get('set-cookie')||'')||!!before,'Session cookie exists');
   const response=await request('pages/login.php',{login:'1',username:credentials.username,password:credentials.password,csrf_token:token(form.text)});
   check(response.status===302&&/system_menu/.test(response.location),'Real isolated login succeeds');
   check(cookie!==before,'Session ID rotates on login');
  }
  await login();
  let page=await request('CSSR_INVENTORY/pages/item_requests.php');check(page.status===200&&/Department Supply Request/.test(page.text),'Shared account opens CSSR using separate DB configuration');
  let confirm=await request('api/logout.php');check(confirm.status===200&&/method="post"/i.test(confirm.text),'GET logout asks for confirmation');
  page=await request('pages/system_menu.php');check(page.status===200,'GET logout preserves authentication');
  let invalid=await request('api/logout.php',{csrf_token:'invalid'});check([400,419].includes(invalid.status),'Invalid logout CSRF rejected');
  page=await request('pages/system_menu.php');check(page.status===200,'Invalid CSRF preserves authentication');
  let ended=await request('api/logout.php',{csrf_token:token(confirm.text)});check(ended.status===302,'Valid shared logout redirects');
  page=await request('pages/system_menu.php');check(page.status===302&&/login/.test(page.location),'Shared logout destroys authentication');
  await login();await request('CSSR_INVENTORY/pages/item_requests.php');
  confirm=await request('CSSR_INVENTORY/logout.php');check(confirm.status===200,'CSSR GET logout confirms');
  page=await request('CSSR_INVENTORY/pages/item_requests.php');check(page.status===200,'CSSR GET logout preserves authentication');
  invalid=await request('CSSR_INVENTORY/logout.php',{csrf_token:'invalid'});check([400,419].includes(invalid.status),'CSSR invalid logout CSRF rejected');
  ended=await request('CSSR_INVENTORY/logout.php',{csrf_token:token(confirm.text)});check(ended.status===302,'Valid CSSR logout redirects');
  page=await request('pages/system_menu.php');check(page.status===302&&/login/.test(page.location),'CSSR logout also destroys shared authentication');
  await login();
  const id=cookie.slice('PHPSESSID='.length);assert(/^[a-zA-Z0-9,-]+$/.test(id));
  const file=path.join(sessionDir,'sess_'+id);const original=fs.readFileSync(file,'utf8');assert(/last_activity\|i:\d+;/.test(original));
  fs.writeFileSync(file,original.replace(/last_activity\|i:\d+;/,'last_activity|i:1;'));
  page=await request('pages/system_menu.php');check(page.status===302&&/timeout=1/.test(page.location),'Expired session is rejected');
  fs.writeFileSync(path.join(recovery,'session-http-verification.json'),JSON.stringify({checked_at:new Date().toISOString(),checks,source:'isolated restore on port 33317',live_database_changed:false},null,2));
  console.log(`Passed ${checks} real HTTP session checks against the isolated restore. No live transactions were posted.`);
 }finally{
  if(server){server.kill();await new Promise(resolve=>{if(server.exitCode!==null)resolve();else server.once('exit',resolve);});}
  if(prepared)execFileSync(php,[path.join(root,'tools/prepare-isolated-auth-test.php'),'restore'],{stdio:'ignore'});
 }
}
main().catch(error=>{console.error(error.message);process.exitCode=1});
