<?php
// Preserve the actual nursing hierarchy: canonicalRole intentionally merges supervisors and assistants elsewhere.
function naRole(array $employee): string {
    $role=strtoupper((string)($employee['role']??''));
    return ['NURSE'=>'STAFF_NURSE','SUPERVISOR'=>'SUPERVISOR_NURSE'][$role]??$role;
}
function naEligible(array $employee): bool {return in_array(naRole($employee),['NURSING_ATTENDANT','STAFF_NURSE','HEAD_NURSE','SUPERVISOR_NURSE','ASSISTANT_CHIEF_NURSE','CHIEF_NURSE'],true);}
function naApprovalAccess(array $actor): bool {return in_array(naRole($actor),['HEAD_NURSE','SUPERVISOR_NURSE','ASSISTANT_CHIEF_NURSE','CHIEF_NURSE','SUPER_ADMIN'],true);}
function naFixed(array $employee): bool{return in_array(naRole($employee),['SUPERVISOR_NURSE','ASSISTANT_CHIEF_NURSE','CHIEF_NURSE'],true);}
function naName(array $employee): string{return trim((string)($employee['full_name']??''))?:trim(implode(' ',array_filter([$employee['first_name']??'',$employee['middle_name']??'',$employee['last_name']??'',$employee['suffix']??''])));}
function naRoute(string $kind,array $employee): array {
    $role=naRole($employee);
    if($kind==='EXCHANGE')return ['NURSING_ATTENDANT'=>['HEAD_NURSE','SUPERVISOR_NURSE','CHIEF_NURSE'],'STAFF_NURSE'=>['HEAD_NURSE','SUPERVISOR_NURSE','CHIEF_NURSE'],'HEAD_NURSE'=>['SUPERVISOR_NURSE','CHIEF_NURSE'],'SUPERVISOR_NURSE'=>['ASSISTANT_CHIEF_NURSE','CHIEF_NURSE'],'ASSISTANT_CHIEF_NURSE'=>['CHIEF_NURSE'],'CHIEF_NURSE'=>['CHIEF_NURSE']][$role]??[];
    if($kind==='OFF')return ['NURSING_ATTENDANT'=>['HEAD_NURSE'],'STAFF_NURSE'=>['HEAD_NURSE'],'HEAD_NURSE'=>['SUPERVISOR_NURSE']][$role]??[];
    return ['NURSING_ATTENDANT'=>['HEAD_NURSE','SUPERVISOR_NURSE','CHIEF_NURSE'],'STAFF_NURSE'=>['HEAD_NURSE','SUPERVISOR_NURSE','CHIEF_NURSE'],'HEAD_NURSE'=>['SUPERVISOR_NURSE','CHIEF_NURSE'],'SUPERVISOR_NURSE'=>['ASSISTANT_CHIEF_NURSE','CHIEF_NURSE'],'ASSISTANT_CHIEF_NURSE'=>['CHIEF_NURSE'],'CHIEF_NURSE'=>[]][$role]??[];
}
function naResolve(mysqli $db,array $employee,string $role,array $excluded=[]): array {
    $rules=nsScheduleQuery($db,'SELECT approver_id FROM nursing_reporting_hierarchy WHERE employee_id=? AND approver_role=? AND enabled=1',[$employee['user_id'],$role])->get_result()->fetch_all(MYSQLI_ASSOC);
    $ids=array_map('intval',array_column($rules,'approver_id'));$out=[];
    foreach(nsScheduleQuery($db,"SELECT * FROM users WHERE is_active=1 AND status='ACTIVE'")->get_result()->fetch_all(MYSQLI_ASSOC) as $actor){
        $id=(int)$actor['user_id'];if(naRole($actor)!==$role||in_array($id,$excluded,true))continue;
        if($rules?!in_array($id,$ids,true):($role==='HEAD_NURSE'&&canonicalDepartment($actor['department'])!==canonicalDepartment($employee['department'])))continue;
        $out[]=$id;
    }
    return $out;
}
