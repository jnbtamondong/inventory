<?php
require_once __DIR__.'/../includes/auth.php';require_login();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo json_encode(['ok'=>false,'errors'=>['_form'=>'Use the transfer form.']]);exit;}
if(!shared_has_permission('cssr_inventory','view')||!shared_has_permission('cssr_inventory','edit')){http_response_code(403);echo json_encode(['ok'=>false,'errors'=>['_form'=>'You do not have permission to transfer inventory.']]);exit;}
require_once __DIR__.'/../includes/storage-transfer.php';
try{
    $user=current_user();$result=cssrTransferToCssr(db(),$_POST,(int)$user['id'],$user['full_name']);
    if($result['ok']){
        $sourceLabel=cssrStorageLocations()[$result['source_location']];
        if(empty($result['replayed']))log_activity('TRANSFER',$sourceLabel,$result['reference'].' to CSSR');
        $result['message']='Transfer completed. '.$sourceLabel.' and CSSR stock have been updated.';set_flash('success',$result['message']);
    }else http_response_code(422);
    echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(DomainException $error){http_response_code(422);echo json_encode(['ok'=>false,'errors'=>['_form'=>$error->getMessage()]]);}
catch(Throwable $error){log_error($error,'STORAGE_TRANSFER');http_response_code(500);echo json_encode(['ok'=>false,'errors'=>['_form'=>'The transfer could not be confirmed. Your entries are preserved. Retry this form to avoid creating a duplicate transfer.']]);}
