<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../includes/legacy-layout.php';
require __DIR__.'/../includes/submission.php';
require __DIR__.'/../includes/design-navigation.php';
function e($v){return cssrUiEscape($v);}function csrf_field(){return '<input type="hidden" name="csrf_token" value="preview-token">';}function can_manage(){return true;}
$selectedRequest=['id'=>1,'status'=>'Pending','request_no'=>'REQ-2026-10-0001','department_name'=>'EMERGENCY ROOM','requested_by'=>'Sample Staff','request_date'=>'2026-10-08','remarks'=>'','approved_by'=>''];
$selectedDetails=[['id'=>1,'item_id'=>47,'item_name'=>'3 WAY STOPCOCK','item_code'=>'CSSR-000047','quantity_requested'=>1,'current_stock'=>0,'item_image'=>'','unit'=>'PIECE','quantity_approved'=>0,'quantity_released'=>0]];
$availableLotsByItem=[];$requestFormState=[];$embedRequest=false;$requestReturnPage='request_queue.php';$canAddToSubmittedRequest=true;
$storage=str_contains($argv[1] ?? '', 'storage');
$s=file_get_contents(__DIR__.'/../pages/'.($storage?'storage_requests.php':'item_requests.php'));
cssrLegacyStart(['title'=>'Request Details','active'=>'issuance','navigation'=>cssrDashboardNavigation(true),'user'=>'Sample Admin','role'=>'Super Admin','initials'=>'SA','settings'=>'#','bodyClass'=>'cu-embedded request-detail-open','embedded'=>true]);
$items=[['id'=>575,'item_code'=>'CSSR-000202','item_name'=>'BIOPSY NEEDLE G14'],['id'=>99,'item_code'=>'CSSR-99','item_name'=>null],['id'=>1,'item_code'=>'CSSR-1','item_name'=>'ALCOHOL'],['id'=>2,'item_code'=>'CSSR-2','item_name'=>'ALCOHOL'],['id'=>3,'item_code'=>'CSSR-3','item_name'=>'ALCOHOL ISOPROPHYL 70% 1 LITER']];
for($i=4;$i<30;$i++)$items[]=['id'=>$i,'item_code'=>'CSSR-'.$i,'item_name'=>'ALCOHOL SAMPLE '.$i];
$header=file_get_contents(__DIR__.'/../includes/header.php');$hstart=strpos($header,'<div class="confirm-modal"');echo substr($header,$hstart);
function current_user(){return ['id'=>1,'full_name'=>'Sample Admin'];}function cssrTransactionToday(){return '2026-10-08';}function cssrCanEditTransactionDate($u){return true;}
$requestQueueOnly=false;$canCreateRequest=true;$canViewAllRequests=true;$currentDepartmentId=1;$departments=[];$previewRequestNo='REQ-PREVIEW';$clearRequestCartAfterSubmit=false;$printPromptRequestId=0;$requestPopupError='';$embedRequest=true;
if(str_starts_with($argv[1] ?? '', 'catalog-')){
 $items=[];
 for($i=1;$i<=50;$i++)$items[]=['id'=>$i,'item_code'=>'CODE-'.$i,'item_name'=>sprintf('Item %03d',$i),'storage_location'=>'STORAGE ROOM 1','current_stock'=>10];
 $catalogData=['has_more'=>true,'next_offset'=>50];
}
if($storage){
 $selectedRequest+=['from_location'=>'STORAGE ROOM 1','to_location'=>'CSSR'];
 $selectedDetails[0]+=['item_description'=>'3 WAY STOPCOCK','lot_number'=>'','available_stock'=>0,'requested_qty'=>1,'released_qty'=>0];
 $selectedLotsByItem=[];$createdRequest=null;$storageReturnPage='request_queue.php';$embedStorageRequest=true;$locations=['STORAGE ROOM 1'];
 foreach($items as &$item){$item['storage_location']='STORAGE ROOM 1';$item['current_stock']=10;}unset($item);
}
$start=strpos($s,$storage?'<?php if ($embedStorageRequest): ?>':'<?php if ($embedRequest): ?>',strpos($s,"include __DIR__ . '/../includes/header.php';"));$end=strpos($s,"<?php include __DIR__ . '/../includes/footer.php'; ?>",$start);eval('?>'.substr($s,$start,$end-$start));cssrLegacyEnd();
