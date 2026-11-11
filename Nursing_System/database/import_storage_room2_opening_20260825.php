<?php
declare(strict_types=1);
require __DIR__.'/../CSSR_INVENTORY/config/database.php';

$commit=in_array('--commit',$argv??[],true);
$rows=[
 ['KIDNEY BASIN 12 INCHES','N/A',null,'PIECE',650.00,'MCTYCOON',10],
 ['ADSON WITH TEETH 6"','N/A',null,'PIECE',262.00,'MCTYCOON',6],
 ['ADSON WITHOUT TEETH 6"','N/A',null,'PIECE',262.00,'MCTYCOON',6],
 ['UNDERPAD','202510','2030-10-01','PIECE',15.00,'SHIELD',600],
 ['DISPOSABLE SYRINGE 1ML','625042601','2029-04-01','PIECE',2.15,'MCTYCOON',30000],
 ['DISPOSABLE SYRINGE 3ML','03696B','2029-05-01','PIECE',2.06,'MCTYCOON',30000],
 ['DISPOSABLE SYRINGE 5ML','03696B','2029-05-01','PIECE',2.15,'MCTYCOON',30000],
];
$pdo=db();$matches=[];$errors=[];
foreach($rows as$i=>$r){$s=$pdo->prepare('SELECT id,item_code,item_description FROM items WHERE UPPER(TRIM(item_description))=UPPER(TRIM(?)) AND status="Active"');$s->execute([$r[0]]);$found=$s->fetchAll();if(count($found)!==1){$errors[]=['row'=>$i+1,'item'=>$r[0],'matches'=>count($found)];continue;}$matches[]=['row'=>$i+1,'item'=>$found[0],'data'=>$r];}
$existing=(int)$pdo->query("SELECT COUNT(*) FROM item_lots WHERE storage_location='STORAGE ROOM 2'")->fetchColumn();
echo 'Rows: '.count($rows).PHP_EOL.'Matched: '.count($matches).PHP_EOL.'Unmatched/Ambiguous: '.count($errors).PHP_EOL.'Existing SR2 lots: '.$existing.PHP_EOL;foreach($errors as$e)echo "ROW {$e['row']} | {$e['item']} | matches={$e['matches']}".PHP_EOL;
if(!$commit)exit($errors?2:0);if($errors)throw new RuntimeException('Import cancelled because some items did not match exactly.');if($existing>0)throw new RuntimeException('Import cancelled: Storage Room 2 already has lot records.');
$pdo->beginTransaction();
try{
 $pdo->exec('CREATE TABLE IF NOT EXISTS opening_sr2_20260825_import_log(id INT AUTO_INCREMENT PRIMARY KEY,item_id INT NOT NULL,item_lot_id INT NOT NULL,quantity INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_opening_lot(item_lot_id)) ENGINE=InnoDB');
 $lot=$pdo->prepare('INSERT INTO item_lots(item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price,quantity_received,quantity_available,status) VALUES(?,?,?,?,?,?,?,?,?,"AVAILABLE")');
 $adj=$pdo->prepare('INSERT INTO stock_adjustments(adjustment_no,location_name,stock_type,source_record_id,item_id,item_name,lot_number,expiry_date,system_quantity,actual_quantity,variance_quantity,reason,adjusted_by,adjusted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $card=$pdo->prepare('INSERT INTO stock_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $log=$pdo->prepare('INSERT INTO opening_sr2_20260825_import_log(item_id,item_lot_id,quantity) VALUES(?,?,?)');$balances=[];
 foreach($matches as$m){[$name,$lotNo,$expiry,$unit,$price,$brand,$qty]=$m['data'];$itemId=(int)$m['item']['id'];$lot->execute([$itemId,$lotNo,$expiry,$brand,'PENDING','STORAGE ROOM 2',$price,$qty,$qty]);$lotId=(int)$pdo->lastInsertId();$balances[$itemId]=($balances[$itemId]??0)+$qty;$technical='OPEN-SR2-20260825-'.str_pad((string)$m['row'],3,'0',STR_PAD_LEFT);$remarks='Opening supply based on physical count as of August 25, 2026. Reference number intentionally blank.';$when='2026-08-25 00:00:00';$adj->execute([$technical,'STORAGE ROOM 2','ITEM LOT',$lotId,$itemId,$name,$lotNo,$expiry,0,$qty,$qty,$remarks,'SYSTEM IMPORT',$when]);$card->execute([$itemId,$when,'','OPENING BALANCE',$lotNo,$expiry,$qty,0,$balances[$itemId],null,'STORAGE ROOM 2','SYSTEM IMPORT',$remarks]);$log->execute([$itemId,$lotId,$qty]);}
 foreach(array_keys($balances)as$itemId){$s=$pdo->prepare('UPDATE items SET current_stock=(SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?) WHERE id=?');$s->execute([$itemId,$itemId]);}
 $pdo->commit();echo 'COMMITTED: '.count($matches).' opening lots; total quantity='.array_sum(array_column($rows,6)).PHP_EOL;
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
