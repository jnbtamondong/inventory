<?php
require_once __DIR__.'/storage-room-samples.php';
function cssrReportSamples(?DateTimeImmutable $now=null): PDO
{
    $now=$now??new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));$db=cssrStorageRoomSamples();
    $db->exec("ALTER TABLE item_lots ADD recall_status TEXT DEFAULT 'CLEAR';
        CREATE TABLE departments(id INTEGER PRIMARY KEY,department_name TEXT,status TEXT);
        INSERT INTO departments VALUES(1,'Emergency Room','Active'),(2,'Operating Room','Active'),(3,'Medical Ward','Active');
        CREATE TABLE stock_out(id INTEGER PRIMARY KEY AUTOINCREMENT,item_id INTEGER,item_lot_id INTEGER,reference_no TEXT,department TEXT,quantity_issued INTEGER,quantity_returned INTEGER DEFAULT 0,issued_date TEXT);
        UPDATE items SET procurement_type='Donation' WHERE id=2;
        UPDATE items SET procurement_type='Emergency Purchase' WHERE id=4;
        UPDATE item_lots SET quantity_available=0 WHERE item_id=7;");
    $in=$db->prepare("INSERT INTO stock_in(item_id,item_lot_id,reference_no,procurement_type,record_status,supplier,quantity_received,unit_price,received_date) VALUES(?,?,?,?,?,?,?,?,?)");
    $out=$db->prepare("INSERT INTO stock_out(item_id,item_lot_id,reference_no,department,quantity_issued,issued_date) VALUES(?,?,?,?,?,?)");
    $endMonth=(int)$now->format('n');$year=$now->format('Y');
    for($month=1;$month<=$endMonth;$month++)for($item=1;$item<=4;$item++){
        $date=$year.'-'.str_pad((string)$month,2,'0',STR_PAD_LEFT).'-01';$types=[1=>'REGULAR',2=>'DONATION',3=>'REGULAR',4=>'EMERGENCY PURCHASE'];
        $in->execute([$item,[1=>1,2=>3,3=>4,4=>5][$item],'IN-'.$year.'-'.$month.'-'.$item,$types[$item],'POSTED','Sample Medical Trading',50*$item+$month*10,10*$item,$date]);
        $out->execute([$item,$item===1?10:[2=>3,3=>4,4=>5][$item],'TRN-'.$year.'-'.$month.'-'.$item,['Emergency Room','Operating Room','Medical Ward'][($item-1)%3],10*$item+$month*5,$date]);
    }
    $in->execute([1,1,'VOID-EXCLUDED','REGULAR','VOID','Sample Medical Trading',99999,10,$now->format('Y-m-d')]);
    $in->execute([1,1,'PRIOR-YEAR','REGULAR','POSTED','Sample Medical Trading',321,10,((int)$year-1).'-12-15']);
    return $db;
}
