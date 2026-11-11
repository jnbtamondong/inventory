<?php
require_once __DIR__.'/../config/db.php';require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/er-live-tracker.php';
requireLogin();header('Content-Type: application/json; charset=utf-8');
if(!erStaffAllowed()){http_response_code(403);echo '{"error":"ER monitoring is not permitted."}';exit;}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo '{"error":"Use the staff workspace for changes."}';exit;}
foreach($_GET as $value)if(!is_scalar($value)){http_response_code(400);echo '{"error":"Invalid filters."}';exit;}
try{
    if(!erTrackerReady($conn))throw new RuntimeException('Schema unavailable.');
    $data=erStaffDataset($conn,$_GET);$data['can_edit']=erStaffAllowed('edit');$data['can_correct']=$data['can_edit']&&hasRole(['DOCTOR','SUPER_ADMIN']);
    $data['can_print']=hasPermission('patients','print','ER');
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    echo json_encode($data,JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(503);error_log('ER staff tracker read failed; code '.(int)$e->getCode());echo '{"error":"ER tracker temporarily unavailable."}';}
