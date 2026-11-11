<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require __DIR__.'/../includes/patient_helpers.php';
require __DIR__.'/../includes/nis-workflow.php';
$allowed=['ER'];$_SESSION=['user_id'=>1];$checks=0;
function hasPermission($module,$action,$department=null): bool {global $allowed;return in_array($department,$allowed,true);}
function check($ok,$label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function blocked(callable $work,string $label): void {try{$work();}catch(DomainException $e){check(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
$conn=new mysqli('127.0.0.1','root','',null,33317);
$db='nis_test_'.bin2hex(random_bytes(5));$conn->query("CREATE DATABASE `$db`");$conn->select_db($db);
try {
    $conn->query("CREATE TABLE patients(patient_id INT PRIMARY KEY,patient_code VARCHAR(30),permanent_patient_id VARCHAR(30),ward_department VARCHAR(30),status VARCHAR(40),current_bed INT NULL) ENGINE=InnoDB");
    $conn->query("CREATE TABLE beds(bed_id INT PRIMARY KEY,bed_number VARCHAR(30),room_number VARCHAR(30),bed_type VARCHAR(30),status VARCHAR(30),patient_id INT NULL) ENGINE=InnoDB");
    $conn->query("CREATE TABLE patient_movements(id INT AUTO_INCREMENT PRIMARY KEY,patient_id INT,encounter_id VARCHAR(30),from_department VARCHAR(60),to_department VARCHAR(60),source_department VARCHAR(60),previous_department VARCHAR(60),current_department VARCHAR(60),destination_department VARCHAR(60),movement_type VARCHAR(80),reason TEXT,status VARCHAR(30),requested_by INT,accepted_by INT,movement_date_time DATETIME,remarks TEXT,created_by INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $conn->query("CREATE TABLE patient_tracking(tracking_id INT AUTO_INCREMENT PRIMARY KEY,patient_id INT,action VARCHAR(100),description TEXT,from_status VARCHAR(50),to_status VARCHAR(50),user_id INT) ENGINE=InnoDB");
    foreach(['micu_records','nicu_records','opd_records','delivery_room_records','or_schedule'] as $table) $conn->query("CREATE TABLE $table(id INT AUTO_INCREMENT PRIMARY KEY,patient_id INT,encounter_id VARCHAR(30),status VARCHAR(30)) ENGINE=InnoDB");
    $conn->query("INSERT INTO beds VALUES(1,'ER-01-B1','ER-01','Emergency','occupied',1),(2,'201-B1','201','General','available',NULL),(3,'WARD-03-B1','WARD-03','General','available',NULL),(4,'201-B2','201','General','maintenance',NULL)");
    $conn->query("INSERT INTO patients VALUES(1,'ENC-TEST-1','HN-KEEP-001','OB','treatment',1),(2,'ENC-TEST-2','HN-KEEP-002','MEDICAL','treatment',NULL)");
    $request=fn()=>nisRequestMovement($conn,1,'ENC-TEST-1','ER','WARD','ER TO WARD','Admission authorized','Synthetic handover');
    blocked(fn()=>nisRequestMovement($conn,1,'WRONG','ER','WARD','ER TO WARD','reason',''),'encounter mismatch rejected');
    $allowed=['WARD_2F'];blocked($request,'sending department permission required');$allowed=['ER'];$request();
    $movement=$conn->query('SELECT * FROM patient_movements')->fetch_assoc();$id=(int)$movement['id'];
    check($movement['destination_department']==='WARD_2F','generic ward resolved from specialty');
    check($conn->query('SELECT current_bed FROM patients WHERE patient_id=1')->fetch_assoc()['current_bed']==1,'request preserves sending location');
    blocked($request,'conflicting active transfer rejected');
    blocked(fn()=>nisTransitionMovement($conn,$id,'RECEIVED','',2),'cannot skip acceptance and handover');
    blocked(fn()=>nisTransitionMovement($conn,$id,'ACCEPTED',''),'sending unit cannot accept');
    $allowed=['WARD_2F'];nisTransitionMovement($conn,$id,'ACCEPTED','Bed team notified');
    blocked(fn()=>nisTransitionMovement($conn,$id,'ACCEPTED',''),'duplicate submission rejected');
    blocked(fn()=>nisTransitionMovement($conn,$id,'IN TRANSIT','handover'),'receiving unit cannot mark departure');
    $allowed=['ER'];blocked(fn()=>nisTransitionMovement($conn,$id,'IN TRANSIT',''),'handover required');nisTransitionMovement($conn,$id,'IN TRANSIT','Identity checked; sending handover recorded');
    $allowed=['WARD_2F'];blocked(fn()=>nisTransitionMovement($conn,$id,'RECEIVED','',3),'wrong ward bed rejected');
    blocked(fn()=>nisTransitionMovement($conn,$id,'RECEIVED','',4),'maintenance bed rejected');
    $conn->query("INSERT INTO patients VALUES(3,'ENC-3','HN-3','OB','admit_inpatient',2)");
    blocked(fn()=>nisTransitionMovement($conn,$id,'RECEIVED','',2),'stale free-bed flag cannot overwrite another encounter');
    $conn->query('UPDATE patients SET current_bed=NULL WHERE patient_id=3');
    $conn->query("INSERT INTO micu_records(patient_id,encounter_id,status) VALUES(1,'ENC-TEST-1','ACTIVE')");
    blocked(fn()=>nisTransitionMovement($conn,$id,'RECEIVED','',2),'critical ledger requires explicit transfer-out');$conn->query("UPDATE micu_records SET status='TRANSFERRED'");
    nisTransitionMovement($conn,$id,'RECEIVED','Identity and physical arrival confirmed',2);
    $patient=$conn->query('SELECT * FROM patients WHERE patient_id=1')->fetch_assoc();
    check($patient['patient_code']==='ENC-TEST-1'&&$patient['permanent_patient_id']==='HN-KEEP-001','Hospital Number and encounter unchanged');
    check($patient['current_bed']==2&&$patient['status']==='admit_inpatient','receiving assigns bed and admission status');
    check($conn->query('SELECT status,patient_id FROM beds WHERE bed_id=1')->fetch_assoc()===['status'=>'available','patient_id'=>null],'sending bed released');
    check($conn->query('SELECT patient_id FROM beds WHERE bed_id=2')->fetch_assoc()['patient_id']==1,'receiving bed occupied');
    check($conn->query('SELECT COUNT(*) n FROM patient_tracking')->fetch_assoc()['n']==4,'each movement step recorded');
    blocked(fn()=>nisTransitionMovement($conn,$id,'CANCELLED','late cancellation'),'received transfer is terminal');
    check(count(nisMovementTransitions('COMPLETED'))===0&&count(nisMovementTransitions('ARRIVED'))===0,'historical terminal states preserved');
    $allowed=['ER'];nisRequestMovement($conn,2,'ENC-TEST-2','ER','WARD_3F','ER TO WARD','Admission','');$id2=$conn->insert_id;
    $id2=(int)$conn->query('SELECT MAX(id) id FROM patient_movements')->fetch_assoc()['id'];
    $allowed=['WARD_3F'];blocked(fn()=>nisTransitionMovement($conn,$id2,'REJECTED',''),'rejection reason required');nisTransitionMovement($conn,$id2,'REJECTED','Receiving unit unable to accept');
    check($conn->query('SELECT current_bed FROM patients WHERE patient_id=2')->fetch_assoc()['current_bed']===null,'rejection does not move patient');
    $allowed=['ER'];nisRequestMovement($conn,2,'ENC-TEST-2','ER','OR','ER TO OR','Procedure authorized','');$id3=(int)$conn->query('SELECT MAX(id) id FROM patient_movements')->fetch_assoc()['id'];
    $allowed=['OR'];nisTransitionMovement($conn,$id3,'ACCEPTED','');$allowed=['ER'];nisTransitionMovement($conn,$id3,'IN TRANSIT','Handover');$allowed=['OR'];
    blocked(fn()=>nisTransitionMovement($conn,$id3,'RECEIVED',''),'specialty intake cannot be bypassed');
    check(nisDepartmentLabel('WARD_2F')==='OB/Pedia Ward'&&nisDepartmentLabel('WARD_3F')==='Medical/Surgery Ward','official ward names match preserved identifiers');
    // Competing receiving nurses must never occupy the same bed twice.
    $conn->query("INSERT INTO patients VALUES(4,'ENC-4','HN-4','MEDICAL','treatment',NULL),(5,'ENC-5','HN-5','MEDICAL','treatment',NULL)");
    $race=[];
    foreach([4,5] as $pid){
        $allowed=['ER'];nisRequestMovement($conn,$pid,'ENC-'.$pid,'ER','WARD_3F','ER TO WARD','Admission','');
        $mid=(int)$conn->query('SELECT MAX(id) id FROM patient_movements')->fetch_assoc()['id'];
        $allowed=['WARD_3F'];nisTransitionMovement($conn,$mid,'ACCEPTED','');$allowed=['ER'];nisTransitionMovement($conn,$mid,'IN TRANSIT','Sending handover');$race[]=$mid;
    }
    $workers=[];
    foreach($race as $mid){$pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/nis-transfer-worker.php',$db,(string)$mid],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes];}
    $results=[];foreach($workers as [$process,$pipes]){$results[]=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($process)===0&&$errors==='','concurrent worker completes without SQL error');}
    sort($results);check($results===['BLOCKED','RECEIVED'],'concurrent receivers produce exactly one successful bed assignment');
    check((int)$conn->query('SELECT COUNT(*) n FROM patients WHERE current_bed=3')->fetch_assoc()['n']===1,'one patient owns contested bed');
    // Existing transfer screens own outer transactions. The shared request must not commit them.
    $conn->query("INSERT INTO patients VALUES(6,'ENC-6','HN-6','MEDICAL','treatment',NULL)");
    $allowed=['ER'];$conn->begin_transaction();nisRequestMovement($conn,6,'ENC-6','ER','WARD_3F','ER TO WARD','Admission','');$conn->rollback();
    check((int)$conn->query('SELECT COUNT(*) n FROM patient_movements WHERE patient_id=6')->fetch_assoc()['n']===0,'outer transaction remains reversible');
    foreach(['up','down'] as $direction){
        $sql=file_get_contents(__DIR__.'/../database/migrations/20261026_nis_movement_indexes.'.$direction.'.sql');
        $conn->multi_query($sql);do{if($result=$conn->store_result())$result->free();}while($conn->more_results()&&$conn->next_result());
        check(true,'optional index migration '.$direction.' executes on synthetic schema');
    }
    echo "$checks checks passed. Synthetic database only.\n";
} finally {
    if (preg_match('/^nis_test_[a-f0-9]{10}$/D',$db)) $conn->query("DROP DATABASE `$db`");
}
