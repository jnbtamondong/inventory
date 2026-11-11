<?php
declare(strict_types=1);
require __DIR__.'/../CSSR_INVENTORY/config/database.php';

$commit=in_array('--commit',$argv??[],true);
$rows=[
['ANESTHESIA BREATHING CIRUIT ADULT','3112','2027-04-30','PIECE',650,'SURGITECH',19],
['ANESTHESIA BREATHING CIRUIT ADULT','24041715','2029-04-01','PIECE',550,'INNO Q',30],
['ANESTHESIA BREATHING CIRUIT PEDIA','240520','2027-07-01','PIECE',650,'SURGITECH',13],
['ANESTHESIA BREATHING CIRUIT PEDIA','24080817','2029-07-08','PIECE',550,'INNO Q',30],
['AUTOCLAVE TAPE','25092024','2027-09-01','PIECE',188,'SURGITECH',50],
['DISPOSABLE HEAD CAPS','20240612','2029-06-11','PIECE',2,'SURGITECH',2000],
['DISPOSABLE VAGINAL SPECULUM, MEDIUM','20230310','2028-03-09','PIECE',38,'SURGITECH',500],
['ENDOTRACHEAL TUBE CUFFED S 7','250105-1','2029-12-01','PIECE',26,'SURGITECH',50],
['ENDOTRACHEAL TUBE CUFFED S 8.5','2204007','2027-04-01','PIECE',32,'SUGITECH',100],
['ENDOTRACHEAL TUBE S 2.5','230720','2028-06-01','PIECE',90,'SURGITECH',100],
['ENDOTRACHEAL TUBE S 3.0','230401','2028-04-30','PIECE',32,'INDOPLAS',100],
['ENDOTRACHEAL TUBE S 3.5','240710','2029-06-01','PIECE',32,'SURGITECH',20],
['ENDOTRACHEAL TUBE S 4','240220','2029-01-01','PIECE',32,'SURGITECH',100],
['ENDOTRACHEAL TUBE S 5.5','250720','2030-06-01','PIECE',26,'SURGITECH',145],
['FOLEY CATHETER FR 10, 2 WAY','230401','2028-03-01','PIECE',26,'SURGITECH',200],
['FOLEY CATHETER FR 12 2 WAY (STERILE)','230401','2028-03-01','PIECE',26,'SURGITECH',350],
['HYDROGEN PEROXIDE 3% SOLUTION 1 LITER','JHP10-3470A','2027-05-01','PIECE',119,'J. CHEMIE',39],
['IV CATHETER GAUGE 18 BOX OF 100S','230863','2028-10-01','PIECE',5.88,'SURGITECH',1000],
['IV CATHETER GAUGE 20 BOX OF 100S','042311','2028-10-01','PIECE',7.50,'IVENY',6000],
['IV CATHETER GAUGE 22 BOX OF 100S','240933','2029-09-01','PIECE',7.58,'SURGITECH',13000],
['IV CATHETER GAUGE 24 BOX OF 100S','230867','2028-10-01','PIECE',8,'SURGITECH',7000],
['LUBRICANT JELLY 5G','20250310','2029-03-01','PIECE',3.98,'SURGITECH',1500],
['MANUAL RESUSCITATOR, NEONATE','2204174','2028-04-01','PIECE',1088,'ALL PRO CORP.',132],
['MANUAL VACUUM ASPIRATION (MVA)','2409071','2029-09-30','PIECE',2665,'TRUST',30],
['NON REBREATHER MASK, PEDIA','231220','2028-11-01','PIECE',58.88,'SURGITECH',50],
['OXYGEN MASK ADULT','250512','2030-04-01','PIECE',31,'SURGITECH',300],
['OXYGEN MASK PEDIA','240820','2029-07-01','PIECE',32,'SURGITECH',100],
['OXYGEN NASAL CANNULA FOR PEDIA','240820','2029-07-01','PIECE',14.88,'SURGITECH',100],
['PLASTER OF PARIS BANDAGE FAST SETTING 6"X 5 YARDS','202506','2030-06-01','PIECE',38.88,'SURGITECH',34],
['SUCTION CATHETER FR 10','250210','2030-01-01','PIECE',18,'SURGITECH',150],
['SUCTION CATHETER FR 12','250210','2030-01-01','PIECE',18,'SURGITECH',50],
['SUCTION CATHETER FR 12','250703','2030-06-01','PIECE',6.18,'SURGITECH',150],
['SUCTION CATHETER FR 14','240610','2029-05-01','PIECE',18,'SURGITECH',50],
['SUCTION CATHETER FR 18','250210','2030-03-31','PIECE',6.18,'SURGITECH',150],
['SUCTION CATHETER FR 6','250210','2030-01-01','PIECE',15,'SURGITECH',62],
['SURGICAL GLOVES ELBOW LENGTH 6.5','20241216','2029-12-15','PAIR',25,'INDOPLAS',200],
['SURGICAL SKIN MARKERS W/ RULER','1944','2027-12-01','PIECE',250,'SANDEL',50],
['SURGICAL STAPLER','241205','2029-12-01','PIECE',288,'SURGITECH',200],
['DISPOSABLE SHOE COVER','20240612','2029-06-01','PIECE',1.75,'SURGITECH',2000],
['THREE BALL INCENCITIVE SPIROMETER','FY2508010','2030-08-01','PIECE',780,'SURGITECH',18],
['URINE BAG ADULT','20241020','2029-10-01','PIECE',16.50,'SURGITECH',1500],
['NEBULIZER KIT WITH FACE MASK (NEONATE)','250410','2030-03-01','PIECE',38,'SURGITECH',50],
['NITRILE GLOVES SIZE MEDIUM','25013124','2030-05-01','BOX',175,'SURGITECH',119],
['OXYGEN NASAL CANNULA ADULT','251105','2030-10-01','PIECE',16.50,'SURGITECH',3200],
['SHARPS DISPOSABLE CONTAINER, 3 LITER','BY250801',null,'PIECE',125,'NO BRAND',180],
['SURGICAL GLOVES ELBOW LENGTH 6.5','20250610(1)','2030-06-09','PAIR',18.88,'SURGITECH',100],
['FEEDING TUBE FR12','20250407','2030-04-01','PIECE',18,'SURGITECH',100],
['FEEDING TUBE FR14','20250407','2028-04-01','PIECE',18,'SURGITECH',50],
['FEEDING TUBE FR18','20250407','2030-04-01','PIECE',18,'SURGITECH',100],
['GLUTARALDEHYDE STERILIZING SOLUTION 5 LITRES','111L5','2028-11-01','PIECE',1890,'AIDEX',40],
['HYDROGEN PEROXIDE 3% SOLUTION 120ML','82602006','2028-02-01','BOTTLE',30,'AGUAFER',13],
['OXYGEN MASK ADULT','251262','2030-12-01','PIECE',23,'VERTEX',3400],
['FOLEY CATHETER FR 16 2 WAY (STERILE)','25E18','2030-05-01','PIECE',25,'STERIMED',600],
['DISPOSABLE SYRINGE 3ML','SC251213PH','2031-01-01','PIECE',2.05,'PARTNERS',8100],
['OXYGEN MASK ADULT','2511014204','2030-10-01','PIECE',45,'PARTNERS',900],
['NEBULIZER KIT WITH FACE MASK (ADULT)','SC260228PH','2031-03-01','PIECE',43.50,'PARTNERS',600],
['NEBULIZER KIT WITH FACE MASK (PEDIA)','SC260228PH','2031-03-01','PIECE',43.50,'PARTNERS',800],
['BP CUFF WITH INFLATION BAG PEDIA','26A01',null,'PIECE',380,'BEROVAN',30],
['FEEDING TUBE FR 5','2422203','2029-03-01','PIECE',7,'BEROVAN',50],
['FEEDING TUBE FR8','2422202','2029-03-01','PIECE',7,'BEROVAN',50],
['ENDOTRACHEAL TUBE S 3.0','AM-24091501','2029-09-01','PIECE',26.50,'BEROVAN',40],
['ENDOTRACHEAL TUBE CUFFED S 7.5','AM-26010901','2031-01-01','PIECE',36.50,'BEROVAN',50],
['MANUAL RESUSCITATOR, NEONATE','EMR0404002','2028-04-30','PIECE',580,'ALL PRO CORP.',132],
['ADHESIVE PLASTER 1 INCH X 10 YARDS','20251001','2028-10-01','ROLL',20,'BEROVAN',1080],
['UNDERPAD','202510','2030-10-01','PIECE',15,'SHIELD',600],
['URINE BAG ADULT','SC260228PH','2031-03-01','PIECE',20,'PARTNERS',750],
['IV SET W/ AIRWAY FOR IV PUSH MICRODRIP PEDIA','20250625','2030-06-01','PIECE',20.50,'SUREGUARD',1500],
['CAUTERY PAD DISPOSABLE','10102025','2028-10-01','PIECE',220,'BEROVAN',200],
['GAUZE SPONGE 4X4','2522201',null,'PACK',91.50,'BEROVAN',1200],
['TONGUE DEPRESSOR','2522211','2030-10-01','PIECE',0.80,'BEROVAN',5000],
];

