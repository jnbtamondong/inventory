<?php
// CLI-only fixture: render the actual view without database or POST handlers.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/legacy-layout.php';
require_once __DIR__.'/../includes/design-navigation.php';
function e($value){return cssrUiEscape($value);}
function csrf_field(){return '<input type="hidden" name="csrf_token" value="preview">';}
$locations=['CSSR','STORAGE ROOM 1','STORAGE ROOM 2','STORAGE ROOM 3'];
const PENDING_LOT_NUMBER='UNIDENTIFIED / PENDING';
$activeItems=[];$targets=[];$recentAdjustments=[];
foreach(['ADHESIVE TAPE, TRANSPARENT, PERFORATED, PLASTIC, 1 INCH x 10 YARDS','ANESTHESIA MASK ADULT (MEDIUM) BLUE','GAUZE SWAB 4x4'] as $i=>$name){
 $activeItems[]=['id'=>$i+1,'item_description'=>$name,'unit'=>'Piece','category'=>'Medical supplies'];
 $targets[]=['location'=>'CSSR','stock_type'=>'CSSR','record_id'=>$i+1,'item_id'=>$i+1,'item_name'=>$name,'lot_number'=>'LOT-'.($i+1),'expiry_date'=>'2028-01-01','quantity'=>100,'unit'=>'Piece','item_image'=>''];
}
$source=file_get_contents(__DIR__.'/../pages/stock_adjustment.php');
$start=strpos($source,"include __DIR__ . '/../includes/header.php';",strpos($source,'$recentAdjustments ='));
$start=strpos($source,'?>',$start)+2;
$template=str_replace("include __DIR__ . '/../includes/footer.php';",'cssrLegacyEnd();',substr($source,$start));
cssrLegacyStart(['title'=>'Physical Count Adjustment','description'=>'Sample records for visual review.','bodyClass'=>'page-stock_adjustment','home'=>'#','active'=>'stock-adjustments','navigation'=>cssrDashboardNavigation(true),'searchAction'=>'#','initials'=>'SS','user'=>'Sample Staff','role'=>'CSSR Staff','settings'=>'#','environment'=>'UI preview']);
eval('?>'.$template);
