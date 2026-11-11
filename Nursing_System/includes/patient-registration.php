<?php
/** Identity service. Legacy patients rows are encounter snapshots, never merged or renumbered. */
require_once __DIR__.'/patient_helpers.php';
require_once __DIR__.'/er-live-tracker.php';

function registrationQuery(mysqli $db, string $sql, array $params = []): mysqli_stmt {
    $s=$db->prepare($sql);
    if (!$s) throw new RuntimeException('Registration query unavailable.');
    if ($params) $s->bind_param(str_repeat('s',count($params)),...$params);
    if (!$s->execute()) throw new RuntimeException('Registration operation failed.');
    return $s;
}
function registrationReady(mysqli $db): bool {
    foreach (['patients','audit_logs','nis_patient_identities','nis_registrations','nis_registration_audit','nis_registration_requests'] as $table) {
        if (!registrationQuery($db,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND ENGINE='InnoDB'",[$table])->get_result()->fetch_assoc()) return false;
    }
    return true;
}
function registrationCanSearch(string $department): bool {
    return hasPermission('patients','create',$department);
}
function registrationSearch(mysqli $db, array $filter, string $department='ER'): array {
    if (!registrationCanSearch($department)) throw new DomainException('Patient identification search is not permitted.');
    $clauses=[];$values=[];
    foreach (['permanent_patient_id','first_name','middle_name','last_name','date_of_birth'] as $key) {
        $value=trim((string)($filter[$key]??''));
        if ($value==='') continue;
        if (strlen($value)>100) throw new DomainException('Search value is too long.');
        if ($key==='date_of_birth') {$clauses[]='p.date_of_birth=?';$values[]=$value;}
        else {$clauses[]="p.$key LIKE ? ESCAPE '='";$values[]='%'.str_replace(['=','%','_'],['==','=%','=_'],$value).'%';}
    }
    if (!$clauses) return [];
    // Identification only: no chart links, diagnoses, status, contact or clinical history.
    return registrationQuery($db,'SELECT p.patient_id,p.permanent_patient_id,p.first_name,p.middle_name,p.last_name,p.date_of_birth,p.gender FROM patients p WHERE '.implode(' AND ',$clauses).' AND NOT EXISTS (SELECT 1 FROM patients newer WHERE newer.permanent_patient_id=p.permanent_patient_id AND newer.patient_id>p.patient_id) ORDER BY p.last_name,p.first_name LIMIT 30',$values)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function registrationValidate(array $input): array {
    $d=[];
    $limits=['first_name'=>50,'last_name'=>50,'middle_name'=>50,'suffix'=>20,'gender'=>20,'civil_status'=>20,'religion'=>50,'phone'=>20,'house_no_street'=>100,'barangay_name'=>100,'municipality_name'=>100,'province_name'=>100,'emergency_contact'=>100,'emergency_phone'=>20,'relationship'=>50,'chief_complaint'=>2000,'referred_by'=>150,'duplicate_reason'=>500];
    foreach($limits as $key=>$max){
        if(!is_scalar($input[$key]??'')) throw new DomainException('Invalid field value.');
        $d[$key]=trim((string)($input[$key]??''));
        if(mb_strlen($d[$key])>$max) throw new DomainException('Please shorten '.str_replace('_',' ',$key).'.');
    }
    foreach(['first_name','last_name','chief_complaint'] as $key) if($d[$key]==='') throw new DomainException('First name, last name and chief complaint are required.');
    $dob=(string)($input['date_of_birth']??'');$date=DateTimeImmutable::createFromFormat('!Y-m-d',$dob);
    if(!$date||$date->format('Y-m-d')!==$dob||$date>new DateTimeImmutable('today')||$date->diff(new DateTimeImmutable('today'))->y>130) throw new DomainException('Enter a valid date of birth.');
    $d['date_of_birth']=$dob;$d['age']=$date->diff(new DateTimeImmutable('today'))->y;
    if(!in_array($d['gender'],['Male','Female','Other'],true)) throw new DomainException('Select a valid sex.');
    if(!in_array($d['civil_status'],['','Single','Married','Widowed','Separated','Divorced'],true)) throw new DomainException('Select a valid civil status.');
    foreach(['phone','emergency_phone'] as $key) if($d[$key]!==''&&strtoupper($d[$key])!=='N/A'&&!preg_match('/^[0-9+()\s-]{7,20}$/',$d[$key])) throw new DomainException('Enter a valid contact number, leave it blank, or use N/A.');
    $arrival=(string)($input['arrival_at']??'');$dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$arrival);
    if(!$dt||$dt->format('Y-m-d\TH:i')!==$arrival||$dt>new DateTimeImmutable('+5 minutes')||$dt<$date) throw new DomainException('Enter a valid arrival date and time, no later than now.');
    $d['arrival_at']=$dt->format('Y-m-d H:i:s');
    foreach(['medicolegal','ip','coordinated'] as $key) $d[$key]=($input[$key]??'')==='1'?1:0;
    return $d;
}
function registrationDuplicates(mysqli $db,array $d): array {
    return registrationQuery($db,'SELECT patient_id,permanent_patient_id,first_name,middle_name,last_name,date_of_birth,gender FROM patients WHERE (LOWER(TRIM(first_name))=LOWER(?) AND LOWER(TRIM(last_name))=LOWER(?)) OR (date_of_birth=? AND LOWER(TRIM(last_name))=LOWER(?)) ORDER BY patient_id DESC LIMIT 30',[$d['first_name'],$d['last_name'],$d['date_of_birth'],$d['last_name']])->get_result()->fetch_all(MYSQLI_ASSOC);
}
function registrationAudit(mysqli $db,int $id,int $identity,int $user,string $event,array $details): void {
    registrationQuery($db,'INSERT INTO nis_registration_audit(patient_id,identity_id,user_id,event,details) VALUES(?,?,?,?,?)',[$id,$identity,$user,$event,json_encode($details,JSON_THROW_ON_ERROR)]);
    // Use the existing security audit stream as well; failure must roll back registration.
    registrationQuery($db,'INSERT INTO audit_logs(user_id,username,role,department,ip_address,action,user_agent,details) VALUES(?,?,?,?,?,?,?,?)',[$user,$_SESSION['username']??'',$_SESSION['role']??'',$_SESSION['department']??'',$_SERVER['REMOTE_ADDR']??'', 'Patient '.$event,substr($_SERVER['HTTP_USER_AGENT']??'',0,1000),json_encode(['patient_id'=>$id,'identity_id'=>$identity]+$details,JSON_THROW_ON_ERROR)]);
}
function registrationDemographics(array $data): string {
    return json_encode(array_intersect_key($data,array_flip(['first_name','last_name','middle_name','suffix','date_of_birth','gender','civil_status','religion','phone','house_no_street','barangay_name','municipality_name','province_name','emergency_contact','emergency_phone','relationship'])),JSON_THROW_ON_ERROR);
}
function registrationHistoryScope(array $patient): array {
    require_once __DIR__.'/nis-tracker.php';
    $where="((p.permanent_patient_id IS NOT NULL AND p.permanent_patient_id<>'' AND p.permanent_patient_id=?) OR p.patient_id=?)";
    $params=[$patient['permanent_patient_id']??'',(string)$patient['patient_id']];
    if(count(nisTrackerDepartments())!==count(nisTrackerUnits())){
        [$scope,$scopeParams]=nisTrackerClinicalScope('p');$where.=' AND '.$scope;$params=array_merge($params,$scopeParams);
    }
    return [$where,$params];
}
function registrationSave(mysqli $db,array $input,int $user): array {
    if($user!==(int)($_SESSION['user_id']??0)||!hasPermission('patients','create','ER')) throw new DomainException('You cannot register ER patients.');
    $key=(string)($input['request_key']??'');
    if(!preg_match('/^[a-f0-9]{64}$/',$key)||!isset($_SESSION['registration_keys'][$key])) throw new DomainException('This form has expired. Open registration again.');
    // Serialize the legacy MAX-based numbering scheme, duplicate review and active encounter check.
    $lock=registrationQuery($db,"SELECT GET_LOCK(CONCAT(DATABASE(),':registration'),10) acquired")->get_result()->fetch_assoc();
    if((int)$lock['acquired']!==1) throw new DomainException('Registration is busy. Please retry.');
    try {
        $db->begin_transaction();
        $prior=registrationQuery($db,'SELECT p.patient_id,p.patient_code,p.permanent_patient_id FROM nis_registration_requests r JOIN patients p ON p.patient_id=r.patient_id WHERE r.request_key=? AND r.user_id=?',[$key,$user])->get_result()->fetch_assoc();
        if($prior){if(erTrackerReady($db))$prior['public_tracking_number']=erTrackerEnroll($db,(int)$prior['patient_id'],$user);if(!$db->commit())throw new RuntimeException('Could not finish registration retry.');return $prior;}
        $sourceId=filter_var($input['source_patient_id']??0,FILTER_VALIDATE_INT);
        if($sourceId===false||$sourceId<0)throw new DomainException('Select a valid existing identity or New Patient.');
        $identity=null;
        if($sourceId){
            $source=registrationQuery($db,'SELECT * FROM patients WHERE patient_id=? FOR UPDATE',[$sourceId])->get_result()->fetch_assoc();
            if(!$source||empty($source['permanent_patient_id'])) throw new DomainException('This legacy record needs Hospital Number review before reuse. No number was changed.');
            $hn=$source['permanent_patient_id'];
            $conflict=registrationQuery($db,"SELECT patient_id FROM patients WHERE permanent_patient_id=? AND (NOT(first_name<=>?) OR NOT(last_name<=>?) OR NOT(middle_name<=>?) OR NOT(date_of_birth<=>?) OR NOT(gender<=>?)) LIMIT 1",[$hn,$source['first_name'],$source['last_name'],$source['middle_name'],$source['date_of_birth'],$source['gender']])->get_result()->fetch_assoc();
            if($conflict) throw new DomainException('This Hospital Number has conflicting identification details. Refer it for authorized identity review.');
            $identity=registrationQuery($db,'SELECT * FROM nis_patient_identities WHERE hospital_number=? FOR UPDATE',[$hn])->get_result()->fetch_assoc();
            $demographics=$identity?json_decode($identity['demographics'],true,512,JSON_THROW_ON_ERROR):$source;
            // Existing identity is authoritative; encounter inputs cannot rewrite it.
            $d=registrationValidate(array_merge($input,array_intersect_key($demographics,array_flip(['first_name','last_name','middle_name','suffix','date_of_birth','gender','civil_status','religion','phone','house_no_street','barangay_name','municipality_name','province_name','emergency_contact','emergency_phone','relationship']))));
            $active=registrationQuery($db,"SELECT p.patient_id,p.patient_code,p.permanent_patient_id,p.status,p.arrival_time,p.admission_date FROM patients p WHERE p.permanent_patient_id=? AND (p.status IS NULL OR p.status NOT IN ('discharge','discharged','referred','ama','hama','thoc','absconded','dama','doa','death','expired','er_death') OR EXISTS(SELECT 1 FROM opd_records o WHERE o.patient_id=p.patient_id AND o.encounter_id=p.patient_code AND o.status='ACTIVE') OR EXISTS(SELECT 1 FROM micu_records c WHERE c.patient_id=p.patient_id AND c.encounter_id=p.patient_code AND c.status='ACTIVE') OR EXISTS(SELECT 1 FROM nicu_records n WHERE n.patient_id=p.patient_id AND n.encounter_id=p.patient_code AND n.status='ACTIVE') OR EXISTS(SELECT 1 FROM or_schedule s WHERE s.patient_id=p.patient_id AND s.encounter_id=p.patient_code AND s.status IN ('IN OR','RECOVERY'))) ORDER BY p.patient_id DESC LIMIT 1 FOR UPDATE",[$hn])->get_result()->fetch_assoc();
            if($active){
                require_once __DIR__.'/nis-tracker.php';
                $location=registrationQuery($db,'SELECT t.department '.nisTrackerPatientFrom().' WHERE t.patient_id=?',[$active['patient_id']])->get_result()->fetch_assoc();
                if(($location['department']??'')!=='ER') throw new DomainException('This patient has an active encounter outside ER. Use the authorized transfer or receiving workflow.');
                if(!$identity){registrationQuery($db,'INSERT INTO nis_patient_identities(hospital_number,demographics,created_by) VALUES(?,?,?)',[$hn,registrationDemographics($d),$user]);$identity=['identity_id'=>$db->insert_id];}
                if(!registrationQuery($db,'SELECT registration_id FROM nis_registrations WHERE patient_id=?',[$active['patient_id']])->get_result()->fetch_assoc()){
                    $triage=registrationQuery($db,'SELECT chief_complaint,referred_by,medicolegal,ip,coordinated FROM triage WHERE patient_id=?',[$active['patient_id']])->get_result()->fetch_assoc();
                    $encounter=array_merge($d,$triage?:[]);
                    registrationQuery($db,'INSERT INTO nis_registrations(identity_id,patient_id,request_key,registered_by,arrival_at,chief_complaint,referred_by,medicolegal,ip,coordinated) VALUES(?,?,?,?,?,?,?,?,?,?)',[$identity['identity_id'],$active['patient_id'],$key,$user,$active['arrival_time']?:$active['admission_date'],$encounter['chief_complaint'],$encounter['referred_by'],$encounter['medicolegal'],$encounter['ip'],$encounter['coordinated']]);
                }
                registrationAudit($db,(int)$active['patient_id'],(int)$identity['identity_id'],$user,'Encounter continued',[]);
                registrationQuery($db,'INSERT INTO nis_registration_requests(request_key,user_id,patient_id) VALUES(?,?,?)',[$key,$user,$active['patient_id']]);
                $publicNumber=erTrackerReady($db)?erTrackerEnroll($db,(int)$active['patient_id'],$user,($input['public_excluded']??'')!=='1'):null;
                if(!$db->commit())throw new RuntimeException('Could not commit encounter continuation.');
                return ['patient_id'=>$active['patient_id'],'patient_code'=>$active['patient_code'],'permanent_patient_id'=>$active['permanent_patient_id'],'continued'=>true]+($publicNumber?['public_tracking_number'=>$publicNumber]:[]);
            }
        } else {
            $d=registrationValidate($input);
            if(registrationDuplicates($db,$d)&&($d['duplicate_reason']===''||($input['duplicate_verified']??'')!=='1')) throw new DomainException('Possible matches found. Review them and select the existing patient, or document verified separate identity.');
            $hn=generatePermanentPatientId($db);
            while(registrationQuery($db,'SELECT identity_id FROM nis_patient_identities WHERE hospital_number=?',[$hn])->get_result()->fetch_assoc()) {
                $hn='PT-'.str_pad((int)substr($hn,3)+1,6,'0',STR_PAD_LEFT);
            }
        }
        if(!$identity){registrationQuery($db,'INSERT INTO nis_patient_identities(hospital_number,demographics,created_by) VALUES(?,?,?)',[$hn,registrationDemographics($d),$user]);$identity=['identity_id'=>$db->insert_id];}
        $identityId=(int)$identity['identity_id'];$code=generatePatientCode($db);$queueDate=date('Y-m-d');$queue=generatePatientQueueNumber($db,$queueDate);
        $fields=['permanent_patient_id'=>$hn,'patient_code'=>$code,'queue_date'=>$queueDate,'queue_number'=>$queue];
        foreach(['first_name','last_name','middle_name','date_of_birth','age','gender','civil_status','religion','phone','house_no_street','barangay_name','municipality_name','province_name'] as $name) $fields[$name]=$d[$name];
        if($fields['civil_status']==='')$fields['civil_status']=null;
        // admission_date is the legacy visit timestamp; inpatient status/bed are never assigned here.
        $fields+=['ward_department'=>'ER PENDING','admission_date'=>$d['arrival_at'],'triage_date'=>substr($d['arrival_at'],0,10),'triage_time'=>substr($d['arrival_at'],11),'arrival_time'=>$d['arrival_at'],'status'=>'triage'];
        registrationQuery($db,'INSERT INTO patients ('.implode(',',array_keys($fields)).') VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$id=(int)$db->insert_id;
        registrationQuery($db,'INSERT INTO nis_registrations(identity_id,patient_id,request_key,registered_by,arrival_at,chief_complaint,referred_by,medicolegal,ip,coordinated) VALUES(?,?,?,?,?,?,?,?,?,?)',[$identityId,$id,$key,$user,$d['arrival_at'],$d['chief_complaint'],$d['referred_by'],$d['medicolegal'],$d['ip'],$d['coordinated']]);
        registrationAudit($db,$id,$identityId,$user,'Registered',['hospital_number'=>$hn,'encounter_id'=>$code,'arrival_at'=>$d['arrival_at'],'recorded_at'=>date('Y-m-d H:i:s'),'duplicate_review'=>$d['duplicate_reason'],'source_patient_id'=>$sourceId?:null]);
        registrationQuery($db,'INSERT INTO nis_registration_requests(request_key,user_id,patient_id) VALUES(?,?,?)',[$key,$user,$id]);
        $publicNumber=erTrackerReady($db)?erTrackerEnroll($db,$id,$user,($input['public_excluded']??'')!=='1'):null;
        if(!$db->commit()) throw new RuntimeException('Could not commit registration.');
        return ['patient_id'=>$id,'patient_code'=>$code,'permanent_patient_id'=>$hn]+($publicNumber?['public_tracking_number'=>$publicNumber]:[]);
    } catch(Throwable $e){$db->rollback();throw $e;}
    finally{registrationQuery($db,"SELECT RELEASE_LOCK(CONCAT(DATABASE(),':registration'))");}
}
function registrationCorrectArrival(mysqli $db,int $patientId,array $input,int $user): void {
    if($user!==(int)($_SESSION['user_id']??0)||!hasPermission('patients','edit','ER')) throw new DomainException('Arrival correction is not permitted.');
    $reason=trim((string)($input['correction_reason']??''));
    if($reason===''||mb_strlen($reason)>500) throw new DomainException('Provide a correction reason, up to 500 characters.');
    require_once __DIR__.'/nis-tracker.php';
    $db->begin_transaction();
    try{
        $r=registrationQuery($db,'SELECT r.*,p.date_of_birth,p.first_name,p.last_name,p.gender FROM nis_registrations r JOIN patients p ON p.patient_id=r.patient_id WHERE r.patient_id=? FOR UPDATE',[$patientId])->get_result()->fetch_assoc();
        $location=registrationQuery($db,'SELECT t.department '.nisTrackerPatientFrom().' WHERE t.patient_id=?',[$patientId])->get_result()->fetch_assoc();
        if(!$r||($location['department']??'')!=='ER') throw new DomainException('Only current ER registrations can be corrected here.');
        $d=registrationValidate(array_merge($r,$input));
        registrationQuery($db,'UPDATE patients SET arrival_time=?,triage_date=?,triage_time=?,admission_date=? WHERE patient_id=?',[$d['arrival_at'],substr($d['arrival_at'],0,10),substr($d['arrival_at'],11),$d['arrival_at'],$patientId]);
        registrationQuery($db,'UPDATE nis_registrations SET arrival_at=? WHERE patient_id=?',[$d['arrival_at'],$patientId]);
        registrationAudit($db,$patientId,(int)$r['identity_id'],$user,'Arrival corrected',['previous'=>$r['arrival_at'],'corrected'=>$d['arrival_at'],'reason'=>$reason]);
        if(!$db->commit())throw new RuntimeException('Could not commit correction.');
    }catch(Throwable $e){$db->rollback();throw $e;}
}
