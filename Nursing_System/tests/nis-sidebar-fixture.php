<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$_SERVER['REQUEST_METHOD']='CLI';$_SERVER['PHP_SELF']='/Nursing_System/pages/dashboard.php';
session_save_path(sys_get_temp_dir());
require __DIR__.'/../includes/auth.php';
$role=getenv('NIS_TEST_ROLE')?:'SUPER_ADMIN';$department=getenv('NIS_TEST_DEPARTMENT')?:'NSO';
$_SESSION=['user_id'=>1,'role'=>$role,'department'=>$department,'first_name'=>'Synthetic'];
$testDatabase=getenv('NIS_TEST_DATABASE');
if($testDatabase){
    if(!preg_match('/^nis_tracker_test_[a-f0-9]{10}$/D',$testDatabase))exit(2);
    $conn=new mysqli('127.0.0.1','root','',$testDatabase,33317);$GLOBALS['conn']=$conn;
}
?><!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/Nursing_System/public/css/style.css"><link rel="stylesheet" href="/Nursing_System/public/css/dashboard.css"><link rel="stylesheet" href="/Nursing_System/public/css/module-theme.css"><script defer src="/Nursing_System/public/js/nursync-ui.js"></script></head><body class="module-ui"><nav class="navbar"><div class="navbar-brand">NURSYNC</div><div class="navbar-menu"></div></nav><div class="dashboard-container"><?php require __DIR__.'/../includes/nursing_sidebar.php'; ?><main class="main-content"><h1>Synthetic clinical workspace</h1></main></div><script src="/Nursing_System/public/js/sidebar.js"></script></body></html>
