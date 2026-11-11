<?php
declare(strict_types=1);
require __DIR__.'/../CSSR_INVENTORY/config/database.php';

$commit=in_array('--commit',$argv??[],true);
$rows=[
['DISPOSABLE SYRINGE 3ML','20240720','2029-07-19','PIECE',2.18,'FUJIKAWA',14400],
['MANUAL RESUSCITATOR, PEDIA','21002843','2028-04-01','PIECE',1100,'SURGITECH',18],
['NITRILE GLOVES SIZE MEDIUM','25002455','2030-02-01','BOX',144,'SURGITECH',10],
['SURGICAL GLOVES ELBOW LENGTH 6.5','030225-500','2030-01-01','PAIR',30,'ENDURE',320],
['SURGICAL GLOVES ELBOW LENGTH 7','160525-500','2030-08-01','PAIR',30,'ENDURE',320],
['POVIDONE 7.5% SKIN CLEANSER/GALLON','25E361','2027-11-01','GAL',1200,'MAXI-AID',44],
['POVIDONE IODINE 10% SOLUTION (ANTISEPTIC)1 GAL','26R026','2028-02-01','PIECE',1300,'MAXI-AID',32],
['NITRILE GLOVES SIZE LARGE','25013313','2030-06-01','BOX',175,'SURGITECH',1260],
['NITRILE GLOVES SIZE MEDIUM','25013124','2030-05-01','BOX',175,'SURGITECH',119],
['NITRILE GLOVES SIZE SMALL','25013124','2030-05-01','BOX',175,'SURGITECH',50],
['PENROSE DRAIN 1/2 INCH','20250408','2030-04-01','PIECE',38,'SURGITECH',75],
['PENROSE DRAIN 1/4 INCH','202409','2029-10-01','PIECE',38,'SURGITECH',50],
['PENROSE DRAIN 3/4 INCH','202409','2029-10-01','PIECE',38,'SURGITECH',50],
['SPECIMEN COLLECTOR 50ML','20250420','2030-04-01','PIECE',12,'SURGITECH',75],
['DISPOSABLE SYRINGE 3ML','20251206','2030-12-01','PIECE',2.48,'FUJIKAWA',9000],
['OXYGEN NASAL CANNULA ADULT','260105','2030-12-01','PIECE',16.50,'SURGITECH',1100],
['NITRILE GLOVES SIZE LARGE','20211005',null,'BOX',170,'FUSION QUANTUM',100],
['COTTON 100\'S PER PACK','28524 (NICU)','2028-02-01','PACK',25,'GINGERMED',100],
['FACEMASK, EARLOOP, 50S/BOX','SC26020PH (NICU)','2031-03-01','BOX',50,'PARTNERS',40],
['ISOLATION GOWN','N/A (NICU)',null,'PIECE',35,'OREX',900],
['DISPOSABLE SYRINGE 1ML','20250520 (NICU)','2030-05-01','PIECE',2.05,'REMEDECA',2700],
['SUCTION CATHETER FR 5','SC250405PH (NICU)','2030-05-01','PIECE',9,'PARTNERS',500],
['SUCTION CATHETER FR 10','SC251020PH (NICU)','2030-11-01','PIECE',9,'PARTNERS',500],
['DISPOSABLE SYRINGE 50ML','20251110 (NICU)','2030-11-01','PIECE',4.20,'REMEDECA',150],
['UNDERPAD','112561 (NICU)','2028-10-01','PIECE',14,'SUNKISS',800],
['NITRILE GLOVES SIZE MEDIUM','112898 (NICU)','2030-06-01','BOX',180,'ROSEMED',100],
['DISPOSABLE SYRINGE 50ML','20251012','2030-10-01','PIECE',11.28,'FUJIKAWA',1350],
['DISPOSABLE SYRINGE 1ML','625042601','2029-04-01','PIECE',2.15,'MCTYCOON',30000],
['DISPOSABLE SYRINGE 3ML','03696B','2029-05-01','PIECE',2.06,'MCTYCOON',30000],
['DISPOSABLE SYRINGE 5ML','03696B','2029-05-01','PIECE',2.15,'MCTYCOON',30000],
['FACEMASK, EARLOOP, 50S/BOX','IP2602 - MCTYCOON','2031-02-01','BOX',54,'INDOPLAS',300],
['IV EXTENSION TUBING, 100CM','12989PM - MCTYCOON','2030-12-01','PIECE',32,'UNIMEX',300],
['POVIDONE 7.5% SKIN CLEANSER/GALLON','042632 - MCTYCOON','2028-04-01','GAL',1187.50,'GREATEST LABORATORIES',46],
['POVIDONE IODINE 10% SOLUTION 120ML','42605 - MCTYCOON','2028-04-01','BOTTLE',79,'GREATEST LABORATORIES',128],
['KIDNEY BASIN 12 INCHES','N/A - MCTYCOON',null,'PIECE',650,'N/A',10],
['ADSON WITH TEETH 6"','N/A - MCTYCOON',null,'PIECE',262,'N/A',6],
['ADSON WITHOUT TEETH 6"','N/A - MCTYCOON',null,'PIECE',262,'N/A',6],
['ULTRASOUND GEL 250ML','20250520','2027-05-19','PIECE',79,'SUGITECH',48],
['ALCOHOL','3000006882645','2029-06-01','GAL',380,'SAFEWELL',40],
['IV SET W/ AIRWAY FOR IV PUSH MICRODRIP PEDIA','241215','2029-11-01','PIECE',12.88,'SURGITECH',1500],
['IV SET W/ AIRWAY FOR IV PUSH VACOSET/ MACRODRIP ADULT','241215','2029-11-01','PIECE',12.88,'SURGITECH',4200],
['SUCTION CATHETER FR 5','250915','2030-03-01','PIECE',12,'SURGITECH',500],
['MULTIVARAIBLE CHEMICAL INDICATOR TYPE 4','N554','2027-07-01','PIECE',48,'MATACHANA',500],
['SHARPS DISPOSABLE CONTAINER, 30 LITER','N/A',null,'PIECE',980,'MEDIKOTEK',40],
];

