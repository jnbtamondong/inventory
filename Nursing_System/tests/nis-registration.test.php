<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
session_save_path(sys_get_temp_dir());$_SERVER['REQUEST_METHOD']='CLI';
require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/patient-registration.php';require_once __DIR__.'/../includes/nis-tracker.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$conn=new mysqli('127.0.0.1','root','',null,33317);$db='nis_tracker_test_'.bin2hex(random_bytes(5));$conn->query("CREATE DATABASE `$db`");$conn->select_db($db);$GLOBALS['conn']=$conn;$checks=0;
function regCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function regBlocked(callable $fn,string $label):void{try{$fn();}catch(DomainException $e){regCheck(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function regInput(array $replace=[]):array{$key=bin2hex(random_bytes(32));$_SESSION['registration_keys'][$key]=time();return array_replace(['request_key'=>$key,'first_name'=>'Synthetic','last_name'=>'Registration','middle_name'=>'','date_of_birth'=>'1994-06-12','gender'=>'Female','arrival_at'=>date('Y-m-d\TH:i'),'chief_complaint'=>'Synthetic test complaint','phone'=>'N/A'], $replace);}
$testUser='reg_'.bin2hex(random_bytes(5));$testPass=bin2hex(random_bytes(16));
try{
 // Reproduce audited column types/defaults in an isolated synthetic database, never import the destructive base schema.
 $schema=json_decode(ltrim(file_get_contents(__DIR__.'/../outputs/nis-reengineering/schema-audit.json'),"\xEF\xBB\xBF"),true,512,JSON_THROW_ON_ERROR);
 foreach($schema as $table=>$meta){if(isset($meta['missing']))continue;$columns=[];
  foreach($meta['columns'] as $c){$def='`'.$c['Field'].'` '.$c['Type'].($c['Null']==='YES'?' NULL':' NOT NULL');if($c['Default']!==null)$def.=' DEFAULT '.(strtolower($c['Default'])==='current_timestamp()'?'CURRENT_TIMESTAMP':"'".$conn->real_escape_string($c['Default'])."'");if($c['Extra']==='auto_increment')$def.=' AUTO_INCREMENT';if($c['Key']==='PRI')$def.=' PRIMARY KEY';$columns[]=$def;}
  $conn->query("CREATE TABLE `$table` (".implode(',',$columns).') ENGINE=InnoDB');
 }
 $conn->query('ALTER TABLE patients ADD UNIQUE KEY test_encounter_code(patient_code)');
 if(!isset($schema['opd_records']))$conn->query('CREATE TABLE opd_records(id INT PRIMARY KEY,patient_id INT,encounter_id VARCHAR(30),status VARCHAR(30))');
 $conn->query('CREATE TABLE bed_capacity_settings(area_name VARCHAR(80),total_bed_capacity INT,status VARCHAR(30))');
 $conn->query('CREATE TABLE provinces(province_id VARCHAR(20) PRIMARY KEY,province_name VARCHAR(100))');
 $conn->query('CREATE TABLE municipalities(municipality_id VARCHAR(20) PRIMARY KEY,municipality_name VARCHAR(100),province_id VARCHAR(20))');
 $conn->query('CREATE TABLE barangays(barangay_id VARCHAR(20) PRIMARY KEY,barangay_name VARCHAR(100),municipality_id VARCHAR(20))');
 $conn->query('CREATE TABLE audit_logs(audit_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,username VARCHAR(100),role VARCHAR(50),department VARCHAR(50),ip_address VARCHAR(45),action VARCHAR(100),user_agent TEXT,details TEXT)');
 $conn->query('CREATE TABLE users(user_id INT PRIMARY KEY,username VARCHAR(50),first_name VARCHAR(50),last_name VARCHAR(50),role VARCHAR(50),department VARCHAR(50),status VARCHAR(30),is_active INT)');
 $conn->query("INSERT INTO users VALUES(1,'synthetic1','Synthetic','ER','STAFF_NURSE','ER','ACTIVE',1),(2,'synthetic2','Synthetic','OPD','STAFF_NURSE','OPD','ACTIVE',1),(3,'synthetic3','Synthetic','Doctor','DOCTOR','ER','ACTIVE',1),(9,'synthetic9','Synthetic','Chief','CHIEF_NURSE','NSO','ACTIVE',1)");
 $conn->query("INSERT INTO provinces VALUES(1,'Synthetic Province A'),(2,'Synthetic Province B')");
 $conn->query("INSERT INTO municipalities(municipality_id,province_id,municipality_name) VALUES(10,1,'Synthetic City A'),(20,2,'Synthetic City B')");
 $conn->query("INSERT INTO barangays(barangay_id,municipality_id,barangay_name) VALUES(100,10,'Synthetic Barangay A'),(200,20,'Synthetic Barangay B')");
 $insert=$conn->prepare('INSERT INTO role_permissions(role,module_name,can_view,can_create,can_edit,can_delete,can_approve,can_print) VALUES(?,?,?,?,?,?,?,?)');
 foreach(defaultRolePermissions() as $role=>$modules)foreach($modules as $module=>$permissions){$values=array_merge([$role,$module],array_values($permissions));$insert->bind_param('ssiiiiii',...$values);$insert->execute();}
 $migration=file_get_contents(__DIR__.'/../database/migrations/20261026_patient_registration.up.sql');$conn->multi_query($migration);do{if($r=$conn->store_result())$r->free();}while($conn->more_results()&&$conn->next_result());
 if(getenv('NIS_TEST_ER_LIVE')==='1'){
  $conn->multi_query(file_get_contents(__DIR__.'/../database/migrations/20261026_er_live_tracker.up.sql'));do{if($r=$conn->store_result())$r->free();}while($conn->more_results()&&$conn->next_result());
  $conn->query("INSERT INTO users VALUES(11,'synthetic11','Synthetic','Admin','SUPER_ADMIN','NSO','ACTIVE',1)");
 }
 $_SESSION=['user_id'=>1,'role'=>'STAFF_NURSE','department'=>'ER','first_name'=>'Synthetic'];
 regCheck(registrationReady($conn),'additive registration migration');
 $input=regInput();$saved=registrationSave($conn,$input,1);$id=(int)$saved['patient_id'];$hn=$saved['permanent_patient_id'];
 regCheck($hn==='PT-000001'&&str_starts_with($saved['patient_code'],'JVGH-'),'existing Hospital Number and encounter numbering preserved');
 regCheck((int)$conn->query('SELECT COUNT(*) n FROM nis_patient_identities')->fetch_assoc()['n']===1,'one permanent identity created');
 regCheck(registrationSave($conn,$input,1)===$saved,'double submission returns original encounter');
 $p=$conn->query("SELECT * FROM patients WHERE patient_id=$id")->fetch_assoc();regCheck($p['status']==='triage'&&$p['current_bed']===null&&$p['ward_department']==='ER PENDING','registration does not assign bed or count as ward admission');
 regCheck((int)$conn->query('SELECT COUNT(*) n FROM triage')->fetch_assoc()['n']===0,'registration does not duplicate vital signs');
 regCheck(count(registrationSearch($conn,['permanent_patient_id'=>$hn]))===1,'Hospital Number identification search');
 regCheck(count(registrationSearch($conn,['first_name'=>'Synthetic','last_name'=>'Registration','date_of_birth'=>'1994-06-12']))===1,'name and date-of-birth search');
 regCheck(registrationSearch($conn,['first_name'=>"%' OR 1=1 --"])===[],'prepared literal identification search');
 regCheck(!array_key_exists('chief_complaint',registrationSearch($conn,['permanent_patient_id'=>$hn])[0]),'identification search excludes clinical history');
 $data=nisPatientTrackerData($conn,['patient_id'=>$id]);regCheck($data['rows'][0]['department']==='ER'&&$data['rows'][0]['admission_status']==='Not currently admitted','new registration appears in authorized Patient Tracker');
 regCheck(registrationValidate($input)['age']==calculateAge('1994-06-12'),'server age calculation');
 regBlocked(fn()=>registrationSave($conn,regInput(['first_name'=>'']),1),'required identification validated');
 regBlocked(fn()=>registrationSave($conn,regInput(['date_of_birth'=>'2026-02-31']),1),'invalid calendar date rejected');
 regBlocked(fn()=>registrationSave($conn,regInput(['phone'=>'invalid']),1),'invalid contact rejected');
 regBlocked(fn()=>registrationSave($conn,regInput(['arrival_at'=>'2099-01-01T12:00']),1),'future arrival rejected');
 regBlocked(fn()=>registrationSave($conn,regInput(),1),'possible duplicate requires verification');
 $continue=regInput(['source_patient_id'=>$id]);$continued=registrationSave($conn,$continue,1);regCheck((int)$continued['patient_id']===$id,'existing active ER encounter continued');
 $conn->query("UPDATE patients SET status='discharge' WHERE patient_id=$id");
 regCheck((int)registrationSave($conn,$continue,1)['patient_id']===$id,'continued request stays idempotent after discharge');
 $returned=registrationSave($conn,regInput(['source_patient_id'=>$id]),1);regCheck($returned['permanent_patient_id']===$hn&&(int)$returned['patient_id']!==$id&&$returned['patient_code']!==$saved['patient_code'],'returning patient gets new encounter with same Hospital Number');
 regCheck((int)$conn->query('SELECT COUNT(*) n FROM nis_patient_identities')->fetch_assoc()['n']===1,'returning encounter reuses permanent identity');
 $returnId=(int)$returned['patient_id'];$conn->query("UPDATE patients SET status='admit_inpatient' WHERE patient_id=$returnId");
 regBlocked(fn()=>registrationSave($conn,regInput(['source_patient_id'=>$returnId]),1),'active receiving-unit encounter cannot be recreated in ER');$conn->query("UPDATE patients SET status='triage' WHERE patient_id=$returnId");
 $conn->query("UPDATE patients SET permanent_patient_id=NULL WHERE patient_id=$returnId");
 regBlocked(fn()=>registrationSave($conn,regInput(['source_patient_id'=>$returnId]),1),'legacy missing Hospital Number requires review without renumbering');registrationQuery($conn,'UPDATE patients SET permanent_patient_id=? WHERE patient_id=?',[$hn,$returnId]);
 $separate=registrationSave($conn,regInput(['duplicate_verified'=>'1','duplicate_reason'=>'Identity documents verified: separate synthetic person.']),1);regCheck($separate['permanent_patient_id']!==$hn,'verified legitimate same-name individual allowed');
 $separateId=(int)$separate['patient_id'];registrationQuery($conn,'UPDATE patients SET permanent_patient_id=?,first_name=? WHERE patient_id=?',[$hn,'Conflicting',$separateId]);
 regBlocked(fn()=>registrationSave($conn,regInput(['source_patient_id'=>$id]),1),'conflicting legacy Hospital Number never silently merges');registrationQuery($conn,'UPDATE patients SET permanent_patient_id=?,first_name=? WHERE patient_id=?',[$separate['permanent_patient_id'],'Synthetic',$separateId]);
 regCheck(!str_contains($conn->query('SELECT demographics FROM nis_patient_identities LIMIT 1')->fetch_assoc()['demographics'],'chief_complaint'),'permanent identity stores demographics separately from encounter complaint');
 $_SESSION['role']='CHIEF_NURSE';$_SESSION['department']='NSO';[$historySql,$historyParams]=registrationHistoryScope(['patient_id'=>$id,'permanent_patient_id'=>$hn]);$historyIds=array_column(registrationQuery($conn,'SELECT p.patient_id FROM patients p WHERE '.$historySql,$historyParams)->get_result()->fetch_all(MYSQLI_ASSOC),'patient_id');
 regCheck(in_array($id,$historyIds)&&in_array($returnId,$historyIds)&&!in_array($separateId,$historyIds),'authorized history reuses Hospital Number and excludes legitimate same-name individuals');$_SESSION['role']='STAFF_NURSE';$_SESSION['department']='ER';
 $conn->query("UPDATE patients SET status='admit_inpatient' WHERE patient_id=$returnId");[$historySql,$historyParams]=registrationHistoryScope(['patient_id'=>$returnId,'permanent_patient_id'=>$hn]);$historyIds=array_column(registrationQuery($conn,'SELECT p.patient_id FROM patients p WHERE '.$historySql,$historyParams)->get_result()->fetch_all(MYSQLI_ASSOC),'patient_id');regCheck(!in_array($returnId,$historyIds),'identity reuse cannot expose receiving-department clinical history');$conn->query("UPDATE patients SET status='triage' WHERE patient_id=$returnId");
 $conn->query("UPDATE patients SET status='discharge' WHERE patient_id=$separateId");registrationQuery($conn,"INSERT INTO opd_records(id,patient_id,encounter_id,status) VALUES(1,?,?,'ACTIVE')",[$separateId,$separate['patient_code']]);
 regBlocked(fn()=>registrationSave($conn,regInput(['source_patient_id'=>$separateId]),1),'active OPD ledger is respected despite legacy discharged patient status');$conn->query('DELETE FROM opd_records WHERE id=1');$conn->query("UPDATE patients SET status='triage' WHERE patient_id=$separateId");
 $conn->query("INSERT INTO patients(permanent_patient_id,patient_code,first_name,last_name,middle_name,date_of_birth,gender,admission_date,arrival_time,status,ward_department) VALUES('LEGACY-HN','LEGACY-ENC','Legacy','Synthetic','','1980-01-01','Other',NOW(),NOW(),'triage','ER PENDING')");$legacyId=(int)$conn->insert_id;
 $legacy=registrationSave($conn,regInput(['source_patient_id'=>$legacyId]),1);regCheck((int)$legacy['patient_id']===$legacyId&&$legacy['patient_code']==='LEGACY-ENC','legacy active ER encounter is linked without replacing its encounter ID');regCheck((int)registrationQuery($conn,'SELECT COUNT(*) n FROM nis_registrations WHERE patient_id=?',[$legacyId])->get_result()->fetch_assoc()['n']===1,'legacy continuation links the encounter to the permanent identity');
 try{$conn->query("INSERT INTO nis_patient_identities(hospital_number,demographics,created_by) VALUES('$hn','{}',1)");throw new RuntimeException('Expected uniqueness failure');}catch(mysqli_sql_exception $e){regCheck($e->getCode()===1062,'database enforces unique permanent Hospital Number');}
 $arrival=date('Y-m-d\TH:i',time()-600);registrationCorrectArrival($conn,(int)$returned['patient_id'],['arrival_at'=>$arrival,'correction_reason'=>'Verified actual arrival from synthetic log.'],1);
 regBlocked(fn()=>registrationCorrectArrival($conn,$returnId,['arrival_at'=>$arrival],1),'arrival correction requires a reason');
 regCheck($conn->query("SELECT event FROM nis_registration_audit ORDER BY audit_id DESC LIMIT 1")->fetch_assoc()['event']==='Arrival corrected','arrival correction audited');
 $counts=$conn->query('SELECT (SELECT COUNT(*) FROM patients) p,(SELECT COUNT(*) FROM nis_patient_identities) i')->fetch_assoc();
 $conn->query("CREATE TRIGGER fail_registration_audit BEFORE INSERT ON nis_registration_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
 try{registrationSave($conn,regInput(['first_name'=>'Rollback','last_name'=>'RollbackTest']),1);throw new RuntimeException('Expected rollback');}catch(mysqli_sql_exception $e){regCheck($e->getCode()===1644,'audit error fails registration');}
 regCheck($counts===$conn->query('SELECT (SELECT COUNT(*) FROM patients) p,(SELECT COUNT(*) FROM nis_patient_identities) i')->fetch_assoc(),'audit failure rolls back identity and encounter');$conn->query('DROP TRIGGER fail_registration_audit');
 $conn->query("CREATE TRIGGER fail_main_audit BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic main audit failure'");
 try{registrationSave($conn,regInput(['first_name'=>'Rollback','last_name'=>'MainAudit']),1);throw new RuntimeException('Expected main audit rollback');}catch(mysqli_sql_exception $e){regCheck($e->getCode()===1644,'main security audit failure rejects registration');}
 regCheck($counts===$conn->query('SELECT (SELECT COUNT(*) FROM patients) p,(SELECT COUNT(*) FROM nis_patient_identities) i')->fetch_assoc(),'main audit failure rolls back identity, encounter and registration audit');$conn->query('DROP TRIGGER fail_main_audit');
 $_SESSION['department']='OPD';regBlocked(fn()=>registrationSave($conn,regInput(['first_name'=>'Unauthorized']),1),'other department cannot register ER encounter');regCheck(count(registrationSearch($conn,['permanent_patient_id'=>$hn],'OPD'))===1,'identification search reusable by authorized department');
 $_SESSION['role']='CHIEF_NURSE';$_SESSION['department']='NSO';regBlocked(fn()=>registrationSave($conn,regInput(),1),'view-only leadership cannot register');$_SESSION['role']='STAFF_NURSE';$_SESSION['department']='ER';
 if(getenv('NIS_TEST_ER_LIVE')==='1')require __DIR__.'/er-live-tracker-scenarios.php';
 // Actual HTTP session/CSRF and responsive browser validation use a disposable database-specific login.
 $conn->query("CREATE USER '$testUser'@'127.0.0.1' IDENTIFIED BY '$testPass'");$conn->query("GRANT ALL ON `$db`.* TO '$testUser'@'127.0.0.1'");
 foreach(['HOST'=>'127.0.0.1','PORT'=>'33317','NAME'=>$db,'USER'=>$testUser,'PASSWORD'=>$testPass] as $key=>$value)putenv('NURSING_DB_'.$key.'='.$value);
 putenv('NIS_TEST_DATABASE='.$db);putenv('NIS_TEST_TOKEN='.bin2hex(random_bytes(20)));
 putenv('NIS_ER_PDF_TEST_NO_SANDBOX=1');
 passthru('node "'.__DIR__.'/nis-registration-http.test.cjs"',$status);regCheck($status===0,'authenticated registration HTTP and responsive browser checks');
 if(getenv('NIS_TEST_ER_LIVE')==='1'){passthru('node "'.__DIR__.'/er-live-tracker-http.test.cjs"',$status);regCheck($status===0,'ER staff and kiosk HTTP, privacy and TV browser checks');}
 echo "Registration tests: $checks passed\n";
}finally{
 if(preg_match('/^nis_tracker_test_[a-f0-9]{10}$/D',$db))$conn->query("DROP DATABASE `$db`");
 $conn->query("DROP USER IF EXISTS '$testUser'@'127.0.0.1'");
}
