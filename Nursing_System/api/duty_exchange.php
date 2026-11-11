<?php
require_once __DIR__.'/../config/db.php';require_once __DIR__.'/../includes/auth.php';requireLogin();require_once __DIR__.'/../includes/duty_exchange.php';
header('Content-Type: application/json; charset=utf-8');
try {
 if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
 exSchema($conn);$actor=exActor($conn,(int)$_SESSION['user_id']);$access=exAccess($conn,$actor);if(!$access['view'])throw new DomainException('Exchange of Duty is unavailable for this account.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrfToken();if((int)($_SERVER['CONTENT_LENGTH']??0)>50000)throw new InvalidArgumentException('The request is too large.');
  $input=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);if(!is_array($input))throw new InvalidArgumentException('Invalid request.');$action=(string)($input['action']??'');
  if(in_array($action,['submit','draft'],true))$data=exSaveRequest($conn,$actor,$input,$action==='draft');
  elseif($action==='configure'){
   if(!$access['admin'])throw new DomainException('Only Super Admin can configure exchange permissions.');
   $stage=(string)($input['stage']??'');$department=($input['department']??'')==='*'?'*':canonicalDepartment($input['department']??'');$approver=exActor($conn,(int)($input['approver_id']??0));
   if(!in_array($stage,['HEAD','SUPERVISOR','ASSISTANT','CHIEF'],true)||!in_array($department,array_merge(['*'],array_keys(nsScheduleDepartments())),true))throw new InvalidArgumentException('Select an approval stage and department.');
   if(naRole($approver)!==['HEAD'=>'HEAD_NURSE','SUPERVISOR'=>'SUPERVISOR_NURSE','CHIEF'=>'CHIEF_NURSE','ASSISTANT'=>'ASSISTANT_CHIEF_NURSE'][$stage])throw new DomainException('The assigned employee must hold the required approval role. Super Admin cannot substitute for a mandatory approver.');
   $conn->begin_transaction();try{nsScheduleQuery($conn,'INSERT INTO nursing_exchange_approvers(stage,department,user_id,enabled) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)',[$stage,$department,$approver['user_id'],!empty($input['enabled'])?1:0]);
    nsScheduleQuery($conn,'INSERT INTO nursing_exchange_events(request_id,round_no,stage,decision,actor_id,actor_name,actor_role,remarks,snapshot_json) VALUES(0,0,?,?,?,?,?,?,?)',['CONFIGURATION','APPROVER_UPDATED',$actor['user_id'],$actor['name'],$actor['canonical_role'],'Approver assignment changed',nsScheduleEncode($input)]);$conn->commit();}catch(Throwable $error){$conn->rollback();throw $error;}$data=['configured'=>true];
  }elseif($action==='permission'){
   if(!$access['admin'])throw new DomainException('Only Super Admin can configure permissions.');$role=(string)($input['role']??'');if(!in_array($role,['STAFF_NURSE','HEAD_NURSE','SUPERVISOR_NURSE','CHIEF_NURSE'],true))throw new InvalidArgumentException('Select a nursing role.');
   $conn->begin_transaction();try{nsScheduleQuery($conn,'INSERT INTO nursing_exchange_permissions(role,can_view,can_submit) VALUES(?,?,?) ON DUPLICATE KEY UPDATE can_view=VALUES(can_view),can_submit=VALUES(can_submit)',[$role,!empty($input['can_view'])?1:0,!empty($input['can_submit'])?1:0]);nsScheduleQuery($conn,'INSERT INTO nursing_exchange_events(request_id,round_no,stage,decision,actor_id,actor_name,actor_role,remarks,snapshot_json) VALUES(0,0,?,?,?,?,?,?,?)',['CONFIGURATION','PERMISSION_UPDATED',$actor['user_id'],$actor['name'],$actor['canonical_role'],'Role permission changed',nsScheduleEncode($input)]);$conn->commit();}catch(Throwable $error){$conn->rollback();throw $error;}$data=['configured'=>true];
  }elseif($action==='read_notifications'){
   nsScheduleQuery($conn,"UPDATE nursing_document_notifications n JOIN nursing_exchange_notifications e ON e.notification_id=n.id SET n.status='READ',n.reviewed_by=?,n.reviewed_at=NOW() WHERE e.recipient_id=?",[$actor['user_id'],$actor['user_id']]);$data=['read'=>true];
  }else $data=exDecide($conn,$actor,(int)($input['id']??0),(int)($input['version']??0),$action,trim((string)($input['remarks']??'')));
 }else{
  $action=(string)($_GET['action']??'list');
  if($action==='assignments'){
   $employee=exActor($conn,(int)($_GET['employee_id']??$actor['user_id']));if(!exAccess($conn,$employee)['submit'])throw new DomainException('Select an eligible employee in your department.');
   $data=[];foreach(exRows($conn,"SELECT * FROM nursing_schedule_months WHERE status='PUBLISHED' AND final_status='PUBLISHED' ORDER BY month_start") as $row){$payload=nsScheduleDecode($row['final_json']);foreach($payload['assignments'][$employee['user_id']]??[] as $date=>$shift){$data[]=['value'=>$row['id'].':'.$employee['user_id'].':'.$date.':'.$row['final_version'],'date'=>$date,'shift'=>$shift,'shift_label'=>$payload['shiftMaster'][$shift]['label']??nsShiftDefaults()[$shift]['label']??$shift,'department'=>$row['department'],'hours'=>$payload['shiftMaster'][$shift]['hours']??nsScheduleDuties()[$shift]??0,'exchange_ref'=>$payload['exchange_refs'][$employee['user_id']][$date]??null,'selectable'=>($payload['shiftMaster'][$shift]['hours']??nsScheduleDuties()[$shift]??0)>0&&empty($payload['exchange_refs'][$employee['user_id']][$date])];}}
  }elseif($action==='detail'){
   $request=exOne($conn,'SELECT * FROM nursing_exchange_requests WHERE id=?',[(int)($_GET['id']??0)]);if(!$request||!exCanView($conn,$actor,$request))throw new DomainException('You cannot access this exchange request.');
   $request['original']=nsScheduleDecode($request['original_json']);$request['proposed']=nsScheduleDecode($request['proposed_json']);$request['validation']=nsScheduleDecode($request['validation_json']);
   $amendments=exRows($conn,'SELECT id,schedule_id,original_version,revised_version,hours_json,created_at FROM nursing_exchange_amendments WHERE request_id=? ORDER BY id',[$request['id']]);
   if($actor['canonical_role']==='STAFF_NURSE')foreach($amendments as &$amendment)$amendment['hours_json']=nsScheduleEncode(array_values(array_filter(nsScheduleDecode($amendment['hours_json']),fn($summary)=>in_array((int)$summary['employee_id'],[(int)$request['requester_id'],(int)$request['partner_id']],true))));unset($amendment);
   $data=['route'=>exRoute($conn,$request),'request'=>$request,'events'=>exRows($conn,'SELECT * FROM nursing_exchange_events WHERE request_id=? ORDER BY id',[$request['id']]),'amendments'=>$amendments,'can_approve'=>exStage($request['status'])?exCurrentApproves($conn,$actor,$request):false];
  }elseif($action==='configuration'){
   if(!$access['admin'])throw new DomainException('Configuration is restricted to Super Admin.');$data=['approvers'=>exRows($conn,'SELECT a.*,u.first_name,u.last_name FROM nursing_exchange_approvers a JOIN users u ON u.user_id=a.user_id ORDER BY stage,department'),'permissions'=>exRows($conn,'SELECT * FROM nursing_exchange_permissions'),'audit'=>exRows($conn,'SELECT * FROM nursing_exchange_events WHERE request_id=0 ORDER BY id DESC LIMIT 100')];
  }else{
   $where=[];$args=[];foreach(['status'=>'r.status','department'=>'r.department'] as $key=>$column)if(!empty($_GET[$key])){$where[]=$column.'=?';$args[]=(string)$_GET[$key];}
   if(!empty($_GET['from'])){$where[]='r.created_at>=?';$args[]=exDate($_GET['from']).' 00:00:00';}if(!empty($_GET['to'])){$where[]='r.created_at<=?';$args[]=exDate($_GET['to']).' 23:59:59';}
   if(!empty($_GET['q'])){$where[]='(r.request_no LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ? OR b.first_name LIKE ? OR b.last_name LIKE ?)';for($i=0;$i<5;$i++)$args[]='%'.substr((string)$_GET['q'],0,120).'%';}
   $rows=exRows($conn,'SELECT r.*,CONCAT(a.first_name," ",a.last_name) requester_name,CONCAT(b.first_name," ",b.last_name) partner_name FROM nursing_exchange_requests r JOIN users a ON a.user_id=r.requester_id JOIN users b ON b.user_id=r.partner_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY r.id DESC',$args);
   $data=[];foreach($rows as $row)if(exCanView($conn,$actor,$row)){$row['can_approve']=exStage($row['status'])?exCurrentApproves($conn,$actor,$row):false;$row['original']=nsScheduleDecode($row['original_json']);$data[]=$row;}
  }
 }
 echo nsScheduleEncode(['success'=>true,'data'=>$data]);
}catch(Throwable $error){$expected=$error instanceof DomainException||$error instanceof InvalidArgumentException||$error instanceof JsonException||$error->getCode()===409;http_response_code($error->getCode()===409?409:($error instanceof DomainException?403:($expected?422:500)));if(!$expected)error_log('Exchange of Duty: '.$error->getMessage());echo json_encode(['success'=>false,'error'=>$expected?$error->getMessage():'Unable to process this exchange. Contact the administrator.']);}
