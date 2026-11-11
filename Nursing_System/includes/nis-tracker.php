<?php
require_once __DIR__.'/nis-workflow.php';

function nisTrackerUnits(): array { return ['ER','OPD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU']; }
function nisTrackerDepartments(string $module='patients'): array {
    return array_values(array_filter(nisTrackerUnits(),fn($unit)=>hasPermission($module,'view',$unit)));
}
function nisTrackerRows(mysqli $conn,string $sql,array $params=[]): array {
    $statement=$conn->prepare($sql);
    if ($params) $statement->bind_param(str_repeat('s',count($params)),...$params);
    $statement->execute();return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}
function nisTrackerCanonicalSql(string $field): string {
    // $field is an internal SQL expression, never request input.
    return "CASE $field WHEN 'MICU' THEN 'ICU' WHEN 'LR/DR' THEN 'LR_DR' WHEN 'LR-DR' THEN 'LR_DR' WHEN '2ND FLOOR WARD' THEN 'WARD_2F' WHEN '3RD FLOOR WARD' THEN 'WARD_3F' ELSE $field END";
}
function nisTrackerBedDepartmentSql(string $alias='b'): string {
    return "CASE WHEN $alias.room_number LIKE 'ER-%' AND $alias.bed_type='Emergency' THEN 'ER'
        WHEN $alias.room_number LIKE 'WARD-03%' OR $alias.room_number LIKE 'WARD-04%' THEN 'WARD_3F'
        WHEN $alias.bed_type='General' AND ($alias.room_number REGEXP '^20[1-9]' OR $alias.room_number IN ('211 ISO A','HW 2ND','NB')) THEN 'WARD_2F'
        WHEN $alias.bed_number LIKE 'NICU-%' THEN 'NICU'
        WHEN $alias.bed_number LIKE 'MICU-%' OR $alias.bed_number LIKE 'ICU-%' THEN 'ICU'
        WHEN $alias.room_number LIKE 'OR-%' THEN 'OR'
        WHEN $alias.room_number LIKE 'LRDR-%' OR $alias.room_number LIKE 'LR-DR%' THEN 'LR_DR'
        ELSE NULL END";
}
function nisTrackerPatientFrom(): string {
    $received=nisTrackerCanonicalSql('m.destination_department');
    $bedDepartment=nisTrackerBedDepartmentSql();
    return "FROM (SELECT p.patient_id,p.permanent_patient_id,p.patient_code,p.first_name,p.last_name,p.status,p.current_bed,p.admission_date,
        b.bed_number,$bedDepartment AS bed_department,
        (SELECT n.bed_no FROM nicu_records n WHERE n.patient_id=p.patient_id AND n.encounter_id=p.patient_code AND n.status='ACTIVE' ORDER BY n.id DESC LIMIT 1) AS neonatal_bed,
        (SELECT i.bed_no FROM micu_records i WHERE i.patient_id=p.patient_id AND i.encounter_id=p.patient_code AND i.status='ACTIVE' ORDER BY i.id DESC LIMIT 1) AS intensive_bed,
        COALESCE(
            (SELECT $received FROM patient_movements m WHERE m.patient_id=p.patient_id AND m.encounter_id=p.patient_code
             AND m.status IN ('RECEIVED','ARRIVED') AND $received IN ('ER','OPD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU')
             ORDER BY COALESCE(m.movement_date_time,m.created_at) DESC,m.id DESC LIMIT 1),
            CASE WHEN EXISTS(SELECT 1 FROM nicu_records n WHERE n.patient_id=p.patient_id AND n.encounter_id=p.patient_code AND n.status='ACTIVE') THEN 'NICU'
                 WHEN EXISTS(SELECT 1 FROM micu_records i WHERE i.patient_id=p.patient_id AND i.encounter_id=p.patient_code AND i.status='ACTIVE') THEN 'ICU'
                 WHEN EXISTS(SELECT 1 FROM or_schedule o WHERE o.patient_id=p.patient_id AND o.encounter_id=p.patient_code AND o.status IN ('IN OR','RECOVERY')) THEN 'OR' ELSE NULL END,
            $bedDepartment,
            CASE WHEN EXISTS(SELECT 1 FROM opd_records o WHERE o.patient_id=p.patient_id AND o.encounter_id=p.patient_code AND o.status='ACTIVE') THEN 'OPD'
                 WHEN EXISTS(SELECT 1 FROM delivery_room_records d WHERE d.patient_id=p.patient_id AND d.encounter_id=p.patient_code) THEN 'LR_DR'
                 WHEN p.status='admit_inpatient' THEN IF(p.ward_department IN ('OB','PEDIA','NEWBORN'),'WARD_2F','WARD_3F')
                 WHEN p.status IN ('triage','waiting_for_consultation','consultation','treatment','waiting_results','ready_for_disposition','for_admission') THEN 'ER' ELSE NULL END
        ) AS department,
        (SELECT m.status FROM patient_movements m WHERE m.patient_id=p.patient_id AND m.encounter_id=p.patient_code ORDER BY COALESCE(m.movement_date_time,m.created_at) DESC,m.id DESC LIMIT 1) AS transfer_status
        FROM patients p LEFT JOIN beds b ON b.bed_id=p.current_bed) t";
}
function nisTrackerPatientScope(array $units): array {
    if (!$units) return ['0=1',[]];
    $marks=implode(',',array_fill(0,count($units),'?'));
    $source=nisTrackerCanonicalSql('s.source_department');$destination=nisTrackerCanonicalSql('s.destination_department');
    return ["(t.department IN ($marks) OR EXISTS(SELECT 1 FROM patient_movements s WHERE s.patient_id=t.patient_id AND s.encounter_id=t.patient_code AND ($source IN ($marks) OR $destination IN ($marks))))",array_merge($units,$units,$units)];
}
function nisTrackerFilter(array $input): array {
    $clean=static fn($value,$limit)=>mb_substr(trim(is_string($value)?$value:''),0,$limit);
    $department=nisDepartment($clean($input['department']??'',60));
    return ['q'=>$clean($input['q']??'',100),'department'=>in_array($department,nisTrackerUnits(),true)?$department:'',
        'status'=>$clean($input['status']??'',40),'transfer_status'=>$clean($input['transfer_status']??'',30),
        'patient_id'=>max(0,(int)($input['patient_id']??0)),'page'=>max(1,min(100000,(int)($input['page']??1)))];
}
function nisPatientTrackerData(mysqli $conn,array $input): array {
    if (!hasPermission('patients','view')) throw new DomainException('Patient Tracker access is not permitted.');
    $units=nisTrackerDepartments();$filter=nisTrackerFilter($input);
    [$where,$params]=nisTrackerPatientScope($units);
    if(count($units)===count(nisTrackerUnits()))$where='('.$where.' OR t.department IS NULL)';
    if ($filter['q']!=='') {
        $where.=" AND (CONCAT(t.last_name,', ',t.first_name) LIKE ? ESCAPE '=' OR t.permanent_patient_id LIKE ? ESCAPE '=' OR t.patient_code LIKE ? ESCAPE '=')";
        $term='%'.strtr($filter['q'],['='=>'==','%'=>'=%','_'=>'=_']).'%';array_push($params,$term,$term,$term);
    }
    foreach(['department','status','transfer_status'] as $column) if($filter[$column]!==''){$where.=" AND t.$column=?";$params[]=$filter[$column];}
    if ($filter['patient_id']) {$where.=' AND t.patient_id=?';$params[]=(string)$filter['patient_id'];}
    $from=nisTrackerPatientFrom();
    $total=(int)nisTrackerRows($conn,"SELECT COUNT(*) AS total $from WHERE $where",$params)[0]['total'];
    $pages=max(1,(int)ceil($total/25));$filter['page']=min($filter['page'],$pages);$offset=($filter['page']-1)*25;
    $rows=nisTrackerRows($conn,"SELECT t.* $from WHERE $where ORDER BY t.patient_id DESC LIMIT 25 OFFSET $offset",$params);
    foreach($rows as &$row){
        $row['patient_name']=$row['last_name'].', '.$row['first_name'];unset($row['first_name'],$row['last_name']);
        $row['can_open_chart']=in_array($row['department'],$units,true)&&hasPermission('patients','view',$row['department']);
        $row['admission_status']=match($row['status']){'for_admission'=>'For admission','admit_inpatient'=>'Admitted',default=>'Not currently admitted'};
        // Avoid exposing a stale sending bed as the patient's receiving location.
        if ($row['department']!==$row['bed_department'])$row['bed_number']=null;
        if($row['department']==='NICU')$row['bed_number']=$row['neonatal_bed'];
        if($row['department']==='ICU')$row['bed_number']=$row['intensive_bed'];
        unset($row['bed_department'],$row['neonatal_bed'],$row['intensive_bed'],$row['current_bed']);
    }unset($row);
    $history=[];
    if ($filter['patient_id'] && $rows) {
        $marks=implode(',',array_fill(0,count($units),'?'));
        $fromUnit=nisTrackerCanonicalSql('source_department');$toUnit=nisTrackerCanonicalSql('destination_department');
        // No clinical remarks or staff identifiers in cross-unit tracking history.
        $historyScope=count($units)===count(nisTrackerUnits())?'1=1':"($fromUnit IN ($marks) OR $toUnit IN ($marks))";
        $historyParams=[(string)$rows[0]['patient_id'],$rows[0]['patient_code']];
        if(count($units)<count(nisTrackerUnits()))array_push($historyParams,...$units,...$units);
        $history=nisTrackerRows($conn,"SELECT id,encounter_id,source_department,destination_department,status,movement_date_time,created_at FROM patient_movements WHERE patient_id=? AND encounter_id=? AND $historyScope ORDER BY COALESCE(movement_date_time,created_at) DESC,id DESC LIMIT 100",$historyParams);
    }
    return ['rows'=>$rows,'total'=>$total,'pages'=>$pages,'filter'=>$filter,'history'=>$history,'units'=>$units];
}
function nisTrackerCanOpenChart(mysqli $conn,int $patientId): bool {
    if(!hasPermission('patients','view'))return false;
    $rows=nisTrackerRows($conn,'SELECT t.department '.nisTrackerPatientFrom().' WHERE t.patient_id=?',[(string)$patientId]);
    if(!$rows)return false;
    if($rows[0]['department']===null)return isSuperAdmin()||hasRole('CHIEF_NURSE');
    return hasPermission('patients','view',$rows[0]['department']);
}
function nisTrackerClinicalScope(string $patientAlias='p'): array {
    $units=nisTrackerDepartments();
    if(!$units)return ['0=1',[]];
    $marks=implode(',',array_fill(0,count($units),'?'));
    return ["$patientAlias.patient_id IN (SELECT t.patient_id ".nisTrackerPatientFrom()." WHERE t.department IN ($marks))",$units];
}

