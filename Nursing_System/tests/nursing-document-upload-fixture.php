<?php
$fixtureMethod=$_SERVER['REQUEST_METHOD']??'CLI';$_SERVER['REQUEST_METHOD']='CLI';session_save_path(sys_get_temp_dir());require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/nursing_requests.php';$_SERVER['REQUEST_METHOD']=$fixtureMethod;
$database=PHP_SAPI==='cli'?($argv[2]??''):getenv('NURSING_UPLOAD_TEST_DATABASE');
if(!preg_match('/^nursync_upload_test_[a-f0-9]{12}$/D',$database))exit('Invalid disposable database');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$conn=new mysqli('localhost','root','');
if(PHP_SAPI==='cli'&&($argv[1]??'')==='setup'){
    $conn->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4");$conn->select_db($database);
    $conn->query('CREATE TABLE users(user_id INT PRIMARY KEY,employee_id VARCHAR(30),first_name VARCHAR(80),last_name VARCHAR(80),position VARCHAR(80),role VARCHAR(50),department VARCHAR(50),status VARCHAR(20),is_active TINYINT) ENGINE=InnoDB');
    foreach([1=>['NURSE','ER'],2=>['HEAD_NURSE','ER'],3=>['SUPERVISOR','NSO'],4=>['CHIEF_NURSE','NSO'],5=>['SUPER_ADMIN','NSO']] as $id=>[$role,$unit])nsScheduleQuery($conn,'INSERT INTO users VALUES(?,?,?,?,?,?,?,?,1)',[$id,'TEST-'.$id,'Upload','Fixture '.$id,$role,$role,$unit,'ACTIVE']);nrSchema($conn);
    $row=nrSave($conn,exActor($conn,1),['kind'=>'LEAVE','dates'=>['2036-01-10'],'leave_type'=>'VL','reason'=>'Synthetic upload test','submission_key'=>str_repeat('a',32)]);echo $row['id'];exit;
}
$conn->select_db($database);
if(PHP_SAPI==='cli'&&($argv[1]??'')==='cleanup'){
    foreach(exRows($conn,'SELECT storage_name FROM nursing_request_supporting_documents') as $document)if(preg_match('/^[a-f0-9]{48}$/D',$document['storage_name'])){ $path=__DIR__.'/../storage/nursing-request-documents/'.$document['storage_name'];if(is_file($path))unlink($path); }
    $conn->query("DROP DATABASE `$database`");exit;
}
if(PHP_SAPI==='cli')exit;
header('Content-Type: application/json');
if(($_SERVER['HTTP_X_FIXTURE_KEY']??'')!==getenv('NURSING_UPLOAD_TEST_KEY')){http_response_code(403);exit('{}');}
try{
    nrUploadDocument($conn,exActor($conn,(int)($_GET['actor']??1)),1,$_FILES['document']??[]);
    $document=exOne($conn,'SELECT * FROM nursing_request_supporting_documents ORDER BY id DESC LIMIT 1');$audit=exOne($conn,"SELECT * FROM nursing_service_request_events WHERE request_id=1 AND decision='DOCUMENT_UPLOADED' ORDER BY id DESC LIMIT 1");
    echo json_encode(['stored'=>is_file(__DIR__.'/../storage/nursing-request-documents/'.$document['storage_name']),'name'=>$document['original_name'],'audit_actor'=>(int)$audit['actor_id']]);
}catch(Throwable $error){http_response_code($error instanceof DomainException?403:422);echo json_encode(['error'=>$error->getMessage()]);}
