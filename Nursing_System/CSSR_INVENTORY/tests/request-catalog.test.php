<?php
require_once __DIR__.'/../includes/request-catalog.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE items(id INTEGER PRIMARY KEY,item_code TEXT,item_name TEXT,item_description TEXT,unit TEXT,brand TEXT,category TEXT,lot_number TEXT,expiry_date TEXT,bin_location TEXT,item_image TEXT,status TEXT)');
$db->exec('CREATE TABLE item_lots(id INTEGER PRIMARY KEY,item_id INTEGER,storage_location TEXT,quantity_available INTEGER,status TEXT,lot_number TEXT,lot_image TEXT,expiry_date TEXT,created_at TEXT)');
$db->exec('CREATE TABLE item_lot_images(id INTEGER PRIMARY KEY,item_lot_id INTEGER,image_name TEXT,sort_order INTEGER)');
$stmt=$db->prepare("INSERT INTO items VALUES(?,?,?,'Description','PIECE','','SUPPLIES','','','','','Active')");
for($i=1;$i<=125;$i++){$stmt->execute([$i,'CODE-'.$i,sprintf('Item %03d',$i)]);$db->exec("INSERT INTO item_lots VALUES($i,$i,'STORAGE ROOM 1',10,'AVAILABLE','LOT-$i',NULL,NULL,'2026-01-01')");}
$checks=0;function catalogCheck($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$first=cssrRequestCatalog($db,[]);catalogCheck(count($first['items'])===50&&$first['has_more'],'Initial query bounded');
$second=cssrRequestCatalog($db,['offset'=>50]);catalogCheck($second['items'][0]['id']===51,'Next page stable');
catalogCheck(count(cssrRequestCatalog($db,['offset'=>100])['items'])===25,'Last page');
catalogCheck(cssrRequestCatalog($db,['q'=>'CODE-125'])['items'][0]['id']===125,'Search finds beyond initial page');
catalogCheck(!cssrRequestCatalog($db,['q'=>"' OR 1=1 --"])['items'],'Search is parameterized');
catalogCheck(!cssrRequestCatalog($db,['q'=>'%'])['items'],'Search wildcard is literal');
catalogCheck(count(cssrRequestCatalog($db,['ids'=>'120,121'])['items'])===2,'Saved cart IDs hydrate');
catalogCheck(cssrRequestCatalog($db,['room'=>'STORAGE ROOM 1'],true)['items'][0]['current_stock']===10,'Storage balance unchanged');
catalogCheck(!cssrRequestCatalog($db,['room'=>'STORAGE ROOM 2'],true)['items'],'Storage rooms stay separate');
catalogCheck(cssrRequestCatalog($db,[])['items'][0]['current_stock']===0,'CSSR excludes storage-room quantity');
try{cssrRequestCatalog($db,['room'=>'invented'],true);throw new Exception('Invalid room accepted');}catch(DomainException $e){$checks++;}
echo "Passed $checks bounded catalog, search, room-scope and cart restoration checks.\n";
