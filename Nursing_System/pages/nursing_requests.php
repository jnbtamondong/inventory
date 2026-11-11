<?php
// Compatibility endpoint only. The retired request lists and approval UI cannot render.
require_once __DIR__.'/../config/db.php';require_once __DIR__.'/../includes/auth.php';requireLogin();
require_once __DIR__.'/../includes/request_tracking.php';require_once __DIR__.'/../includes/schedule_routes.php';
nrSchema($conn);$actor=exActor($conn,(int)$_SESSION['user_id']);
if(!naEligible($actor)&&naRole($actor)!=='SUPER_ADMIN'){http_response_code(403);exit('Nursing access required.');}
if($_SERVER['REQUEST_METHOD']==='POST'||isset($_GET['leave_balance'])){$_SERVER['PHP_SELF']='/Nursing_System/pages/request_application.php';require __DIR__.'/request_application.php';exit;}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
if(($_GET['view']??'')==='approvals'&&!naApprovalAccess($actor)){http_response_code(403);exit('Approval access denied.');}
if(($_GET['view']??'')==='history'&&naRole($actor)!=='SUPER_ADMIN'){http_response_code(403);exit('History access denied.');}
try{if(isset($_GET['id']))rtDetail($conn,$actor,($_GET['kind']??'OFF')==='LEAVE'?'LEAVE':'OFF',(int)$_GET['id']);$target=nsRequestRoute($_GET);}
catch(Throwable $e){http_response_code(403);exit('Request access denied.');}
header('Cache-Control: no-store');header('Location: '.$target,true,302);exit;