$pdo=db();$matches=[];$errors=[];
foreach($rows as$i=>$r){[$name,$lot,$expiry,$unit,$price,$supplier,$qty]=$r;$stmt=$pdo->prepare('SELECT id,item_code,item_description,unit FROM items WHERE UPPER(TRIM(item_description))=UPPER(TRIM(?)) AND status="Active"');$stmt->execute([$name]);$found=$stmt->fetchAll();if(count($found)!==1){$errors[]=['row'=>$i+1,'item'=>$name,'matches'=>count($found)];continue;}$matches[]=['row'=>$i+1,'item'=>$found[0],'data'=>$r];}
$existing=(int)$pdo->query("SELECT COUNT(*) FROM item_lots WHERE storage_location='STORAGE ROOM 1'")->fetchColumn();
echo 'Rows: '.count($rows).PHP_EOL.'Matched: '.count($matches).PHP_EOL.'Unmatched/Ambiguous: '.count($errors).PHP_EOL.'Existing SR1 lots: '.$existing.PHP_EOL;
foreach($errors as$e)echo "ROW {$e['row']} | {$e['item']} | matches={$e['matches']}".PHP_EOL;
if(!$commit)exit($errors?2:0);if($errors)throw new RuntimeException('Import cancelled because some items did not match exactly.');if($existing>0)throw new RuntimeException('Import cancelled: Storage Room 1 already has lot records.');

