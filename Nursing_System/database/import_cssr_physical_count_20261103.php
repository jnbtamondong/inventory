<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../CSSR_INVENTORY/config/database.php';
$dir=__DIR__.'/../outputs/counts_20261103/';
$plan=json_decode(file_get_contents($dir.'cssr-live-plan.json'),true,512,JSON_THROW_ON_ERROR);
$norm=fn($s)=>strtoupper(trim(preg_replace('/\s+/',' ',(string)$s)));
$mapping=[];foreach(json_decode(file_get_contents(__DIR__.'/../outputs/inventory_2026/corrected-tally-mapping.json'),true) as [$a,$b])$mapping[$norm($a)]=$norm($b);
$canon=fn($s)=>$mapping[$norm($s)]??$norm($s);
$items=db_all('SELECT * FROM items');$byName=[];$byCanon=[];
foreach($items as $it)foreach(array_unique([$it['item_name'],$it['item_description']]) as $n){if(!$n)continue;$byName[$norm($n)][$it['id']]=$it;$byCanon[$canon($n)][$it['id']]=$it;}
$ready=[];$review=[];$newItems=[];
foreach($plan as $g){
 if($g['missing']){$review[]=['item'=>$g['name'],'lot'=>$g['lot'],'reason'=>'Missing actual count; unchanged'];continue;}
 $matches=$byName[$norm($g['name'])]??$byCanon[$canon($g['name'])]??[];
 if(count($matches)>1){$review[]=['item'=>$g['name'],'lot'=>$g['lot'],'reason'=>'Ambiguous item match'];continue;}
 $it=$matches?array_values($matches)[0]:null;
 if($it&&$it['status']!=='Active'){$review[]=['item'=>$g['name'],'reason'=>'Inactive item'];continue;}
 $g['item_id']=$it?(int)$it['id']:null;
 if(!$it)$newItems[$g['name']]=true;
 $lots=$it?db_all('SELECT * FROM item_lots WHERE item_id=? AND storage_location=? AND UPPER(TRIM(lot_number))=? AND UPPER(TRIM(brand))=? ORDER BY id',[$it['id'],'CSSR',$g['lot'],$g['brand']]):[];
 if(count($lots)>1){$review[]=['item'=>$g['name'],'lot'=>$g['lot'],'reason'=>'Multiple existing lots; allocation needed'];continue;}
 $lot=$lots[0]??null;
 if($lot&&(!in_array($lot['status'],['AVAILABLE','DEPLETED'],true)||(int)$lot['quantity_quarantined']>0||$lot['recall_status']!=='CLEAR')){$review[]=['item'=>$g['name'],'lot'=>$g['lot'],'reason'=>'Lot held, recalled or quarantined'];continue;}
 $g['existing_lot']=$lot;$g['reference']='COUNT-CSSR-20261103-'.substr(hash('sha256',json_encode([$g['name'],$g['lot'],$g['brand'],$g['unit']])),0,24);
 if(db_value('SELECT COUNT(*) FROM stock_adjustments WHERE adjustment_no=?',[$g['reference']])){$review[]=['item'=>$g['name'],'lot'=>$g['lot'],'reason'=>'Already imported'];continue;}
 $ready[]=$g;
}
$keys=[];foreach($ready as $g){$k=json_encode([$g['item_id']??$g['name'],$g['lot'],$g['brand']]);$keys[$k]=($keys[$k]??0)+1;}
$ready=array_values(array_filter($ready,function($g)use($keys,&$review){$k=json_encode([$g['item_id']??$g['name'],$g['lot'],$g['brand']]);if($keys[$k]>1){$review[]=['item'=>$g['name'],'lot'=>$g['lot'],'reason'=>'Conflicting source units; counts not combined'];return false;}return true;}));
$report=['ready'=>count($ready),'new_items'=>array_keys($newItems),'review'=>$review,'apply'=>in_array('--apply',$argv,true)];
file_put_contents($dir.'cssr-live-import-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT).PHP_EOL;
if(!$report['apply'])exit;
if((int)db_value("SELECT GET_LOCK('cssr-count-import-20261103',10)")!==1)throw new RuntimeException('Unable to lock import');
db()->beginTransaction();
try{
 db_all('SELECT id FROM items ORDER BY id FOR UPDATE');db_all('SELECT id FROM item_lots ORDER BY id FOR UPDATE');
 $snapshot=['items'=>db_all('SELECT * FROM items'),'item_lots'=>db_all('SELECT * FROM item_lots'),'stock_adjustments'=>db_all('SELECT * FROM stock_adjustments'),'bin_cards'=>db_all('SELECT * FROM bin_cards')];
 $backup=$dir.'cssr-before-import-'.date('Ymd_His').'.json';if(file_put_contents($backup,json_encode($snapshot,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Backup failed');
 $created=[];$changed=0;$unchanged=0;$now=(new DateTimeImmutable('now',new DateTimeZone('Asia/Singapore')))->format('Y-m-d H:i:s');
 foreach($ready as $g){
  if(db_value('SELECT COUNT(*) FROM stock_adjustments WHERE adjustment_no=?',[$g['reference']]))throw new RuntimeException('Import changed concurrently');
  $itemId=$g['item_id'];
  if(!$itemId){if(isset($created[$g['name']]))$itemId=$created[$g['name']];else{db_statement('INSERT INTO items(item_code,item_name,item_description,unit,brand,bin_location,current_stock,status) VALUES(?,?,?,?,?,?,0,?)',['COUNT-CSSR-'.substr(hash('sha256',$g['name']),0,16),$g['name'],$g['name'],$g['unit']?:null,$g['brand'],'CSSR','Active']);$itemId=(int)db()->lastInsertId();$created[$g['name']]=$itemId;}}
  $lot=$g['existing_lot'];$before=$lot?(int)$lot['quantity_available']:0;$qty=$g['quantity'];$expiry=$lot['expiry_date']??null;
  if(!$lot){
   $es=array_unique(array_filter($g['expiries']));if(count($es)===1){$e=reset($es);if(preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~',$e,$m)&&checkdate((int)$m[1],(int)$m[2],(int)$m[3]))$expiry=sprintf('%04d-%02d-%02d',$m[3],$m[1],$m[2]);elseif(preg_match('~^(\d{1,2})/{1,2}(\d{4})$~',$e,$m)&&checkdate((int)$m[1],1,(int)$m[2]))$expiry=(new DateTimeImmutable(sprintf('%04d-%02d-01',$m[2],$m[1])))->format('Y-m-t');}
   $prices=array_unique(array_filter($g['prices'],fn($p)=>$p!==''));$price=count($prices)===1?str_replace(',','',reset($prices)):null;
   db_statement('INSERT INTO item_lots(item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price,quantity_received,quantity_available,status) VALUES(?,?,?,?,?,?,?,0,?,?)',[$itemId,$g['lot'],$expiry,$g['brand'],'','CSSR',$price,$qty,$qty>0?'AVAILABLE':'DEPLETED']);$lotId=(int)db()->lastInsertId();
  }else{$lotId=(int)$lot['id'];$current=db_one('SELECT * FROM item_lots WHERE id=?',[$lotId]);if((int)$current['quantity_available']!==$before)throw new RuntimeException('Stock changed during preparation');db_statement('UPDATE item_lots SET quantity_available=?,status=? WHERE id=?',[$qty,$qty>0?'AVAILABLE':'DEPLETED',$lotId]);}
  $delta=$qty-$before;$delta===0?$unchanged++:$changed++;
  $reason='User-supplied physical count; CSSR storage actual count dated 2026-11-03. No historical issue quantities edited. Sources: '.json_encode($g['sources']).'; Expiry source: '.json_encode(array_unique($g['expiries']));
  db_statement('INSERT INTO stock_adjustments(adjustment_no,location_name,stock_type,source_record_id,item_id,item_name,lot_number,expiry_date,system_quantity,actual_quantity,variance_quantity,reason,adjusted_by,adjusted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$g['reference'],'CSSR','ITEM LOT',$lotId,$itemId,$g['name'],$g['lot'],$expiry,$before,$qty,$delta,$reason,'USER COUNT IMPORT',$now]);
  $balance=(int)db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=? AND storage_location=?',[$itemId,'CSSR']);
  db_statement('INSERT INTO bin_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$itemId,$now,$g['reference'],'PHYSICAL COUNT ADJUSTMENT',$g['lot'],$expiry,max(0,$delta),max(0,-$delta),$balance,'CSSR','USER COUNT IMPORT',$reason]);
  db_statement('UPDATE items SET current_stock=(SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?) WHERE id=?',[$itemId,$itemId]);
  if((int)db_value('SELECT quantity_available FROM item_lots WHERE id=?',[$lotId])!==$qty)throw new RuntimeException('Count verification failed');
 }
 db()->commit();$report+=['posted'=>count($ready),'changed'=>$changed,'unchanged'=>$unchanged,'created_items'=>count($created),'backup'=>$backup];file_put_contents($dir.'cssr-live-import-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($report).PHP_EOL;
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}finally{db_value("SELECT RELEASE_LOCK('cssr-count-import-20261103')");}
