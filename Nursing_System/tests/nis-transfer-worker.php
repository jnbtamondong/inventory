<?php
if(PHP_SAPI!=='cli')exit;
if(!preg_match('/^nis_test_[a-f0-9]{10}$/D',$argv[1]??''))exit(2);
require __DIR__.'/../includes/patient_helpers.php';
require __DIR__.'/../includes/nis-workflow.php';
function hasPermission($module,$action,$department=null):bool{return $department==='WARD_3F';}
$_SESSION=['user_id'=>2];
$conn=new mysqli('127.0.0.1','root','',$argv[1],33317);
try{nisTransitionMovement($conn,(int)$argv[2],'RECEIVED','Synthetic concurrent arrival',3);echo 'RECEIVED';}
catch(DomainException $e){echo 'BLOCKED';}
