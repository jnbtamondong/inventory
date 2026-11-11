<?php
require_once __DIR__.'/../design-system/reports-samples.php';require_once __DIR__.'/../includes/reports-data.php';require_once __DIR__.'/../includes/reports-export.php';
$checks=0;function check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$now=new DateTimeImmutable('2026-10-07 10:30:00',new DateTimeZone('Asia/Manila'));$db=cssrReportSamples($now);
$get=static fn($filters=[])=>cssrReportData($db,$filters,$now);
$base=$get();check($base['count']===80,'All posted receipts and issues, excluding void and prior year');
check($base['totals']['in']===7200&&$base['totals']['out']===2100,'Exact period totals');
check(count($base['chart'])===10&&$base['chart'][0]['in']===540&&$base['chart'][0]['out']===120,'Monthly chart values');
check($base['totals']['in']===array_sum(array_column($base['chart'],'in')),'In chart total matches');
check($base['totals']['out']===array_sum(array_column($base['chart'],'out')),'Out chart total matches');
check($get(['from'=>'2026-03-01','to'=>'2026-03-31'])['count']===8,'Dates select a calendar month');
check($get(['from'=>'2026-02-01','to'=>'2026-03-01'])['count']===16,'Inclusive date bounds');
check($get(['from'=>'2026-02-02','to'=>'2026-03-01'])['count']===8,'From date boundary');
check($get(['year'=>'2025','month'=>'13'])['count']===$base['count'],'Removed year/month parameters cannot silently restrict results');
check($get(['from'=>'2025-12-01'])['count']===81,'All years can include prior-year receipt');
check($get(['from'=>'2025-01-01','to'=>'2025-12-31'])['totals']['in']===321,'Historical year selection');
check(count($get(['from'=>'2026-12-01','to'=>'2026-12-31'])['chart'])===1,'A date range without transactions retains its chart month');
$department=$get(['department'=>'Emergency Room']);check($department['count']===20&&$department['totals']['in']===0,'Department includes only associated issues, no unrelated receipts');
check(count(array_unique(array_column($department['rows'],'party')))===1,'Department rows match selection');
check($get(['classification'=>'MEDICAL INSTRUMENTS'])['count']===20,'Classification filters both movement directions');
check($get(['procurement_type'=>'Donation'])['count']===20,'Receipt and item procurement filtering');
$combined=$get(['from'=>'2026-03-01','to'=>'2026-03-31','department'=>'Operating Room','classification'=>'MEDICAL SUPPLIES','procurement_type'=>'Donation']);check($combined['count']===1&&$combined['rows'][0]['out']===35,'Filters compose without being ignored');
check($get(['department'=>"' OR 1=1 --"])['count']===0,'Department query parameter is literal');
check($get(['classification'=>'nonexistent'])['count']===0,'Unknown classification does not broaden results');
foreach(['from'=>'2026-02-30','to'=>'2025-01-01','report'=>'x','procurement_type'=>'x'] as $key=>$value)check(isset($get([$key=>$value])['errors'][$key]),'Invalid '.$key.' rejected');
check($get(['from'=>['x']])['errors']===[],'Array parameter safely defaults');
check(isset($get(['from'=>'2000-01-01'])['errors']['to']),'Excessive range rejected explicitly');
check($get(['report'=>'stock_in'])['filters']['report']==='inbound','Legacy link aliases');
foreach(cssrReportTypes() as $type=>$label){$d=$get(['report'=>$type]);check($d['errors']===[]&&$d['title']===$label&&count($d['columns'])>=4,'Report variant '.$type);}
$balance=$get(['report'=>'inventory_balance']);check($balance['count']===7,'All active items including zero balance');$stocks=array_column($balance['rows'],null,'code');
check($stocks['CSSR-000001']['balance']===1200,'Storage rooms and CSSR included once');
check($stocks['CSSR-000002']['balance']===32,'Legacy CSSR balance included once');
check($stocks['CSSR-000005']['balance']===0&&$stocks['CSSR-000006']['balance']===0,'Expired and held excluded');
check($get(['report'=>'low_stock'])['count']===1&&$get(['report'=>'out_of_stock'])['count']===3,'Low/out stock boundaries');
$low=$get(['report'=>'low_stock']);check($low['totals']['items']===1&&$low['totals']['in']===1550,'Low stock chart restricted to matching items');
$historical=$get(['report'=>'inventory_balance','from'=>'2026-01-01','to'=>'2026-01-31']);check($historical['rows'][0]['balance']===$balance['rows'][0]['balance'],'Current balance is never mislabeled as historical');
check($get(['report'=>'inventory_balance','department'=>'Emergency Room'])['count']===2,'Balance department scope selects actually issued items');
$fast=$get(['report'=>'fast_moving']);check($fast['rows'][0]['code']==='CSSR-000004'&&$fast['rows'][0]['out']===675,'Fast-moving order by gross period issuance');
$procurement=$get(['report'=>'procurement_type']);check(count($procurement['rows'])===3&&array_sum(array_column($procurement['rows'],'in'))===7200,'Procurement aggregation totals');
$consumption=$get(['report'=>'department_consumption']);check(count($consumption['rows'])===4&&array_sum(array_column($consumption['rows'],'out'))===2100,'Department consumption sums actual issues once');
check($get(['page'=>'999'])['page']===4,'Pagination bounded');
$monthly=$get(['report'=>'monthly']);check($monthly['filters']['from']==='2026-10-01'&&$monthly['filters']['to']==='2026-10-31'&&$monthly['count']===8,'Monthly shortcut filters the current calendar month');
$weekly=$get(['report'=>'weekly']);check($weekly['filters']['from']==='2026-10-05'&&$weekly['filters']['to']==='2026-10-11'&&$weekly['count']===0,'Weekly shortcut uses Monday through Sunday');
$annual=$get(['report'=>'annual']);check($annual['filters']['to']==='2026-12-31'&&$annual['count']===80,'Annual shortcut includes the calendar year');
$crossYear=cssrReportFilters(['report'=>'weekly'],new DateTimeImmutable('2027-01-01'));check($crossYear['filters']['from']==='2026-12-28'&&$crossYear['filters']['to']==='2027-01-03'&&!isset($crossYear['filters']['year'],$crossYear['filters']['month']),'Cross-year week is not truncated by year filter');
check($get(['report'=>'monthly','from'=>'2026-02-01','to'=>'2026-02-28'])['count']===8,'Explicit dates override period shortcuts');
$range=$get(['report'=>'consumption','from'=>'2026-02-01','to'=>'2026-03-01']);check(array_sum(array_column($range['rows'],'out'))===300,'Consumption uses inclusive From and To dates');
check($get(['report'=>'department_consumption','from'=>'2026-02-01','to'=>'2026-03-01','department'=>'Operating Room'])['rows'][0]['out']===65,'Department consumption respects selected period and department');
$available=$get(['report'=>'available']);check($available['count']===3&&array_unique(array_column($available['rows'],'status'))===['Available'],'Available report excludes low and zero stock');
check($available['totals']['in']===array_sum(array_column($available['chart'],'in')),'Available chart and totals agree');
$db->beginTransaction();
$db->exec("UPDATE item_lots SET expiry_date='2028-01-01'; UPDATE item_lots SET expiry_date='2026-10-07' WHERE id=1; UPDATE item_lots SET expiry_date='2027-01-05' WHERE id=10; UPDATE item_lots SET expiry_date='2026-10-10',recall_status='RECALLED' WHERE id=2; UPDATE item_lots SET expiry_date='2026-10-06' WHERE id=3; UPDATE item_lots SET expiry_date='2026-10-10',quantity_available=60 WHERE id=7; UPDATE cssr_available_stock SET expiry_date='2026-10-15'");
$near=$get(['report'=>'near_expiry']);check($near['count']===3&&$near['totals']['balance']===607,'Near expiry includes both boundaries and CSSR, excludes expired, recalled and held');
check($get(['report'=>'near_expiry','from'=>'2026-10-08','to'=>'2026-10-15'])['count']===1,'Expiry window is filtered by selected From and To');
check($get(['report'=>'near_expiry','from'=>'2027-01-01','to'=>'2027-01-31'])['count']===1,'Expiry month filter works across year boundary');
check($get(['report'=>'near_expiry','procurement_type'=>'Donation'])['totals']['balance']===7,'Expiry procurement filter applies');
check(str_contains(cssrReportPdfHtml($near),'Near Expiry')&&str_contains(cssrReportXlsx($near),'Expiration Date'),'Expiry exports retain report and columns');
$db->rollBack();
$db->exec("UPDATE items SET current_stock=99999999");check($get(['report'=>'inventory_balance'])['totals']['balance']===$balance['totals']['balance'],'Balance uses stock records instead of stale cached hospital totals');
$html=cssrReportPdfHtml($base);$pdf=cssrReportPdf($base);
check(str_starts_with($pdf,'%PDF-'),'Browser-generated PDF signature');
check(!str_contains($html,'PRIOR-YEAR')&&!str_contains($html,'VOID-EXCLUDED'),'PDF document excludes filtered and void records');
check(str_contains($html,'TRN-2026-1-1'),'PDF document includes rows beyond the first screen page');
check(str_contains($html,'size:A4 portrait')&&str_contains($html,'rp-print-title'),'PDF uses the shared portrait print styles and title');
preg_match_all('/\/Type\s*\/Page\b/',$pdf,$pageMatch);check(count($pageMatch[0])>1,'PDF retains multi-page report output');
foreach(array_merge(cssrReportLetterhead()['lines'],cssrReportLetterhead()['note'],[cssrReportLetterhead()['code'],'Rev Number','Eff Date']) as $text)check(str_contains($html,$text),'Shared PDF letterhead/footer: '.$text);
check(substr_count($html,'data:image/png;base64,')===2,'PDF embeds the original two logos');
check(!str_contains($html,'<script')&&!preg_match('/<link[^>]*stylesheet/',$html),'PDF document is self-contained and does not execute page scripts');
$xlsx=cssrReportXlsx($base);check(str_starts_with($xlsx,"PK\x03\x04"),'XLSX ZIP signature');check(str_contains($xlsx,'TRN-2026-1-1'),'Workbook includes every page');
// Inspect the actual ZIP parts, not XML-like bytes that may occur inside images.
$parts=[];$cursor=0;while(substr($xlsx,$cursor,4)==="PK\x03\x04"){$entry=unpack('Vsignature/vversion/vflags/vcompression/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra',substr($xlsx,$cursor,30));$name=substr($xlsx,$cursor+30,$entry['name']);$start=$cursor+30+$entry['name']+$entry['extra'];$parts[$name]=substr($xlsx,$start,$entry['size']);$cursor=$start+$entry['size'];}
foreach($parts as $name=>$part)if(preg_match('/\.(xml|rels|vml)$/',$name)){$doc=new DOMDocument();check($doc->loadXML($part),'Valid export XML: '.$name);}
foreach([1] as $sheet){$doc=new DOMDocument();$doc->loadXML($parts['xl/worksheets/sheet'.$sheet.'.xml']);$header=$doc->getElementsByTagName('oddHeader')->item(0)->textContent;
    check(strlen($header)<=255&&str_contains($header,'&L&G')&&str_contains($header,'&R&G'),'Excel header length and two logo references');
    foreach(cssrReportLetterhead()['lines'] as $text)check(str_contains($header,$text),'Excel printed letterhead: '.$text);
    check(str_contains($doc->getElementsByTagName('oddFooter')->item(0)->textContent,cssrReportLetterhead()['code']),'Excel document-control footer');}
check(isset($parts['xl/media/doh-logo.png'],$parts['xl/media/jvgh-seal.png']),'Workbook embeds both original hospital logos');
check(!isset($parts['xl/worksheets/sheet2.xml'])&&!str_contains($xlsx,'Monthly chart data')&&!str_contains($xlsx,'Chart scope:'),'Excel exports report details without chart or summary sheets');
check(str_contains($parts['xl/worksheets/sheet1.xml'],'orientation="portrait"'),'Excel prints in portrait');
$hostile=$base;$hostile['rows'][0]['name']='=HYPERLINK("https://example.invalid") <script> & ñ';$xlsxHostile=cssrReportXlsx($hostile);check(str_contains($xlsxHostile,'<t xml:space="preserve">=HYPERLINK(&quot;'),'Formula-like values are literal inline text');check(str_contains($xlsxHostile,'&lt;script&gt; &amp; ñ'),'Workbook XML escapes text and retains Unicode');
if(isset($argv[1])){file_put_contents($argv[1].'/reports-test.pdf',$pdf);file_put_contents($argv[1].'/reports-test.xlsx',$xlsxHostile);}
echo "Passed $checks report data and export checks.\n";
