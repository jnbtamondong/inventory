<?php
// Deliberately unavailable under Apache and against application databases.
if(PHP_SAPI!=='cli-server'||!preg_match('/^nursync_tracking_test_[a-f0-9]{12}$/D',getenv('REQUEST_TEST_DATABASE')?:'')||getenv('REQUEST_TEST_DATABASE')!==getenv('NURSING_DB_NAME')||strlen(getenv('REQUEST_TEST_TOKEN')?:'')<32){http_response_code(404);exit;}
$root=realpath(__DIR__.'/..');$url=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($url==='/__login'){
 if(!hash_equals(getenv('REQUEST_TEST_TOKEN'),$_SERVER['HTTP_X_TEST_TOKEN']??'')){http_response_code(403);exit;}
 require $root.'/config/db.php';require $root.'/includes/auth.php';require $root.'/includes/request_tracking.php';$actor=exActor($conn,(int)($_GET['id']??1));session_regenerate_id(true);$_SESSION=$actor;header('Content-Type: application/json');echo json_encode(['csrf'=>csrfToken()]);exit;
}
$relative=preg_replace('#^/Nursing_System/#','',$url);$file=realpath($root.'/'.$relative);
if(!$file||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)){http_response_code(404);exit;}
if(!str_ends_with($file,'.php')){if(!str_starts_with($relative,'public/')){http_response_code(404);exit;}header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','png'=>'image/png','svg'=>'image/svg+xml','woff2'=>'font/woff2'][pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream'));readfile($file);exit;}
if(!in_array($relative,['pages/my_requests.php','api/nursing_requests.php','api/duty_exchange.php'],true)){http_response_code(404);exit;}
$_SERVER['SCRIPT_NAME']=$_SERVER['PHP_SELF']='/Nursing_System/'.$relative;chdir(dirname($file));require $file;
