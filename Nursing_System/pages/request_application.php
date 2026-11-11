<?php
require_once __DIR__.'/../config/db.php';require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/request_application.php';requireLogin();nrSchema($conn);$actor=exActor($conn,(int)$_SESSION['user_id']);
if(!naEligible($actor)&&naRole($actor)!=='SUPER_ADMIN'){http_response_code(403);exit('Nursing access required.');}
if(isset($_GET['leave_balance'])){
    header('Content-Type: application/json');header('Cache-Control: no-store');
    $balanceEmployee=naRole($actor)==='SUPER_ADMIN'?(int)($_GET['employee_id']??0):(int)$actor['user_id'];$balanceYear=(int)($_GET['year']??date('Y'));
    if(!$balanceEmployee||$balanceYear<2000||$balanceYear>2100){http_response_code(422);echo json_encode(['error'=>'Select an employee and valid year.']);exit;}
    $balanceIgnore=0;if(!empty($_GET['request_id'])){$balanceRequest=exOne($conn,'SELECT * FROM nursing_service_requests WHERE id=?',[(int)$_GET['request_id']]);if($balanceRequest&&(int)$balanceRequest['employee_id']===$balanceEmployee&&nrCanEditRequest($actor,$balanceRequest))$balanceIgnore=(int)$balanceRequest['id'];}
    $balances=[];foreach(['FL','WL','SPL'] as $balanceType)$balances[$balanceType]=nrValidateLeaveRules($conn,$balanceEmployee,$balanceType,[$balanceYear.'-01-01'],$balanceIgnore,true)[$balanceYear];
    echo json_encode(['year'=>$balanceYear,'remaining'=>$balances]);exit;
}
$kind=($_GET['kind']??'OFF')==='LEAVE'?'LEAVE':'OFF';$title='Request Leave / Off';$error='';$row=null;
try{
if($_SERVER['REQUEST_METHOD']==='POST'){requireCsrfToken();$input=$_POST;if(isset($input['request_type'])&&!isset($input['decision'])&&!isset($input['upload'])){if(!is_string($input['request_type'])||$input['request_type']==='')throw new InvalidArgumentException('Select OFF or a leave type.');$kind=$input['request_type']==='OFF'?'OFF':'LEAVE';if($kind==='LEAVE')$input['leave_type']=$input['request_type'];$title='Request Leave / Off';}$input['kind']=$kind;
 if(isset($input['upload'])){nrUploadDocument($conn,$actor,(int)$input['id'],$_FILES['document']??[]);$saved=exOne($conn,'SELECT * FROM nursing_service_requests WHERE id=?',[$input['id']]);}
 elseif(isset($input['decision']))$saved=nrDecide($conn,$actor,$input);
 else{if($kind==='LEAVE'&&isset($input['start_date'],$input['end_date'])){exDate($input['start_date']);exDate($input['end_date']);$start=new DateTimeImmutable($input['start_date']);$end=new DateTimeImmutable($input['end_date']);if($end<$start||$start->diff($end)->days>365)throw new InvalidArgumentException('Choose a valid leave range of up to 366 days.');$range=[];for($date=$start;$date<=$end;$date=$date->modify('+1 day'))$range[]=$date->format('Y-m-d');$input['dates_text']=implode(', ',$range);}$input['month']=$input['request_month']??null;$input['dates']=preg_split('/[\s,]+/',trim($input['dates_text']??''),-1,PREG_SPLIT_NO_EMPTY);$saved=nrSave($conn,$actor,$input);if($kind==='LEAVE'&&($_FILES['document']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$row=$saved;nrUploadDocument($conn,$actor,(int)$saved['id'],$_FILES['document']);}}
 header('Location: my_requests.php?type='.$kind.'&id='.$saved['id']);exit;
}
if(isset($_GET['document'])){$doc=exOne($conn,'SELECT * FROM nursing_request_supporting_documents WHERE id=?',[$_GET['document']]);$request=$doc?exOne($conn,'SELECT * FROM nursing_service_requests WHERE id=?',[$doc['request_id']]):null;if(!$request||!nrCanView($conn,$actor,$request))throw new DomainException('Document access denied.');header('Content-Type: '.$doc['mime_type']);header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="supporting-document"');readfile(__DIR__.'/../storage/nursing-request-documents/'.$doc['storage_name']);exit;}
if(isset($_GET['id'])){$requested=exOne($conn,'SELECT * FROM nursing_service_requests WHERE id=?',[$_GET['id']]);if(!$requested||!nrCanView($conn,$actor,$requested))throw new DomainException('Request access denied.');$row=$requested;$kind=$row['kind'];$title='Request Leave / Off';}
}catch(Throwable $e){$known=$e instanceof InvalidArgumentException||$e instanceof DomainException||$e->getCode()===409;$error=$known?$e->getMessage():'Unable to process this request.';http_response_code($e->getCode()===409?409:($e instanceof DomainException?403:($known?422:500)));if(!$known)error_log($e->getMessage());}
$isAdmin=naRole($actor)==='SUPER_ADMIN';$canSubmit=nrCanEncode($actor,$kind);$view='new';$correct=$row&&nrCanEditRequest($actor,$row);$printMode=($_GET['print']??'')==='1';
if($row&&!$error&&!$correct&&!$printMode){header('Location: my_requests.php?type='.$row['kind'].'&id='.$row['id']);exit;}
if($printMode&&(!$row||$row['kind']!=='LEAVE'||$row['status']!=='APPROVED_FOR_HR_FILING')){http_response_code(403);exit('A cleared leave application is required for printing.');}
if(!$row&&!$error&&!$canSubmit){header('Location: my_requests.php?type='.$kind);exit;}
$title=$printMode?'Internal Leave Approval Form':($kind==='OFF'?'Request-Off':'Leave Application');
$requestEmployees=[];$requestDepartments=[];$requestSelectedDepartment='';$requestApplicant=$isAdmin?['name'=>'','employee_id'=>'','position'=>'','unit'=>'']:$actor;
if($isAdmin){
    foreach(exRows($conn,"SELECT * FROM users WHERE is_active=1 AND status='ACTIVE' ORDER BY department,last_name,first_name") as $candidate){
        if(!naEligible($candidate)||($kind==='OFF'&&!naRoute('OFF',$candidate)))continue;
        $candidate['name']=naName($candidate);$candidate['unit']=canonicalDepartment($candidate['department']);$requestEmployees[]=$candidate;
    }
    usort($requestEmployees,fn($a,$b)=>strcmp($a['unit'],$b['unit'])?:((naRole($a)==='NURSING_ATTENDANT')<=>(naRole($b)==='NURSING_ATTENDANT'))?:strcmp($a['name'],$b['name']));
    $departmentLabels=nsScheduleDepartments();foreach($requestEmployees as $candidate)$requestDepartments[$candidate['unit']]=$departmentLabels[$candidate['unit']]??str_replace('_',' ',$candidate['unit']);
    foreach($requestEmployees as $candidate)if((int)$candidate['user_id']===(int)($_POST['employee_id']??$_GET['employee_id']??0))$requestApplicant=$candidate;
    $selectedDepartment=canonicalDepartment($_POST['request_department']??$_GET['department']??($requestApplicant['unit']??''));if(isset($requestDepartments[$selectedDepartment]))$requestSelectedDepartment=$selectedDepartment;
    if(!$correct&&$requestApplicant['unit']!==$requestSelectedDepartment)$requestApplicant=['name'=>'','employee_id'=>'','position'=>'','unit'=>''];
    if($correct){$applicant=exOne($conn,'SELECT * FROM users WHERE user_id=?',[$row['employee_id']]);$requestApplicant=$applicant?array_merge($applicant,['name'=>naName($applicant),'unit'=>canonicalDepartment($applicant['department'])]):['user_id'=>$row['employee_id'],'employee_id'=>$row['employee_id'],'name'=>$row['employee_name'],'position'=>$row['position'],'unit'=>$row['department']];}
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?> | JVGH</title><link rel="stylesheet" href="../public/css/style.css"><link rel="stylesheet" href="../public/css/dashboard.css"><link rel="stylesheet" href="../public/css/module-theme.css?v=18"><link rel="stylesheet" href="../public/css/schedule-management.css"><link rel="stylesheet" href="../public/css/request-tracking.css?v=2"><link rel="stylesheet" href="../public/css/request-application.css?v=<?=filemtime(__DIR__.'/../public/css/request-application.css')?>"><script defer src="../public/js/sidebar.js"></script><script defer src="../public/js/nursync-ui.js?v=4"></script><script defer src="../public/js/schedule-sidebar.js?v=1"></script></head><body class="module-ui schedule-page request-tracking-page request-application-page <?=$printMode?'request-print-mode':''?>"><nav class="navbar"><div class="navbar-brand"><img class="schedule-seal" src="../public/images/branding/jvgh_seal.png" alt="JVGH seal"><h1>NURSYNC</h1></div><div class="navbar-menu"><span class="user-info"><?=h($actor['name'])?></span></div></nav><div class="dashboard-container"><?php include __DIR__.'/../includes/schedule_sidebar.php';?><main class="main-content schedule-main">
<header class="schedule-heading"><div><p class="schedule-eyebrow">NURSING SCHEDULE MANAGEMENT</p><h2><?=h($title)?></h2><p><?=$kind==='OFF'?'Preferred rest dates use one approval: nurses and nursing attendants to Head Nurse; Head Nurse to Supervisor Nurse. Chief Nurse approval is not required.':'Internal Nursing Service leave clearance. Follow the approval tracker, then file the approved application with HR outside this system.'?></p></div></header>
<?php if($kind==='OFF'&&naEligible($actor)&&!naRoute('OFF',$actor)):?><p class="schedule-notice">Request-Off is not applicable to Supervisor Nurse, Assistant Chief Nurse, and Chief Nurse positions.</p><?php endif;?>
<?php require __DIR__.'/../includes/leave_approval_alerts.php'; ?><?php if($error):?><p class="schedule-notice" data-tone="error" role="alert"><?=h($error)?></p><?php endif;?>
<nav class="schedule-actions request-controls"><a class="btn btn-light" href="my_requests.php">My Requests</a><?php if(naApprovalAccess($actor)):?><a class="btn btn-light" href="my_requests.php?view=approvals">Requests for Approval</a><?php endif;?></nav>
<?php if(!$printMode&&($view==='new'||$correct)&&$canSubmit):?><section class="schedule-period rt-panel" data-request-kind="<?=h($kind)?>"><form method="post" enctype="multipart/form-data" class="schedule-form"><h3 class="full">Off / Leave Application</h3><input type="hidden" name="csrf_token" value="<?=h(csrfToken())?>"><input type="hidden" name="submission_key" value="<?=bin2hex(random_bytes(16))?>"><?php if($correct):?><input type="hidden" name="id" value="<?=$row['id']?>"><input type="hidden" name="version" value="<?=$row['version']?>"><?php endif;?>
<?php if($isAdmin&&!$correct):?><label class="full">Department<select name="request_department" id="requestDepartment" required><option value="">Select a department</option><?php foreach($requestDepartments as $unit=>$departmentLabel):?><option value="<?=h($unit)?>" <?=$requestSelectedDepartment===$unit?'selected':''?>><?=h($departmentLabel)?></option><?php endforeach;?></select></label>
<label class="full">Employee<select name="employee_id" id="requestEmployee" data-selected-employee="<?=h($requestApplicant['user_id']??'')?>" required disabled><option value="">Select a department first</option></select></label>
<template id="requestEmployeeOptions"><?php foreach($requestEmployees as $candidate):?><option value="<?=$candidate['user_id']?>" data-employee-id="<?=h($candidate['employee_id']??$candidate['user_id'])?>" data-name="<?=h($candidate['name'])?>" data-position="<?=h($candidate['position']??naRole($candidate))?>" data-department="<?=h($candidate['unit'])?>"><?=h($candidate['name'].' / '.($candidate['position']??naRole($candidate)))?></option><?php endforeach;?></template>
<p class="full">Encode this application for the selected employee. It follows that employee's approval route.</p><?php elseif($isAdmin):?><input type="hidden" name="employee_id" value="<?=$row['employee_id']?>"><?php endif;?>
<label>Employee ID<input id="requestEmployeeId" readonly value="<?=h($requestApplicant['employee_id']??$requestApplicant['user_id']??'')?>"></label><label>Full name<input id="requestEmployeeName" readonly value="<?=h($requestApplicant['name'])?>"></label><label>Position<input id="requestEmployeePosition" readonly value="<?=h($requestApplicant['position']??naRole($requestApplicant))?>"></label><label>Assigned department<input id="requestEmployeeDepartment" readonly value="<?=h($requestApplicant['unit'])?>"></label>
<label class="full">Request Type<select name="request_type" id="requestType" required <?= $correct&&$kind==='OFF'?'disabled':'' ?>><option value="">Select a request type</option><option value="OFF" <?= $kind==='OFF'?'selected':'' ?> <?=nrCanEncode($actor,'OFF')&&!($correct&&$kind==='LEAVE')?'':'disabled'?>>Request-Off</option><?php foreach(exRows($conn,'SELECT * FROM nursing_leave_policy WHERE enabled=1') as $type):?><option value="<?=h($type['code'])?>" <?=$kind==='LEAVE'&&($row['leave_type']??$_POST['request_type']??$_GET['leave_type']??'')===$type['code']?'selected':''?>><?=h($type['label'])?></option><?php endforeach;?></select></label>
<p class="full" id="requestLeaveRules" aria-live="polite"></p><?php if($correct&&$kind==='OFF'):?><input type="hidden" name="request_type" value="<?=h($kind==='OFF'?'OFF':$row['leave_type'])?>"><?php endif;?>
<?php if($kind==='LEAVE'):?><input type="hidden" name="leave_type" id="requestLeaveType" value="<?=h($row['leave_type']??$_POST['request_type']??$_GET['leave_type']??'')?>"><?php endif;?><label>Selected month<input type="month" <?=$kind==='OFF'?'name="request_month"':''?> id="requestMonth" value="<?=h($correct?substr(json_decode($row['dates_json'],true)[0],0,7):date('Y-m'))?>"></label>
<div id="requestOffCalendar" class="full" aria-label="<?=$kind==='OFF'?'Preferred OFF dates':'Leave dates'?>" data-date-kind="<?=$kind?>"></div><?php if($kind==='LEAVE'):?><p class="full" id="leaveDuration" aria-live="polite">Requested Duration: 0 days</p><?php endif;?>
<input type="hidden" name="dates_text" id="requestDates" value="<?=h($correct?implode(', ',json_decode($row['dates_json'],true)):'')?>"><label class="full">Selected dates<textarea id="requestDateRanges" rows="3" readonly aria-live="polite"></textarea></label>
<label class="full"><?=$kind==='OFF'?'Reason / remarks (optional)':'Reason'?><textarea name="reason" maxlength="2000" rows="3" <?=$kind==='LEAVE'?'required':''?>><?=h($correct?$row['reason']:'')?></textarea></label><label class="schedule-check full"><input type="checkbox" required>I confirm these dates and understand the approval process.</label><?php if($kind==='LEAVE'):?><label class="full">Supporting documents (optional at submission)<input name="document" type="file" accept="application/pdf,image/jpeg,image/png"><small>PDF, JPEG or PNG, up to 5 MB. Policy-required documents must be present before approval.</small></label><?php endif;?><footer><?php if($kind==='OFF'):?><button class="btn btn-light" name="action" value="save_draft" formnovalidate>Save Draft</button><?php endif; ?><button class="btn btn-primary" name="action" value="submit"><?=$kind==='OFF'?'Submit Request-Off':'Submit Leave Request'?></button></footer></form></section><?php endif;?>
<?php if($row&&$printMode):$employee=exOne($conn,'SELECT first_name,last_name FROM users WHERE user_id=?',[$row['employee_id']]);$employee['name']=($row['employee_name']??'')?:trim(($employee['first_name']??'Employee').' '.($employee['last_name']??$row['employee_id']));$events=exRows($conn,'SELECT * FROM nursing_service_request_events WHERE request_id=? ORDER BY id',[$row['id']]);?>
<section class="rt-panel printable-leave"><div class="print-title"><h2>JONI VILLANUEVA GENERAL HOSPITAL</h2><h3>NURSING SERVICE DEPARTMENT</h3><h3>INTERNAL LEAVE APPROVAL FORM</h3></div><h3><?=h($row['request_no'])?> — <?=h($row['status'])?></h3><p><?=h($employee['name'].' / '.$row['position'].' / '.$row['department'])?></p><p><?=h(($row['leave_type']??'Request-Off').' / '.implode(', ',json_decode($row['dates_json'],true)))?></p><p>Requested duration: <?=count(json_decode($row['dates_json'],true))?> day(s)</p><p><?=h($row['reason'])?></p><?php if($kind==='OFF'):?><p>Scheduling outcome: <?=h($row['outcome'])?></p><?php endif;?>
<?php if($row['approval_method']==='AUTOMATIC_ROLE_EXEMPTION'):?><p>No internal approval required for the Chief Nurse. Approval method: AUTOMATIC_ROLE_EXEMPTION. No approver or signature was assigned.</p><?php endif;?>
<ol><?php foreach(json_decode($row['route_json'],true) as $index=>$stage):?><li><?=h($stage['role'])?> — <?=$index<(int)$row['stage']?'Completed':($index===(int)$row['stage']&&$row['status']==='PENDING_APPROVAL'?'Current approval level':'Pending / closed')?>; Assigned IDs: <?=h(implode(', ',$stage['approvers']))?></li><?php endforeach;?></ol>
<details open><summary>Schedule conflicts</summary><?php $found=false;foreach(exRows($conn,"SELECT department,month_start,final_json FROM nursing_schedule_months WHERE final_status='PUBLISHED'") as $schedule){$payload=nsScheduleDecode($schedule['final_json']);foreach(json_decode($row['dates_json'],true) as $date){$code=$payload['assignments'][$row['employee_id']][$date]??null;if($code&&((nsScheduleDuties()[$code]??0)>0)){$found=true;echo '<p>'.h($date.' / '.$schedule['department'].' / '.$code).'</p>';}}}if(!$found)echo '<p>No published duty conflicts found.</p>';?></details>
<div class="table-responsive"><table class="schedule-table"><thead><tr><th>Round</th><th>Actor / role</th><th>Decision</th><th>Remarks</th><th>Server timestamp</th></tr></thead><tbody><?php foreach($events as $event):?><tr class="<?=((int)$event['round_no']!==(int)$row['round_no'])?'request-superseded-round':''?>"><td><?=h($event['round_no'])?><?=((int)$event['round_no']!==(int)$row['round_no'])?' / superseded':''?></td><td><?=h($event['actor_name'].' / '.$event['actor_role'])?></td><td><?=h($event['decision'])?></td><td><?=h($event['remarks'])?></td><td><?=h($event['created_at'])?></td></tr><?php endforeach;?></tbody></table></div>
<?php if($kind==='LEAVE'):?><p>Nursing Service clearance only. Proceed to HR for official leave filing; this document is not final official leave authorization.</p><?php if($row['status']==='APPROVED_FOR_HR_FILING'):?><button type="button" class="btn btn-light request-controls" onclick="window.print()">Print Internal Leave Approval Form</button><?php endif;?><?php endif;?></section>
<?php endif;?>
</main></div><script>
function initialiseRequestEncoder(){
const requestEmployee=document.getElementById('requestEmployee'),requestDepartment=document.getElementById('requestDepartment');
function updateRequestEmployee(){if(!requestEmployee)return;const data=requestEmployee.selectedOptions[0]?.dataset||{};for(const [id,key] of [['requestEmployeeId','employeeId'],['requestEmployeeName','name'],['requestEmployeePosition','position'],['requestEmployeeDepartment','department']])document.getElementById(id).value=data[key]||'';}
function filterRequestEmployees(selectedEmployee=''){
    if(!requestEmployee||!requestDepartment)return;
    const department=requestDepartment.value,options=[...document.getElementById('requestEmployeeOptions').content.querySelectorAll('option')].filter(option=>option.dataset.department===department);
    requestEmployee.replaceChildren(new Option(department?'Select an employee':'Select a department first',''),...options.map(option=>option.cloneNode(true)));
    requestEmployee.disabled=!department||!options.length;requestEmployee.value=selectedEmployee;updateRequestEmployee();
}
requestDepartment?.addEventListener('change',()=>filterRequestEmployees());requestEmployee?.addEventListener('change',updateRequestEmployee);filterRequestEmployees(requestEmployee?.dataset.selectedEmployee||'');
const requestType=document.getElementById('requestType');
function updateRequestLeaveRules(){const rules={WL:'Wellness Leave: 5 days per calendar year, maximum 3 consecutive days.',FL:'Force Leave: 5 days per calendar year; all 5 may be consecutive.',SPL:'Special Privilege Leave: 3 days per calendar year.'};const note=document.getElementById('requestLeaveRules');if(note)note.textContent=rules[requestType?.value]||'';}
updateRequestLeaveRules();
let requestTypeSequence=0,requestTypeAbort=null;
requestType?.addEventListener('change',async()=>{
    const sequence=++requestTypeSequence;requestTypeAbort?.abort();
    const type=requestType.value;updateRequestLeaveRules();
    const card=requestType.closest('[data-request-kind]'),kind=type==='OFF'?'OFF':'LEAVE';
    if(!type||kind===card.dataset.requestKind){card.querySelectorAll('button').forEach(button=>button.disabled=false);card.removeAttribute('aria-busy');}
    if(!type)return;
    if(kind===card.dataset.requestKind){const leave=document.getElementById('requestLeaveType');if(leave)leave.value=type;return;}
    const url=new URL('request_application.php',location.href);url.searchParams.set('view','new');url.searchParams.set('kind',kind);if(kind==='LEAVE')url.searchParams.set('leave_type',type);
    if(requestDepartment?.value)url.searchParams.set('department',requestDepartment.value);if(requestEmployee?.value)url.searchParams.set('employee_id',requestEmployee.value);
    const reason=card.querySelector('[name=reason]')?.value||'',buttons=[...card.querySelectorAll('button')];buttons.forEach(button=>button.disabled=true);card.setAttribute('aria-busy','true');
    requestTypeAbort=new AbortController();
    try{
        const response=await fetch(url,{credentials:'same-origin',signal:requestTypeAbort.signal});if(!response.ok||response.redirected)throw Error('Unable to load the selected request type.');
        const page=new DOMParser().parseFromString(await response.text(),'text/html'),replacement=page.querySelector('[data-request-kind]');if(!replacement)throw Error('The selected request form is unavailable.');
        if(sequence!==requestTypeSequence||!card.isConnected)return;
        replacement.querySelector('[name=reason]').value=reason;card.replaceWith(replacement);initialiseRequestEncoder();document.getElementById('requestType')?.focus({preventScroll:true});
    }catch(error){if(sequence!==requestTypeSequence||error.name==='AbortError')return;requestType.value=card.dataset.requestKind==='OFF'?'OFF':document.getElementById('requestLeaveType')?.value||'';const note=document.getElementById('requestLeaveRules');if(note)note.textContent=error.message;}
    finally{if(sequence===requestTypeSequence){buttons.forEach(button=>button.disabled=false);card.removeAttribute('aria-busy');}}
});
const requestDates=document.getElementById("requestDates"),requestMonth=document.getElementById("requestMonth");
function formatSelectedDateRanges(dates){
    const ordered=[...new Set(dates)].sort(),ranges=[];if(!ordered.length)return '';
    const display=date=>date.slice(5,7)+'-'+date.slice(8,10)+'-'+date.slice(0,4);
    let start=ordered[0],end=start;
    const append=()=>ranges.push(start===end?display(start):display(start)+' - '+display(end));
    for(const date of ordered.slice(1)){if(Date.parse(date+'T00:00:00Z')===Date.parse(end+'T00:00:00Z')+86400000)end=date;else{append();start=end=date;}}
    append();return ranges.join(', ');
}
function updateSelectedDateRanges(){const display=document.getElementById('requestDateRanges');if(display&&requestDates)display.value=formatSelectedDateRanges(requestDates.value.split(/[\s,]+/).filter(Boolean));const duration=document.getElementById("leaveDuration");if(duration&&requestDates)duration.textContent="Requested Duration: "+new Set(requestDates.value.split(/[\s,]+/).filter(Boolean)).size+" days";}
let offCalendarDrag=null;
let leaveBalances={},leaveBalanceSequence=0;
const leaveBalanceNotice=document.createElement('p');leaveBalanceNotice.className='full';leaveBalanceNotice.setAttribute('aria-live','polite');document.getElementById('requestOffCalendar')?.before(leaveBalanceNotice);
async function loadLeaveBalance(){
    const sequence=++leaveBalanceSequence;leaveBalances={};
    if(!['FL','WL','SPL'].includes(requestType?.value)){leaveBalanceNotice.textContent='';return;}
    const employee=requestEmployee?.value;if(requestEmployee&&!employee){leaveBalanceNotice.textContent='Select an employee to check remaining leave.';return;}
    leaveBalanceNotice.textContent='Checking remaining leave…';
    const years=[...new Set([requestMonth.value.slice(0,4),...requestDates.value.split(/[\s,]+/).filter(Boolean).map(date=>date.slice(0,4))])];
    try{const entries=await Promise.all(years.map(async year=>{const url=new URL(location.href);url.search='';url.searchParams.set('leave_balance','1');url.searchParams.set('year',year);if(employee)url.searchParams.set('employee_id',employee);const id=document.querySelector('form [name=id]')?.value;if(id)url.searchParams.set('request_id',id);const response=await fetch(url,{credentials:'same-origin'});if(!response.ok)throw Error();return [year,(await response.json()).remaining];}));if(sequence!==leaveBalanceSequence)return;leaveBalances=Object.fromEntries(entries);const year=requestMonth.value.slice(0,4);leaveBalanceNotice.textContent='Remaining '+requestType.selectedOptions[0].textContent+': '+leaveBalances[year][requestType.value]+' days in '+year+'.';}
    catch{if(sequence===leaveBalanceSequence)leaveBalanceNotice.textContent='Unable to check remaining leave. Please try selecting the employee or month again.';}
}
function leaveSelectionAllowed(dates){
    const type=requestType?.value;if(!['WL','FL','SPL'].includes(type))return true;
    const ordered=[...dates].sort(),counts={};let previous='',run=0;
    for(const date of ordered){const year=date.slice(0,4);counts[year]=(counts[year]||0)+1;const remaining=leaveBalances[year]?.[type];if(remaining===undefined){leaveBalanceNotice.textContent='Please wait for the remaining leave check before selecting dates.';return false;}if(counts[year]>remaining){leaveBalanceNotice.textContent='You can select only '+remaining+' remaining days in '+year+'.';return false;}run=Date.parse(date+'T00:00:00Z')===Date.parse(previous+'T00:00:00Z')+86400000?run+1:1;previous=date;if(type==='WL'&&run>3){leaveBalanceNotice.textContent='Wellness Leave allows a maximum of 3 consecutive days. Select any remaining days separately.';return false;}}
    return true;
}
requestEmployee?.addEventListener('change',loadLeaveBalance);requestDepartment?.addEventListener('change',loadLeaveBalance);requestType?.addEventListener('change',loadLeaveBalance);requestMonth?.addEventListener('change',loadLeaveBalance);
document.querySelector('form [name=dates_text]')?.closest('form')?.addEventListener('submit',event=>{if(!leaveSelectionAllowed(new Set(requestDates.value.split(/[\s,]+/).filter(Boolean))))event.preventDefault();});
loadLeaveBalance();
function updateOffSelection(dates){
    if(!leaveSelectionAllowed(dates))return;
    requestDates.value=[...dates].sort().join(', ');updateSelectedDateRanges();
    document.querySelectorAll('[data-off-date]').forEach(button=>{const selected=dates.has(button.dataset.offDate);button.setAttribute('aria-pressed',String(selected));button.classList.toggle('btn-primary',selected);button.classList.toggle('btn-light',!selected);});
}
function previewOffRange(date){
    const drag=offCalendarDrag;if(!drag)return;
    const from=Math.min(drag.start,Number(date.slice(-2))),to=Math.max(drag.start,Number(date.slice(-2))),dates=new Set(drag.base);
    for(let day=from;day<=to;day++){const value=requestMonth.value+'-'+String(day).padStart(2,'0');if(drag.remove)dates.delete(value);else dates.add(value);}
    updateOffSelection(dates);
}
function renderOffCalendar(){
    updateSelectedDateRanges();const panel=document.getElementById('requestOffCalendar');if(!panel||!requestMonth.value)return;
    const month=requestMonth.value,selected=new Set(requestDates.value.split(/[\s,]+/).filter(Boolean)),[year,m]=month.split('-').map(Number),days=new Date(Date.UTC(year,m,0)).getUTCDate(),offset=new Date(month+'-01T00:00:00Z').getUTCDay();
    panel.innerHTML='<p>Click or drag across dates to select days.</p><div class="request-date-grid">'+['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d=>'<small>'+d+'</small>').join('')+'<span></span>'.repeat(offset)+Array.from({length:days},(_,i)=>{const date=month+'-'+String(i+1).padStart(2,'0');return '<button type="button" class="btn '+(selected.has(date)?'btn-primary':'btn-light')+'" data-off-date="'+date+'" aria-pressed="'+selected.has(date)+'">'+(i+1)+'</button>';}).join('')+'</div>';
    panel.onpointerdown=event=>{
        const button=event.target.closest('[data-off-date]');if(!button||event.button!==0||offCalendarDrag)return;
        event.preventDefault();const date=button.dataset.offDate,base=new Set(requestDates.value.split(/[\s,]+/).filter(Boolean));
        offCalendarDrag={pointerId:event.pointerId,start:Number(date.slice(-2)),base,remove:base.has(date)};
        try{panel.setPointerCapture(event.pointerId);}catch{}previewOffRange(date);
    };
    panel.onpointermove=event=>{if(!offCalendarDrag||event.pointerId!==offCalendarDrag.pointerId)return;const button=document.elementFromPoint(event.clientX,event.clientY)?.closest('[data-off-date]');if(button&&panel.contains(button))previewOffRange(button.dataset.offDate);};
    panel.onpointerup=event=>{if(offCalendarDrag&&event.pointerId===offCalendarDrag.pointerId){offCalendarDrag=null;renderOffCalendar();}};
    panel.onpointercancel=event=>{if(offCalendarDrag&&event.pointerId===offCalendarDrag.pointerId){updateOffSelection(offCalendarDrag.base);offCalendarDrag=null;renderOffCalendar();}};
    panel.onlostpointercapture=()=>{offCalendarDrag=null;};
    panel.onclick=event=>{if(event.detail!==0)return;const button=event.target.closest('[data-off-date]');if(!button)return;const dates=new Set(requestDates.value.split(/[\s,]+/).filter(Boolean)),date=button.dataset.offDate;if(dates.has(date))dates.delete(date);else dates.add(date);updateOffSelection(dates);};
}
requestMonth?.addEventListener("change",()=>{offCalendarDrag=null;if(document.getElementById("requestOffCalendar")?.dataset.dateKind==="OFF")requestDates.value="";renderOffCalendar();});renderOffCalendar();
updateSelectedDateRanges();
}
initialiseRequestEncoder();
</script></body></html>
