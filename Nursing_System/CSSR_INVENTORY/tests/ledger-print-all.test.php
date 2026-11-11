<?php
require_once __DIR__.'/../includes/ledger-card-data.php';
$connection=new PDO('sqlite::memory:');$connection->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$connection->sqliteCreateFunction('CONCAT',fn(...$values)=>implode('',$values));
function db(){global $connection;return $connection;}
function db_all($sql,$params=[]){$statement=db()->prepare($sql);$statement->execute($params);return $statement->fetchAll();}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function expect($condition,$message){if(!$condition)throw new RuntimeException($message);}
$connection->exec("CREATE TABLE items(id INTEGER,item_code TEXT,item_name TEXT,item_description TEXT,supplier TEXT,unit TEXT,category TEXT,bin_location TEXT,status TEXT,brand TEXT);
CREATE TABLE item_lots(id INTEGER,item_id INTEGER,lot_number TEXT,expiry_date TEXT,storage_location TEXT,quantity_available INTEGER);
CREATE TABLE bin_cards(id INTEGER,item_id INTEGER,transaction_date TEXT,reference_no TEXT,lot_number TEXT,received_qty INTEGER,issued_qty INTEGER);
CREATE TABLE stock_in(id INTEGER,item_id INTEGER,item_lot_id INTEGER,received_date TEXT,reference_no TEXT,receiving_status TEXT,lot_number TEXT,expiry_date TEXT,quantity_received INTEGER,supplier TEXT,received_by TEXT);
CREATE TABLE stock_cards(id INTEGER,item_id INTEGER,transaction_date TEXT,reference_no TEXT,lot_number TEXT,received_qty INTEGER,issued_qty INTEGER,from_location TEXT,to_location TEXT);
INSERT INTO items VALUES(1,'A','First Supply','First Supply','Supplier','pc','Medical','A','Active',''),(2,'B','Second Supply','Second Supply','Supplier','pc','Medical','B','Active','');
INSERT INTO item_lots VALUES(1,1,'LOT-A',NULL,'CSSR',50),(2,2,'LOT-B',NULL,'CSSR',20),(3,1,'LOT-C',NULL,'STORAGE ROOM 1',8),(4,2,'LOT-D',NULL,'STORAGE ROOM 2',5);
INSERT INTO bin_cards VALUES(1,1,'2026-01-01','OPEN','LOT-A',100,0);
INSERT INTO stock_in VALUES(1,1,1,'2026-02-01','HELD','FOR INSPECTION','LOT-A',NULL,3,'Supplier','Receiver');
INSERT INTO stock_cards VALUES(1,1,'2026-01-01','IN','LOT-C',10,0,NULL,'STORAGE ROOM 1'),(2,1,'2026-02-01','OUT','LOT-C',0,2,'STORAGE ROOM 1','CSSR'),(3,2,'2026-02-01','IN','LOT-D',5,0,NULL,'STORAGE ROOM 2');");
for($i=2;$i<=51;$i++)$connection->exec("INSERT INTO bin_cards VALUES($i,1,'2026-02-01','OUT','LOT-A',0,1)");
$items=db_all('SELECT * FROM items ORDER BY id');
$first=cssrBinCardData($items,1,'','');$second=cssrBinCardData($items,2,'','');
expect(count($first['rowPages'])===2,'Long Bin Card spans multiple pages');
$last=$first['rowPages'][1];expect(end($last)['balance_qty']===50,'Bin balances include held delivery without adding usable stock');
expect($second['rowPages'][0][0]['balance_qty']===20,'Each Bin Card has an independent opening balance');
$stock=cssrStockCardData($items,1,'2026-02-01','2026-02-01');expect($stock['rowPages'][0][0]['balance_qty']===8,'Stock date filter includes prior running balance');
expect($stock['ledgerScopeLabel']==='STORAGE ROOM 1','Stock Card shows only the selected item storage room');
expect(cssrStockCardData($items,2,'','')['ledgerScopeLabel']==='STORAGE ROOM 2','Each item has its own storage-room heading');
expect(cssrStockCardData($items,0,'','')['ledgerScopeLabel']==='','Blank Stock Card leaves storage room empty');
expect(cssrStockCardData($items,2,'','')['rowPages'][0][0]['balance_qty']===5,'Stock Card balances are independent per item');
if(($argv[1]??'')==='fixture'){
 $kind=($argv[2]??'')==='stock'?'stock':'bin';$_GET=[];
 require_once __DIR__.'/../../includes/theme-presentation.php';
 $source=file_get_contents(__DIR__.'/../pages/'.$kind.'_card.php');
 $source=str_replace(["require_once __DIR__ . '/../includes/auth.php';",'require_admin_or_cssr();',"require_once __DIR__.'/../includes/ledger-card-data.php';"],'',$source);
 $source=str_replace("include __DIR__ . '/../includes/header.php';","echo '<!doctype html><html><head><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">'.nursingThemeAssets().'<link rel=\"stylesheet\" href=\"../assets/css/app.css\"></head><body class=\"cu-app\">';",$source);
 $source=str_replace("include __DIR__ . '/../includes/footer.php';","echo '</body></html>';",$source);
 eval('?>'.$source);exit;
}
foreach(['bin','stock'] as $kind){
 $_GET=['all'=>'1','selected_items'=>['1','2']];$source=file_get_contents(__DIR__.'/../pages/'.$kind.'_card.php');
 $source=str_replace("require_once __DIR__ . '/../includes/auth.php';",'', $source);
 $source=str_replace('require_admin_or_cssr();','',$source);
 $source=str_replace("require_once __DIR__.'/../includes/ledger-card-data.php';",'',$source);
 $source=str_replace(["include __DIR__ . '/../includes/header.php';","include __DIR__ . '/../includes/footer.php';"],'',$source);
 ob_start();eval('?>'.$source);$html=ob_get_clean();
 expect(str_contains($html,'data-ledger-picker-open')&&str_contains($html,'data-ledger-clear-all'),'Bulk printing opens item picker with Clear All');
 expect(substr_count($html,'class="bin-card-sheet')===($kind==='bin'?3:2),'Bulk print renders every item and all continuation pages');
 expect(str_contains($html,'First Supply')&&str_contains($html,'Second Supply'),'All eligible items appear in print output');
 expect(str_contains($html,'window.print()'),'Bulk printing opens print dialog');
 expect(str_contains($html,$kind==='stock'?'<h2>STOCK CARD</h2>':'<h2>BIN CARD</h2>'),'Each page uses its own card form');
 $_GET=['all'=>'1','selected_items'=>['2'],'from'=>'2026-02-01','to'=>'2026-02-01','print_mode'=>'preview'];ob_start();eval('?>'.$source);$preview=ob_get_clean();
 expect(substr_count($preview,'class="bin-card-sheet')===1,'Only selected items print');
 $_GET['print_mode']='print';ob_start();eval('?>'.$source);$printed=ob_get_clean();expect($printed===$preview,'Print and Print Preview have identical output');
 $_GET=['all'=>'1'];ob_start();eval('?>'.$source);$empty=ob_get_clean();expect(!str_contains($empty,'class="bin-card-sheet')&&str_contains($empty,'Select at least one item'),'Empty selection does not print');
 $_GET=['item_id'=>'1'];ob_start();eval('?>'.$source);$single=ob_get_clean();
 expect(substr_count($single,'class="bin-card-sheet')===($kind==='bin'?2:1),'Single-item pagination still works');
 $_GET=['blank'=>'1','all'=>'1'];ob_start();eval('?>'.$source);$blank=ob_get_clean();
 expect(substr_count($blank,'class="bin-card-sheet')===1,'Blank printing keeps a single empty form');
}
expect(count(cssrLedgerSelectedItems($items,['2','2','999']))===1,'Selection is deduplicated and restricted to available items');
expect(cssrLedgerPrintError('2026-02-30','',[$items[0]])!==''&&cssrLedgerPrintError('2026-03-01','2026-02-01',[$items[0]])!=='','Invalid or reversed dates are rejected');
echo "Passed bulk card rendering, pagination, independent balances, held deliveries, date filtering, and print-button checks.\n";
