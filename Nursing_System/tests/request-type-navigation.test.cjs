const {execFileSync}=require('node:child_process'),vm=require('node:vm'),assert=require('node:assert/strict');
const render=(view,query='')=>execFileSync('C:/xampp/php/php.exe',[require('node:path').join(__dirname,'nursing-management-ui-fixture.php'),view,query],{encoding:'utf8'});
async function switchType(html,value){
 const start=html.indexOf("const requestType=document.getElementById('requestType');"),end=html.indexOf('const requestDates=',start);assert(start>=0&&end>start);
 let change,url,replaced=false,initialized=0;const leave={value:'SL'},reason={value:'Keep entered reason'},note={},button={disabled:false},replacementReason={value:''};
 const replacement={querySelector(){return replacementReason;}};
 const kind=html.match(/data-request-kind="(OFF|LEAVE)"/)[1];
 const card={dataset:{requestKind:kind},isConnected:true,querySelector(){return reason;},querySelectorAll(){return [button];},setAttribute(){},removeAttribute(){},replaceWith(){replaced=true;}};
 const type={value,closest(){return card;},addEventListener(event,handler){change=handler;},focus(){}};
 const context={URL,AbortController,document:{getElementById(id){return id==='requestType'?type:id==='requestLeaveType'?leave:note;}},requestDepartment:{value:'ER'},requestEmployee:{value:'7'},location:{href:'http://localhost/Nursing_System/pages/nursing_requests.php?view=new',assign(){throw Error('Page navigation must not happen');}},fetch:async destination=>{url=new URL(destination);return {ok:true,redirected:false,text:async()=>''};},DOMParser:class{parseFromString(){return {querySelector(){return replacement;}};}},initialiseRequestEncoder(){initialized++;}};
 vm.runInNewContext(html.slice(start,end),context);await change();return {url,leave,replaced,initialized,reason:replacementReason.value,button};
}
(async()=>{
 const off=render('adminOff'),leave=render('adminLeave','leave_type=SL&department=ER&employee_id=7');
 let result=await switchType(off,'SL');assert.equal(result.url.searchParams.get('kind'),'LEAVE');assert.equal(result.url.searchParams.get('department'),'ER');assert.equal(result.url.searchParams.get('employee_id'),'7');assert(result.replaced&&result.initialized===1);assert.equal(result.reason,'Keep entered reason');assert(!result.button.disabled);
 result=await switchType(leave,'OFF');assert.equal(result.url.searchParams.get('kind'),'OFF');assert(result.replaced);
 result=await switchType(leave,'VL');assert.equal(result.url,undefined);assert.equal(result.leave.value,'VL');
 assert(!off.includes('OFF ? Request-Off'));assert(!off.includes("VL ? Vacation Leave"));assert(off.includes('>Vacation Leave</option>'));
 console.log('Passed in-place OFF/Leave switching, applicant/reason preservation, full request names, and same-form leave updates without navigation.');
})().catch(error=>{console.error(error);process.exitCode=1;});
