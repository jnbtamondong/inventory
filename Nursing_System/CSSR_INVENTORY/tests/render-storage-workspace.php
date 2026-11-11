<?php
// Render production presentation with synthetic records; never run its database or POST handlers.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/legacy-layout.php';
require_once __DIR__.'/../includes/design-navigation.php';
require_once __DIR__.'/../../includes/cssr-access.php';
function e($value){return cssrUiEscape($value);}
function current_user(){return ['full_name'=>'Sample Staff','role'=>'CSSR_STAFF','department_name'=>'CSSR'];}
function csrf_field(){return '<input type="hidden" name="csrf_token" value="preview">';}
$page=($argv[1]??'')==='requests'?'storage_requests':'storage_masterlist';
$selectedRoom='STORAGE ROOM 1';$startDate='';$endDate='';$storageRooms=['CSSR','STORAGE ROOM 1','STORAGE ROOM 2','STORAGE ROOM 3'];$locations=array_slice($storageRooms,1);
$selectedRequest=null;$createdRequest=null;$embedStorageRequest=false;$previewRequestNo='SR-SAMPLE-001';$storageReturnPage='storage_requests.php';
$items=[];foreach(['ANESTHESIA MASK ADULT','ANESTHESIA MASK ADULT (MEDIUM) BLUE','GAUZE SWAB 4 × 4'] as $i=>$name)$items[]=['id'=>$i+1,'item_code'=>'CSSR-00'.($i+1),'item_name'=>$name,'item_description'=>'','unit'=>'Piece','brand'=>'Sample','category'=>'Medical supplies','item_image'=>'','storage_location'=>'STORAGE ROOM 1','current_stock'=>1000,'room_quantity'=>1000,'total_issued'=>25,'lot_count'=>2];
$totals=['item_count'=>3,'lot_count'=>6,'total_quantity'=>3000];
$source=file_get_contents(__DIR__.'/../pages/'.$page.'.php');$marker="include __DIR__ . '/../includes/header.php';";
$start=strpos($source,$marker);if($start===false)throw new RuntimeException('View not found');$start=strpos($source,'?>',$start)+2;
$template=substr($source,$start);$template=str_replace("include __DIR__ . '/../includes/footer.php';",'cssrLegacyEnd();',$template);
cssrLegacyStart(['title'=>$page==='storage_requests'?'Storage Requests':'All storage rooms','description'=>'Sample records for visual review.','bodyClass'=>'page-'.$page,'home'=>'../design-system/dashboard.php','active'=>$page==='storage_requests'?'storage-requests':'all-storage','navigation'=>cssrDashboardNavigation(true),'searchAction'=>'#','initials'=>'SS','user'=>'Sample Staff','role'=>'CSSR Staff','settings'=>'#','environment'=>'UI preview']);
eval('?>'.$template);
