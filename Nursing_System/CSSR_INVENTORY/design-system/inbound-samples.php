<?php
require_once __DIR__.'/../includes/inbound-service.php';
function cssrInboundSampleDb(bool $seed=true): PDO
{
    $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec("CREATE TABLE inbound_documents(id INTEGER PRIMARY KEY AUTOINCREMENT,request_key TEXT UNIQUE,inbound_no TEXT UNIQUE,status TEXT,payload TEXT,received_date TEXT,supplier TEXT,item_count INTEGER,total_value NUMERIC,created_by INTEGER,encoded_by TEXT,version INTEGER,created_at TEXT,updated_at TEXT,completed_at TEXT);
        CREATE TABLE items(id INTEGER PRIMARY KEY,item_code TEXT,item_name TEXT,item_description TEXT,unit TEXT,brand TEXT,category TEXT,current_stock INTEGER,status TEXT);
        CREATE TABLE suppliers(id INTEGER PRIMARY KEY,supplier_name TEXT,status TEXT);
        CREATE TABLE item_lots(id INTEGER PRIMARY KEY AUTOINCREMENT,item_id INTEGER,lot_number TEXT,expiry_date TEXT,brand TEXT,supplier TEXT,storage_location TEXT,unit_price NUMERIC,quantity_received INTEGER,quantity_available INTEGER,quantity_quarantined INTEGER,inspection_status TEXT,status TEXT);
        CREATE TABLE stock_in(id INTEGER PRIMARY KEY AUTOINCREMENT,item_id INTEGER,item_lot_id INTEGER,inbound_no TEXT,reference_no TEXT,po_number TEXT,delivery_receipt_no TEXT,procurement_type TEXT,receiving_status TEXT,record_status TEXT,delivery_completion TEXT,supplier TEXT,quantity_received INTEGER,expected_quantity INTEGER,remaining_quantity INTEGER,accepted_quantity INTEGER,quarantined_quantity INTEGER,rejected_quantity INTEGER,unit_price NUMERIC,lot_number TEXT,expiry_date TEXT,received_date TEXT,received_by TEXT,remarks TEXT);
        CREATE TABLE receiving_inspections(id INTEGER PRIMARY KEY AUTOINCREMENT,item_lot_id INTEGER,stock_in_id INTEGER,inspection_no TEXT UNIQUE,delivered_qty INTEGER,accepted_qty INTEGER,quarantined_qty INTEGER,rejected_qty INTEGER,findings TEXT,decision TEXT,inspected_by TEXT,inspected_at TEXT);
        CREATE TABLE stock_cards(id INTEGER PRIMARY KEY AUTOINCREMENT,item_id INTEGER,transaction_date TEXT,reference_no TEXT,transaction_type TEXT,lot_number TEXT,expiry_date TEXT,received_qty INTEGER,issued_qty INTEGER,balance_qty INTEGER,from_location TEXT,to_location TEXT,encoded_by TEXT,remarks TEXT);
        CREATE TABLE bin_cards(id INTEGER PRIMARY KEY AUTOINCREMENT,item_id INTEGER,transaction_date TEXT,reference_no TEXT,transaction_type TEXT,lot_number TEXT,expiry_date TEXT,received_qty INTEGER,issued_qty INTEGER,balance_qty INTEGER,department TEXT,encoded_by TEXT,remarks TEXT);
        CREATE TABLE item_price_list(id INTEGER PRIMARY KEY AUTOINCREMENT,item_name TEXT,item_description TEXT,lot_number TEXT,expiration_text TEXT,unit_type TEXT,classification TEXT,unit_price NUMERIC,markup_cost NUMERIC,total_price NUMERIC,selling_price NUMERIC,source_as_of TEXT,source_hash TEXT UNIQUE);
        INSERT INTO items VALUES(1,'CSSR-000001','Gauze Swab 4 × 4','Sterile gauze swabs','Pack','','MEDICAL SUPPLIES',0,'Active'),(2,'CSSR-000002','Examination Gloves','Non-sterile examination gloves','Box','','MEDICAL SUPPLIES',0,'Active'),(3,'CSSR-000003','Syringe 5 mL','Disposable syringes','Piece','','MEDICAL SUPPLIES',0,'Active'),(4,'CSSR-000004','Surgical Scissors','Stainless steel scissors','Piece','','MEDICAL INSTRUMENTS',0,'Active');
        INSERT INTO suppliers VALUES(1,'Sample Medical Trading','Active'),(2,'Sample Healthcare Foundation','Active'),(3,'Sample Hospital Supply','Active');");
    if($seed){
        for($i=1;$i<=8;$i++){
            $input=cssrInboundSampleInput();$input['request_key']=str_pad(dechex($i),32,'0',STR_PAD_LEFT);$input['received_date']=date('Y-m-d',strtotime('-'.($i-1).' days'));
            $input['supplier']=['Sample Medical Trading','Sample Healthcare Foundation','Sample Hospital Supply'][($i-1)%3];
            $input['procurement_type']=array_keys(cssrInboundTypes())[($i-1)%3];$input['delivery_receipt_no']='DR-2026-'.str_pad((string)$i,4,'0',STR_PAD_LEFT);$input['po_number']=$i%3===2?'':'PO-2026-'.(200+$i);
            $input['storage_location']=$i%4===0?'CSSR':'STORAGE ROOM 1';$input['lines'][0]['quantity']=(string)($i*20);$input['lines'][1]['quantity']=(string)($i*5);
            cssrSaveInbound($db,$input,1,'Sample CSSR Staff');
        }
        $input=cssrInboundSampleInput();$input['request_key']=str_repeat('d',32);$input['action']='draft';$input['supplier']='Sample Medical Trading';$input['lines'][0]['quantity']='';cssrSaveInbound($db,$input,1,'Sample CSSR Staff');
    }
    return $db;
}
function cssrInboundSampleInput(): array
{
    return ['action'=>'complete','request_key'=>str_repeat('a',32),'received_date'=>date('Y-m-d'),'supplier'=>'Sample Medical Trading','delivery_receipt_no'=>'DR-2026-0001','po_number'=>'PO-2026-0201','procurement_type'=>'REGULAR','storage_location'=>'STORAGE ROOM 1','remarks'=>'Supplies received and checked against the delivery receipt.','lines'=>[
        ['item_id'=>'1','quantity'=>'100','unit_cost'=>'45.50','expiry_date'=>'2099-06-30','lot_number'=>'GZ-2026-A'],
        ['item_id'=>'2','quantity'=>'20','unit_cost'=>'220.00','expiry_date'=>'2099-07-31','lot_number'=>'GL-2026-A'],
    ]];
}
