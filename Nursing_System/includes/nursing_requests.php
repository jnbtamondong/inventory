<?php
require_once __DIR__.'/duty_exchange.php';
require_once __DIR__.'/schedule_policy.php';
function nrSchema(mysqli $db): void {
    exSchema($db);nsPolicySchema($db);
    $db->query("CREATE TABLE IF NOT EXISTS nursing_service_request_notifications(request_id INT NOT NULL,recipient_id INT NOT NULL,request_version INT NOT NULL,notification_id INT NOT NULL,PRIMARY KEY(request_id,recipient_id,request_version),UNIQUE KEY request_notice_id(notification_id)) ENGINE=InnoDB");
    foreach([
    "CREATE TABLE IF NOT EXISTS nursing_service_requests(id INT AUTO_INCREMENT PRIMARY KEY,request_no VARCHAR(40) NULL UNIQUE,kind VARCHAR(10) NOT NULL,employee_id INT NOT NULL,department VARCHAR(50) NOT NULL,position VARCHAR(100) NOT NULL,status VARCHAR(40) NOT NULL,leave_type VARCHAR(30) NULL,dates_json TEXT NOT NULL,reason VARCHAR(2000) NOT NULL,route_json TEXT NOT NULL,stage INT NOT NULL DEFAULT 0,round_no INT NOT NULL DEFAULT 1,version INT NOT NULL DEFAULT 1,submission_key VARCHAR(64) NOT NULL,approval_method VARCHAR(40) NOT NULL DEFAULT 'SEQUENTIAL',outcome VARCHAR(30) NOT NULL DEFAULT 'NOT_YET_SCHEDULED',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY request_submission(employee_id,kind,submission_key),KEY request_status(kind,status,department)) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_service_request_events(id INT AUTO_INCREMENT PRIMARY KEY,request_id INT NOT NULL,round_no INT NOT NULL,actor_id INT NOT NULL,actor_name VARCHAR(160) NOT NULL,actor_role VARCHAR(50) NOT NULL,decision VARCHAR(40) NOT NULL,remarks VARCHAR(2000) NOT NULL,before_json LONGTEXT NULL,after_json LONGTEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY request_events(request_id,id)) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_leave_policy(code VARCHAR(30) PRIMARY KEY,label VARCHAR(100) NOT NULL,enabled TINYINT NOT NULL DEFAULT 1,max_days INT NULL,required_document TINYINT NOT NULL DEFAULT 0,eligible_roles TEXT NULL) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_request_supporting_documents(id INT AUTO_INCREMENT PRIMARY KEY,request_id INT NOT NULL,original_name VARCHAR(255) NOT NULL,storage_name VARCHAR(100) NOT NULL,mime_type VARCHAR(100) NOT NULL,uploaded_by INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY request_document(request_id)) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_document_audit_logs(id INT AUTO_INCREMENT PRIMARY KEY,employee_document_id INT NULL,user_id INT NULL,employee_id INT NULL,action VARCHAR(80),remarks TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_doc_audit_created(created_at)) ENGINE=InnoDB"
    ] as $sql)$db->query($sql);
    if(!exOne($db,"SHOW COLUMNS FROM nursing_leave_policy LIKE 'credited_hours'"))$db->query('ALTER TABLE nursing_leave_policy ADD COLUMN credited_hours DECIMAL(5,2) NOT NULL DEFAULT 0');
    if(!exOne($db,"SHOW COLUMNS FROM nursing_service_requests LIKE 'employee_name'"))$db->query("ALTER TABLE nursing_service_requests ADD COLUMN employee_name VARCHAR(160) NOT NULL DEFAULT ''");
    if(!exOne($db,"SHOW COLUMNS FROM nursing_service_requests LIKE 'employee_role'"))$db->query("ALTER TABLE nursing_service_requests ADD COLUMN employee_role VARCHAR(40) NOT NULL DEFAULT ''");
    foreach(['VL'=>'Vacation Leave','SL'=>'Sick Leave','SPL'=>'Special Privilege Leave','WL'=>'Wellness Leave','FL'=>'Force Leave','ML'=>'Maternity Leave','PL'=>'Paternity Leave','SOLO_PARENT'=>'Solo Parent Leave','OTHER_LEAVE'=>'Other Authorized Leave'] as $code=>$label)nsScheduleQuery($db,'INSERT IGNORE INTO nursing_leave_policy(code,label) VALUES(?,?)',[$code,$label]);
}
function nrValidateLeaveRules(mysqli $db,int $employeeId,string $type,array $dates,int $ignoreId=0,bool $balanceOnly=false): array {
    $limits=['WL'=>[5,3],'FL'=>[5,5],'SPL'=>[3,0]];if(!isset($limits[$type]))return [];
    [$total,$consecutive]=$limits[$type];$leaveLabel=['WL'=>'Wellness Leave','FL'=>'Force Leave','SPL'=>'Special Privilege Leave'][$type];
    $combined=$dates;$existingDates=[];$recordedDates=[];
    foreach(exRows($db,"SELECT dates_json FROM nursing_service_requests WHERE employee_id=? AND kind='LEAVE' AND leave_type=? AND id<>? AND status IN ('PENDING_APPROVAL','APPROVED_FOR_HR_FILING')",[$employeeId,$type,$ignoreId]) as $row){$days=json_decode($row['dates_json'],true);$existingDates=array_merge($existingDates,$days);$combined=array_merge($combined,$days);}
    $years=array_values(array_unique(array_map(fn($date)=>substr($date,0,4),$dates)));sort($years);
    if(exOne($db,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nursing_employee_attendance'")){
        $first=(new DateTimeImmutable($years[0].'-01-01'))->modify('-'.$consecutive.' days')->format('Y-m-d');
        $last=(new DateTimeImmutable(end($years).'-12-31'))->modify('+'.$consecutive.' days')->format('Y-m-d');
        foreach(exRows($db,'SELECT record_date,leave_type,COALESCE(leave_end_date,DATE_ADD(record_date,INTERVAL GREATEST(COALESCE(CEIL(leave_days),1)-1,0) DAY)) AS end_date FROM nursing_employee_attendance WHERE employee_id=? AND record_date<=? AND COALESCE(leave_end_date,DATE_ADD(record_date,INTERVAL GREATEST(COALESCE(CEIL(leave_days),1)-1,0) DAY))>=?',[$employeeId,$last,$first]) as $record){
            if(nrLeaveCode($record['leave_type']??'')!==$type)continue;
            for($day=max($record['record_date'],$first);$day<=min($record['end_date'],$last);$day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d')){$combined[]=$day;$existingDates[]=$day;$recordedDates[]=$day;}
        }
    }
    $combined=array_values(array_unique($combined));sort($combined);$previous=null;$run=0;
    $totals=[];foreach($combined as $date){$year=substr($date,0,4);$totals[$year]=($totals[$year]??0)+1;}
    $existingTotals=[];foreach(array_unique($existingDates) as $date){$year=substr($date,0,4);$existingTotals[$year]=($existingTotals[$year]??0)+1;}
    $recordedTotals=[];foreach(array_unique($recordedDates) as $date){$year=substr($date,0,4);$recordedTotals[$year]=($recordedTotals[$year]??0)+1;}
    $column=['WL'=>'wellness_leave_used','FL'=>'force_leave_used','SPL'=>'special_privilege_leave_used'][$type];
    if(exOne($db,"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nursing_employee_leave_summary' AND COLUMN_NAME=?",[$column]))foreach($years as $year){
        $summary=exOne($db,"SELECT `$column` AS used FROM nursing_employee_leave_summary WHERE employee_id=? AND record_year=?",[$employeeId,$year]);
        $additional=max(0,(float)($summary['used']??0)-($recordedTotals[$year]??0));$totals[$year]=($totals[$year]??0)+$additional;$existingTotals[$year]=($existingTotals[$year]??0)+$additional;
    }
    $remaining=[];foreach($years as $year)$remaining[$year]=max(0,$total-($existingTotals[$year]??0));
    if($balanceOnly)return $remaining;
    if(!$ignoreId)foreach($years as $year)if(($existingTotals[$year]??0)>=$total)throw new DomainException('No '.$leaveLabel.' allowance remains for '.$year.'. You cannot file another application.');
    foreach($years as $year)if(($totals[$year]??0)>$total)throw new DomainException($leaveLabel.' allows '.$total.' days per calendar year. Pending, internally approved and recorded leave in '.$year.' exceed this allowance.');
    if($consecutive)foreach($combined as $date){$run=$previous!==null&&strtotime($date.' UTC')===strtotime($previous.' UTC')+86400?$run+1:1;$previous=$date;if($run>$consecutive&&array_intersect(array_slice($combined,array_search($date,$combined,true)-$run+1,$run),$dates))throw new DomainException($leaveLabel.' allows a maximum of '.$consecutive.' consecutive days, including adjacent pending or approved requests.');}
    return $remaining;
}
function nrEvent(mysqli $db,array $actor,array $row,string $decision,string $remarks,?array $before): void {
    nsScheduleQuery($db,'INSERT INTO nursing_service_request_events(request_id,round_no,actor_id,actor_name,actor_role,decision,remarks,before_json,after_json) VALUES(?,?,?,?,?,?,?,?,?)',[$row['id'],$row['round_no'],$actor['user_id'],$actor['name'],naRole($actor),$decision,$remarks,$before?nsScheduleEncode($before):null,nsScheduleEncode($row)]);
    if($row['kind']==='LEAVE'&&$row['status']==='APPROVED_FOR_HR_FILING'&&($before['status']??null)!=='APPROVED_FOR_HR_FILING')nsScheduleQuery($db,'INSERT INTO nursing_document_audit_logs(user_id,employee_id,action,remarks) VALUES(?,?,?,?)',[$actor['user_id'],$row['employee_id'],'Leave Application Approved',$row['request_no'].' | '.$row['status'].' | '.$remarks]);
}
function nrStageApprovers(mysqli $db,array $row,int $index): array {
    $route=json_decode($row['route_json'],true);$stage=$route[$index]??null;if(!$stage)return [];$employee=exOne($db,'SELECT * FROM users WHERE user_id=?',[$row['employee_id']]);if(!$employee)return [];
    $employee['department']=$row['department'];$current=naResolve($db,$employee,$stage['role'],[(int)$row['employee_id']]);$configured=exRows($db,'SELECT approver_id FROM nursing_reporting_hierarchy WHERE employee_id=? AND approver_role=? AND enabled=1',[$row['employee_id'],$stage['role']]);return $configured?$current:array_values(array_intersect(array_map('intval',$stage['approvers']),$current));
}
function nrCanDecide(mysqli $db,array $actor,array $row): bool {
    if(!naApprovalAccess($actor)||(int)$actor['user_id']===(int)$row['employee_id']||$row['status']!=='PENDING_APPROVAL')return false;
    $route=json_decode($row['route_json'],true);$stage=$route[$row['stage']]??null;
    if($stage&&naRole($actor)==='SUPER_ADMIN')return true;
    return $stage&&naRole($actor)===$stage['role']&&in_array((int)$actor['user_id'],nrStageApprovers($db,$row,(int)$row['stage']),true);
}
function nrCanView(mysqli $db,array $actor,array $row): bool {
    if((int)$row['employee_id']===(int)$actor['user_id']||naRole($actor)==='SUPER_ADMIN')return true;
    foreach(json_decode($row['route_json'],true) as $index=>$stage)if(naRole($actor)===$stage['role']&&(in_array((int)$actor['user_id'],$stage['approvers'],true)||in_array((int)$actor['user_id'],nrStageApprovers($db,$row,$index),true)))return true;
    if(in_array(naRole($actor),['HEAD_NURSE','SUPERVISOR_NURSE','ASSISTANT_CHIEF_NURSE','CHIEF_NURSE'],true)&&exOne($db,"SELECT id FROM nursing_service_request_events WHERE request_id=? AND actor_id=? AND decision IN ('APPROVE','REJECT','RETURN') LIMIT 1",[$row['id'],$actor['user_id']]))return true;
    return false;
}
function nrCanEncode(array $actor,string $kind): bool {
    return in_array($kind,['OFF','LEAVE'],true)&&(naRole($actor)==='SUPER_ADMIN'||(naEligible($actor)&&($kind==='LEAVE'||(bool)naRoute('OFF',$actor))));
}
function nrCanEditRequest(array $actor,array $row): bool {
    return in_array($row['status']??'',['DRAFT','RETURNED'],true)&&((int)($actor['user_id']??0)===(int)($row['employee_id']??-1)||naRole($actor)==='SUPER_ADMIN');
}
function nrCanUploadDocument(array $actor,array $row): bool {
    return ($row['kind']??'')==='LEAVE'&&in_array($row['status']??'',['PENDING_APPROVAL','RETURNED','APPROVED_FOR_HR_FILING'],true)&&((int)($actor['user_id']??0)===(int)($row['employee_id']??-1)||naRole($actor)==='SUPER_ADMIN');
}
function nrPublishedAssignments(mysqli $db,array $row): array {$dates=json_decode($row['dates_json'],true);$periods=[];foreach($dates as $date)$periods[substr($date,0,7).'-01']=true;$out=[];foreach(exRows($db,'SELECT final_json FROM nursing_schedule_months WHERE final_status="PUBLISHED" AND month_start IN ('.implode(',',array_fill(0,count($periods),'?')).')',array_keys($periods)) as $month){$payload=nsScheduleDecode($month['final_json']);foreach($dates as $date)if(isset($payload['assignments'][$row['employee_id']][$date]))$out[$date][]=$payload['assignments'][$row['employee_id']][$date];}return $out;}
function nrCanWithdraw(mysqli $db,array $actor,array $row): bool {if((int)$actor['user_id']!==(int)$row['employee_id'])return false;if(in_array($row['status'],['DRAFT','PENDING_APPROVAL','RETURNED'],true))return true;return $row['kind']==='OFF'&&$row['status']==='APPROVED'&&!nrPublishedAssignments($db,$row);}
function nrNotify(mysqli $db,array $row): void {
    $ids=[(int)$row['employee_id']];if($row['status']==='PENDING_APPROVAL')$ids=array_merge($ids,nrStageApprovers($db,$row,(int)$row['stage']));
    $label=match($row['status']){'PENDING_APPROVAL'=>'Pending review','APPROVED_FOR_HR_FILING'=>'Cleared for HR Filing (Nursing Service only)','WITHDRAWN'=>'Cancelled',default=>ucfirst(strtolower(str_replace('_',' ',$row['status'])))};
    $schedule=($row['kind']==='OFF'&&($row['outcome']??'NOT_YET_SCHEDULED')!=='NOT_YET_SCHEDULED')?' Schedule: '.str_replace('_',' ',strtolower($row['outcome'])).'.':'';
    $message=$row['request_no'].': '.$label.'.'.$schedule.' Open request details for reviewer, reason and history.';
    foreach(array_unique($ids) as $id){
        if(exOne($db,'SELECT notification_id FROM nursing_service_request_notifications WHERE request_id=? AND recipient_id=? AND request_version=?',[$row['id'],$id,$row['version']]))continue;
        nsScheduleQuery($db,'INSERT INTO nursing_document_notifications(employee_document_id,employee_id,notification_type,message,notification_date) VALUES(NULL,?,?,?,CURDATE())',[$id,$row['kind']==='OFF'?'REQUEST_OFF':($row['status']==='APPROVED_FOR_HR_FILING'?'LEAVE_APPROVED':'INTERNAL_LEAVE'),$message]);$notice=$db->insert_id;
        nsScheduleQuery($db,'INSERT INTO nursing_service_request_notifications(request_id,recipient_id,request_version,notification_id) VALUES(?,?,?,?)',[$row['id'],$id,$row['version'],$notice]);
    }
}
function nrSave(mysqli $db,array $actor,array $input): array {
    $kind=$input['kind']??'';if(!nrCanEncode($actor,$kind))throw new DomainException('This account is not eligible.');
    $admin=naRole($actor)==='SUPER_ADMIN';$correction=null;
    if(!empty($input['id'])){
        $correction=exOne($db,'SELECT * FROM nursing_service_requests WHERE id=?',[$input['id']]);
        if(!$correction||$correction['kind']!==$kind||(!$admin&&(int)$correction['employee_id']!==(int)$actor['user_id']))throw new DomainException('Only the applicant or System Admin can correct this request.');
        if($admin&&isset($input['employee_id'])&&(int)$input['employee_id']!==(int)$correction['employee_id'])throw new DomainException('The applicant cannot be changed on an existing request.');
        $employeeId=(int)$correction['employee_id'];
    }elseif($admin){
        $employeeId=filter_var($input['employee_id']??null,FILTER_VALIDATE_INT);
        if(!$employeeId||$employeeId<1)throw new InvalidArgumentException('Select an active nursing employee.');
    }else $employeeId=(int)$actor['user_id'];
    $employee=$employeeId===(int)$actor['user_id']?$actor:exActor($db,$employeeId);
    if(!naEligible($employee)||!nrCanEncode($employee,$kind))throw new DomainException('The selected employee is not eligible for this request.');
    $requestDepartment=null;
    if($admin&&!$correction&&array_key_exists('request_department',$input)){
        if(!is_string($input['request_department'])||trim($input['request_department'])==='')throw new InvalidArgumentException('Select a department before selecting an employee.');
        $requestDepartment=canonicalDepartment($input['request_department']);
        if($requestDepartment!==$employee['unit'])throw new DomainException('Select an employee from the selected department.');
    }
    $draft=$kind==='OFF'&&($input['action']??'')==='save_draft';
    $roles=naRoute($kind,$employee);if($kind==='OFF'&&!$roles)throw new DomainException('Request-Off is available only to Nursing Attendants, Staff Nurses, and Head Nurses.');
    $dates=array_values(array_unique($input['dates']??[]));sort($dates);if(!$dates||count($dates)>366)throw new InvalidArgumentException('Select one or more dates.');
    foreach($dates as $date)exDate($date);if($kind==='OFF'&&substr($dates[0],0,7)!==substr(end($dates),0,7))throw new InvalidArgumentException('Request-Off dates must be within one month.');
    if($kind==='OFF'&&!empty($input['month'])){nsScheduleMonth($input['month']);if(substr($dates[0],0,7)!==$input['month'])throw new InvalidArgumentException('Preferred OFF dates must be in the selected month.');}
    $reason=trim($input['reason']??'');if(($kind==='LEAVE'&&!$reason)||strlen($reason)>2000)throw new InvalidArgumentException('Enter a reason of up to 2,000 characters.');
    $type=null;if($kind==='LEAVE'){$type=$input['leave_type']??'';$policy=exOne($db,'SELECT * FROM nursing_leave_policy WHERE code=? AND enabled=1',[$type]);if(!$policy)throw new DomainException('This leave type is unavailable.');if($policy['max_days']!==null&&count($dates)>(int)$policy['max_days'])throw new DomainException('The requested duration exceeds configured policy.');$eligible=json_decode($policy['eligible_roles']??'null',true);if($eligible&&!in_array(naRole($employee),$eligible,true))throw new DomainException('This leave type is not enabled for the applicant position.');}
    $key=$input['submission_key']??'';if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/',$key))throw new InvalidArgumentException('Invalid submission key.');
    $db->begin_transaction();try{
        nsScheduleQuery($db,'SELECT id FROM nursing_exchange_mutex WHERE id=1 FOR UPDATE');
        nsScheduleQuery($db,'SELECT user_id FROM users WHERE user_id IN (?,?) ORDER BY user_id FOR UPDATE',[$actor['user_id'],$employeeId]);
        $liveActor=exActor($db,(int)$actor['user_id']);if(naRole($liveActor)!==naRole($actor)||$liveActor['unit']!==$actor['unit'])throw new RuntimeException('Employee role or department changed. Reload before submitting.',409);$actor=$liveActor;
        if(!nrCanEncode($actor,$kind)||(!$admin&&(int)$actor['user_id']!==$employeeId))throw new DomainException('This account cannot encode the request.');
        $liveEmployee=exActor($db,$employeeId);if(naRole($liveEmployee)!==naRole($employee)||$liveEmployee['unit']!==$employee['unit'])throw new RuntimeException('Applicant role or department changed. Reload before submitting.',409);$employee=$liveEmployee;
        if(!naEligible($employee)||!nrCanEncode($employee,$kind))throw new DomainException('The selected employee is not eligible for this request.');
        if($requestDepartment!==null&&$requestDepartment!==$employee['unit'])throw new DomainException('Select an employee from the selected department.');
        $roles=naRoute($kind,$employee);
        if($kind==='LEAVE'&&nsScheduleEncode($policy)!==nsScheduleEncode(exOne($db,'SELECT * FROM nursing_leave_policy WHERE code=? AND enabled=1',[$type])))throw new RuntimeException('Leave policy changed. Reload before submitting.',409);
        $existing=exOne($db,'SELECT * FROM nursing_service_requests WHERE employee_id=? AND kind=? AND submission_key=? FOR UPDATE',[$employeeId,$kind,$key]);
        if($existing){$db->commit();return $existing;}
        $before=null;if(!empty($input['id'])){$before=exOne($db,'SELECT * FROM nursing_service_requests WHERE id=? FOR UPDATE',[$input['id']]);if(!$before||!nrCanEditRequest($actor,$before)||(int)$before['employee_id']!==$employeeId||$before['kind']!==$kind||(int)$before['version']!==(int)($input['version']??0))throw new DomainException('Only an applicant or System Admin can correct a draft or returned request with its current version.');}
        if($kind==='LEAVE')nrValidateLeaveRules($db,$employeeId,$type,$dates,(int)($before['id']??0));
        if($kind==='OFF')foreach(exRows($db,"SELECT final_json FROM nursing_schedule_months WHERE month_start=? AND final_status='PUBLISHED'",[substr($dates[0],0,7).'-01']) as $published){$document=nsScheduleDecode($published['final_json']);foreach($dates as $date)if(isset($document['assignments'][$employeeId][$date]))throw new DomainException('The duty period is already published. Use Exchange of Duty for changes to published assignments.');}
        $route=[];foreach($draft?[]:$roles as $role){$approvers=naResolve($db,$employee,$role,[$employeeId]);if(!$approvers)throw new DomainException('No independent '.$role.' is configured. Ask the administrator to assign the reporting hierarchy.');$route[]=['role'=>$role,'approvers'=>$approvers];}
        $status=$draft?'DRAFT':($route?'PENDING_APPROVAL':'APPROVED_FOR_HR_FILING');$method=$draft||$route?'SEQUENTIAL':'AUTOMATIC_ROLE_EXEMPTION';
        if($before){nsScheduleQuery($db,'UPDATE nursing_service_requests SET department=?,position=?,status=?,leave_type=?,dates_json=?,reason=?,route_json=?,stage=0,round_no=round_no+?,version=version+1,submission_key=?,approval_method=? WHERE id=?',[$employee['unit'],$employee['position']??'', $status,$type,nsScheduleEncode($dates),$reason,nsScheduleEncode($route),$before['status']==='DRAFT'?0:1,$key,$method,$before['id']]);$id=$before['id'];}
        else{nsScheduleQuery($db,'INSERT INTO nursing_service_requests(kind,employee_id,department,position,status,leave_type,dates_json,reason,route_json,submission_key,approval_method) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$kind,$employeeId,$employee['unit'],$employee['position']??'',$status,$type,nsScheduleEncode($dates),$reason,nsScheduleEncode($route),$key,$method]);$id=$db->insert_id;nsScheduleQuery($db,'UPDATE nursing_service_requests SET request_no=? WHERE id=?',[($kind==='OFF'?'RO':'LV').'-'.date('Y').'-'.sprintf('%06d',$id),$id]);}
        nsScheduleQuery($db,'UPDATE nursing_service_requests SET employee_name=?,employee_role=? WHERE id=?',[$employee['name'],naRole($employee),$id]);$row=exOne($db,'SELECT * FROM nursing_service_requests WHERE id=?',[$id]);nrEvent($db,$actor,$row,$draft?'DRAFT_SAVED':($before&&$before['status']!=='DRAFT'?'RESUBMITTED':'SUBMITTED'),$reason,$before);if(!$draft)nrNotify($db,$row);$db->commit();return $row;
    }catch(Throwable $error){$db->rollback();throw $error;}
}
function nrDecide(mysqli $db,array $actor,array $input): array {
    $decision=$input['decision']??'';$remarks=trim($input['remarks']??'');if(!in_array($decision,['approve','reject','return','withdraw'],true)||strlen($remarks)>2000)throw new InvalidArgumentException('Invalid decision.');if(in_array($decision,['reject','return'],true)&&!$remarks)throw new InvalidArgumentException('Remarks are required for rejection or correction.');
    $db->begin_transaction();try{nsScheduleQuery($db,'SELECT id FROM nursing_exchange_mutex WHERE id=1 FOR UPDATE');$actor=exActor($db,(int)$actor['user_id']);$row=exOne($db,'SELECT * FROM nursing_service_requests WHERE id=? FOR UPDATE',[$input['id']??0]);if(!$row||(int)$row['version']!==(int)($input['version']??0))throw new RuntimeException('The request changed. Reload before deciding.',409);$before=$row;if($row['kind']==='OFF'&&$decision==='return')throw new DomainException('Request-Off decisions are approve or reject; rejection requires remarks.');
        if($decision==='withdraw'){if(!nrCanWithdraw($db,$actor,$row))throw new DomainException('This request cannot be withdrawn after publication or completed internal leave clearance.');$row['status']='WITHDRAWN';}
        else{if(!nrCanDecide($db,$actor,$row))throw new DomainException('Only the assigned independent approver at the current stage can decide.');if($decision==='approve'){$currentEmployee=exActor($db,(int)$row['employee_id']);if((!empty($row['employee_role'])&&naRole($currentEmployee)!==$row['employee_role'])||$currentEmployee['unit']!==$row['department'])throw new DomainException('The applicant role or assigned department changed. Return this request for correction or let the owner withdraw and resubmit.');$policy=$row['kind']==='LEAVE'?exOne($db,'SELECT * FROM nursing_leave_policy WHERE code=?',[$row['leave_type']]):null;if($row['kind']==='LEAVE'){if(!$policy||!(int)$policy['enabled'])throw new DomainException('This leave type is no longer enabled. Return for correction.');$roles=json_decode($policy['eligible_roles']??'null',true);if($roles&&!in_array(naRole($currentEmployee),$roles,true))throw new DomainException('The current leave policy does not allow this position.');if($policy['max_days']!==null&&count(json_decode($row['dates_json'],true))>(int)$policy['max_days'])throw new DomainException('The requested duration exceeds the current configured policy.');}if($row['kind']==='LEAVE')nrValidateLeaveRules($db,(int)$row['employee_id'],$row['leave_type'],json_decode($row['dates_json'],true),(int)$row['id']);if($policy&&!empty($policy['required_document'])&&!exOne($db,'SELECT id FROM nursing_request_supporting_documents WHERE request_id=?',[$row['id']]))throw new DomainException('The required supporting document must be uploaded before approval.');$row['stage']++;$route=json_decode($row['route_json'],true);if($row['stage']>=count($route))$row['status']=$row['kind']==='OFF'?'APPROVED':'APPROVED_FOR_HR_FILING';}else $row['status']=$decision==='reject'?'REJECTED':'RETURNED';}
        if($row['kind']==='OFF'&&$row['status']==='APPROVED'){$published=nrPublishedAssignments($db,$row);$dates=json_decode($row['dates_json'],true);if(count($published)===count($dates)){$row['outcome']='ACCOMMODATED';foreach($published as $codes)if(array_diff($codes,['OFF','REQUEST_OFF']))$row['outcome']='NOT_ACCOMMODATED';}}
        $route=json_decode($row['route_json'],true);foreach($route as $index=>&$routeStage)if($index>=(int)$before['stage'])$routeStage['approvers']=nrStageApprovers($db,$before,$index);unset($routeStage);$row['route_json']=nsScheduleEncode($route);
        $row['version']++;nsScheduleQuery($db,'UPDATE nursing_service_requests SET status=?,stage=?,version=?,outcome=?,route_json=? WHERE id=?',[$row['status'],$row['stage'],$row['version'],$row['outcome'],$row['route_json'],$row['id']]);nrEvent($db,$actor,$row,strtoupper($decision),$remarks,$before);nrNotify($db,$row);$db->commit();return $row;
    }catch(Throwable $error){$db->rollback();throw $error;}
}
function nrScheduleRequests(mysqli $db,array $payload,string $month): array {
    $payload['internalLeaveIndicators']=[];
    $selectedIds=array_column($payload['staff'],'id');
    foreach(exRows($db,"SELECT id,employee_id,leave_type,dates_json FROM nursing_service_requests WHERE kind='LEAVE' AND status='APPROVED_FOR_HR_FILING'") as $cleared)if(in_array((int)$cleared['employee_id'],$selectedIds,true))foreach(json_decode($cleared['dates_json'],true) as $date)if(substr($date,0,7)===$month)$payload['internalLeaveIndicators'][]=['employee_id'=>(int)$cleared['employee_id'],'date'=>$date,'leave_type'=>$cleared['leave_type'],'request_id'=>(int)$cleared['id'],'status'=>'APPROVED_FOR_HR_FILING'];
    $ids=array_map('intval',array_column($payload['staff'],'id'));if(!$ids)return $payload;
    // Internal leave clearance is deliberately excluded: official leave remains the attendance record.
    $payload['requests']=array_values(array_filter($payload['requests']??[],fn($r)=>empty($r['service_request_id'])));
    foreach(exRows($db,"SELECT * FROM nursing_service_requests WHERE kind='OFF' AND status='APPROVED'") as $row)if(in_array((int)$row['employee_id'],$ids,true))foreach(json_decode($row['dates_json'],true) as $date)if(substr($date,0,7)===$month){$payload['requests']=array_values(array_filter($payload['requests'],fn($r)=>!((int)$r['employee_id']===(int)$row['employee_id']&&$r['date']===$date)));$payload['requests'][]=['employee_id'=>(int)$row['employee_id'],'date'=>$date,'type'=>'REQUEST_OFF','status'=>'APPROVED','remarks'=>$row['reason'],'service_request_id'=>(int)$row['id']];}
    if(exOne($db,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nursing_employee_attendance'"))foreach(exRows($db,'SELECT employee_id,record_date,leave_type,COALESCE(leave_end_date,DATE_ADD(record_date,INTERVAL GREATEST(CEIL(leave_days)-1,0) DAY)) AS end_date FROM nursing_employee_attendance WHERE leave_type IS NOT NULL AND leave_type<>"" AND record_date<=LAST_DAY(?) AND COALESCE(leave_end_date,DATE_ADD(record_date,INTERVAL GREATEST(CEIL(leave_days)-1,0) DAY))>=?',[$month.'-01',$month.'-01']) as $row)if(in_array((int)$row['employee_id'],$ids,true))for($date=max($row['record_date'],$month.'-01');$date<=min($row['end_date'],date('Y-m-t',strtotime($month.'-01')));$date=date('Y-m-d',strtotime($date.' +1 day'))){$payload['requests']=array_values(array_filter($payload['requests'],fn($r)=>!((int)$r['employee_id']===(int)$row['employee_id']&&$r['date']===$date)));$payload['requests'][]=['employee_id'=>(int)$row['employee_id'],'date'=>$date,'type'=>'LEAVE','status'=>'APPROVED','remarks'=>'Official recorded leave','official_leave'=>true,'leave_code'=>nrLeaveCode($row['leave_type'])];}
    $payload['leaveCredits']=[];foreach(exRows($db,'SELECT code,credited_hours FROM nursing_leave_policy') as $credit)$payload['leaveCredits'][$credit['code']]=(float)$credit['credited_hours'];
    return $payload;
}
function nrLeaveCode(string $type): string {$key=strtoupper(trim($type));$map=['VACATION LEAVE'=>'VL','SICK LEAVE'=>'SL','SPECIAL PRIVILEGE LEAVE'=>'SPL','WELLNESS LEAVE'=>'WL','FORCE LEAVE'=>'FL','FORCED LEAVE'=>'FL','MATERNITY LEAVE'=>'ML','PATERNITY LEAVE'=>'PL','SOLO PARENT LEAVE'=>'SOLO_PARENT','OTHER AUTHORIZED LEAVE'=>'OTHER_LEAVE'];return $map[$key]??$key;}

function nrSchedulePublishedNotice(mysqli $db,array $payload,string $department,string $month,int $version): void {
    foreach(array_unique(array_column($payload['staff'],'id')) as $id)nsScheduleQuery($db,'INSERT INTO nursing_document_notifications(employee_document_id,employee_id,notification_type,message,notification_date) VALUES(NULL,?,?,?,CURDATE())',[$id,'SCHEDULE_PUBLISHED',substr($department.' '.$month.' published, version '.$version,0,255)]);
}

function nrUploadDocument(mysqli $db,array $actor,int $requestId,array $file): void {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>5*1024*1024||!is_uploaded_file($file['tmp_name']??''))throw new InvalidArgumentException('Choose a PDF, JPEG or PNG document up to 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true))throw new InvalidArgumentException('Only PDF, JPEG and PNG documents are accepted.');
    $dir=__DIR__.'/../storage/nursing-request-documents';$storageName=bin2hex(random_bytes(24));$moved=false;
    $db->begin_transaction();try{
        nsScheduleQuery($db,'SELECT id FROM nursing_exchange_mutex WHERE id=1 FOR UPDATE');$actor=exActor($db,(int)$actor['user_id']);$request=exOne($db,'SELECT * FROM nursing_service_requests WHERE id=? FOR UPDATE',[$requestId]);
        if(!$request||!nrCanUploadDocument($actor,$request))throw new DomainException('Document upload is unavailable.');
        if(!is_dir($dir))mkdir($dir,0700,true);if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$storageName))throw new RuntimeException('Document storage failed.');$moved=true;
        nsScheduleQuery($db,'INSERT INTO nursing_request_supporting_documents(request_id,original_name,storage_name,mime_type,uploaded_by) VALUES(?,?,?,?,?)',[$requestId,basename($file['name']),$storageName,$mime,$actor['user_id']]);nrEvent($db,$actor,$request,'DOCUMENT_UPLOADED',basename($file['name']),$request);$db->commit();
    }catch(Throwable $error){$db->rollback();if($moved)unlink($dir.'/'.$storageName);throw $error;}
}
function nrPublishOutcomes(mysqli $db,array $payload,string $month,string $department,int $version): void {
    foreach(exRows($db,"SELECT * FROM nursing_service_requests WHERE kind='OFF' AND status='APPROVED'") as $row){$dates=array_values(array_filter(json_decode($row['dates_json'],true),fn($d)=>substr($d,0,7)===$month));if(!$dates||!isset($payload['assignments'][$row['employee_id']]))continue;$outcome='ACCOMMODATED';foreach($dates as $date)if(!in_array($payload['assignments'][$row['employee_id']][$date]??null,['OFF','REQUEST_OFF'],true))$outcome='NOT_ACCOMMODATED';if($row['outcome']!==$outcome){$before=$row;$row['outcome']=$outcome;$row['version']++;$row['scheduling_review']=$payload['requestOffOverrides'][$row['employee_id']]??[];nsScheduleQuery($db,'UPDATE nursing_service_requests SET outcome=?,version=version+1 WHERE id=?',[$outcome,$row['id']]);$actor=exActor($db,(int)$payload['published_by']);nrEvent($db,$actor,$row,'SCHEDULING_OUTCOME',substr($department.' '.$month.' version '.$version.' / '.implode('; ',array_map(fn($date,$review)=>$date.': '.$review['reason'],array_keys($row['scheduling_review']),array_values($row['scheduling_review']))),0,2000),$before);nrNotify($db,$row);}}
    foreach($payload['staff'] as $employee)nsScheduleQuery($db,'INSERT INTO nursing_document_notifications(employee_document_id,employee_id,notification_type,message,notification_date) VALUES(NULL,?,?,?,CURDATE())',[$employee['id'],'SCHEDULE_PUBLISHED',$department.' '.$month.' published version '.$version.' /Nursing_System/pages/schedule_management.php']);
}