$pdo=db();$matches=[];$errors=[];
foreach($rows as$i=>$r){$s=$pdo->prepare('SELECT id,item_code,item_description FROM items WHERE UPPER(TRIM(item_description))=UPPER(TRIM(?)) AND status="Active"');$s->execute([$r[0]]);$found=$s->fetchAll();if(count($found)!==1){$errors[]=['row'=>$i+1,'item'=>$r[0],'matches'=>count($found)];continue;}$matches[]=['row'=>$i+1,'item'=>$found[0],'data'=>$r];}
$existing=(int)$pdo->query("SELECT COUNT(*) FROM item_lots WHERE storage_location='STORAGE ROOM 3'")->fetchColumn();
echo 'Rows: '.count($rows).PHP_EOL.'Matched: '.count($matches).PHP_EOL.'Unmatched/Ambiguous: '.count($errors).PHP_EOL.'Existing SR3 lots: '.$existing.PHP_EOL;
foreach($errors as$e)echo "ROW {$e['row']} | {$e['item']} | matches={$e['matches']}".PHP_EOL;
if(!$commit)exit($errors?2:0);
if($errors)throw new RuntimeException('Import cancelled because some items did not match exactly.');
if($existing>0)throw new RuntimeException('Import cancelled: Storage Room 3 already has lot records.');

$pdo->exec('CREATE TABLE IF NOT EXISTS opening_sr3_20260825_import_log(id INT AUTO_INCREMENT PRIMARY KEY,item_id INT NOT NULL,item_lot_id INT NOT NULL,quantity INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_opening_lot(item_lot_id)) ENGINE=InnoDB');
$pdo->beginTransaction();
try{
 $lot=$pdo->prepare('INSERT INTO item_lots(item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price,quantity_received,quantity_available,status) VALUES(?,?,?,?,?,?,?,?,?,"AVAILABLE")');
 $adj=$pdo->prepare('INSERT INTO stock_adjustments(adjustment_no,location_name,stock_type,source_record_id,item_id,item_name,lot_number,expiry_date,system_quantity,actual_quantity,variance_quantity,reason,adjusted_by,adjusted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $card=$pdo->prepare('INSERT INTO stock_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $log=$pdo->prepare('INSERT INTO opening_sr3_20260825_import_log(item_id,item_lot_id,quantity) VALUES(?,?,?)');$balances=[];
 foreach($matches as$m){[$name,$lotNo,$expiry,$unit,$price,$brand,$qty]=$m['data'];$itemId=(int)$m['item']['id'];$lot->execute([$itemId,$lotNo,$expiry,$brand,'PENDING','STORAGE ROOM 3',$price,$qty,$qty]);$lotId=(int)$pdo->lastInsertId();$balances[$itemId]=($balances[$itemId]??0)+$qty;$technical='OPEN-SR3-20260825-'.str_pad((string)$m['row'],3,'0',STR_PAD_LEFT);$remarks='Opening supply based on physical count as of August 25, 2026. Reference number intentionally blank.';$when='2026-08-25 00:00:00';$adj->execute([$technical,'STORAGE ROOM 3','ITEM LOT',$lotId,$itemId,$name,$lotNo,$expiry,0,$qty,$qty,$remarks,'SYSTEM IMPORT',$when]);$card->execute([$itemId,$when,'','OPENING BALANCE',$lotNo,$expiry,$qty,0,$balances[$itemId],null,'STORAGE ROOM 3','SYSTEM IMPORT',$remarks]);$log->execute([$itemId,$lotId,$qty]);}
 foreach(array_keys($balances)as$itemId){$s=$pdo->prepare('UPDATE items SET current_stock=(SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?) WHERE id=?');$s->execute([$itemId,$itemId]);}
 $pdo->commit();echo 'COMMITTED: '.count($matches).' opening lots; total quantity='.array_sum(array_column($rows,6)).PHP_EOL;
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
