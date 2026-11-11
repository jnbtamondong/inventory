<?php
require_once __DIR__.'/inbound-samples.php';
function cssrStorageRoomSamples(): PDO
{
    $db=cssrInboundSampleDb(false);
    $db->exec("ALTER TABLE items ADD minimum_stock INTEGER DEFAULT 0; ALTER TABLE items ADD procurement_type TEXT DEFAULT 'Procured';
        ALTER TABLE item_lots ADD quantity_issued INTEGER DEFAULT 0; ALTER TABLE item_lots ADD lot_image TEXT;
        ALTER TABLE item_lots ADD created_at TEXT DEFAULT '2026-01-01 00:00:00';
        CREATE TABLE stock_reservations(id INTEGER PRIMARY KEY,item_lot_id INTEGER,quantity INTEGER,status TEXT,expires_at TEXT);
        CREATE TABLE storage_transfers(id INTEGER PRIMARY KEY AUTOINCREMENT,request_key TEXT UNIQUE,reference_no TEXT UNIQUE,item_id INTEGER,source_lot_id INTEGER,destination_lot_id INTEGER,quantity INTEGER,transfer_date TEXT,remarks TEXT,created_by INTEGER,encoded_by TEXT,created_at TEXT);
        CREATE TABLE stock_location_movements(id INTEGER PRIMARY KEY AUTOINCREMENT,item_id INTEGER,movement_type TEXT,reference_no TEXT,lot_number TEXT,expiry_date TEXT,quantity INTEGER,from_location_id INTEGER,from_location_code TEXT,to_location_id INTEGER,to_location_code TEXT,processed_by TEXT,processed_at TEXT,remarks TEXT,status TEXT);
        CREATE TABLE cssr_available_stock(id INTEGER PRIMARY KEY,item_id INTEGER,item_name TEXT,item_description TEXT,lot_number TEXT,expiry_date TEXT,quantity INTEGER,unit_type TEXT,classification TEXT,procurement_type TEXT,source_location_code TEXT,status TEXT);
        INSERT INTO items(id,item_code,item_name,item_description,unit,brand,category,current_stock,status,minimum_stock) VALUES
        (5,'CSSR-000005','Surgical Mask','Disposable surgical mask','Box','','MEDICAL SUPPLIES',0,'Active',100),
        (6,'CSSR-000006','Suction Catheter','Sterile suction catheter','Piece','','MEDICAL SUPPLIES',0,'Active',30),
        (7,'CSSR-000007','IV Cannula','Intravenous cannula','Piece','','MEDICAL SUPPLIES',0,'Active',20);
        UPDATE items SET minimum_stock=100 WHERE id=1; UPDATE items SET minimum_stock=50 WHERE id=2; UPDATE items SET minimum_stock=200 WHERE id=3; UPDATE items SET minimum_stock=5 WHERE id=4;
        INSERT INTO cssr_available_stock VALUES(1,2,'Examination Gloves','Existing CSSR stock','LEGACY-GL',NULL,7,'Box','MEDICAL SUPPLIES','Procured','STORAGE ROOM 2','AVAILABLE');");
    $near=date('Y-m-d',strtotime('+30 days'));$later=date('Y-m-d',strtotime('+180 days'));$expired=date('Y-m-d',strtotime('-5 days'));
    $rows=[
        [1,1,'GZ-2026-A',$near,500,0,'AVAILABLE','STORAGE ROOM 1'],
        [2,1,'GZ-2026-B',$later,200,0,'AVAILABLE','STORAGE ROOM 1'],
        [3,2,'GL-2026-A',$later,25,0,'AVAILABLE','STORAGE ROOM 1'],
        [4,3,'SY-2026-A',$later,900,0,'AVAILABLE','STORAGE ROOM 1'],
        [5,4,'SC-2026-A',null,12,0,'AVAILABLE','STORAGE ROOM 1'],
        [6,5,'MK-2025-A',$expired,30,0,'AVAILABLE','STORAGE ROOM 1'],
        [7,6,'CT-2026-A',$later,0,60,'QUARANTINED','STORAGE ROOM 1'],
        [8,7,'IV-2026-A',$later,40,0,'AVAILABLE','STORAGE ROOM 1'],
        [9,1,'GZ-OTHER',$later,400,0,'AVAILABLE','STORAGE ROOM 2'],
        [10,1,'GZ-2026-A',$near,100,0,'AVAILABLE','CSSR'],
    ];
    foreach($rows as [$id,$item,$lot,$expiry,$qty,$held,$status,$room])$db->prepare("INSERT INTO item_lots(id,item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price,quantity_received,quantity_available,quantity_quarantined,inspection_status,status) VALUES(?,?,?,?,?,'Sample Medical Trading',?,45.50,?,?,?,'CLEARED',?)")->execute([$id,$item,$lot,$expiry,'',$room,$qty+$held,$qty,$held,$status]);
    $db->exec("UPDATE items SET current_stock=(SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=items.id);
        INSERT INTO stock_reservations VALUES(1,8,40,'ACTIVE','2099-01-01');");
    return $db;
}
