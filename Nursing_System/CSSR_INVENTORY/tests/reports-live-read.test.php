<?php
// Explicit CLI-only, read-only integration check against the configured MySQL database.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/design-system.php';
require_once __DIR__.'/../includes/design-navigation.php';
require_once __DIR__.'/../includes/reports-data.php';
require_once __DIR__.'/../includes/reports-export.php';
require_once __DIR__.'/../includes/reports-view.php';
$checks=0;
function reportLiveCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
db()->exec('SET TRANSACTION READ ONLY');db()->beginTransaction();
try{
 foreach(cssrReportTypes() as $type=>$label){
  $data=cssrReportData(db(),['report'=>$type]);
  reportLiveCheck(!$data['errors'],'Filters: '.$type);
  reportLiveCheck(count($data['columns'])>=4,'Report columns: '.$type);
  ob_start();
  cssrRenderReports($data,['title'=>$label,'home'=>'#','active'=>cssrReportNavigationActive($type),'navigation'=>cssrDashboardNavigation(true),'searchAction'=>'#','initials'=>'QA','user'=>'Read-only check','role'=>'Preview','settings'=>'#']);
  $html=ob_get_clean();
  reportLiveCheck(str_contains($html,'<h1>'.cssrUiEscape($label).'</h1>'),'Report heading: '.$type);
  reportLiveCheck(str_contains($html,'rp-table'),'Report table: '.$type);
  reportLiveCheck(str_starts_with(cssrReportPdf($data),'%PDF-'),'PDF: '.$type);
  reportLiveCheck(str_starts_with(cssrReportXlsx($data),"PK\x03\x04"),'Excel: '.$type);
 }
 // Run only GET report queries before the shared header; never execute login or schema migrations.
 foreach(['stock_card','bin_card','monthly_report','reorder_alerts','expiry_monitoring'] as $page){
  (static function($page){
   $_SERVER['REQUEST_METHOD']='GET';$_GET=[];
   $source=file_get_contents(__DIR__.'/../pages/'.$page.'.php');
   $source=substr($source,5,strpos($source,"include __DIR__ . '/../includes/header.php';")-5);
   $source=preg_replace("~require_once __DIR__ \\. '/../includes/auth\\.php';~",'',$source);
   $source=str_replace('require_admin_or_cssr();','',$source);
   if($page==='reorder_alerts')$source=substr($source,0,strpos($source,'$out ='));
   eval($source);
  })($page);
  reportLiveCheck(true,'Legacy report query: '.$page);
 }
 echo "Passed $checks read-only MySQL report, render, export and legacy query checks. No records changed.\n";
}finally{if(db()->inTransaction())db()->rollBack();}