function nisBedTrackerData(mysqli $conn,array $input): array {
    if (!hasPermission('beds','view')) throw new DomainException('Bed Tracker access is not permitted.');
    $filter=nisTrackerFilter($input);$departmentSql=nisTrackerBedDepartmentSql();
    // Cross-department availability contains no names, diagnoses, IDs or chart links.
    $sql="SELECT b.bed_id,b.bed_number,b.status AS recorded_status,$departmentSql AS department,
        (b.patient_id IS NOT NULL OR EXISTS(SELECT 1 FROM patients p WHERE p.current_bed=b.bed_id AND p.status NOT IN ('discharge','discharged','referred','ama','hama','dama','thoc','death','er_death','expired','doa','absconded'))) AS has_assignment FROM beds b";
    $rows=nisTrackerRows($conn,$sql);$rows=array_values(array_filter($rows,fn($r)=>in_array($r['department'],nisTrackerUnits(),true)));
    $registered=array_column($rows,'department');
    $notices=[];
    foreach(['ICU'=>['ICU / MICU','MICU'],'NICU'=>['NICU','NICU']] as $unit=>[$area,$prefix]){
        if($filter['department']!==''&&$filter['department']!==$unit)continue;
        if(in_array($unit,$registered,true))continue;
        $capacity=nisTrackerRows($conn,"SELECT total_bed_capacity FROM bed_capacity_settings WHERE area_name=? AND status='ACTIVE'",[$area]);
        if(!$capacity)continue;
        $table=$unit==='ICU'?'micu_records':'nicu_records';
        $active=nisTrackerRows($conn,"SELECT bed_no,COUNT(*) AS total FROM $table WHERE status='ACTIVE' GROUP BY bed_no");
        $assigned=[];$unassigned=0;
        $capacity=max(0,(int)$capacity[0]['total_bed_capacity']);
        foreach($active as $record){$assigned[(string)$record['bed_no']]=(int)$record['total'];}
        for($number=1;$number<=$capacity;$number++){
            $bed=$prefix.'-'.str_pad((string)$number,2,'0',STR_PAD_LEFT);$count=$assigned[$bed]??0;unset($assigned[$bed]);
            $rows[]=['bed_id'=>null,'bed_number'=>$bed,'department'=>$unit,'recorded_status'=>$count?'occupied':'available','has_assignment'=>$count>0,'conflict'=>$count>1,'capacity_slot'=>true];
        }
        $unassigned=array_sum($assigned);
        if($unassigned){$notices[]=nisDepartmentLabel($unit).': '.$unassigned.' active records have no matching configured bed. Unassigned slots require verification.';
            foreach($rows as &$row)if($row['department']===$unit&&!$row['has_assignment'])$row['recorded_status']='unverified';unset($row);
        }
        $notices[]=nisDepartmentLabel($unit).': slots use the existing configured capacity and active unit records. Reservation and out-of-service states are not recorded in that ledger.';
    }
    foreach($rows as &$row){
        $row['has_assignment']=(bool)$row['has_assignment'];
        $row['status']=match(true){$row['has_assignment']=> 'occupied',in_array($row['recorded_status'],['maintenance','out_of_service','unavailable'],true)=>'out_of_service',default=>$row['recorded_status']};
        $row['conflict']=($row['conflict']??false)||($row['has_assignment']&&$row['recorded_status']!=='occupied');
    }unset($row);
    if($filter['department']!=='')$rows=array_values(array_filter($rows,fn($r)=>$r['department']===$filter['department']));
    $summary=['total'=>count($rows),'available'=>0,'occupied'=>0,'reserved'=>0,'out_of_service'=>0,'unverified'=>0];
    foreach($rows as $row){if(isset($summary[$row['status']]))$summary[$row['status']]++;else $summary['unverified']++;}
    $hasRegistered=(bool)array_filter($rows,fn($row)=>$row['bed_id']!==null);
    $hasSlots=(bool)array_filter($rows,fn($row)=>!empty($row['capacity_slot']));
    if($filter['status']!=='')$rows=array_values(array_filter($rows,fn($r)=>$r['status']===$filter['status']));
    $total=count($rows);$pages=max(1,(int)ceil($total/50));$filter['page']=min($filter['page'],$pages);
    $statusType=nisTrackerRows($conn,"SHOW COLUMNS FROM beds WHERE Field='status'")[0]['Type']??'';
    return ['rows'=>array_slice($rows,($filter['page']-1)*50,50),'total'=>$total,'pages'=>$pages,'filter'=>$filter,'summary'=>$summary,'notices'=>$notices,
        'reservation_supported'=>$hasRegistered&&str_contains(strtolower($statusType),'reserved'),
        'out_of_service_supported'=>$hasRegistered&&(str_contains(strtolower($statusType),'maintenance')||str_contains(strtolower($statusType),'out_of_service')),
        'partial_state_coverage'=>$hasSlots,'units'=>nisTrackerUnits()];
}
