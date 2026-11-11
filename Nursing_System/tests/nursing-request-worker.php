<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(getenv('REQUEST_TEST_PORT'))ini_set('mysqli.default_port',(string)(int)getenv('REQUEST_TEST_PORT'));
$database=$argv[1]??'';if(!preg_match('/^nursync_management_test_[a-f0-9]{12}$/D',$database))exit(2);
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$conn=new mysqli('localhost','root','',$database);session_save_path(sys_get_temp_dir());$_SERVER['REQUEST_METHOD']='CLI';require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/nursing_requests.php';
try{$actor=exActor($conn,(int)($argv[3]??0));if(($argv[2]??'')==='submit')$r=nrSave($conn,$actor,['kind'=>'OFF','dates'=>['2035-05-10'],'reason'=>'Concurrent synthetic submission','submission_key'=>$argv[4]??'']);elseif(($argv[2]??'')==='decide')$r=nrDecide($conn,$actor,['id'=>(int)($argv[4]??0),'version'=>(int)($argv[5]??0),'decision'=>'approve','remarks'=>'Concurrent synthetic review']);else exit(2);echo json_encode(['result'=>'SUCCESS','id'=>$r['id']]);}catch(Throwable $e){echo json_encode(['result'=>$e->getCode()===409?'CONFLICT':'ERROR','message'=>$e->getMessage()]);}
