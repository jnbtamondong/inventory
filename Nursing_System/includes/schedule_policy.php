<?php
require_once __DIR__.'/nursing_approval.php';
function nsPolicySchema(mysqli $db): void {
    foreach([
    "CREATE TABLE IF NOT EXISTS nursing_exchange_mutex(id INT PRIMARY KEY) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_reporting_hierarchy(employee_id INT NOT NULL,approver_role VARCHAR(40) NOT NULL,approver_id INT NOT NULL,enabled TINYINT NOT NULL DEFAULT 1,PRIMARY KEY(employee_id,approver_role,approver_id)) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_schedule_employee_policy(employee_id INT PRIMARY KEY,scheduling_active TINYINT NOT NULL DEFAULT 1,fixed_shift VARCHAR(20) NOT NULL DEFAULT '8',shift_eligibility TEXT NULL) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_schedule_policy(department VARCHAR(50) PRIMARY KEY,mode VARCHAR(20) NOT NULL,target_hours DECIMAL(7,2) NOT NULL DEFAULT 160,operating_days VARCHAR(30) NOT NULL DEFAULT '1,2,3,4,5',required_shifts TEXT NOT NULL,updated_by INT NOT NULL DEFAULT 0) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_shift_master(code VARCHAR(20) PRIMARY KEY,label VARCHAR(100) NOT NULL,start_hour DECIMAL(5,2) NOT NULL,elapsed_hours DECIMAL(5,2) NOT NULL,meal_hours DECIMAL(5,2) NOT NULL DEFAULT 0,credited_hours DECIMAL(5,2) NOT NULL,cross_midnight TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_department_shifts(department VARCHAR(50) NOT NULL,code VARCHAR(20) NOT NULL,label VARCHAR(100) NOT NULL,start_hour DECIMAL(5,2) NOT NULL,elapsed_hours DECIMAL(5,2) NOT NULL,meal_hours DECIMAL(5,2) NOT NULL DEFAULT 0,credited_hours DECIMAL(5,2) NOT NULL,cross_midnight TINYINT NOT NULL DEFAULT 0,PRIMARY KEY(department,code)) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_schedule_holidays(holiday_date DATE PRIMARY KEY,label VARCHAR(160) NOT NULL,updated_by INT NOT NULL) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_schedule_history(id INT AUTO_INCREMENT PRIMARY KEY,department VARCHAR(50) NOT NULL,month_start DATE NOT NULL,version INT NOT NULL,payload_json LONGTEXT NOT NULL,changed_by INT NOT NULL,remarks VARCHAR(1000) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY history_version(department,month_start,version)) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS nursing_schedule_policy_audit(id INT AUTO_INCREMENT PRIMARY KEY,actor_id INT NOT NULL,action VARCHAR(40) NOT NULL,before_json LONGTEXT NULL,after_json LONGTEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"
    ] as $sql)$db->query($sql);
    $db->query('INSERT IGNORE INTO nursing_exchange_mutex VALUES(1)');
    foreach(nsShiftDefaults() as $code=>$shift)nsScheduleQuery($db,'INSERT IGNORE INTO nursing_shift_master(code,label,start_hour,elapsed_hours,meal_hours,credited_hours,cross_midnight) VALUES(?,?,?,?,?,?,?)',[$code,$shift['label'],$shift['start'],$shift['duration'],$shift['meal']??0,$shift['hours'],($shift['start']+$shift['duration']>=24)?1:0]);
}
function nsShiftDefaults(): array {
    $defs=['AM'=>['AM',6,8,8],'PM'=>['PM',14,8,8],'NIGHT8'=>['N',22,8,8],'DAY12'=>['AM',6,12,12],'NIGHT12'=>['PM',18,12,12],'ADJUST8'=>['AM8',6,8,8],'PM8'=>['PM8',14,8,8],'6'=>['6',6,9,8],'7'=>['7',7,9,8],'8'=>['8',8,9,8],'M'=>['M',7,11,10],'OFF'=>['OFF',0,0,0],'PH'=>['PH',0,0,0],'SPL'=>['SPL',0,0,0],'SL'=>['SL',0,0,0],'VL'=>['VL',0,0,0],'WL'=>['WL',0,0,0],'FL'=>['FL',0,0,0],'ML'=>['ML',0,0,0],'PL'=>['PL',0,0,0],'SOLO_PARENT'=>['Solo parent',0,0,0],'OTHER_LEAVE'=>['Other leave',0,0,0],'REQUEST_OFF'=>['Request off',0,0,0],'LEAVE'=>['Leave',0,0,0],'TRAINING'=>['Training',8,8,0],'OFFICIAL_BUSINESS'=>['Official business',8,8,0]];
    $out=[];foreach($defs as $code=>[$label,$start,$duration,$hours])$out[$code]=['label'=>$label,'start'=>$start,'duration'=>$duration,'hours'=>$hours,'meal'=>$hours>0?$duration-$hours:0];return $out;
}
function nsDutyTarget(array $employee,float $reference): float {return ($employee['pattern']??'8')==='12'&&!naFixed($employee)?ceil($reference/12)*12:$reference;}
function nsPolicy(mysqli $db,string $department): array {
    $fixed=in_array($department,['OPD','NSO','NURSING','NURSING_TRAINING','CSSR'],true);
    $row=nsScheduleQuery($db,'SELECT * FROM nursing_schedule_policy WHERE department=?',[$department])->get_result()->fetch_assoc();
    return $row?['mode'=>$row['mode'],'target'=>(float)$row['target_hours'],'operatingDays'=>array_map('intval',explode(',',$row['operating_days'])),'requiredShifts'=>json_decode($row['required_shifts'],true)]:['mode'=>$fixed?'FIXED':'ROTATING','target'=>160,'operatingDays'=>[1,2,3,4,5],'requiredShifts'=>$fixed||$department==='OR'?[]:['AM','PM','NIGHT8']];
}
function nsPolicyHydrate(mysqli $db,array $payload,string $department,string $month): array {
    $policy=nsPolicy($db,$department);$shifts=[];
    foreach(nsScheduleQuery($db,'SELECT * FROM nursing_shift_master')->get_result()->fetch_all(MYSQLI_ASSOC) as $row)$shifts[$row['code']]=['label'=>$row['label'],'start'=>(float)$row['start_hour'],'duration'=>(float)$row['elapsed_hours'],'hours'=>(float)$row['credited_hours'],'meal'=>(float)$row['meal_hours'],'crossMidnight'=>(bool)$row['cross_midnight']];
    foreach(nsScheduleQuery($db,'SELECT * FROM nursing_department_shifts WHERE department=?',[$department])->get_result()->fetch_all(MYSQLI_ASSOC) as $row)$shifts[$row['code']]=['label'=>$row['label'],'start'=>(float)$row['start_hour'],'duration'=>(float)$row['elapsed_hours'],'hours'=>(float)$row['credited_hours'],'meal'=>(float)$row['meal_hours'],'crossMidnight'=>(bool)$row['cross_midnight']];
    $policy['department']=$department;$payload['shiftMaster']=$shifts;$payload['policy']=$policy;$payload['holidays']=[];
    foreach(nsScheduleQuery($db,'SELECT * FROM nursing_schedule_holidays WHERE holiday_date BETWEEN ? AND LAST_DAY(?)',[$month.'-01',$month.'-01'])->get_result()->fetch_all(MYSQLI_ASSOC) as $row)$payload['holidays'][$row['holiday_date']]=$row['label'];
    $payload['rules']['target']=$policy['target'];
    return $payload;
}
function nsPolicyErrors(array $payload,string $month,array $boundaries=[]): array {
    $errors=[];$defs=$payload['shiftMaster']??nsShiftDefaults();$days=(int)(new DateTimeImmutable($month.'-01'))->format('t');$coverage=[];
    $fixed=($payload['policy']['mode']??'ROTATING')==='FIXED';$required=$payload['policy']['requiredShifts']??[];
    $operatingRoom=($payload['policy']['department']??'')==='OR';
    if($operatingRoom&&!$required)$errors[]='Configure the Operating Room operational shifts before finalization.';
    if(!$fixed&&($payload['staff'][0]['pattern']??'8')==='12'&&$required===['AM','PM','NIGHT8'])$required=['DAY12','NIGHT12'];
    foreach($payload['staff'] as $employee){$employeeFixed=$fixed||naFixed($employee);$id=$employee['id'];$own=$payload['assignments'][$id]??[];$intervals=[];$total=0;
        if(!$employee['active'])$errors[]=$employee['name'].': inactive employee.';
        for($n=1;$n<=$days;$n++){$date=$month.'-'.sprintf('%02d',$n);$code=$own[$date]??'';$shift=$defs[$code]??null;
            if(!$shift){$errors[]=$employee['name'].' '.$date.': blank or unknown duty.';continue;}
            $total+=$shift['hours'];if($shift['hours']>0){if(nsScheduleCategory($employee)!=='NA')$coverage[$date][$code]=($coverage[$date][$code]??0)+1;
                $allowed=$employeeFixed?(($payload['policy']['department']??'')==='OPD'?['6','7','8','M']:['8','M']):(($employee['pattern']??'8')==='12'?['DAY12','NIGHT12']:['AM','PM','NIGHT8']);
                if($operatingRoom&&!$employeeFixed)$allowed=($employee['pattern']??'8')==='12'?array_values(array_intersect($required,['DAY12','NIGHT12'])):$required;
                if(!in_array((string)$code,$allowed,true))$errors[]=$employee['name'].' '.$date.': duty does not match department or shift pattern.';
                if(($employeeFixed||$operatingRoom)&&!in_array((int)(new DateTimeImmutable($date))->format('w'),$payload['policy']['operatingDays'],true))$errors[]=$employee['name'].' '.$date.': duty outside operating days.';
                if(!empty($employee['shiftEligibility'])&&!in_array($code,$employee['shiftEligibility'],true))$errors[]=$employee['name'].' '.$date.': ineligible shift.';
            }
        }
        $entries=[];foreach($own as $date=>$code)$entries[]=['date'=>$date,'duty'=>$code];foreach($boundaries as $cell)if((int)$cell['employee_id']===(int)$id)$entries[]=$cell;
        foreach($entries as $cell){$shift=$cell['shift_definition']??$defs[$cell['duty']]??null;if(!$shift||!$shift['duration'])continue;$start=strtotime($cell['date'].' UTC')/3600+$shift['start'];$intervals[]=['start'=>$start,'end'=>$start+$shift['duration'],'date'=>$cell['date'],'code'=>$cell['duty']];}
        usort($intervals,fn($a,$b)=>$a['start']<=>$b['start']);
        for($i=1;$i<count($intervals);$i++){$a=$intervals[$i-1];$b=$intervals[$i];if(substr($a['date'],0,7)!==$month&&substr($b['date'],0,7)!==$month)continue;
            if($a['date']===$b['date'])$errors[]=$employee['name'].' '.$b['date'].': multiple unit duties on the same date.';
            if($b['start']-$a['end']<(float)$payload['rules']['minRest'])$errors[]=$employee['name'].' '.$b['date'].': overlap or minimum rest conflict.';
            if(!$fixed&&$a['code']==='DAY12'&&$b['code']==='NIGHT12'&&date('Y-m-d',strtotime($a['date'].' +1 day'))===$b['date'])$errors[]=$employee['name'].' '.$b['date'].': prohibited AM-to-PM on consecutive 12-hour dates.';
            if(!$fixed&&in_array($a['code'],['PM','PM8','NIGHT12'],true)&&in_array($b['code'],['AM','ADJUST8','DAY12'],true)&&date('Y-m-d',strtotime($a['date'].' +1 day'))===$b['date'])$errors[]=$employee['name'].' '.$b['date'].': prohibited PM-to-AM.';
        }
        $known=[];foreach($entries as $entry)$known[$entry['date']]=$entry['duty'];$workingDates=array_fill_keys(array_column($intervals,'date'),true);$run=0;
        $from=(new DateTimeImmutable($month.'-01'))->modify('-14 days')->format('Y-m-d');$to=(new DateTimeImmutable($month.'-01'))->modify('last day of this month')->modify('+14 days')->format('Y-m-d');
        $last=date('Y-m-t',strtotime($month.'-01'));$offRun=0;$priorWork=false;
        for($date=$from;$date<=$to;$date=date('Y-m-d',strtotime($date.' +1 day'))){$code=$known[$date]??'';$working=isset($workingDates[$date]);$run=$working?$run+1:0;$runStart=date('Y-m-d',strtotime($date.' -'.max(0,$run-1).' days'));if($run>$payload['rules']['maxConsecutive']&&$date>=$month.'-01'&&$runStart<=$last)$errors[]=$employee['name'].': consecutive duty limit across month boundary.';
            if($working){if($offRun&&$priorWork&&$offRun<$payload['rules']['minOff']&&$date>=$month.'-01'&&date('Y-m-d',strtotime($date.' -'.$offRun.' days'))<=$last)$errors[]=$employee['name'].': too few consecutive rest days.';$offRun=0;$priorWork=true;}
            elseif($code)$offRun++;else{$offRun=0;$priorWork=false;}
            if(substr($date,0,7)!==$month)continue;$knownDays=0;$restDays=0;for($back=0;$back<7;$back++){$d=date('Y-m-d',strtotime($date.' -'.$back.' days'));if(!empty($known[$d])){$knownDays++;if(!isset($workingDates[$d]))$restDays++;}}if($knownDays===7&&$restDays<$payload['rules']['offPer7'])$errors[]=$employee['name'].' '.$date.': too few rest days in seven days.';
        }
        foreach($payload['requests']??[] as $request)if((int)$request['employee_id']===(int)$id&&$request['status']==='APPROVED'){
            if(in_array($request['type'],['TRAINING','OFFICIAL_BUSINESS'],true)){if(($own[$request['date']]??null)!==$request['type'])$errors[]=$employee['name'].' '.$request['date'].': approved '.$request['type'].' must remain assigned.';continue;}
            if($request['type']==='REQUEST_OFF'&&!empty($payload['requestOffOverrides'][$id][$request['date']]['reason']))continue;
            $absence=strtotime($request['date'].' UTC')/3600;foreach($intervals as $interval)if(substr($interval['date'],0,7)===$month&&$interval['start']<$absence+24&&$interval['end']>$absence)$errors[]=$employee['name'].' '.$request['date'].': approved unavailability including overnight duty.';
        }
        if(!$employeeFixed&&abs($total-nsDutyTarget($employee,(float)($payload['rules']['target']??160)))>.01)$errors[]=$employee['name'].': target hours unresolved.';
    }
    for($n=1;$n<=$days;$n++){$date=$month.'-'.sprintf('%02d',$n);if(($fixed||$operatingRoom)&&(!in_array((int)(new DateTimeImmutable($date))->format('w'),$payload['policy']['operatingDays'],true)||$fixed&&isset($payload['holidays'][$date])))continue;foreach($required as $code)if(empty($coverage[$date][$code]))$errors[]=$date.': missing '.$code.' coverage.';}
    foreach($payload['rules']['staffingShifts']??[] as $code)for($n=1;$n<=$days;$n++){$date=$month.'-'.sprintf('%02d',$n);foreach($payload['rules']['staffingTargets']??[] as $role=>$target){$count=0;foreach($payload['staff'] as $employee)if(($payload['assignments'][$employee['id']][$date]??'')===$code&&($employee['staffingRole']??($employee['category']==='NA'?'NA':'NURSE'))===$role)$count++;if($count<$target)$errors[]=$date.': missing qualified '.$role.' for '.$code;}}
    if(!$payload['staff'])$errors[]='Select eligible employees.';return array_values(array_unique($errors));
}
function nsHistoricalSave(mysqli $db,array $access,string $department,string $month,array $input): array {
    nsScheduleScope($access,$department);if(!$access['edit']||$month>=date('Y-m'))throw new DomainException('Historical references must be from a past month.');
    $remarks=trim($input['remarks']??'');if(!$remarks||strlen($remarks)>1000)throw new InvalidArgumentException('Enter a historical reference reason.');
    $db->begin_transaction();try{
        nsScheduleQuery($db,'SELECT id FROM nursing_exchange_mutex WHERE id=1 FOR UPDATE');
        $currentActor=nsScheduleQuery($db,'SELECT user_id,role,department,status,is_active FROM users WHERE user_id=? FOR UPDATE',[$access['user_id']])->get_result()->fetch_assoc();
        if(!$currentActor||!$currentActor['is_active']||$currentActor['status']!=='ACTIVE')throw new DomainException('An active scheduler account is required.');$access=nsScheduleAccess($currentActor);nsScheduleScope($access,$department);if(!$access['edit'])throw new DomainException('Your current role cannot encode historical schedules.');
        $published=nsScheduleQuery($db,'SELECT id FROM nursing_schedule_months WHERE department=? AND month_start=? AND final_json IS NOT NULL FOR UPDATE',[$department,$month.'-01'])->get_result()->fetch_assoc();if($published)throw new DomainException('A published reference already exists. Historical encoding cannot overwrite it.');
        $latest=nsScheduleQuery($db,'SELECT * FROM nursing_schedule_history WHERE department=? AND month_start=? ORDER BY version DESC LIMIT 1 FOR UPDATE',[$department,$month.'-01'])->get_result()->fetch_assoc();
        $payload=nsScheduleValidate($input['payload'],nsScheduleStaff($db,$access,$department),$latest?nsScheduleDecode($latest['payload_json']):null,$access,$month);$payload=nsPolicyHydrate($db,$payload,$department,$month);$version=(int)($latest['version']??0)+1;
        if((int)($input['historyVersion']??0)!==(int)($latest['version']??0))throw new RuntimeException('Historical reference changed. Reload first.',409);
        nsScheduleQuery($db,'INSERT INTO nursing_schedule_history(department,month_start,version,payload_json,changed_by,remarks) VALUES(?,?,?,?,?,?)',[$department,$month.'-01',$version,nsScheduleEncode($payload),$access['user_id'],$remarks]);$db->commit();return ['historyVersion'=>$version,'saved'=>true];
    }catch(Throwable $e){$db->rollback();throw $e;}
}
