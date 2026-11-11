<?php
// Synthetic, in-memory records for the public preview and automated checks only.
function cssrInventorySampleConnection(): PDO
{
    $connection = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $connection->exec('CREATE TABLE items (id INTEGER PRIMARY KEY,item_code TEXT UNIQUE,item_name TEXT,item_description TEXT,category TEXT,unit TEXT,current_stock INTEGER,minimum_stock INTEGER,status TEXT,bin_location TEXT,procurement_type TEXT)');
    $connection->exec('CREATE TABLE item_lots (item_id INTEGER,quantity_available INTEGER,storage_location TEXT)');
    $connection->exec('CREATE TABLE stock_in (item_id INTEGER,procurement_type TEXT)');
    $names = ['Alcohol 70%','Bandage scissors','Cotton balls','Disposable syringe 5 mL','Examination gloves','Gauze swabs 4 x 4','Hemostatic forceps','IV cannula 20G','Kidney basin','Micropore surgical tape','Nasal cannula','Oxygen mask','Protective apron','Rubber tourniquet','Safety lancet','Scalpel handle','Sterile dressing set','Sterile gauze roll','Suction catheter','Surgical blade','Surgical mask','Surgical scissors','Suture removal kit','Thermometer covers','Tongue depressor','Urine collection bag','Wound dressing','Zinc oxide tape'];
    $insert = $connection->prepare('INSERT INTO items VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $lot = $connection->prepare('INSERT INTO item_lots VALUES (?,?,?)');
    $procurement = $connection->prepare('INSERT INTO stock_in VALUES (?,?)');
    foreach ($names as $index=>$name) {
        $id = $index+1; $instrument = in_array($id,[2,7,9,16,22],true);
        $quantity = $id % 7 === 0 ? 0 : ($id % 4 === 0 ? 24 : 120 + $id*10);
        $unit = $id === 3 ? '' : ($instrument ? 'Piece' : ($id % 2 ? 'Box' : 'Pack'));
        $location = $id % 3 === 0 ? 'STORAGE ROOM 1' : 'CSSR';
        $insert->execute([$id,'CSSR-'.str_pad((string)$id,4,'0',STR_PAD_LEFT),$name,'Sample item specifications',$instrument ? 'MEDICAL INSTRUMENTS' : 'MEDICAL SUPPLIES',$unit,$quantity,50,'Active',$location,$id % 3 === 0 ? 'Donation' : 'Procured']);
        $lot->execute([$id,$quantity,$location]);
        $procurement->execute([$id,$id % 3 === 0 ? 'DONATION' : 'REGULAR']);
    }
    return $connection;
}
