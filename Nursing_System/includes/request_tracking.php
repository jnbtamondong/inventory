<?php
require_once __DIR__.'/nursing_requests.php';

// Read model only: decisions continue through the established transactional workflows.
function rtStatus(string $status): array {
    if(str_starts_with($status,'PENDING'))return ['PENDING','Pending'];
    return match($status){
        'APPROVED_FOR_HR_FILING'=>['APPROVED','Cleared for HR Filing'],
        'WITHDRAWN','CANCELLED'=>['CANCELLED','Cancelled'],
        'DECLINED_BY_PARTNER'=>['REJECTED','Declined by other employee'],
        'RETURNED','RETURNED_FOR_CORRECTION'=>['RETURNED','Returned for correction'],
        'UPDATE_FAILED'=>['ERROR','Schedule update failed'],
        default=>[$status,ucfirst(strtolower(str_replace('_',' ',$status)))]
    };
}
function rtNames(mysqli $db,array $ids): string {
    if(!$ids)return 'No active reviewer configured';
    return implode(', ',array_map(fn($r)=>naName($r),exRows($db,'SELECT * FROM users WHERE user_id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids)));
}
function rtItem(mysqli $db,array $actor,array $row,bool $exchange=false): array {
    [$status,$label]=rtStatus($row['status']);
    $dates=$exchange?array_column(nsScheduleDecode($row['original_json']),'date'):json_decode($row['dates_json'],true);
    $employee=exOne($db,'SELECT * FROM users WHERE user_id=?',[$row[$exchange?'requester_id':'employee_id']]);
    $approver='Completed';$canDecide=false;
    if($exchange){
        if($row['status']==='PENDING_PARTNER_CONFIRMATION')$approver='Other employee: '.rtNames($db,[(int)$row['partner_id']]);
        elseif(exStage($row['status'])){$next=exNext($db,$row);$scoped=$row;$scoped['department']=$next['department']??$row['department'];$approver=str_replace('_',' ',exStage($row['status'])).' review: '.rtNames($db,exApprovers($db,$scoped,exStage($row['status'])));}
        $canDecide=exCurrentApproves($db,$actor,$row);
    }elseif($row['status']==='PENDING_APPROVAL'){
        $stage=json_decode($row['route_json'],true)[$row['stage']]??[];
        $approver=str_replace('_',' ',$stage['role']??'Reviewer').': '.rtNames($db,nrStageApprovers($db,$row,(int)$row['stage']));$canDecide=nrCanDecide($db,$actor,$row);
    }
    if(in_array($status,['DRAFT','RETURNED'],true))$approver='Employee action required';
    if($row['status']==='APPROVED_FOR_HR_FILING')$approver='HR filing outside Nursing Service';
    $submitted=$exchange?($row['submitted_at']??$row['created_at']):(exOne($db,"SELECT created_at FROM nursing_service_request_events WHERE request_id=? AND round_no=? AND decision IN ('SUBMITTED','RESUBMITTED') ORDER BY id LIMIT 1",[$row['id'],$row['round_no']])['created_at']??$row['created_at']);
    return ['id'=>(int)$row['id'],'type'=>$exchange?'EXCHANGE':$row['kind'],'number'=>$row['request_no']?:'Draft #'.$row['id'],'status'=>$status,'label'=>$label,'raw_status'=>$row['status'],'employee'=>($row['employee_name']??'')?:($employee?naName($employee):'Employee #'.$row[$exchange?'requester_id':'employee_id']),'employee_id'=>(int)$row[$exchange?'requester_id':'employee_id'],'department'=>$row['department'],'dates'=>array_values(array_unique($dates)),'submitted'=>$status==='DRAFT'?'Not submitted':$submitted,'approver'=>$approver,'can_decide'=>$canDecide,'row'=>$row];
}
function rtList(mysqli $db,array $actor,bool $approvals=false,bool $history=false): array {
    if($approvals&&!naApprovalAccess($actor))throw new DomainException('Approval access denied.');
    if($history&&!naApprovalAccess($actor))throw new DomainException('History access denied.');
    $items=[];
    $rows=exRows($db,'SELECT * FROM nursing_service_requests'.($approvals?" WHERE status='PENDING_APPROVAL'":($history?'':' WHERE employee_id=?')).' ORDER BY id DESC',$approvals||$history?[]:[$actor['user_id']]);
    foreach($rows as $row)if(($approvals?nrCanDecide($db,$actor,$row):true)&&(!$history||nrCanView($db,$actor,$row)))$items[]=rtItem($db,$actor,$row);
    if(exAccess($db,$actor)['view'])foreach(exRows($db,'SELECT * FROM nursing_exchange_requests'.($approvals||$history?'':' WHERE requester_id=? OR partner_id=?').' ORDER BY id DESC',$approvals||$history?[]:[$actor['user_id'],$actor['user_id']]) as $row)if(($approvals?exCurrentApproves($db,$actor,$row):true)&&(!$history||exCanView($db,$actor,$row)))$items[]=rtItem($db,$actor,$row,true);
    usort($items,fn($a,$b)=>strcmp($b['submitted'],$a['submitted'])?:$b['id']<=>$a['id']);return $items;
}
function rtDetail(mysqli $db,array $actor,string $type,int $id): array {
    if(!in_array($type,['OFF','LEAVE','EXCHANGE'],true)||$id<1)throw new InvalidArgumentException('Invalid request.');
    $exchange=$type==='EXCHANGE';$row=exOne($db,'SELECT * FROM '.($exchange?'nursing_exchange_requests':'nursing_service_requests').' WHERE id=?',[$id]);
    if(!$row||(!$exchange&&$row['kind']!==$type)||!($exchange?exCanView($db,$actor,$row):nrCanView($db,$actor,$row)))throw new DomainException('Request access denied.');
    $item=rtItem($db,$actor,$row,$exchange);
    $item['events']=exRows($db,'SELECT * FROM '.($exchange?'nursing_exchange_events':'nursing_service_request_events').' WHERE request_id=? ORDER BY id',[$id]);
    $item['route']=$exchange?exRoute($db,$row):json_decode($row['route_json'],true);
    return $item;
}
function rtFilter(array $items,array $filters): array {
    $from=trim($filters['from']??'');$to=trim($filters['to']??'');$month=trim($filters['month']??'');
    if($from)exDate($from);if($to)exDate($to);if($month)nsScheduleMonth($month);if($from&&$to&&$from>$to)throw new InvalidArgumentException('The end date must follow the start date.');
    return array_values(array_filter($items,function($r)use($filters,$from,$to,$month){
        foreach(['type','status','department','employee_id'] as $key)if(($filters[$key]??'')!==''&&(string)$r[$key]!== (string)$filters[$key])return false;
        if(($filters['q']??'')!==''&&stripos($r['number'],trim($filters['q']))===false)return false;
        return (bool)array_filter($r['dates'],fn($d)=>(!$month||substr($d,0,7)===$month)&&(!$from||$d>=$from)&&(!$to||$d<=$to));
    }));
}
function rtNotices(mysqli $db,array $actor): array {
    $notices=exRows($db,"SELECT n.id,n.message,n.status,n.created_at,r.kind AS type,l.request_id FROM nursing_service_request_notifications l JOIN nursing_document_notifications n ON n.id=l.notification_id JOIN nursing_service_requests r ON r.id=l.request_id WHERE n.employee_id=? AND n.status='UNREAD' UNION ALL SELECT n.id,n.message,n.status,n.created_at,'EXCHANGE' AS type,l.request_id FROM nursing_exchange_notifications l JOIN nursing_document_notifications n ON n.id=l.notification_id WHERE n.employee_id=? AND n.status='UNREAD' ORDER BY id DESC LIMIT 50",[$actor['user_id'],$actor['user_id']]);
    return array_values(array_filter($notices,function($n)use($db,$actor){try{rtDetail($db,$actor,$n['type'],(int)$n['request_id']);return true;}catch(DomainException $e){return false;}}));
}
function rtScheduleState(mysqli $db,array $item): string {
    $row=$item['row'];
    if($item['type']==='EXCHANGE')return $item['raw_status']==='APPROVED'?(exOne($db,'SELECT id FROM nursing_exchange_amendments WHERE request_id=?',[$item['id']])?'Published assignments updated; hours recalculated.':'No recorded schedule amendment.'):'Schedule unchanged until final authorization.';
    if($item['status']!=='APPROVED')return 'No approved scheduling constraint.';
    if($item['type']==='LEAVE')return 'Internal clearance recorded. HR approval is not recorded here; official attendance leave controls schedule assignment.';
    $assignments=nrPublishedAssignments($db,$row);$covered=0;$conflicts=[];
    foreach($item['dates'] as $date){if(!empty($assignments[$date])){if(array_diff($assignments[$date],['OFF','REQUEST_OFF']))$conflicts[]=$date;else $covered++;}}
    return $conflicts?'Published duty conflict: '.implode(', ',$conflicts).'. Requires an authorized change.':($covered===count($item['dates'])?'Reflected in the published monthly schedule.':'Approved constraint is available to monthly scheduling; awaiting publication for remaining dates.');
}
