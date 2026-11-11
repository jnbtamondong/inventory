// Header/status probes only. No credentials or business transactions.
const assert=require('node:assert/strict');
(async()=>{
 const base=process.env.NURSING_TEST_URL||'http://localhost/Nursing_System/';let checks=0;
 for(const route of ['setup.php','users_backup_before_delete_20260818_145553.csv','CSSR_INVENTORY/config/database.php','CSSR_INVENTORY/logs/php_errors.log','infinityfree_upload/index.php','.git/config','config/runtime-database.php','config/remote-access/cloudflared.example.yml','storage/reviews/recovery-current.txt','database/automatic_backups/backup_set_20261008_144307.json','public/uploads/admitted/']){
  const r=await fetch(new URL(route,base),{method:'HEAD',redirect:'manual'});assert.equal(r.status,403,route);checks++;
 }
 for(const route of ['CSSR_INVENTORY/assets/css/mobile.css','public/js/dashboard.js']){
  const r=await fetch(new URL(route,base),{method:'HEAD'});assert.equal(r.status,200);assert.match(r.headers.get('cache-control'),/public, max-age=3600/);checks++;
 }
 for(const route of ['CSSR_INVENTORY/api/inbound.php','CSSR_INVENTORY/api/chat-attachment.php?file=sample.jpg','CSSR_INVENTORY/uploads/chat/sample.jpg']){
  const r=await fetch(new URL(route,base),{redirect:'manual'});assert.equal(r.status,302,route);assert.match(r.headers.get('location'),/login/);assert.match(r.headers.get('cache-control'),/no-store/);checks++;
 }
 const response=await fetch(new URL('CSSR_INVENTORY/assets/css/app.css',base));
 for(const name of ['content-security-policy','x-content-type-options','x-frame-options','referrer-policy','permissions-policy']){assert(response.headers.get(name),name);checks++;}
 if(response.headers.get('content-encoding')==='gzip'){checks++;console.log('PASS: gzip compression.');}
 else console.log('WARNING: gzip is not active on this server; enable Apache mod_filter/mod_deflate for the configured compression rules.');
 console.log('Passed '+checks+' HTTP exposure, authentication, caching and header checks. HTTPS is not certified by these local HTTP checks.');
})().catch(e=>{console.error(e);process.exitCode=1});
