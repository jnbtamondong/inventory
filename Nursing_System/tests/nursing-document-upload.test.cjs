const {spawn,execFileSync}=require('node:child_process'),path=require('node:path'),crypto=require('node:crypto'),net=require('node:net'),assert=require('node:assert/strict');
const php='C:/xampp/php/php.exe',fixture=path.join(__dirname,'nursing-document-upload-fixture.php'),database='nursync_upload_test_'+crypto.randomBytes(6).toString('hex'),key=crypto.randomBytes(24).toString('hex');
const pause=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{let server,created=false;try{
    execFileSync(php,[fixture,'setup',database]);created=true;
    const port=await new Promise(resolve=>{const socket=net.createServer();socket.listen(0,'127.0.0.1',()=>{const port=socket.address().port;socket.close(()=>resolve(port));});});
    server=spawn(php,['-d','upload_tmp_dir='+require('node:os').tmpdir(),'-S','127.0.0.1:'+port,fixture],{windowsHide:true,stdio:'ignore',env:{...process.env,NURSING_UPLOAD_TEST_DATABASE:database,NURSING_UPLOAD_TEST_KEY:key}});
    const url='http://127.0.0.1:'+port+'/upload';for(let i=0;i<50;i++){try{await fetch(url);break;}catch{await pause(100);}}
    const upload=(actor,content,type,name)=>{const form=new FormData();form.append('document',new Blob([content],{type}),name);return fetch(url+'?actor='+actor,{method:'POST',headers:{'X-Fixture-Key':key},body:form});};
    const pdf='%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF';
    let response=await upload(1,pdf,'application/pdf','support.pdf');assert.equal(response.status,200);const body=await response.text();let payload;try{payload=JSON.parse(body);}catch{throw Error(body);}assert.deepEqual(payload,{stored:true,name:'support.pdf',audit_actor:1});
    response=await upload(2,pdf,'application/pdf','other.pdf');assert.equal(response.status,403,'Approver cannot upload as applicant');
    response=await upload(5,pdf,'application/pdf','admin-support.pdf');assert.equal(response.status,200,'System Admin can upload supporting documents for an employee');assert.deepEqual(await response.json(),{stored:true,name:'admin-support.pdf',audit_actor:5},'Document audit records the actual System Admin encoder');
    response=await upload(1,'plain text','text/plain','bad.txt');assert.equal(response.status,422,'Unsupported file MIME denied');
    console.log('Passed actual multipart upload, private storage, applicant/admin authorization, MIME validation and actual encoder audit checks.');
}finally{if(server){server.kill();await pause(150);}if(created)execFileSync(php,[fixture,'cleanup',database]);}})().catch(error=>{console.error(error);process.exitCode=1;});
