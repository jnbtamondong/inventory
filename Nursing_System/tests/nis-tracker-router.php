<?php
// Local synthetic test server only; never a deployed authentication endpoint.
if(PHP_SAPI!=='cli-server'||!preg_match('/^nis_tracker_test_[a-f0-9]{10}$/D',getenv('NIS_TEST_DATABASE')?:'')){http_response_code(404);exit;}
$root=realpath(__DIR__.'/..');$url=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($url==='/__test_login'||$url==='/__test_session'){
    if(!hash_equals(getenv('NIS_TEST_TOKEN'),$_SERVER['HTTP_X_TEST_TOKEN']??'')){http_response_code(403);exit;}
    require $root.'/config/db.php';require $root.'/includes/auth.php';
    if($url==='/__test_login'){
        $id=(int)($_GET['id']??1);$statement=$conn->prepare('SELECT * FROM users WHERE user_id=?');$statement->bind_param('i',$id);$statement->execute();$user=$statement->get_result()->fetch_assoc();
        if(!$user){http_response_code(404);exit;}
        session_regenerate_id(true);$_SESSION=['user_id'=>$id,'username'=>$user['username'],'first_name'=>$user['first_name'],'role'=>$user['role'],'department'=>$user['department']];
    }
    header('Content-Type: application/json');echo json_encode(['user_id'=>$_SESSION['user_id']??null,'csrf_token'=>csrfToken()]);exit;
}
$relative=preg_replace('#^/Nursing_System/#','',$url);$file=realpath($root.'/'.$relative);
if(!$file||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)){http_response_code(404);exit;}
if(!str_ends_with($file,'.php')){
    $extension=pathinfo($file,PATHINFO_EXTENSION);header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','png'=>'image/png','svg'=>'image/svg+xml'][$extension]??'application/octet-stream'));readfile($file);exit;
}
if(!str_starts_with($relative,'pages/')&&!str_starts_with($relative,'api/')){http_response_code(404);exit;}
$_SERVER['SCRIPT_NAME']=$_SERVER['PHP_SELF']='/Nursing_System/'.$relative;chdir(dirname($file));require $file;
