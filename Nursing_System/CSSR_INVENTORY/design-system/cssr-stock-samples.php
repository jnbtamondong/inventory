<?php
require_once __DIR__.'/storage-room-samples.php';
function cssrStockPageSamples(): PDO
{
    $db=cssrStorageRoomSamples();
    $db->exec("ALTER TABLE item_lots ADD recall_status TEXT DEFAULT 'CLEAR';
        CREATE TABLE stock_out(id INTEGER PRIMARY KEY AUTOINCREMENT,item_id INTEGER,item_lot_id INTEGER,reference_no TEXT,department TEXT,quantity_issued INTEGER,quantity_returned INTEGER DEFAULT 0,issued_date TEXT);
        UPDATE item_lots SET quantity_received=700,quantity_issued=300,quantity_available=400 WHERE id=10;
        INSERT INTO item_lots(item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price,quantity_received,quantity_issued,quantity_available,quantity_quarantined,inspection_status,status) VALUES
        (2,'GL-CSSR-A','2099-01-01','','Sample Medical Trading','CSSR',220,100,80,20,0,'CLEARED','AVAILABLE'),
        (3,'SY-CSSR-A','2099-02-01','','Sample Medical Trading','CSSR',5,1500,700,800,0,'CLEARED','AVAILABLE'),
        (4,'SC-CSSR-A',NULL,'','Sample Medical Trading','CSSR',90,25,25,0,0,'CLEARED','DEPLETED'),
        (5,'MK-CSSR-A','2099-01-01','','Sample Medical Trading','CSSR',50,60,60,0,0,'CLEARED','DEPLETED'),
        (6,'CT-CSSR-A','2099-01-01','','Sample Medical Trading','CSSR',10,100,0,0,100,'QUARANTINED','QUARANTINED'),
        (7,'IV-CSSR-A','2099-01-01','','Sample Medical Trading','CSSR',30,150,75,75,0,'CLEARED','AVAILABLE');
        INSERT INTO stock_reservations VALUES(2,10,100,'ACTIVE','2099-01-01');
        UPDATE items SET current_stock=(SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=items.id);");
    $monthStart=date('Y-m-01');$previous=date('Y-m-d',strtotime($monthStart.' -1 day'));
    $statement=$db->prepare('INSERT INTO stock_out(item_id,item_lot_id,reference_no,department,quantity_issued,issued_date) VALUES(?,?,?,?,?,?)');
    foreach([[1,10,300,'Emergency Room'],[2,11,80,'Medical Ward'],[3,12,700,'Operating Room'],[4,13,25,'Emergency Room'],[5,14,60,'Outpatient Department'],[7,16,75,'Medical Ward']] as [$item,$lot,$qty,$dept])$statement->execute([$item,$lot,'REQ-'.date('Ym').'-'.str_pad((string)$item,4,'0',STR_PAD_LEFT),$dept,$qty,$monthStart]);
    // A storage-room issuance and an earlier-month CSSR issue must not inflate this month's CSSR figure.
    $statement->execute([1,1,'STORAGE-ISSUE','Storage Room 1',9000,$monthStart]);
    $statement->execute([1,10,'PREVIOUS-ISSUE','Emergency Room',40,$previous]);
    return $db;
}
