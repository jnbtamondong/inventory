<?php
if(PHP_SAPI!=='cli'||!preg_match('/^nursync_exchange_test_[a-f0-9]{12}$/D',$argv[1]??''))exit(2);
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
if(getenv('REQUEST_TEST_PORT'))ini_set('mysqli.default_port',(string)(int)getenv('REQUEST_TEST_PORT'));
$db=new mysqli(getenv('EXCHANGE_TEST_HOST')?:'localhost',getenv('EXCHANGE_TEST_USER')?:'root',getenv('EXCHANGE_TEST_PASSWORD')?:'',$argv[1]);
$_SERVER['REQUEST_METHOD']='CLI';session_save_path(sys_get_temp_dir());require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/duty_exchange.php';
$actor=exActor($db,1);$input=['partner_id'=>2,'assignment_a'=>$argv[2].':1:2035-01-10:10','assignment_b'=>$argv[2].':2:2035-01-10:10','reason'=>'Synthetic concurrent request','submission_key'=>bin2hex(random_bytes(24))];
try{exSaveRequest($db,$actor,$input);echo 'CREATED';}catch(Throwable $error){echo 'BLOCKED';}
