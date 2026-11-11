<?php
require_once '../config/db.php';require_once '../includes/auth.php';require_once '../includes/nis-tracker.php';
header('Content-Type: application/json; charset=utf-8');
$type=$_GET['tracker']??'patients';
if(!in_array($type,['patients','beds'],true)){http_response_code(400);echo json_encode(['error'=>'Unknown tracker.']);exit;}
requireApiPermission($type==='patients'?'patients':'beds','view');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo json_encode(['error'=>'This tracker is read-only.']);exit;}
try{echo json_encode($type==='patients'?nisPatientTrackerData($conn,$_GET):nisBedTrackerData($conn,$_GET),JSON_THROW_ON_ERROR);}
catch(Throwable $e){http_response_code(503);error_log('NIS tracker API failed; code '.(int)$e->getCode());echo json_encode(['error'=>'Tracker information is temporarily unavailable.']);}
