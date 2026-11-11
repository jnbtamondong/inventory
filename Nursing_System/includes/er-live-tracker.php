<?php
/** ER display projection. No separate clinical status or public identity lookup. */
require_once __DIR__.'/patient_helpers.php';
require_once __DIR__.'/nis-tracker.php';

function erQuery(mysqli $db,string $sql,array $params=[]): mysqli_stmt {
    $s=$db->prepare($sql);
    if($params)$s->bind_param(str_repeat('s',count($params)),...$params);
    if(!$s->execute())throw new RuntimeException('ER tracker query failed.');
    return $s;
}
function erTrackerReady(mysqli $db): bool {
    $row=erQuery($db,"SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('er_public_tracking','er_tracker_events','er_display_settings','er_display_devices') AND ENGINE='InnoDB'")->get_result()->fetch_assoc();
    return (int)$row['n']===4;
}
function erStages(): array {
    return ['REGISTERED'=>'Registered','IN_TRIAGE'=>'In Triage','FOR_CONSULTATION'=>'For Consultation','UNDER_TREATMENT'=>'Under Treatment','WAITING_FOR_LABS'=>'Waiting for Labs','FOR_ADMISSION'=>'For Admission','FOR_DISCHARGE'=>'For Discharge','TRANSFERRED_ADMITTED'=>'Transferred / Admitted','COMPLETED'=>'Completed'];
}
function erStage(array $r): ?string {
    // A bed allocation/admitted code alone is not receiving confirmation.
    if(($r['department']??'')!=='ER'&&!empty($r['confirmed_er_exit']))return 'TRANSFERRED_ADMITTED';
    return match($r['status']){
        'triage'=>empty($r['triage_started_at'])?'REGISTERED':'IN_TRIAGE',
        'waiting_for_consultation'=>'FOR_CONSULTATION',
        'consultation','treatment'=>'UNDER_TREATMENT',
        'waiting_results'=>'WAITING_FOR_LABS',
        'ready_for_disposition'=>empty($r['discharge_started_at'])?'UNDER_TREATMENT':'FOR_DISCHARGE',
        'for_admission','admit_inpatient'=>'FOR_ADMISSION',
        'discharge','discharged','referred','ama','hama','thoc','absconded','dama','doa','death','expired','er_death'=>'COMPLETED',
        default=>null,
    };
}
function erTerminal(?string $stage): bool {return in_array($stage,['COMPLETED','TRANSFERRED_ADMITTED'],true);}
function erTrackerReadSql(bool $staff=false): string {
    $identity=$staff?",p.permanent_patient_id,p.first_name,p.last_name,p.middle_name,CASE WHEN t.department='ER' AND t.bed_department='ER' THEN t.bed_number ELSE NULL END er_bed":'';
    return "SELECT r.*,p.status,p.arrival_time,t.department,t.transfer_status$identity,
        EXISTS(SELECT 1 FROM patient_movements m WHERE m.patient_id=p.patient_id AND m.encounter_id=p.patient_code AND m.source_department='ER' AND m.destination_department<>'ER' AND m.status IN ('RECEIVED','ARRIVED')) confirmed_er_exit,
        e.event_id,e.stage last_stage,e.clinical_code last_code,e.effective_at status_since
        FROM er_public_tracking r JOIN patients p ON p.patient_id=r.patient_id AND p.patient_code=r.encounter_id
        JOIN (SELECT t.patient_id,t.department,t.transfer_status,t.bed_number,t.bed_department ".nisTrackerPatientFrom().") t ON t.patient_id=p.patient_id
        LEFT JOIN er_tracker_events e ON e.event_id=(SELECT MAX(h.event_id) FROM er_tracker_events h WHERE h.tracking_id=r.tracking_id)";
}
function erAudit(mysqli $db,int $user,string $action,array $details): void {
    erQuery($db,'INSERT INTO audit_logs(user_id,username,role,department,ip_address,action,user_agent,details) VALUES(?,?,?,?,?,?,?,?)',[$user,$_SESSION['username']??'',$_SESSION['role']??'',$_SESSION['department']??'',$_SERVER['REMOTE_ADDR']??'',$action,substr($_SERVER['HTTP_USER_AGENT']??'',0,1000),json_encode($details,JSON_THROW_ON_ERROR)]);
}
function erTrackerObserve(mysqli $db,int $patient,int $actor,string $source,string $reason='',bool $force=false): void {
    if(!erTrackerReady($db))return; // Existing installations remain operational before the explicit migration.
    $r=erQuery($db,erTrackerReadSql().' WHERE r.patient_id=?',[$patient])->get_result()->fetch_assoc();
    if(!$r)return;
    if($r['status']!=='ready_for_disposition'&&$r['discharge_started_at']){
        erQuery($db,'UPDATE er_public_tracking SET discharge_started_at=NULL WHERE tracking_id=?',[$r['tracking_id']]);$r['discharge_started_at']=null;
    }
    if(erTerminal($r['last_stage'])&&$r['department']==='ER'&&$r['status']==='triage'){
        erQuery($db,'UPDATE er_public_tracking SET triage_started_at=NULL WHERE tracking_id=?',[$r['tracking_id']]);$r['triage_started_at']=null;
    }
    $stage=erStage($r);
    if(!$stage)return;
    // Once ER receiving is confirmed, later ward care must not restart the ER
    // completion clock or enter ER public history. An actual return to ER reopens it.
    if($stage==='TRANSFERRED_ADMITTED'&&$r['last_stage']==='TRANSFERRED_ADMITTED')return;
    if($force||$stage!==$r['last_stage']||$r['status']!==$r['last_code']){
        erQuery($db,'INSERT INTO er_tracker_events(tracking_id,stage,clinical_code,source,actor_id,reason) VALUES(?,?,?,?,?,?)',[$r['tracking_id'],$stage,$r['status'],$source,$actor,$reason]);
    }
}
function erTrackerEnroll(mysqli $db,int $patient,int $actor,bool $visible=true): string {
    // Caller holds the patient row lock and transaction. Enrollment never creates a patient.
    $existing=erQuery($db,'SELECT public_number FROM er_public_tracking WHERE patient_id=?',[$patient])->get_result()->fetch_assoc();
    if($existing)return $existing['public_number'];
    $p=erQuery($db,'SELECT p.patient_code,t.department FROM patients p JOIN (SELECT t.patient_id,t.department '.nisTrackerPatientFrom().') t ON t.patient_id=p.patient_id WHERE p.patient_id=?',[$patient])->get_result()->fetch_assoc();
    if(!$p||$p['department']!=='ER')throw new DomainException('Only a current ER encounter can receive ER public tracking.');
    for($attempt=0;$attempt<6;$attempt++){
        $number='ER-'.strtoupper(bin2hex(random_bytes(3)));
        try{
            erQuery($db,'INSERT INTO er_public_tracking(patient_id,encounter_id,public_number,public_visible,enrolled_by) VALUES(?,?,?,?,?)',[$patient,$p['patient_code'],$number,$visible?1:0,$actor]);
            erTrackerObserve($db,$patient,$actor,'registration');
            erAudit($db,$actor,'ER public tracking assigned',['patient_id'=>$patient,'public_number'=>$number,'public_visible'=>$visible]);
            return $number;
        }catch(mysqli_sql_exception $e){if($e->getCode()!==1062)throw $e;}
    }
    throw new RuntimeException('Tracking number allocation failed.');
}
function erStaffAllowed(string $action='view'): bool {return hasPermission('patients',$action,'ER');}
function erTrackerAction(mysqli $db,string $action,array $input,int $actor): void {
    if($actor!==(int)($_SESSION['user_id']??0)||!erStaffAllowed('edit'))throw new DomainException('ER tracker changes are not permitted.');
    $patient=filter_var($input['patient_id']??0,FILTER_VALIDATE_INT);
    if(!$patient)throw new DomainException('Select an ER encounter.');
    $reason=trim((string)($input['reason']??''));
    if(mb_strlen($reason)>500)throw new DomainException('Reason is limited to 500 characters.');
    $db->begin_transaction();
    try{
        $p=erQuery($db,'SELECT * FROM patients WHERE patient_id=? FOR UPDATE',[$patient])->get_result()->fetch_assoc();
        $r=erQuery($db,erTrackerReadSql().' WHERE r.patient_id=?',[$patient])->get_result()->fetch_assoc();
        $location=erQuery($db,'SELECT t.department '.nisTrackerPatientFrom().' WHERE t.patient_id=?',[$patient])->get_result()->fetch_assoc();
        $displayOnly=$r&&($action==='privacy'||($action==='verify_sync'&&erTerminal(erStage($r))));
        if(!$p||(($location['department']??'')!=='ER'&&!$displayOnly))throw new DomainException('Use the receiving department workflow for this encounter.');
        if($action==='enroll'){
            erTrackerEnroll($db,$patient,$actor,($input['public_visible']??'')==='1');
        }elseif(!$r){throw new DomainException('Assign this encounter a tracking number first.');
        }elseif($action==='privacy'){
            if($reason==='')throw new DomainException('Record the privacy visibility reason.');
            erQuery($db,'UPDATE er_public_tracking SET public_visible=?,privacy_reason=? WHERE patient_id=?',[($input['public_visible']??'')==='1'?1:0,$reason,$patient]);
        }elseif($action==='triage_start'){
            if($p['status']!=='triage')throw new DomainException('Triage can begin only for an encounter awaiting triage.');
            if(!$r['triage_started_at']){
                erQuery($db,'UPDATE er_public_tracking SET triage_started_at=NOW(6) WHERE patient_id=?',[$patient]);
                erTrackerObserve($db,$patient,$actor,'triage_started');
            }
        }elseif($action==='discharge_start'){
            if(!hasRole(['DOCTOR','SUPER_ADMIN'])||$p['status']!=='ready_for_disposition'||$reason==='')throw new DomainException('An authorized doctor must verify readiness for disposition and record the discharge-processing reason.');
            erQuery($db,'UPDATE er_public_tracking SET discharge_started_at=COALESCE(discharge_started_at,NOW(6)) WHERE patient_id=?',[$patient]);
            erTrackerObserve($db,$patient,$actor,'discharge_processing',$reason);
        }elseif($action==='correct_status'){
            $next=(string)($input['status']??'');$allowed=['waiting_for_consultation','consultation','treatment','waiting_results','ready_for_disposition'];
            if(!hasRole(['DOCTOR','SUPER_ADMIN'])||!in_array($p['status'],$allowed,true)||!in_array($next,$allowed,true)||$reason==='')throw new DomainException('A doctor may correct an active assessed ER care stage with a documented reason.');
            if(($input['expected_status']??'')!==$p['status'])throw new DomainException('The encounter changed. Refresh before correcting it.');
            if(!erQuery($db,'SELECT triage_id FROM triage WHERE patient_id=?',[$patient])->get_result()->fetch_assoc())throw new DomainException('Complete triage before changing an assessed care stage.');
            if($next!==$p['status']){
                erQuery($db,'UPDATE patients SET status=? WHERE patient_id=?',[$next,$patient]);
                erQuery($db,"INSERT INTO patient_tracking(patient_id,action,description,from_status,to_status,status_started_at,status_ended_at,user_id,wait_time_minutes) VALUES(?,'Status Updated',?,?,?,COALESCE(?,NOW()),NOW(),?,GREATEST(TIMESTAMPDIFF(MINUTE,COALESCE(?,NOW()),NOW()),0))",[$patient,'ER doctor correction: '.$reason,$p['status'],$next,$r['status_since'],$actor,$r['status_since']]);
                erTrackerObserve($db,$patient,$actor,'doctor_correction',$reason,true);
            }
        }elseif($action==='verify_sync'){
            if($reason==='')throw new DomainException('Record the source verification reason.');
            erTrackerObserve($db,$patient,$actor,'verified_sync',$reason);
        }else{throw new DomainException('Unknown ER tracker action.');}
        erAudit($db,$actor,'ER tracker '.$action,['patient_id'=>$patient,'reason'=>$reason]);
        if(!$db->commit())throw new RuntimeException('Could not save ER tracker action.');
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function erPublicDataset(mysqli $db): array {
    $seconds=(int)erQuery($db,'SELECT completed_seconds FROM er_display_settings WHERE settings_id=1')->get_result()->fetch_assoc()['completed_seconds'];
    $raw=erQuery($db,erTrackerReadSql()." WHERE r.public_visible=1 AND (e.stage NOT IN ('COMPLETED','TRANSFERRED_ADMITTED') OR e.effective_at>=DATE_SUB(NOW(6),INTERVAL ? SECOND)) ORDER BY r.tracking_id",[$seconds])->get_result()->fetch_all(MYSQLI_ASSOC);
    $rows=[];$counts=array_fill_keys(array_keys(erStages()),0);$now=time();
    foreach($raw as $r){
        $stage=erStage($r);
        // Fail closed if a legacy/direct write has no matching verified workflow observation.
        if(!$stage||$stage!==$r['last_stage']||($stage!=='TRANSFERRED_ADMITTED'&&$r['status']!==$r['last_code']))continue;
        if(!erTerminal($stage)&&$r['department']!=='ER')continue;
        $since=strtotime($r['status_since']);
        if(erTerminal($stage)&&($seconds===0||$now-$since>=$seconds))continue;
        $counts[$stage]++;
        $rows[]=['tracking_number'=>$r['public_number'],'status'=>erStages()[$stage],'status_key'=>$stage,'location'=>erTerminal($stage)?'ER visit completed':'Emergency Room','updated_at'=>date('c',$since),'elapsed_seconds'=>max(0,$now-$since)];
    }
    return ['rows'=>$rows,'counts'=>$counts,'server_time'=>date('c'),'poll_seconds'=>5,'rotate_seconds'=>15];
}
function erStaffDataset(mysqli $db,array $input): array {
    if(!erStaffAllowed())throw new DomainException('ER monitoring is not permitted.');
    $q=mb_substr(trim((string)($input['q']??'')),0,100);$stage=(string)($input['stage']??'');
    $sql=erTrackerReadSql(true);
    // Every r row was explicitly enrolled while in ER. Keep unconfirmed outgoing
    // admissions visible internally without exposing receiving clinical records.
    $params=[];$where=' WHERE 1=1';
    $selectedPatient=max(0,(int)($input['patient_id']??0));
    if($selectedPatient){$where.=' AND r.patient_id=?';$params[]=$selectedPatient;}
    elseif($q==='')$where.=" AND (e.stage NOT IN ('COMPLETED','TRANSFERRED_ADMITTED') OR e.effective_at>=DATE_SUB(NOW(6),INTERVAL 1 DAY))";
    if($q!==''){$where.=" AND (CONCAT(p.last_name,', ',p.first_name,' ',COALESCE(p.middle_name,'')) LIKE ? ESCAPE '=' OR p.permanent_patient_id LIKE ? ESCAPE '=' OR r.public_number LIKE ? ESCAPE '=')";$term='%'.strtr($q,['='=>'==','%'=>'=%','_'=>'=_']).'%';array_push($params,$term,$term,$term);}
    $rows=erQuery($db,$sql.$where.' ORDER BY r.tracking_id DESC',$params)->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach($rows as &$r){$r['stage']=erStage($r);$r['stage_label']=erStages()[$r['stage']]??'Status needs review';$r['can_open_chart']=$r['department']==='ER'&&hasPermission('patients','view','ER');$r['verified']=$r['stage']===$r['last_stage']&&($r['stage']==='TRANSFERRED_ADMITTED'||$r['status']===$r['last_code']);if($r['stage']==='TRANSFERRED_ADMITTED')$r['status']=$r['last_code'];}unset($r);
    if($stage!=='')$rows=array_values(array_filter($rows,fn($r)=>$r['stage']===$stage));
    $total=count($rows);$pages=max(1,(int)ceil($total/20));$page=max(1,min($pages,(int)($input['page']??1)));
    $history=[];$patient=max(0,(int)($input['patient_id']??0));
    if($patient){foreach($rows as $r)if((int)$r['patient_id']===$patient){$history=erQuery($db,'SELECT e.stage,e.clinical_code,e.source,e.actor_id,e.effective_at,e.reason FROM er_tracker_events e WHERE e.tracking_id=? ORDER BY e.event_id DESC LIMIT 100',[$r['tracking_id']])->get_result()->fetch_all(MYSQLI_ASSOC);break;}}
    foreach($history as &$event)$event['stage_label']=erStages()[$event['stage']]??$event['stage'];unset($event);
    $untrackedFilter='';$untrackedParams=[];
    if($q!==''){$untrackedFilter=" AND (CONCAT(p.last_name,', ',p.first_name,' ',COALESCE(p.middle_name,'')) LIKE ? ESCAPE '=' OR p.permanent_patient_id LIKE ? ESCAPE '=')";$untrackedParams=[$term,$term];}
    $untracked=erQuery($db,"SELECT p.patient_id,p.permanent_patient_id,p.first_name,p.last_name FROM patients p JOIN (SELECT t.patient_id,t.department ".nisTrackerPatientFrom().") t ON t.patient_id=p.patient_id LEFT JOIN er_public_tracking r ON r.patient_id=p.patient_id WHERE t.department='ER' AND r.tracking_id IS NULL $untrackedFilter ORDER BY p.patient_id DESC LIMIT 50",$untrackedParams)->get_result()->fetch_all(MYSQLI_ASSOC);
    return ['rows'=>array_slice($rows,($page-1)*20,20),'history'=>$history,'untracked'=>$untracked,'total'=>$total,'page'=>$page,'pages'=>$pages,'q'=>$q,'stage'=>$stage];
}
function erConfigureRetention(mysqli $db,int $seconds,int $actor): void {
    if(!isSuperAdmin()||$actor!==(int)($_SESSION['user_id']??0)||$seconds<0||$seconds>7200)throw new DomainException('Only Super Admin may set a completion display period from 0 to 120 minutes.');
    $db->begin_transaction();try{erQuery($db,'UPDATE er_display_settings SET completed_seconds=?,updated_by=?,updated_at=NOW() WHERE settings_id=1',[$seconds,$actor]);erAudit($db,$actor,'ER display retention changed',['completed_seconds'=>$seconds]);$db->commit();}catch(Throwable $e){$db->rollback();throw $e;}
}
function erIssueDevice(mysqli $db,int $actor,string $label): string {
    if(!erStaffAllowed('edit')||$actor!==(int)($_SESSION['user_id']??0))throw new DomainException('Display authorization is not permitted.');
    $label=trim($label);if($label===''||mb_strlen($label)>80)throw new DomainException('Enter a display-device label, up to 80 characters.');
    $token=bin2hex(random_bytes(32));$db->begin_transaction();
    try{erQuery($db,'INSERT INTO er_display_devices(token_hash,label,issued_by,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 12 HOUR))',[hash('sha256',$token),$label,$actor]);erAudit($db,$actor,'ER display authorized',['device_id'=>$db->insert_id,'label'=>$label]);$db->commit();return $token;}catch(Throwable $e){$db->rollback();throw $e;}
}
function erAuthorizeDevice(mysqli $db,string $token,bool $poll): bool {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
    $hash=hash('sha256',$token);$r=erQuery($db,'SELECT device_id FROM er_display_devices WHERE token_hash=? AND revoked_at IS NULL AND expires_at>NOW()',[$hash])->get_result()->fetch_assoc();
    if(!$r)return false;
    if($poll){$s=erQuery($db,'UPDATE er_display_devices SET last_poll_at=NOW(6) WHERE device_id=? AND (last_poll_at IS NULL OR last_poll_at<=DATE_SUB(NOW(6),INTERVAL 2 SECOND))',[$r['device_id']]);if($s->affected_rows!==1)throw new DomainException('Display polling rate exceeded.');}
    return true;
}
function erRevokeDevice(mysqli $db,int $id,int $actor): void {
    if(!erStaffAllowed('edit')||$actor!==(int)($_SESSION['user_id']??0))throw new DomainException('Device revocation is not permitted.');
    $db->begin_transaction();try{erQuery($db,'UPDATE er_display_devices SET revoked_at=NOW() WHERE device_id=? AND (issued_by=? OR ?=1)',[$id,$actor,isSuperAdmin()?1:0]);erAudit($db,$actor,'ER display revoked',['device_id'=>$id]);$db->commit();}catch(Throwable $e){$db->rollback();throw $e;}
}
