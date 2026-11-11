<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo json_encode(['ok'=>false,'errors'=>['_form'=>'Use the inbound form to save.']]);exit;}
if(!shared_has_permission('cssr_inventory','create')||!shared_has_permission('cssr_inventory','view')||!can_manage()){http_response_code(403);echo json_encode(['ok'=>false,'errors'=>['_form'=>'You do not have permission to receive supplies.']]);exit;}
require_once __DIR__.'/../includes/inbound-service.php';
try{
    $input=json_decode((string)($_POST['payload']??''),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($input))throw new DomainException('The form contents could not be read.');
    $user=current_user();$result=cssrSaveInbound(db(),$input,(int)$user['id'],$user['full_name'],is_cssr_inventory_admin());
    if($result['ok']){
        $message=$result['completed']?'Inbound '.$result['inbound_no'].' completed. Inventory updated.':'Draft saved. Stock has not changed.';
        if(empty($result['already_completed']))log_activity($result['completed']?'CREATE':'SAVE DRAFT','Inbound Supplies',$result['inbound_no']??('Draft '.$result['id']));
        $result['message']=$message;set_flash('success',$message);
    }else http_response_code(422);
    echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(DomainException|JsonException $error){http_response_code(422);echo json_encode(['ok'=>false,'errors'=>['_form'=>$error->getMessage()]]);}
catch(Throwable $error){log_error($error,'INBOUND');http_response_code(500);echo json_encode(['ok'=>false,'errors'=>['_form'=>'The save could not be confirmed. Your entries are preserved. Retry using this form to avoid duplicate receipt records.']]);}
