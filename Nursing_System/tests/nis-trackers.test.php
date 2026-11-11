<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
session_save_path(sys_get_temp_dir());$_SERVER['REQUEST_METHOD']='CLI';
require __DIR__.'/../includes/auth.php';require __DIR__.'/../includes/nis-tracker.php';
require __DIR__.'/../includes/nursing_reports.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$conn=new mysqli('127.0.0.1','root','',null,33317);$db='nis_tracker_test_'.bin2hex(random_bytes(5));$conn->query("CREATE DATABASE `$db`");$conn->select_db($db);$GLOBALS['conn']=$conn;$checks=0;
function trackerCheck(bool $value,string $message):void{global $checks;if(!$value)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function trackerActor(string $role,string $unit):void{$_SESSION=['user_id'=>1,'role'=>$role,'department'=>$unit,'first_name'=>'Synthetic'];}
try {
    $conn->query('CREATE TABLE role_permissions(role VARCHAR(50),module_name VARCHAR(100),can_view INT,can_create INT,can_edit INT,can_delete INT,can_approve INT,can_print INT)');
    $insert=$conn->prepare('INSERT INTO role_permissions VALUES(?,?,?,?,?,?,?,?)');
    foreach(defaultRolePermissions() as $role=>$modules)foreach($modules as $module=>$permissions){$values=array_merge([$role,$module],array_values($permissions));$insert->bind_param('ssiiiiii',...$values);$insert->execute();}
    $conn->query("CREATE TABLE patients(patient_id INT PRIMARY KEY,patient_code VARCHAR(30),permanent_patient_id VARCHAR(30),first_name VARCHAR(50),last_name VARCHAR(50),status VARCHAR(40),current_bed INT,ward_department VARCHAR(30),admission_date DATETIME) ENGINE=InnoDB");
    $conn->query("CREATE TABLE beds(bed_id INT PRIMARY KEY,bed_number VARCHAR(30),room_number VARCHAR(30),bed_type VARCHAR(30),status ENUM('available','occupied','maintenance','reserved'),patient_id INT NULL) ENGINE=InnoDB");
    $conn->query("CREATE TABLE patient_movements(id INT PRIMARY KEY,patient_id INT,encounter_id VARCHAR(30),source_department VARCHAR(60),destination_department VARCHAR(60),status VARCHAR(30),movement_date_time DATETIME,created_at DATETIME) ENGINE=InnoDB");
    foreach(['micu_records','nicu_records'] as $table)$conn->query("CREATE TABLE $table(id INT PRIMARY KEY,patient_id INT,encounter_id VARCHAR(30),status VARCHAR(30),bed_no VARCHAR(30))");
    foreach(['opd_records','or_schedule'] as $table)$conn->query("CREATE TABLE $table(id INT PRIMARY KEY,patient_id INT,encounter_id VARCHAR(30),status VARCHAR(30))");
    $conn->query('CREATE TABLE delivery_room_records(id INT PRIMARY KEY,patient_id INT,encounter_id VARCHAR(30))');
    $conn->query('CREATE TABLE bed_capacity_settings(area_name VARCHAR(80),total_bed_capacity INT,status VARCHAR(30))');
    $conn->query('CREATE TABLE users(user_id INT PRIMARY KEY,username VARCHAR(50),first_name VARCHAR(50),last_name VARCHAR(50),role VARCHAR(50),department VARCHAR(50),status VARCHAR(30),is_active INT)');
    foreach(['ER','OPD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU','NSO','ER'] as $offset=>$unit){$id=$offset+1;$role=$id===9?'CHIEF_NURSE':($id===10?'SECRETARY':'STAFF_NURSE');$conn->query("INSERT INTO users VALUES($id,'synthetic$id','Synthetic','Test','$role','$unit','ACTIVE',1)");}
    $conn->query("INSERT INTO bed_capacity_settings VALUES('ICU / MICU',3,'ACTIVE'),('NICU',2,'ACTIVE')");
    $conn->query("INSERT INTO beds VALUES(1,'ER-01-B1','ER-01','Emergency','occupied',1),(2,'201-B1','201','General','occupied',3),(3,'WARD-03-B1','WARD-03','General','occupied',2),(4,'WARD-03-B2','WARD-03','General','occupied',9),(5,'ER-01-B2','ER-01','Emergency','occupied',11),(6,'ER-02-B1','ER-02','Emergency','available',NULL),(7,'ER-02-B2','ER-02','Emergency','reserved',NULL),(8,'201-B2','201','General','maintenance',NULL),(9,'WARD-03-B3','WARD-03','General','available',10)");
    for($id=1;$id<=12;$id++){
        $bed=[1=>1,2=>3,3=>2,9=>4,10=>9,11=>5][$id]??'NULL';
        $specialty=$id===3?'OB':'MEDICAL';$status=in_array($id,[2,3,4,5,9,10],true)?'admit_inpatient':'treatment';
        $conn->query("INSERT INTO patients VALUES($id,'ENC-$id','HN-$id','Patient$id','Synthetic','$status',$bed,'$specialty','2026-10-26 08:00:00')");
    }
    $conn->query("INSERT INTO micu_records VALUES(1,4,'ENC-4','ACTIVE','MICU-01')");
    $conn->query("INSERT INTO nicu_records VALUES(1,5,'ENC-5','ACTIVE','NICU-01')");
    $conn->query("INSERT INTO opd_records VALUES(1,6,'ENC-6','ACTIVE')");
    $conn->query("INSERT INTO or_schedule VALUES(1,7,'ENC-7','IN OR')");
    $conn->query("INSERT INTO delivery_room_records VALUES(1,8,'ENC-8')");
    $conn->query("INSERT INTO patient_movements VALUES(1,9,'ENC-9','ER','WARD_3F','RECEIVED','2026-10-26 09:00:00','2026-10-26 08:30:00'),(2,11,'ENC-11','ER','WARD_3F','PENDING',NULL,'2026-10-26 09:30:00'),(3,9,'OTHER-ENCOUNTER','ER','NICU','RECEIVED','2026-10-26 11:00:00','2026-10-26 10:30:00')");
    $conn->query("ALTER TABLE patients ADD middle_name VARCHAR(50) DEFAULT '',ADD final_diagnosis VARCHAR(100),ADD updated_at DATETIME DEFAULT '2026-10-26 08:00:00',ADD queue_number INT DEFAULT 1,ADD arrival_time DATETIME DEFAULT '2026-10-26 08:00:00',ADD triage_date DATE DEFAULT '2026-10-26',ADD triage_time TIME DEFAULT '08:00:00',ADD queue_date DATE DEFAULT '2026-10-26'");
    $conn->query('CREATE TABLE triage(patient_id INT,triage_level VARCHAR(30),chief_complaint VARCHAR(100))');
    $conn->query('CREATE TABLE patient_tracking(tracking_id INT,patient_id INT,action VARCHAR(100),status_ended_at DATETIME,created_at DATETIME)');
    $conn->query('CREATE TABLE ward_discharge_tat_records(patient_id INT,ward VARCHAR(80),date_mgh_ordered DATE,time_mgh_order TIME,chart_to_phic_signature_at DATETIME,signed_soa_received_at DATETIME,disposition_at DATETIME,tagubilin_receiver_signature VARCHAR(100),patient_survey_signature VARCHAR(100),actual_discharge_tat_minutes INT,remarks VARCHAR(100))');
    $conn->query("INSERT INTO ward_discharge_tat_records VALUES(3,'2nd Floor Ward','2026-10-26','08:00:00','2026-10-26 09:00:00','2026-10-26 10:00:00','2026-10-26 11:00:00','Synthetic','Synthetic',180,'Synthetic'),(2,'3rd Floor Ward','2026-10-26','08:00:00','2026-10-26 09:00:00','2026-10-26 10:00:00','2026-10-26 16:00:00','Synthetic','Synthetic',480,'Synthetic')");
    trackerActor('STAFF_NURSE','ER');$data=nisPatientTrackerData($conn,[]);$ids=array_column($data['rows'],'patient_id');
    trackerCheck(in_array(1,$ids)&&in_array(9,$ids)&&!in_array(10,$ids),'ER sees current patients and outgoing encounter, not unrelated receiving-unit patients');
    $outgoing=nisPatientTrackerData($conn,['patient_id'=>9]);$row=$outgoing['rows'][0];
    trackerCheck($row['department']==='WARD_3F'&&$row['bed_number']==='WARD-03-B2'&&!$row['can_open_chart'],'outgoing location remains trackable without receiving chart access');
    trackerCheck(count($outgoing['history'])===1&&$outgoing['history'][0]['encounter_id']==='ENC-9','movement history cannot mix encounters');
    trackerCheck(!array_key_exists('remarks',$outgoing['history'][0]),'cross-unit history excludes clinical handover text');
    trackerCheck(!nisTrackerCanOpenChart($conn,9)&&nisTrackerCanOpenChart($conn,1),'backend chart access enforces current department');
    trackerCheck(nisPatientTrackerData($conn,['q'=>'HN-9'])['total']===1,'Hospital Number search');
    trackerCheck(nisPatientTrackerData($conn,['q'=>'Patient1'])['total']>=1,'name search');
    trackerCheck(nisPatientTrackerData($conn,['q'=>"%' OR 1=1 --"])['total']===0,'search metacharacters remain literal data');
    trackerCheck(nisPatientTrackerData($conn,['department'=>'WARD_3F','transfer_status'=>'RECEIVED'])['total']===1,'department and transfer filters preserve outgoing scope');
    trackerCheck(nisPatientTrackerData($conn,['patient_id'=>11])['rows'][0]['department']==='ER','pending transfer does not move patient location');
    foreach(['ER'=>1,'WARD_3F'=>2,'WARD_2F'=>3,'ICU'=>4,'NICU'=>5,'OPD'=>6,'OR'=>7,'LR_DR'=>8] as $unit=>$id){
        trackerActor('STAFF_NURSE',$unit);$result=nisPatientTrackerData($conn,['patient_id'=>$id]);
        trackerCheck(count($result['rows'])===1&&$result['rows'][0]['department']===$unit,'assigned department scope '.$unit);
    }
    trackerActor('STAFF_NURSE','ER');$beds=nisBedTrackerData($conn,[]);
    trackerCheck($beds['summary']['total']===14,'bed total uses registered beds and configured critical slots');
    trackerCheck($beds['summary']['reserved']===1&&$beds['reservation_supported'],'reserved beds come from actual ledger');
    trackerCheck($beds['summary']['out_of_service']===1,'out-of-service beds counted');
    trackerCheck(!str_contains(json_encode($beds),'HN-')&&!str_contains(json_encode($beds),'Patient9'),'cross-unit bed availability contains no patient identifiers');
    $ward=nisBedTrackerData($conn,['department'=>'WARD_3F']);
    trackerCheck($ward['summary']['available']===0&&$ward['summary']['occupied']===3,'stale available flag never advertises an assigned bed as free');
    $conn->query("INSERT INTO nicu_records VALUES(2,12,'ENC-12','ACTIVE',NULL)");
    $nicu=nisBedTrackerData($conn,['department'=>'NICU']);trackerCheck($nicu['summary']['unverified']===1&&$nicu['summary']['available']===0,'unassigned critical records make remaining capacity unverified');
    trackerCheck(!$nicu['reservation_supported']&&!$nicu['out_of_service_supported'],'unrecorded critical-unit reservation and service states stay unknown');
    trackerActor('CHIEF_NURSE','NSO');trackerCheck(count(nisTrackerDepartments())===8&&nisPatientTrackerData($conn,[])['total']===12,'authorized leadership can track multiple units');
    $conn->query("INSERT INTO patient_movements VALUES(4,9,'ENC-9','WARD','OTHER FACILITY','COMPLETED','2026-10-25 09:00:00','2026-10-25 08:30:00')");
    trackerCheck(count(nisPatientTrackerData($conn,['patient_id'=>9])['history'])===2,'authorized leadership retains legacy generic movement history');
    trackerCheck(!hasPermission('patients','edit','ER'),'leadership title does not grant clinical editing');
    trackerActor('HEAD_NURSE','WARD_2F');trackerCheck(nursingReportCanAccess('patient_census')&&!nursingReportCanAccess('er_statistics'),'head nurse report types stay department-scoped');
    $census=nursingReportConfig('patient_census',$conn,'2026-10-26 00:00:00','2026-10-26 23:59:59');
    trackerCheck(array_column($census['rows'],'Department')===['PEDIA','OB','NEWBORN'],'department report limits census rows without changing formulas');
    $tat=nursingReportConfig('ward_tat',$conn,'2026-10-26 00:00:00','2026-10-26 23:59:59');
    trackerCheck($tat['summary']['Total TAT Records']===1&&$tat['summary']['Discharge Time < 6 Hours']===1,'ward discharge report scope preserves original duration calculations');
    $report=nursingReportConfig('bed_tracker',$conn,'2026-10-26 00:00:00','2026-10-26 23:59:59');
    $names=array_values(array_filter(array_column($report['rows'],'Patient Name')));
    trackerCheck(count($names)===1&&str_contains($names[0],'Patient3'),'bed report cannot expose another department clinical identifiers');
    trackerActor('SUPERVISOR_NURSE','ER');trackerCheck(nursingReportCanAccess('er_statistics')&&!nursingReportCanAccess('or_safe'),'assigned supervisor report types stay department-scoped');
    trackerActor('STAFF_NURSE','MICU');trackerCheck(nisTrackerDepartments()===['ICU'],'official MICU alias resolves to existing ICU ID');
    trackerActor('SECRETARY','ER');
    try{nisPatientTrackerData($conn,[]);trackerCheck(false,'ungranted tracker restriction');}catch(DomainException $e){trackerCheck(true,'ungranted tracker permission denied');}
    // Render the actual shared sidebar for every assigned account using actual role permissions.
    foreach(['ER','OPD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU'] as $unit){
        $env=array_merge(getenv(),['NIS_TEST_DATABASE'=>$db,'NIS_TEST_ROLE'=>'STAFF_NURSE','NIS_TEST_DEPARTMENT'=>$unit]);
        $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/nis-sidebar-fixture.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);fclose($pipes[0]);$html=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        trackerCheck(proc_close($process)===0&&$errors==='','sidebar fixture renders '.$unit);
        trackerCheck(substr_count($html,'class="has-submenu ')===1,'one assigned clinical module '.$unit);
        foreach([$unit==='ER'?'ER Patient Tracker':'Patient Tracker','Bed Tracker','Settings','Back to Main Menu'] as $label)trackerCheck(substr_count($html,'title="'.$label.'"')===1,'single shared link '.$label.' '.$unit);
        trackerCheck(!str_contains($html,'Monthly Duty Scheduling')&&!str_contains($html,'title="Patient Care"')&&!str_contains($html,'title="Reports"'),'no obsolete global menus '.$unit);
        file_put_contents(__DIR__.'/../outputs/nis-navigation-20261026/sidebar-'.$unit.'.html',$html);
    }
    foreach(['HEAD_NURSE'=>1,'SUPERVISOR_NURSE'=>1,'ASSISTANT_CHIEF_NURSE'=>1,'CHIEF_NURSE'=>8,'SUPER_ADMIN'=>8] as $role=>$expected){
        $unit=$expected===8?'NSO':'ER';$env=array_merge(getenv(),['NIS_TEST_DATABASE'=>$db,'NIS_TEST_ROLE'=>$role,'NIS_TEST_DEPARTMENT'=>$unit]);
        $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/nis-sidebar-fixture.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);fclose($pipes[0]);$html=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        trackerCheck(proc_close($process)===0&&$errors===''&&substr_count($html,'class="has-submenu ')===$expected,'leadership respects existing department scope '.$role);
        trackerCheck(substr_count($html,'title="Reports"')===($expected===8?1:0),'one centralized Reports entry only when authorized '.$role);
    }
    trackerActor('SUPER_ADMIN','NSO');
    file_put_contents(__DIR__.'/../outputs/nis-navigation-20261026/patient-data.json',json_encode(nisPatientTrackerData($conn,['patient_id'=>9])));
    file_put_contents(__DIR__.'/../outputs/nis-navigation-20261026/bed-data.json',json_encode(nisBedTrackerData($conn,[])));
    $httpUser='nis_http_'.substr($db,-10);$httpPassword=bin2hex(random_bytes(16));
    $conn->query("CREATE USER '$httpUser'@'localhost' IDENTIFIED BY '$httpPassword'");
    $conn->query("GRANT SELECT ON `$db`.* TO '$httpUser'@'localhost'");
    $env=array_merge(getenv(),['NIS_TEST_DATABASE'=>$db,'NIS_TEST_TOKEN'=>bin2hex(random_bytes(16)),
        'NURSING_APP_ENV'=>'development','NURSING_DB_HOST'=>'127.0.0.1','NURSING_DB_PORT'=>'33317','NURSING_DB_NAME'=>$db,'NURSING_DB_USER'=>$httpUser,'NURSING_DB_PASSWORD'=>$httpPassword]);
    $pipes=[];$process=proc_open(['node',__DIR__.'/nis-tracker-http.test.cjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);echo $output,$errors;
    trackerCheck($exit===0,'authenticated HTTP and responsive tracker browser suite');
    echo "$checks tracker and navigation checks passed; synthetic database only.\n";
}finally{
    if(isset($httpUser)&&preg_match('/^nis_http_[a-f0-9]{10}$/D',$httpUser))$conn->query("DROP USER IF EXISTS '$httpUser'@'localhost'");
    if(preg_match('/^nis_tracker_test_[a-f0-9]{10}$/D',$db))$conn->query("DROP DATABASE `$db`");
}
