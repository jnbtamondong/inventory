<?php
// Synthetic, in-memory records only. Never connects to the hospital database.
function cssrRequisitionSamples(): PDO
{
    $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE TABLE item_requests(id INTEGER PRIMARY KEY,request_no TEXT,department_id INTEGER,department_name TEXT,requested_by TEXT,request_date TEXT,purpose TEXT,remarks TEXT,status TEXT);
        CREATE TABLE items(id INTEGER PRIMARY KEY,item_code TEXT,item_name TEXT,unit TEXT);
        CREATE TABLE item_request_details(id INTEGER PRIMARY KEY,request_id INTEGER,item_id INTEGER,quantity_requested INTEGER,quantity_approved INTEGER,quantity_released INTEGER,approved_item_lot_id INTEGER,remarks TEXT);
        CREATE TABLE item_lots(id INTEGER PRIMARY KEY,item_id INTEGER,lot_number TEXT,storage_location TEXT,status TEXT,quantity_available INTEGER,expiry_date TEXT,created_at TEXT);
        CREATE TABLE stock_reservations(id INTEGER PRIMARY KEY,request_id INTEGER,request_detail_id INTEGER,item_lot_id INTEGER,quantity INTEGER,status TEXT,expires_at TEXT);
        INSERT INTO items VALUES(1,'CSSR-000001','Gauze Swab 4 × 4','Pack'),(2,'CSSR-000002','Examination Gloves','Box'),(3,'CSSR-000003','Syringe 5 mL','Piece');
        INSERT INTO item_lots VALUES(1,1,'GZ-2026-A','CSSR','AVAILABLE',1200,'2099-06-30','2026-01-01'),(2,2,'GL-2026-A','CSSR','AVAILABLE',240,'2099-07-31','2026-01-01'),(3,3,'SY-2026-A','CSSR','AVAILABLE',3000,'2099-08-31','2026-01-01');");
    $departments=[1=>'Emergency Room',2=>'Operating Room',3=>'Medical Ward',4=>'Outpatient Department'];
    $statuses=['Pending','Approved','Approved','Released','Rejected','Pending','Pending','Released','Cancelled','Pending','Approved','Released','Pending','Rejected','Pending','Approved','Released','Pending'];
    foreach ($statuses as $index=>$status) {
        $id=$index+1; $dept=$index%4+1; $date=date('Y-m-d',strtotime('-'.(int)floor($index/3).' days'));
        $stmt=$pdo->prepare('INSERT INTO item_requests VALUES(?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$id,'REQ-'.date('Y-m').'-'.str_pad((string)(100-$index),4,'0',STR_PAD_LEFT),$dept,$departments[$dept],['Sample Nurse A','Sample Nurse B','Sample Nurse C','Sample Nurse D'][$dept-1],$date,'Routine departmental supply replenishment',$status==='Rejected'?'Duplicate request. Please refer to the previously approved requisition.':'For daily patient care.',$status]);
        foreach ([1,2,3] as $itemId) {
            $detailId=$id*10+$itemId; $qty=[1=>40,2=>6,3=>60][$itemId]; $approved=in_array($status,['Approved','Released'],true)?$qty:0;
            $pdo->prepare('INSERT INTO item_request_details VALUES(?,?,?,?,?,?,?,?)')->execute([$detailId,$id,$itemId,$qty,$approved,$status==='Released'?$approved:0,$approved?$itemId:null,$itemId===2?'Non-sterile, medium size':'']);
            if ($status==='Approved' && $id!==3) $pdo->prepare('INSERT INTO stock_reservations VALUES(?,?,?,?,?,?,?)')->execute([$detailId,$id,$detailId,$itemId,$approved,'ACTIVE','2099-01-01 00:00:00']);
        }
    }
    return $pdo;
}
