<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$_SERVER['REQUEST_METHOD']='CLI';session_save_path(sys_get_temp_dir());require_once __DIR__.'/../includes/auth.php';
function exh($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$view=$argv[1]??'dashboard';$_SERVER['PHP_SELF']='/Nursing_System/pages/duty_exchange.php';$role=$view==='configuration'?'SUPER_ADMIN':($view==='detail'?'HEAD_NURSE':'STAFF_NURSE');$id=$view==='configuration'?6:($view==='detail'?3:1);
$_SESSION=['user_id'=>$id,'first_name'=>'Fixture','last_name'=>$view==='detail'?'Head':'Alpha','role'=>$role,'department'=>'ER'];
$actor=['user_id'=>$id,'name'=>'Fixture '.$_SESSION['last_name'],'employee_id'=>'A','role'=>$role,'canonical_role'=>$role,'unit'=>'ER'];
$access=['view'=>true,'submit'=>$role!=='SUPER_ADMIN','admin'=>$role==='SUPER_ADMIN'];
$employees=[['id'=>1,'name'=>'Fixture Alpha','employee_id'=>'A','department'=>'ER','role'=>'STAFF_NURSE'],['id'=>2,'name'=>'Fixture Beta','employee_id'=>'B','department'=>'ER','role'=>'STAFF_NURSE'],['id'=>3,'name'=>'Fixture Head','employee_id'=>'H','department'=>'ER','role'=>'HEAD_NURSE'],['id'=>4,'name'=>'Fixture Supervisor','employee_id'=>'S','department'=>'NSO','role'=>'SUPERVISOR_NURSE'],['id'=>5,'name'=>'Fixture Chief','employee_id'=>'C','department'=>'NSO','role'=>'CHIEF_NURSE']];$notices=[];
$boot=['view'=>$view,'id'=>$view==='detail'?12:0,'actor'=>['id'=>$id,'name'=>$actor['name'],'department'=>'ER','employee_id'=>'A','role'=>$role],'access'=>$access,'employees'=>$employees,'csrf'=>'synthetic-csrf','submission_key'=>str_repeat('a',48)];
$source=file_get_contents(__DIR__.'/../pages/duty_exchange.php');$source=substr($source,strpos($source,'<!doctype html>'));eval('?>'.$source);
