<?php
// Compatibility endpoint only. Exchange tracking and approval live in My Requests.
require_once __DIR__.'/../config/db.php';require_once __DIR__.'/../includes/auth.php';requireLogin();
require_once __DIR__.'/../includes/request_tracking.php';require_once __DIR__.'/../includes/schedule_routes.php';
nrSchema($conn);$actor=exActor($conn,(int)$_SESSION['user_id']);$access=exAccess($conn,$actor);
if(!$access['view']){http_response_code(403);exit('Exchange access denied.');}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
$view=$_GET['view']??'dashboard';if(($view==='new'&&!$access['submit'])||($view==='configuration'&&!$access['admin'])||($view==='approvals'&&!naApprovalAccess($actor))){http_response_code(403);exit('Access denied.');}
try{if(isset($_GET['id']))rtDetail($conn,$actor,'EXCHANGE',(int)$_GET['id']);$target=nsRequestRoute($_GET,true);}
catch(Throwable $e){http_response_code(403);exit('Exchange access denied.');}
header('Cache-Control: no-store');header('Location: '.$target,true,302);exit;