$pdo->beginTransaction();
try{
 $pdo->exec("CREATE TABLE IF NOT EXISTS opening_sr1_20260825_import_log(id INT AUTO_INCREMENT PRIMARY KEY,item_id INT NOT NULL,item_lot_id INT NOT NULL,quantity INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_opening_lot(item_lot_id)) ENGINE=InnoDB");
 $lotStmt=$pdo->prepare('INSERT INTO item_lots(item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price,quantity_received,quantity_available,status) VALUES(?,?,?,?,?,?,?,?,? ,"AVAILABLE")');
 $adjStmt=$pdo->prepare('INSERT INTO stock_adjustments(adjustment_no,location_name,stock_type,source_record_id,item_id,item_name,lot_number,expiry_date,system_quantity,actual_quantity,variance_quantity,reason,adjusted_by,adjusted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $cardStmt=$pdo->prepare('INSERT INTO stock_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $logStmt=$pdo->prepare('INSERT INTO opening_sr1_20260825_import_log(item_id,item_lot_id,quantity) VALUES(?,?,?)');
 $balances=[];
 foreach($matches as$m){[$name,$lot,$expiry,$unit,$price,$brand,$qty]=$m['data'];$itemId=(int)$m['item']['id'];$lotStmt->execute([$itemId,$lot,$expiry,$brand,'PENDING','STORAGE ROOM 1',$price,$qty,$qty]);$lotId=(int)$pdo->lastInsertId();$balances[$itemId]=($balances[$itemId]??0)+$qty;$adj='OPEN-SR1-20260825-'.str_pad((string)$m['row'],3,'0',STR_PAD_LEFT);$remarks='Opening supply based on physical count as of August 25, 2026. Reference number intentionally blank.';$when='2026-08-25 00:00:00';$adjStmt->execute([$adj,'STORAGE ROOM 1','ITEM LOT',$lotId,$itemId,$name,$lot,$expiry,0,$qty,$qty,$remarks,'SYSTEM IMPORT',$when]);$cardStmt->execute([$itemId,$when,'','OPENING BALANCE',$lot,$expiry,$qty,0,$balances[$itemId],null,'STORAGE ROOM 1','SYSTEM IMPORT',$remarks]);$logStmt->execute([$itemId,$lotId,$qty]);}
 foreach(array_keys($balances)as$itemId){$stmt=$pdo->prepare('UPDATE items SET current_stock=(SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?) WHERE id=?');$stmt->execute([$itemId,$itemId]);}
 $pdo->commit();echo 'COMMITTED: '.count($matches).' opening lots; total quantity='.array_sum(array_column($rows,6)).PHP_EOL;
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
