<?php
require_once __DIR__.'/schedule_management.php';
const NS_EXCHANGE_PENDING=['PENDING_PARTNER_CONFIRMATION','PENDING_HEAD_NURSE_APPROVAL','PENDING_SUPERVISOR_APPROVAL','PENDING_CHIEF_NURSE_APPROVAL','PENDING_ASSISTANT_CHIEF_APPROVAL'];
function exRows(mysqli $db,string $sql,array $args=[]): array{return nsScheduleQuery($db,$sql,$args)->get_result()->fetch_all(MYSQLI_ASSOC);}
function exOne(mysqli $db,string $sql,array $args=[]): ?array{return exRows($db,$sql,$args)[0]??null;}
function exSchema(mysqli $db): void {
 nsScheduleSchema($db);
 foreach([
 "CREATE TABLE IF NOT EXISTS nursing_exchange_requests(id INT AUTO_INCREMENT PRIMARY KEY,request_no VARCHAR(30) NULL UNIQUE,requester_id INT NOT NULL,partner_id INT NOT NULL,department VARCHAR(50) NOT NULL,status VARCHAR(50) NOT NULL,reason VARCHAR(2000) NOT NULL,original_json LONGTEXT NOT NULL,proposed_json LONGTEXT NOT NULL,validation_json LONGTEXT NOT NULL,version INT NOT NULL DEFAULT 1,round_no INT NOT NULL DEFAULT 1,submission_key VARCHAR(64) NOT NULL,submitted_at DATETIME NULL,error_message VARCHAR(1000) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_exchange_submit(requester_id,submission_key),KEY ix_exchange_status(status,department),KEY ix_exchange_partner(partner_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS nursing_exchange_events(id INT AUTO_INCREMENT PRIMARY KEY,request_id INT NOT NULL,round_no INT NOT NULL,stage VARCHAR(50) NOT NULL,decision VARCHAR(40) NOT NULL,actor_id INT NOT NULL,actor_name VARCHAR(160) NOT NULL,actor_role VARCHAR(50) NOT NULL,remarks VARCHAR(2000) NOT NULL,snapshot_json LONGTEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY ix_exchange_event(request_id,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS nursing_exchange_reservations(schedule_id INT NOT NULL,employee_id INT NOT NULL,duty_date DATE NOT NULL,request_id INT NOT NULL,PRIMARY KEY(schedule_id,employee_id,duty_date),KEY ix_exchange_reservation(request_id)) ENGINE=InnoDB",
 "CREATE TABLE IF NOT EXISTS nursing_exchange_amendments(id INT AUTO_INCREMENT PRIMARY KEY,request_id INT NOT NULL,schedule_id INT NOT NULL,original_version INT NOT NULL,revised_version INT NOT NULL,before_json LONGTEXT NOT NULL,after_json LONGTEXT NOT NULL,hours_json LONGTEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_exchange_amendment(request_id,schedule_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS nursing_exchange_approvers(stage VARCHAR(20) NOT NULL,department VARCHAR(50) NOT NULL,user_id INT NOT NULL,enabled TINYINT NOT NULL DEFAULT 1,PRIMARY KEY(stage,department,user_id)) ENGINE=InnoDB",
 "CREATE TABLE IF NOT EXISTS nursing_exchange_permissions(role VARCHAR(50) PRIMARY KEY,can_view TINYINT NOT NULL DEFAULT 1,can_submit TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB",
 "CREATE TABLE IF NOT EXISTS nursing_exchange_mutex(id INT PRIMARY KEY) ENGINE=InnoDB",
 "CREATE TABLE IF NOT EXISTS nursing_document_notifications(id INT AUTO_INCREMENT PRIMARY KEY,employee_document_id INT NULL,employee_id INT NOT NULL,notification_type VARCHAR(60) NOT NULL,message VARCHAR(255) NOT NULL,notification_date DATE NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'UNREAD',reviewed_by INT NULL,reviewed_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_doc_notice(employee_document_id,notification_type,notification_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS nursing_exchange_notifications(id INT AUTO_INCREMENT PRIMARY KEY,request_id INT NOT NULL,recipient_id INT NOT NULL,request_version INT NOT NULL,notification_id INT NOT NULL,UNIQUE KEY uq_exchange_notice(request_id,recipient_id,request_version)) ENGINE=InnoDB",
 ] as $sql)$db->query($sql);
 $db->query('INSERT IGNORE INTO nursing_exchange_mutex VALUES(1)');
}
function exActor(mysqli $db,int $id): array {
 $row=exOne($db,'SELECT * FROM users WHERE user_id=?',[$id]);
 if(!$row||!(int)$row['is_active']||$row['status']!=='ACTIVE')throw new DomainException('The employee account is inactive or unavailable.');
 $row['name']=naName($row);$row['canonical_role']=canonicalRole($row['role']);$row['unit']=canonicalDepartment($row['department']);return $row;
}
function exAccess(mysqli $db,array $actor): array {
 $roles=['STAFF_NURSE','HEAD_NURSE','SUPERVISOR_NURSE','CHIEF_NURSE','SUPER_ADMIN'];$role=$actor['canonical_role'];
 $permission=exOne($db,'SELECT * FROM nursing_exchange_permissions WHERE role=?',[$role]);
 return ['view'=>(naEligible($actor)||$role==='SUPER_ADMIN')&&in_array($role,$roles,true)&&(!$permission||(bool)$permission['can_view']),'submit'=>naEligible($actor)&&in_array($role,array_slice($roles,0,4),true)&&(!$permission||(bool)$permission['can_submit']),'admin'=>$role==='SUPER_ADMIN'];
}
function exStage(string $status): ?string{return ['PENDING_HEAD_NURSE_APPROVAL'=>'HEAD','PENDING_SUPERVISOR_APPROVAL'=>'SUPERVISOR','PENDING_CHIEF_NURSE_APPROVAL'=>'CHIEF','PENDING_ASSISTANT_CHIEF_APPROVAL'=>'ASSISTANT'][$status]??null;}
function exRoute(mysqli $db,array $request): array {
 $originals=nsScheduleDecode($request['original_json']??'[]');$a=exOne($db,'SELECT role FROM users WHERE user_id=?',[$request['requester_id']])??['role'=>'STAFF_NURSE'];$b=exOne($db,'SELECT role FROM users WHERE user_id=?',[$request['partner_id']])??['role'=>'STAFF_NURSE'];$a['role']=$originals[0]['employee_role']??$a['role'];$b['role']=$originals[1]['employee_role']??$b['role'];$rank=['NURSING_ATTENDANT'=>0,'STAFF_NURSE'=>0,'HEAD_NURSE'=>1,'SUPERVISOR_NURSE'=>2,'ASSISTANT_CHIEF_NURSE'=>3,'CHIEF_NURSE'=>3];
 $originals=nsScheduleDecode($request['original_json']??'[]');$units=array_unique(array_column($originals,'department'));if(!$units)$units=[$request['department']];$route=[];
 $senior=($rank[naRole($a)]??0)>=($rank[naRole($b)]??0)?$a:$b;
 foreach(naRoute('EXCHANGE',$senior) as $role){$stage=['HEAD_NURSE'=>'HEAD','SUPERVISOR_NURSE'=>'SUPERVISOR','ASSISTANT_CHIEF_NURSE'=>'ASSISTANT','CHIEF_NURSE'=>'CHIEF'][$role];if($stage==='HEAD')foreach($units as $unit)$route[]=['stage'=>$stage,'department'=>$unit,'key'=>count($units)>1?'HEAD:'.$unit:'HEAD'];else $route[]=['stage'=>$stage,'department'=>$request['department'],'key'=>$stage];}return $route;
}
function exNext(mysqli $db,array $request): ?array {
 foreach(exRoute($db,$request) as $step)if(!exOne($db,"SELECT id FROM nursing_exchange_events WHERE request_id=? AND round_no=? AND stage=? AND decision='APPROVE'",[$request['id'],$request['round_no'],$step['key']]))return $step;return null;
}
function exPendingStatus(string $stage): string{return ['HEAD'=>'PENDING_HEAD_NURSE_APPROVAL','SUPERVISOR'=>'PENDING_SUPERVISOR_APPROVAL','ASSISTANT'=>'PENDING_ASSISTANT_CHIEF_APPROVAL','CHIEF'=>'PENDING_CHIEF_NURSE_APPROVAL'][$stage];}
function exCurrentApproves(mysqli $db,array $actor,array $request): bool {$next=exNext($db,$request);if(!$next||exStage($request['status'])!==$next['stage'])return false;$request['department']=$next['department'];return exApproves($db,$actor,$request,$next['stage']);}
function exApproves(mysqli $db,array $actor,array $request,string $stage): bool {
 $role=['HEAD'=>'HEAD_NURSE','SUPERVISOR'=>'SUPERVISOR_NURSE','CHIEF'=>'CHIEF_NURSE','ASSISTANT'=>'ASSISTANT_CHIEF_NURSE'][$stage]??null;
 if(!$role||!naApprovalAccess($actor)||in_array((int)$actor['user_id'],[(int)$request['requester_id'],(int)$request['partner_id']],true))return false;
 if(!exAccess($db,$actor)['view'])return false;
 if(naRole($actor)==='SUPER_ADMIN')return true;
 if(naRole($actor)!==$role)return false;
 $configured=exRows($db,'SELECT * FROM nursing_exchange_approvers WHERE stage=? AND department IN (?,?)',[$stage,$request['department'],'*']);
 if($configured)return (bool)array_filter($configured,fn($rule)=>(int)$rule['user_id']===(int)$actor['user_id']&&(bool)$rule['enabled']);
 if($stage==='HEAD'&&$actor['unit']!==$request['department'])return false;
 foreach(nsScheduleDecode($request['original_json']??'[]') as $original){if($stage==='HEAD'&&$original['department']!==$request['department'])continue;$hierarchy=exRows($db,'SELECT approver_id FROM nursing_reporting_hierarchy WHERE employee_id=? AND approver_role=? AND enabled=1',[$original['employee_id'],$role]);if($hierarchy)return in_array((int)$actor['user_id'],array_map('intval',array_column($hierarchy,'approver_id')),true);}
 return $stage!=='HEAD'||$actor['unit']===$request['department'];
}
function exCanView(mysqli $db,array $actor,array $request): bool {
 if(!exAccess($db,$actor)['view'])return false;
 if(in_array((int)$actor['user_id'],[(int)$request['requester_id'],(int)$request['partner_id']],true))return true;
 if($actor['canonical_role']==='SUPER_ADMIN')return true;
 foreach(exRoute($db,$request) as $step){$scoped=$request;$scoped['department']=$step['department'];if(exApproves($db,$actor,$scoped,$step['stage']))return true;}return false;
}
function exInterval(string $date,string $shift,array $definitions=[]): ?array {
 if(isset($definitions[$shift])){$def=$definitions[$shift];if(!$def['duration'])return null;$start=(new DateTimeImmutable($date.' 00:00:00',new DateTimeZone('Asia/Manila')))->getTimestamp()+$def['start']*3600;return [$start,$start+$def['duration']*3600];}
 $times=['6'=>[6,9],'7'=>[7,9],'8'=>[8,9],'M'=>[7,11],'PM8'=>[14,8],'AM'=>[6,8],'PM'=>[14,8],'NIGHT8'=>[22,8],'DAY12'=>[6,12],'NIGHT12'=>[18,12],'ADJUST8'=>[6,8],'TRAINING'=>[8,8],'OFFICIAL_BUSINESS'=>[8,8]];
 if(!isset($times[$shift]))return null;$start=(new DateTimeImmutable($date.' 00:00:00',new DateTimeZone('Asia/Manila')))->getTimestamp()+$times[$shift][0]*3600;return [$start,$start+$times[$shift][1]*3600];
}
function exValidateProposal(array $schedules,array $originals,array $employees): array {
 $changed=$schedules;$updates=[];
 foreach($originals as $cell){$row=$changed[$cell['schedule_id']]??null;$id=$cell['employee_id'];$date=$cell['date'];
  if(!$row||$row['status']!=='PUBLISHED'||$row['final_status']!=='PUBLISHED')throw new DomainException('Both duties must belong to published rosters without a pending revision.');
  if((int)$row['version']!==(int)$cell['version']||(int)$row['final_version']!==(int)$cell['published_version'])throw new DomainException('A published roster changed during approval. Return this request for correction.');
  if(($row['payload']['assignments'][$id][$date]??null)!==$cell['shift']||!empty($row['payload']['exchange_refs'][$id][$date]))throw new DomainException('An original duty was changed or already exchanged.');
  $member=array_column($row['payload']['staff'],null,'id')[$id]??null;$actual=array_values(array_filter($employees,fn($e)=>(int)$e['user_id']===(int)$id))[0]??null;if(!$member||!$actual||($member['category']??'NURSE')!==nsScheduleCategory($actual))throw new DomainException('Current nursing qualifications do not match the original published duty.');
  if(!(nsScheduleDuties()[$cell['shift']]??0))throw new DomainException('Select a working duty, not an absence or day off.');
 }

 if(nsScheduleCategory($employees[0])!==nsScheduleCategory($employees[1]))throw new DomainException('Both employees must have matching nursing duty qualifications.');
 foreach($originals as $cell){$changed[$cell['schedule_id']]['payload']['assignments'][$cell['employee_id']][$cell['date']]='OFF';$updates[]=['schedule_id'=>$cell['schedule_id'],'employee_id'=>$cell['employee_id'],'date'=>$cell['date'],'shift'=>'OFF'];}
 foreach($originals as $index=>$cell){$recipient=$originals[1-$index]['employee_id'];$payload=&$changed[$cell['schedule_id']]['payload'];
  $staff=array_column($payload['staff'],null,'id');if(!isset($staff[$recipient])||($staff[$recipient]['active']??true)!==true)throw new DomainException('The exchange partner is not eligible in the destination published roster.');
  $actualRecipient=$employees[1-$index];$pattern=$staff[$recipient]['pattern']??'8';$allowed=(($payload['policy']['mode']??'')==='FIXED'||naFixed($actualRecipient))?['6','7','8','M']:($pattern==='12'?['DAY12','NIGHT12']:['AM','PM','NIGHT8']);$eligibility=$actualRecipient['live_shift_eligibility']??$staff[$recipient]['shiftEligibility']??[];if($eligibility)$allowed=array_intersect($allowed,$eligibility);
  if(!in_array($cell['shift'],$allowed,true))throw new DomainException('The resulting duty does not match the employee duty pattern.');
  $existing=$payload['assignments'][$recipient][$cell['date']]??'OFF';if(!in_array($existing,['OFF','REQUEST_OFF'],true))throw new DomainException('The proposed date already has an assignment or absence.');
  $payload['assignments'][$recipient][$cell['date']]=$cell['shift'];$updates[]=['schedule_id'=>$cell['schedule_id'],'employee_id'=>$recipient,'date'=>$cell['date'],'shift'=>$cell['shift']];unset($payload);
 }
 $minimumRest=0;foreach($originals as $cell)$minimumRest=max($minimumRest,(int)($schedules[$cell['schedule_id']]['payload']['rules']['minRest']??12));
 foreach($employees as $employee){$intervals=[];$id=(int)$employee['user_id'];
  foreach($changed as $row){$payload=$row['payload'];foreach($payload['requests']??[] as $absence){if((int)$absence['employee_id']===$id&&$absence['status']==='APPROVED'){$absenceStart=(new DateTimeImmutable($absence['date'].' 00:00:00',new DateTimeZone('Asia/Manila')))->getTimestamp();foreach($updates as $cell){$interval=exInterval($cell['date'],$cell['shift'],$changed[$cell['schedule_id']]['payload']['shiftMaster']??[]);if($cell['employee_id']===$id&&$interval&&$interval[0]<$absenceStart+86400&&$interval[1]>$absenceStart)throw new DomainException('The proposed duty conflicts with an approved absence, including an overnight duty.');}}}
   foreach($payload['assignments'][$id]??[] as $date=>$shift){$interval=exInterval($date,$shift,$payload['shiftMaster']??[]);if($interval)$intervals[]=['date'=>$date,'start'=>$interval[0],'end'=>$interval[1],'schedule_id'=>$row['id']];}
  }
  usort($intervals,fn($a,$b)=>$a['start']<=>$b['start']);
  for($i=1;$i<count($intervals);$i++){
   $left=$intervals[$i-1];$right=$intervals[$i];$touches=false;foreach($updates as $cell)if($cell['employee_id']===$id&&in_array($cell['date'],[$left['date'],$right['date']],true))$touches=true;
   if(!$touches)continue;$gap=($right['start']-$left['end'])/3600;
   if($gap<0)throw new DomainException('The proposed duties overlap, including a cross-midnight assignment.');
   $leftCode=$changed[$left['schedule_id']]['payload']['assignments'][$id][$left['date']]??'';$rightCode=$changed[$right['schedule_id']]['payload']['assignments'][$id][$right['date']]??'';if(in_array($leftCode,['PM','PM8','NIGHT12'],true)&&in_array($rightCode,['AM','ADJUST8','DAY12'],true)&&date('Y-m-d',strtotime($left['date'].' +1 day'))===$right['date'])throw new DomainException('The proposed exchange creates prohibited PM-to-AM duty.');
   if($leftCode==='DAY12'&&$rightCode==='NIGHT12'&&date('Y-m-d',strtotime($left['date'].' +1 day'))===$right['date'])throw new DomainException('The proposed exchange creates prohibited consecutive AM-to-PM twelve-hour duties.');
   if($gap<$minimumRest)throw new DomainException('The resulting duties do not satisfy the '.$minimumRest.'-hour minimum rest interval.');
  }
 }
 foreach(array_unique(array_column($updates,'schedule_id')) as $sid){$payload=$changed[$sid]['payload'];$before=$schedules[$sid]['payload'];$required=$payload['policy']['requiredShifts']??[];if(($payload['staff'][0]['pattern']??'8')==='12'&&$required===['AM','PM','NIGHT8'])$required=['DAY12','NIGHT12'];foreach($updates as $cell){if($cell['schedule_id']!==$sid)continue;foreach($required as $code){$count=0;foreach($payload['staff'] as $employee)if(nsScheduleCategory($employee)!=='NA'&&($payload['assignments'][$employee['id']][$cell['date']]??null)===$code)$count++;if(!$count)throw new DomainException('The proposed exchange leaves '.$cell['date'].' without required shift coverage.');}}}
 foreach($updates as $cell){$payload=$changed[$cell['schedule_id']]['payload'];foreach($payload['rules']['staffingShifts']??[] as $code)foreach($payload['rules']['staffingTargets']??[] as $role=>$target){$count=0;foreach($payload['staff'] as $employee)if(($payload['assignments'][$employee['id']][$cell['date']]??null)===$code&&($employee['staffingRole']??(nsScheduleCategory($employee)==='NA'?'NA':'NURSE'))===$role)$count++;if($count<$target)throw new DomainException('The proposed exchange removes required qualified '.$role.' coverage on '.$cell['date']);}}
 $hours=[];$beforeHours=[];$periods=[];$participants=array_map('intval',array_column($employees,'user_id'));
 foreach(array_unique(array_column($updates,'schedule_id')) as $scheduleId){$include=fn($summary)=>in_array((int)$summary['employee_id'],$participants,true);$hours[$scheduleId]=array_values(array_filter(nsScheduleSummary($changed[$scheduleId]['payload']),$include));$beforeHours[$scheduleId]=array_values(array_filter(nsScheduleSummary($schedules[$scheduleId]['payload']),$include));$periods[$scheduleId]=substr($schedules[$scheduleId]['month_start'],0,7);}
 return ['schedules'=>$changed,'updates'=>$updates,'validation'=>['validated_at'=>date('c'),'minimum_rest'=>$minimumRest,'staffing_minimum_enforced'=>false,'monthly_hours'=>$hours,'before_monthly_hours'=>$beforeHours,'periods'=>$periods]];
}
function exLoadSchedules(mysqli $db,string $from,string $to,bool $lock=false): array {
 $start=(new DateTimeImmutable(substr($from,0,7).'-01'))->modify('-1 month')->format('Y-m-d');$end=(new DateTimeImmutable(substr($to,0,7).'-01'))->modify('+1 month')->format('Y-m-d');$out=[];
 $hasRequests=(bool)exOne($db,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nursing_service_requests'");
 if($hasRequests)require_once __DIR__.'/nursing_requests.php';
 foreach(exRows($db,'SELECT * FROM nursing_schedule_months WHERE month_start BETWEEN ? AND ? AND final_status="PUBLISHED" ORDER BY id'.($lock?' FOR UPDATE':''),[$start,$end]) as $row){$row['payload']=nsScheduleDecode($row['final_json']);if($row['payload']){if($hasRequests)$row['payload']=nrScheduleRequests($db,$row['payload'],substr($row['month_start'],0,7));$out[(int)$row['id']]=$row;}}return $out;
}
function exDate($value): string{$date=(string)$value;$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Select a valid duty date.');return $date;}
function exOriginal(mysqli $db,array $employee,int $scheduleId,string $date,array $rows): array {
 $row=$rows[$scheduleId]??null;if(!$row||substr($date,0,7)!==substr($row['month_start'],0,7))throw new DomainException('Select a published duty assigned to this employee.');
 $shift=$row['payload']['assignments'][$employee['user_id']][$date]??null;if(!$shift)throw new DomainException('The selected original duty does not exist.');
 return ['employee_role'=>naRole($employee),'category'=>nsScheduleCategory($employee),'assignment_id'=>$scheduleId.':'.$employee['user_id'].':'.$date.':'.$row['final_version'],'schedule_id'=>$scheduleId,'employee_id'=>(int)$employee['user_id'],'name'=>$employee['name'],'employee_number'=>$employee['employee_id']??$employee['user_id'],'department'=>$row['department'],'date'=>$date,'shift'=>$shift,'shift_label'=>$row['payload']['shiftMaster'][$shift]['label']??nsShiftDefaults()[$shift]['label']??$shift,'times'=>exInterval($date,$shift,$row['payload']['shiftMaster']??[]),'version'=>(int)$row['version'],'published_version'=>(int)$row['final_version']];
}
function exValidateLive(mysqli $db,array $originals,int $requestId=0,bool $lock=true): array {
 $employees=[exActor($db,(int)$originals[0]['employee_id']),exActor($db,(int)$originals[1]['employee_id'])];
 foreach($employees as $index=>&$employee){if(!exAccess($db,$employee)['submit'])throw new DomainException('An employee is no longer eligible in the original department.');if(isset($originals[$index]['employee_role'])&&naRole($employee)!==$originals[$index]['employee_role'])throw new DomainException('An employee role changed. Return the request for correction and restart the appropriate approval route.');if(isset($originals[$index]['category'])&&nsScheduleCategory($employee)!==$originals[$index]['category'])throw new DomainException('An employee nursing qualification changed. Return this request for correction.');$policy=exOne($db,'SELECT * FROM nursing_schedule_employee_policy WHERE employee_id=?',[$employee['user_id']]);if($policy&&!(int)$policy['scheduling_active'])throw new DomainException('An employee is no longer active for scheduling.');$employee['live_shift_eligibility']=$policy?json_decode($policy['shift_eligibility']??'null',true):null;}unset($employee);
 $dates=array_column($originals,'date');sort($dates);$rows=exLoadSchedules($db,$dates[0],end($dates),$lock);$proposal=exValidateProposal($rows,$originals,$employees);
 foreach($proposal['updates'] as $cell){if(exOne($db,'SELECT request_id FROM nursing_exchange_reservations WHERE schedule_id=? AND employee_id=? AND duty_date=? AND request_id<>?',[$cell['schedule_id'],$cell['employee_id'],$cell['date'],$requestId]))throw new DomainException('A conflicting exchange request is already pending for this duty.');}
 // Attendance leave records are also authoritative absences, when the existing table is installed.
 if(exOne($db,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nursing_employee_attendance'"))foreach($proposal['updates'] as $cell){$interval=exInterval($cell['date'],$cell['shift'],$proposal['schedules'][$cell['schedule_id']]['payload']['shiftMaster']??[]);if(!$interval)continue;$lastDate=(new DateTimeImmutable('@'.($interval[1]-1)))->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d');if(exOne($db,'SELECT id FROM nursing_employee_attendance WHERE employee_id=? AND leave_type IS NOT NULL AND leave_type<>"" AND record_date<=? AND COALESCE(leave_end_date,DATE_ADD(record_date,INTERVAL GREATEST(CEIL(leave_days)-1,0) DAY))>=?',[$cell['employee_id'],$lastDate,$cell['date']]))throw new DomainException('The resulting duty conflicts with a recorded leave period, including an overnight duty.');}
 return $proposal;
}
function exEvent(mysqli $db,array $request,array $actor,string $stage,string $decision,string $remarks,array $snapshot=[]): void {
 nsScheduleQuery($db,'INSERT INTO nursing_exchange_events(request_id,round_no,stage,decision,actor_id,actor_name,actor_role,remarks,snapshot_json) VALUES(?,?,?,?,?,?,?,?,?)',[$request['id'],$request['round_no'],$stage,$decision,$actor['user_id'],$actor['name'],naRole($actor),$remarks,nsScheduleEncode($snapshot)]);
}
function exApprovers(mysqli $db,array $request,string $stage): array {
 $out=[];foreach(exRows($db,"SELECT * FROM users WHERE is_active=1 AND status='ACTIVE'") as $employee){$employee['canonical_role']=canonicalRole($employee['role']);$employee['unit']=canonicalDepartment($employee['department']);if(exApproves($db,$employee,$request,$stage))$out[]=(int)$employee['user_id'];}return $out;
}
function exNotify(mysqli $db,array $request): void {
 $recipients=[(int)$request['requester_id'],(int)$request['partner_id']];$stage=exStage($request['status']);if($stage){$next=exNext($db,$request);$scoped=$request;$scoped['department']=$next['department']??$request['department'];$recipients=array_merge($recipients,exApprovers($db,$scoped,$stage));}
 if($request['status']==='UPDATE_FAILED')foreach(exRows($db,"SELECT user_id FROM users WHERE role='SUPER_ADMIN' AND is_active=1 AND status='ACTIVE'") as $admin)$recipients[]=(int)$admin['user_id'];
 $originals=nsScheduleDecode($request['original_json']);$dates=implode(' / ',array_column($originals,'date'));$message=substr($request['request_no'].' · '.$dates.' · '.str_replace('_',' ',$request['status']).' · /Nursing_System/pages/duty_exchange.php?view=detail&id='.$request['id'],0,255);
 foreach(array_unique($recipients) as $id){if(exOne($db,'SELECT id FROM nursing_exchange_notifications WHERE request_id=? AND recipient_id=? AND request_version=?',[$request['id'],$id,$request['version']]))continue;
  nsScheduleQuery($db,"INSERT INTO nursing_document_notifications(employee_document_id,employee_id,notification_type,message,notification_date) VALUES(NULL,?,'EXCHANGE_DUTY',?,CURDATE())",[$id,$message]);$notice=$db->insert_id;
  nsScheduleQuery($db,'INSERT INTO nursing_exchange_notifications(request_id,recipient_id,request_version,notification_id) VALUES(?,?,?,?)',[$request['id'],$id,$request['version'],$notice]);
 }
}
function exReserve(mysqli $db,array $request,array $proposal): void {
 nsScheduleQuery($db,'DELETE FROM nursing_exchange_reservations WHERE request_id=?',[$request['id']]);$seen=[];
 foreach($proposal['updates'] as $cell){$key=$cell['schedule_id'].':'.$cell['employee_id'].':'.$cell['date'];if(isset($seen[$key]))continue;$seen[$key]=true;nsScheduleQuery($db,'INSERT INTO nursing_exchange_reservations(schedule_id,employee_id,duty_date,request_id) VALUES(?,?,?,?)',[$cell['schedule_id'],$cell['employee_id'],$cell['date'],$request['id']]);}
}
function exParseAssignment(string $value,int $employeeId): array {
 $parts=explode(':',$value);if(count($parts)!==4||!ctype_digit($parts[0])||!ctype_digit($parts[1])||!ctype_digit($parts[3])||(int)$parts[1]!==$employeeId)throw new InvalidArgumentException('Select an actual published assignment for the employee.');return [(int)$parts[0],exDate($parts[2]),(int)$parts[3]];
}
function exSaveRequest(mysqli $db,array $actor,array $input,bool $draft=false): array {
 if(!exAccess($db,$actor)['submit'])throw new DomainException('Your account cannot submit duty exchanges.');
 $reason=trim((string)($input['reason']??''));if((!$draft&&$reason==='')||strlen($reason)>2000)throw new InvalidArgumentException('Enter a reason of no more than 2,000 characters.');
 $partner=exActor($db,(int)($input['partner_id']??0));if((int)$partner['user_id']===(int)$actor['user_id']||!exAccess($db,$partner)['submit'])throw new DomainException('Select another active nursing employee as the exchange partner.');
 $a=exParseAssignment((string)($input['assignment_a']??''),(int)$actor['user_id']);$b=exParseAssignment((string)($input['assignment_b']??''),(int)$partner['user_id']);
 $key=(string)($input['submission_key']??'');if(!preg_match('/^[a-f0-9]{32,64}$/',$key))throw new InvalidArgumentException('Invalid submission token. Reload the form.');
 $db->begin_transaction();try {
  nsScheduleQuery($db,'SELECT id FROM nursing_exchange_mutex WHERE id=1 FOR UPDATE')->get_result();
  $id=(int)($input['id']??0);$existing=$id?exOne($db,'SELECT * FROM nursing_exchange_requests WHERE id=? FOR UPDATE',[$id]):exOne($db,'SELECT * FROM nursing_exchange_requests WHERE requester_id=? AND submission_key=?',[$actor['user_id'],$key]);
  if(!$id&&$existing){$db->commit();return $existing;}
  if($id&&(!$existing||(int)$existing['requester_id']!==(int)$actor['user_id']||!in_array($existing['status'],['DRAFT','RETURNED_FOR_CORRECTION'],true)))throw new DomainException('This request cannot be edited.');
  if($existing&&(int)($input['version']??0)!==(int)$existing['version'])throw new RuntimeException('The request changed. Reload before editing.',409);
  $dates=[$a[1],$b[1]];sort($dates);$schedules=exLoadSchedules($db,$dates[0],end($dates),true);
  $originals=[exOriginal($db,$actor,$a[0],$a[1],$schedules),exOriginal($db,$partner,$b[0],$b[1],$schedules)];
  if($originals[0]['published_version']!==$a[2]||$originals[1]['published_version']!==$b[2])throw new DomainException('A selected assignment changed. Select it again from the current published roster.');
  $proposal=exValidateLive($db,$originals,$id,true);
  $request=$existing??['requester_id'=>$actor['user_id'],'partner_id'=>$partner['user_id'],'department'=>$actor['unit']];$request['partner_id']=$partner['user_id'];$request['department']=$originals[0]['department'];$request['original_json']=nsScheduleEncode($originals);
  if(!$draft)foreach(exRoute($db,$request) as $routeStep){$scoped=$request;$scoped['department']=$routeStep['department'];$stage=$routeStep['stage'];if(!exApprovers($db,$scoped,$stage))throw new DomainException('No independent '.$stage.' approver is available. Ask the administrator to assign another authorized approver.');}
  $status=$draft?'DRAFT':'PENDING_PARTNER_CONFIRMATION';$round=(int)($existing['round_no']??0)+1;
  $args=[$partner['user_id'],$actor['unit'],$status,$reason,nsScheduleEncode($originals),nsScheduleEncode($proposal['updates']),nsScheduleEncode($proposal['validation']),$round];
  if($existing)nsScheduleQuery($db,'UPDATE nursing_exchange_requests SET partner_id=?,department=?,status=?,reason=?,original_json=?,proposed_json=?,validation_json=?,round_no=?,version=version+1,error_message=NULL,submitted_at='.($draft?'NULL':'NOW()').' WHERE id=?',array_merge($args,[$id]));
  else{nsScheduleQuery($db,'INSERT INTO nursing_exchange_requests(partner_id,department,status,reason,original_json,proposed_json,validation_json,round_no,requester_id,submission_key,submitted_at) VALUES(?,?,?,?,?,?,?,?,?,?,'.($draft?'NULL':'NOW()').')',array_merge($args,[$actor['user_id'],$key]));$id=$db->insert_id;nsScheduleQuery($db,'UPDATE nursing_exchange_requests SET request_no=? WHERE id=?',['EX-'.date('Y').'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT),$id]);}
  $request=exOne($db,'SELECT * FROM nursing_exchange_requests WHERE id=?',[$id]);
  nsScheduleQuery($db,'DELETE FROM nursing_exchange_reservations WHERE request_id=?',[$id]);if(!$draft)exReserve($db,$request,$proposal);
  exEvent($db,$request,$actor,'SUBMITTED',$draft?'DRAFT':'SUBMITTED',$reason,['original'=>$originals,'proposed'=>$proposal['updates']]);if(!$draft)exNotify($db,$request);
  $db->commit();return $request;
 }catch(Throwable $error){$db->rollback();if($error instanceof mysqli_sql_exception&&in_array($error->getCode(),[1062,1213],true))throw new RuntimeException('A conflicting request was saved. Reload and try again.',409);throw $error;}
}
function exApply(mysqli $db,array $actor,array $request,array $proposal): void {
 $affected=array_unique(array_column($proposal['updates'],'schedule_id'));sort($affected);
 foreach($affected as $scheduleId){$row=$proposal['schedules'][$scheduleId];$before=nsScheduleDecode($row['final_json']);$after=$row['payload'];
  foreach($proposal['updates'] as $cell)if($cell['schedule_id']===$scheduleId)$after['exchange_refs'][$cell['employee_id']][$cell['date']]=$request['request_no'];
  $version=(int)$row['version']+1;$json=nsScheduleEncode($after);
  $updated=nsScheduleQuery($db,'UPDATE nursing_schedule_months SET draft_json=?,final_json=?,version=?,final_version=?,updated_by=? WHERE id=? AND version=?',[$json,$json,$version,$version,$actor['user_id'],$scheduleId,$row['version']]);
  if($updated->affected_rows!==1)throw new RuntimeException('The published schedule changed while applying the exchange.',409);
  nsScheduleQuery($db,'INSERT INTO nursing_exchange_amendments(request_id,schedule_id,original_version,revised_version,before_json,after_json,hours_json) VALUES(?,?,?,?,?,?,?)',[$request['id'],$scheduleId,$row['final_version'],$version,nsScheduleEncode($before),$json,nsScheduleEncode(nsScheduleSummary($after))]);
  nsScheduleQuery($db,'INSERT INTO nursing_schedule_revisions(schedule_id,version,action,changed_by,changed_by_name,remarks,payload_json,changes_json) VALUES(?,?,?,?,?,?,?,?)',[$scheduleId,$version,'DUTY_EXCHANGE',$actor['user_id'],$actor['name'],$request['request_no'],$json,nsScheduleEncode(nsScheduleChanges($before,$after))]);
 }
}
function exDecide(mysqli $db,array $actor,int $id,int $version,string $action,string $remarks): array {
 if(strlen($remarks)>2000)throw new InvalidArgumentException('Remarks must be at most 2,000 characters.');
 if(in_array($action,['reject','return','decline','reopen'],true)&&trim($remarks)==='')throw new InvalidArgumentException('Enter remarks explaining this decision.');
 $db->begin_transaction();try {
  nsScheduleQuery($db,'SELECT id FROM nursing_exchange_mutex WHERE id=1 FOR UPDATE')->get_result();$actor=exActor($db,(int)$actor['user_id']);$request=exOne($db,'SELECT * FROM nursing_exchange_requests WHERE id=? FOR UPDATE',[$id]);
  if(!$request||!exCanView($db,$actor,$request))throw new DomainException('You cannot access this request.');
  if((int)$request['version']!==$version)throw new RuntimeException('This request changed. Reload before deciding.',409);
  $stage=exStage($request['status']);$status=null;$eventStage=$stage??'PARTNER';$proposal=null;
  if(in_array($action,['accept','decline'],true)){
   if($request['status']!=='PENDING_PARTNER_CONFIRMATION'||(int)$actor['user_id']!==(int)$request['partner_id'])throw new DomainException('Only the selected exchange partner can confirm this request.');
   if($action==='accept')$proposal=exValidateLive($db,nsScheduleDecode($request['original_json']),$id);$status=$action==='accept'?exPendingStatus(exNext($db,$request)['stage']):'DECLINED_BY_PARTNER';
  }elseif($action==='withdraw'){
   if((int)$actor['user_id']!==(int)$request['requester_id']||!in_array($request['status'],array_merge(NS_EXCHANGE_PENDING,['DRAFT','RETURNED_FOR_CORRECTION']),true))throw new DomainException('Only the requester may withdraw before final approval.');$status='WITHDRAWN';$eventStage='REQUESTER';
  }elseif($action==='reopen'){
   if($actor['canonical_role']!=='SUPER_ADMIN'||$request['status']!=='UPDATE_FAILED')throw new DomainException('Only the administrator may return a failed update for correction.');$status='RETURNED_FOR_CORRECTION';$eventStage='ADMIN';
  }elseif(in_array($action,['approve','reject','return'],true)){
   $next=exNext($db,$request);$scoped=$request;if($next)$scoped['department']=$next['department'];$eventStage=$next['key']??$stage;
   if(!$stage||!$next||$next['stage']!==$stage||!exApproves($db,$actor,$scoped,$stage))throw new DomainException('You are not an independent authorized approver for this stage.');
   if($action==='reject')$status='REJECTED';elseif($action==='return')$status='RETURNED_FOR_CORRECTION';else{
    if(!exOne($db,"SELECT id FROM nursing_exchange_events WHERE request_id=? AND round_no=? AND stage='PARTNER' AND decision='ACCEPT'",[$id,$request['round_no']]))throw new DomainException('Partner confirmation is missing.');
    if($stage==='CHIEF'){
     $db->query('SAVEPOINT exchange_apply');
     try{$proposal=exValidateLive($db,nsScheduleDecode($request['original_json']),$id);exApply($db,$actor,$request,$proposal);$status='APPROVED';}
     catch(Throwable $error){$db->query('ROLLBACK TO SAVEPOINT exchange_apply');$status='UPDATE_FAILED';$message=$error instanceof DomainException||$error->getCode()===409?$error->getMessage():'Schedule update failed; original rosters were retained. Contact the administrator.';nsScheduleQuery($db,'UPDATE nursing_exchange_requests SET error_message=? WHERE id=?',[$message,$id]);error_log('Duty exchange '.$request['request_no'].': '.$error->getMessage());exEvent($db,$request,$actor,'SCHEDULE','UPDATE_FAILED',$message);}
    }else{$proposal=exValidateLive($db,nsScheduleDecode($request['original_json']),$id);$route=exRoute($db,$request);$index=array_search($eventStage,array_column($route,'key'),true);$status=exPendingStatus($route[$index+1]['stage']);}
   }
  }else throw new InvalidArgumentException('Unknown exchange action.');
  exEvent($db,$request,$actor,$eventStage,strtoupper($action),$remarks,$proposal?['validation'=>$proposal['validation']]:[]);
  if($status==='APPROVED')exEvent($db,$request,$actor,'SCHEDULE','UPDATED',$request['request_no'],['updates'=>$proposal['updates'],'hours'=>$proposal['validation']['monthly_hours']]);
  nsScheduleQuery($db,'UPDATE nursing_exchange_requests SET status=?,validation_json=?,version=version+1 WHERE id=?',[$status,$proposal?nsScheduleEncode($proposal['validation']):$request['validation_json'],$id]);
  if(!in_array($status,NS_EXCHANGE_PENDING,true))nsScheduleQuery($db,'DELETE FROM nursing_exchange_reservations WHERE request_id=?',[$id]);
  $request=exOne($db,'SELECT * FROM nursing_exchange_requests WHERE id=?',[$id]);exNotify($db,$request);$db->commit();return $request;
 }catch(Throwable $error){$db->rollback();throw $error;}
}
function exProtectAmendments(?array $previous,array $next): void {
 foreach($previous['exchange_refs']??[] as $id=>$dates)foreach($dates as $date=>$reference){if(($next['assignments'][$id][$date]??null)!==($previous['assignments'][$id][$date]??null))throw new DomainException('An approved Exchange of Duty amendment ('.$reference.') cannot be overwritten by monthly generation or editing.');}
}
